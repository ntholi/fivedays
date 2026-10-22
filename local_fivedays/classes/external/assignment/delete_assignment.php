<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_assignment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment to delete'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        $cm = $DB->get_record('course_modules', ['id' => $params['cmid']]);
        if (!$cm) {
            return [
                'success' => false,
                'message' => 'Course module not found'
            ];
        }

        $module = $DB->get_record('modules', ['id' => $cm->module]);
        if (!$module || $module->name !== 'assign') {
            return [
                'success' => false,
                'message' => 'The specified course module is not an assignment'
            ];
        }

        $assign = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign) {
            return [
                'success' => false,
                'message' => 'Assignment instance not found'
            ];
        }

        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/activity_utils:deleteassignment', $context);
        require_capability('moodle/course:manageactivities', $context);

        $assignname = $assign->name;

        course_delete_module($cm->id);

        return [
            'success' => true,
            'message' => 'Assignment "' . $assignname . '" deleted successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
