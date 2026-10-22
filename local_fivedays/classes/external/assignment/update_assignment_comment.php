<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class update_assignment_comment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'commentid' => new external_value(PARAM_INT, 'Comment ID'),
            'content' => new external_value(PARAM_RAW, 'Updated comment text'),
        ]);
    }

    public static function execute(int $commentid, string $content): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'commentid' => $commentid,
            'content' => $content,
        ]);
        $text = trim($params['content']);
        if ($text === '') {
            throw new \invalid_parameter_exception('Comment text is required');
        }

        [$comment, , , , , $context] = helper::require_assignment_comment($params['commentid']);
        self::validate_context($context);
        require_capability('local/activity_utils:manageassignmentcomments', $context);
        require_capability('mod/assign:grade', $context);

        $comment->content = $text;
        $DB->update_record('comments', $comment);
        $comment = $DB->get_record('comments', ['id' => $comment->id], '*', MUST_EXIST);
        $author = $DB->get_record('user', ['id' => $comment->userid], '*', MUST_EXIST);

        return array_merge(helper::format_assignment_comment($comment, $author), [
            'success' => true,
            'message' => 'Comment updated successfully',
        ]);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Comment ID'),
            'content' => new external_value(PARAM_RAW, 'Comment text'),
            'userid' => new external_value(PARAM_INT, 'Author user ID'),
            'author' => new external_value(PARAM_TEXT, 'Author name'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
