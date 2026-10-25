<?php
// Cutover phase 5b / rehearsal (D18): pushes every file to the bucket now instead of waiting for cron. Runs in a
// one-off `app` container of the new stack (host/cutover-new.sh objectfs), whose config.php forces
// sizethreshold = 0 and minimumage = 0, so every non-empty file is a push candidate.
//
// tool_objectfs only pushes files it has a tool_objectfs_objects row for: the checker task adds rows for every
// mdl_files hash without one (classes/local/object_manipulator/candidates/checker_candidates.php), the pusher
// uploads rows with location OBJECT_LOCATION_LOCAL (lib.php:49) and marks them OBJECT_LOCATION_DUPLICATED
// (lib.php:55; candidates/pusher_candidates.php). Each task stops after maxtaskruntime (60 s by default,
// classes/local/manager.php:60, object_manipulator/manipulator.php:77), so both run in a loop until nothing is
// left. Empty files (filesize 0) are never candidates and need no object.
// Exit 0 when no non-empty file is left without a bucket copy; the bucket listing check follows in bash.

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

\core\session\manager::set_user(get_admin());
$fs = get_file_storage()->get_file_system();
if (!$fs instanceof \tool_objectfs\s3_file_system) {
    fwrite(STDERR, 'FAIL  the file system is ' . get_class($fs) . ", not tool_objectfs\\s3_file_system (MOODLE_BUCKET unset?)\n");
    exit(1);
}

$unchecked = static fn(): int => (int) $GLOBALS['DB']->count_records_sql(
    'SELECT COUNT(DISTINCT f.contenthash) FROM {files} f
       LEFT JOIN {tool_objectfs_objects} o ON o.contenthash = f.contenthash
      WHERE f.filesize > 0 AND o.id IS NULL');
$bylocation = static function (): array {
    global $DB;
    return $DB->get_records_sql_menu(
        'SELECT o.location, COUNT(1) FROM {tool_objectfs_objects} o
          WHERE EXISTS (SELECT 1 FROM {files} f WHERE f.contenthash = o.contenthash AND f.filesize > 0)
          GROUP BY o.location');
};

// 90 minutes fits the window (prod: about 1 GB, 7,300 files; 2 minutes locally). A push that stops making progress
// (the same number of files left for STALL_ROUNDS rounds, e.g. bucket errors) fails early instead.
const STALL_ROUNDS = 5;
$start = time();
$deadline = $start + 90 * MINSECS;
$best = PHP_INT_MAX;
$stalled = 0;
for ($round = 1; ; $round++) {
    foreach (['\tool_objectfs\task\check_objects_location', '\tool_objectfs\task\push_objects_to_storage'] as $class) {
        \core\task\manager::get_scheduled_task($class)->execute();
    }
    $locations = $bylocation();
    $local = (int) ($locations[OBJECT_LOCATION_LOCAL] ?? 0);
    $left = $unchecked();
    printf("objectfs round %d (%d s): unchecked %d, local only %d, in bucket %d, errors %d\n", $round, time() - $start,
        $left, $local, (int) ($locations[OBJECT_LOCATION_DUPLICATED] ?? 0) + (int) ($locations[OBJECT_LOCATION_EXTERNAL] ?? 0),
        (int) ($locations[OBJECT_LOCATION_ERROR] ?? 0));
    if ($left === 0 && $local === 0) {
        break;
    }
    $remaining = $left + $local;
    if ($remaining < $best) {
        $best = $remaining;
        $stalled = 0;
    } else if (++$stalled >= STALL_ROUNDS) {
        fwrite(STDERR, "FAIL  objectfs: {$remaining} files still not in the bucket and no progress for " . STALL_ROUNDS . " rounds\n");
        exit(1);
    }
    if (time() > $deadline) {
        fwrite(STDERR, "FAIL  objectfs: {$remaining} files still not in the bucket after 90 minutes\n");
        exit(1);
    }
}

// OBJECT_LOCATION_ERROR (lib.php:43): the checker found no local file for a hash. bin/verify already proved every
// hash is on disk, so this would be a new problem.
$errors = (int) ($bylocation()[OBJECT_LOCATION_ERROR] ?? 0);
if ($errors) {
    fwrite(STDERR, "FAIL  objectfs: {$errors} objects are in the error state (file missing locally)\n");
    exit(1);
}
echo "ok    objectfs: zero local-only objects\n";
