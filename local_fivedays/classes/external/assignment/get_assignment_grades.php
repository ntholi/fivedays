<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class get_assignment_grades extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
        ]);
    }

    public static function execute(int $assignmentid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
        ]);

        [$assignment, , , $context] = helper::get_assign($params['assignmentid']);
        self::validate_context($context);
        require_capability('local/activity_utils:gradeassignment', $context);
        require_capability('mod/assign:grade', $context);

        return [
            'grades' => helper::list_assignment_grades($assignment),
            'success' => true,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'grades' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'Student user ID'),
                    'grade' => new external_value(PARAM_FLOAT, 'Stored grade (-1 if none)'),
                    'releasestate' => new external_value(PARAM_ALPHA, 'notgraded, notreleased, released, or beingedited'),
                ])
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ]);
    }
}
