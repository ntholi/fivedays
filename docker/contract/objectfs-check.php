<?php
// `just test` only (D16): proves tool_objectfs works on this Moodle. It stores a file, runs objectfs's
// push task, deletes the local copy and reads the file back, which can only come from the bucket.
// Run in a one-off `app` container of a throwaway project whose MOODLE_BUCKET points at the S3 stand-in.

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL  objectfs: {$message}\n");
    exit(1);
};

if (get_class(get_file_storage()->get_file_system()) !== 'tool_objectfs\s3_file_system') {
    $fail('the file system is ' . get_class(get_file_storage()->get_file_system()) . ', not tool_objectfs\s3_file_system');
}

// The S3 stand-in starts empty; Lightsail buckets are created in the console.
$bucket = getenv('MOODLE_BUCKET');
$curl = curl_init("http://{$bucket}.s3:8333/");
curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_RETURNTRANSFER => true]);
curl_exec($curl);
curl_close($curl);

$content = 'objectfs check ' . random_string(32);
$file = get_file_storage()->create_file_from_string([
    'contextid' => context_system::instance()->id,
    'component' => 'local_fivedays',
    'filearea' => 'objectfscheck',
    'itemid' => 0,
    'filepath' => '/',
    'filename' => 'check-' . time() . '.txt',
], $content);
$hash = $file->get_contenthash();

foreach (['\tool_objectfs\task\check_objects_location', '\tool_objectfs\task\push_objects_to_storage'] as $class) {
    \core\task\manager::get_scheduled_task($class)->execute();
}

$location = (int) $DB->get_field('tool_objectfs_objects', 'location', ['contenthash' => $hash]);
if ($location !== OBJECT_LOCATION_DUPLICATED) {
    $fail("after the push the object's location is {$location}, expected " . OBJECT_LOCATION_DUPLICATED . ' (in the bucket and local)');
}

$localpath = $CFG->dataroot . '/filedir/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
unlink($localpath);
$readback = get_file_storage()->get_file_by_hash($file->get_pathnamehash())->get_content();
if ($readback !== $content) {
    $fail('the file read back without its local copy does not match what was stored');
}

echo "ok    objectfs: pushed to the bucket and read back without the local copy\n";
