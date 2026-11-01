<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * Which FiveDays activities may be shown to students. luct writes a row for each weighted activity;
 * approved = 0 keeps it hidden until a principal lecturer or faculty manager approves it there. A course
 * module without a row is not managed here and is never touched.
 */
class activity_approval {

    private const TABLE = 'local_fivedays_activity_approval';

    /**
     * @param int[] $cmids
     */
    public static function set(array $cmids, bool $approved): void {
        global $DB;

        $now = time();
        foreach (array_values(array_unique(array_map('intval', $cmids))) as $cmid) {
            $row = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
            if ($row) {
                if ((int) $row->approved !== (int) $approved) {
                    $DB->update_record(self::TABLE, (object) [
                        'id' => $row->id,
                        'approved' => (int) $approved,
                        'timemodified' => $now,
                    ]);
                }
                continue;
            }
            $DB->insert_record(self::TABLE, (object) [
                'cmid' => $cmid,
                'approved' => (int) $approved,
                'timemodified' => $now,
            ]);
        }
    }

    /** True when luct has not approved this course module, so students must not see it. */
    public static function is_blocked(int $cmid): bool {
        global $DB;

        return $DB->record_exists(self::TABLE, ['cmid' => $cmid, 'approved' => 0]);
    }

    /** Refuses to show an activity luct has not approved. */
    public static function require_showable(int $cmid): void {
        if (self::is_blocked($cmid)) {
            throw new \moodle_exception('activitynotapproved', 'local_fivedays');
        }
    }

    /** Hides the course module again when it is visible but not approved. */
    public static function enforce(int $cmid): bool {
        global $DB;

        if (!self::is_blocked($cmid)) {
            return false;
        }
        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, visible, visibleoncoursepage');
        if (!$cm || (int) $cm->visible === 0) {
            return false;
        }
        \core_courseformat\formatactions::cm((int) $cm->course)->set_visibility($cmid, 0);
        debugging("local_fivedays: course module {$cmid} is not approved in luct; hidden again.", DEBUG_DEVELOPER);
        return true;
    }

    public static function forget(int $cmid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }
}
