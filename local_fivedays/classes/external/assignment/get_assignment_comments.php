<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class get_assignment_comments extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
        ]);
    }

    public static function execute(int $assignmentid, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
            'userid' => $userid,
        ]);

        [$assignment, , , $context] = helper::get_assign_for_comments($params['assignmentid']);
        self::validate_context($context);
        require_capability('local/activity_utils:viewassignmentcomments', $context);
        require_capability('mod/assign:grade', $context);

        helper::enable_assignment_comments($assignment);

        return [
            'comments' => helper::list_assignment_comments($assignment, $params['userid']),
            'success' => true,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Comment ID'),
                    'content' => new external_value(PARAM_RAW, 'Comment text'),
                    'userid' => new external_value(PARAM_INT, 'Author user ID'),
                    'author' => new external_value(PARAM_TEXT, 'Author name'),
                    'timecreated' => new external_value(PARAM_INT, 'Created timestamp'),
                ])
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ]);
    }
}
