<?php
namespace local_activity_utils\external\bigbluebuttonbn;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_bigbluebuttonbn extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the BigBlueButton activity to delete'),
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
        if (!$module || $module->name !== 'bigbluebuttonbn') {
            return [
                'success' => false,
                'message' => 'The specified course module is not a BigBlueButton activity'
            ];
        }

        $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', ['id' => $cm->instance]);
        if (!$bigbluebuttonbn) {
            return [
                'success' => false,
                'message' => 'BigBlueButton activity instance not found'
            ];
        }

        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/activity_utils:deletebigbluebuttonbn', $context);
        require_capability('moodle/course:manageactivities', $context);

        $activityname = $bigbluebuttonbn->name;

        course_delete_module($cm->id);

        return [
            'success' => true,
            'message' => 'BigBlueButton activity "' . $activityname . '" deleted successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
