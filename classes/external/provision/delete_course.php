<?php
namespace local_activity_utils\external\provision;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\provision_helper;

class delete_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Moodle course id'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        provision_helper::require_system_capability();

        $courseid = (int) $params['courseid'];
        if ($courseid === (int) SITEID) {
            throw new \moodle_exception('cannotdeletesitecourse', 'local_activity_utils');
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new \moodle_exception('coursenotfound', 'local_activity_utils');
        }

        $context = \context_course::instance($course->id);
        require_capability('moodle/course:delete', $context);

        return ['success' => \delete_course($course, false)];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the course was deleted'),
        ]);
    }
}
