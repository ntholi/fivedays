<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class add_assignment_comment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'content' => new external_value(PARAM_RAW, 'Comment text'),
        ]);
    }

    public static function execute(int $assignmentid, int $userid, string $content): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
            'userid' => $userid,
            'content' => $content,
        ]);
        $text = trim($params['content']);
        if ($text === '') {
            throw new \invalid_parameter_exception('Comment text is required');
        }

        [$assignment, $cm, $course, $context] = helper::get_assign_for_comments($params['assignmentid']);
        self::validate_context($context);
        require_capability('local/activity_utils:manageassignmentcomments', $context);
        require_capability('mod/assign:grade', $context);

        $student = $DB->get_record('user', ['id' => $params['userid']], '*', MUST_EXIST);
        if (!is_enrolled($context, $student)) {
            throw new \invalid_parameter_exception('Student is not enrolled in this course');
        }

        helper::enable_assignment_comments($assignment);
        $submission = $assignment->get_user_submission($params['userid'], true);
        $manager = helper::assignment_comment_manager($assignment, $cm, $course, (int)$submission->id);
        $added = $manager->add($text, FORMAT_MOODLE);
        $comment = $DB->get_record('comments', ['id' => $added->id], '*', MUST_EXIST);

        return array_merge(helper::format_assignment_comment($comment, $USER), [
            'success' => true,
            'message' => 'Comment added successfully',
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
