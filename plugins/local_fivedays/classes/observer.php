<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

class observer {

    /** Drops the copy_activities records of a deleted course. */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $DB->delete_records('local_fivedays_reuse_copies', ['courseid' => $event->objectid]);
    }
}
