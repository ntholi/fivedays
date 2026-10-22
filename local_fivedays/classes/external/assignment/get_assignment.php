<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class get_assignment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment instance ID'),
        ]);
    }

    /**
     * Unlike mod_assign_get_assignments, this does not require the caller to be
     * enrolled, so the system token can locate assignments in any class.
     */
    public static function execute(int $assignmentid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
        ]);

        [$assign, $cm] = helper::require_activity('assign', $params['assignmentid'], 'id, course, name');

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:viewassignment', $context);
        require_capability('mod/assign:view', $context);

        return [
            'id' => (int)$assign->id,
            'coursemoduleid' => (int)$cm->id,
            'courseid' => (int)$cm->course,
            'name' => $assign->name,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Assignment instance ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Assignment name'),
        ]);
    }
}
