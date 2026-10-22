<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class save_assignment_grade extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'grade' => new external_value(PARAM_FLOAT, 'Overall mark to store'),
        ]);
    }

    public static function execute(int $assignmentid, int $userid, float $grade): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
            'userid' => $userid,
            'grade' => $grade,
        ]);

        [$assignment, , , $context] = helper::get_assign($params['assignmentid']);
        self::validate_context($context);
        require_capability('local/activity_utils:gradeassignment', $context);
        require_capability('mod/assign:grade', $context);

        $student = $DB->get_record('user', ['id' => $params['userid']], '*', MUST_EXIST);
        if (!is_enrolled($context, $student)) {
            throw new \invalid_parameter_exception('Student is not enrolled in this course');
        }

        $max = (float)$assignment->get_instance()->grade;
        if ($params['grade'] < 0 || ($max > 0 && $params['grade'] > $max)) {
            throw new \invalid_parameter_exception('Grade must be between 0 and ' . $max);
        }

        $saved = helper::save_assignment_grade($assignment, $params['userid'], $params['grade']);

        return [
            'grade' => $saved['grade'],
            'releasestate' => $saved['releasestate'],
            'success' => true,
            'message' => 'Grade saved',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'grade' => new external_value(PARAM_FLOAT, 'Stored grade'),
            'releasestate' => new external_value(PARAM_ALPHA, 'notgraded, notreleased, released, or beingedited'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
