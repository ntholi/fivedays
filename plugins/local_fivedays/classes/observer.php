<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

class observer {

    /** Drops the copy_activities records of a deleted course. */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $DB->delete_records('local_fivedays_reuse_copies', ['courseid' => $event->objectid]);
    }

    /** A weighted activity luct has not approved is hidden again when someone shows it in Moodle. */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        activity_approval::enforce((int) $event->objectid);
    }

    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        activity_approval::forget((int) $event->objectid);
    }
}
