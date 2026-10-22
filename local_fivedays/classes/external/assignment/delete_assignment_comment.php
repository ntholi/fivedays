<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class delete_assignment_comment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'commentid' => new external_value(PARAM_INT, 'Comment ID'),
        ]);
    }

    public static function execute(int $commentid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'commentid' => $commentid,
        ]);

        [$comment, $submission, $assignment, $cm, $course, $context] =
            helper::require_assignment_comment($params['commentid']);
        self::validate_context($context);
        require_capability('local/activity_utils:manageassignmentcomments', $context);
        require_capability('mod/assign:grade', $context);

        $manager = helper::assignment_comment_manager(
            $assignment,
            $cm,
            $course,
            (int)$submission->id
        );
        $manager->delete($comment);

        return [
            'success' => true,
            'message' => 'Comment deleted successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
