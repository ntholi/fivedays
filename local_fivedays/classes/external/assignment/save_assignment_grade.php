<?php
namespace local_fivedays\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;

class save_assignment_grade extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'grade' => new external_value(PARAM_FLOAT, 'Overall mark to store'),
            'alsouserids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Other student user ID'),
                'Other students who get the same grade; those not enrolled are skipped',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute(int $assignmentid, int $userid, float $grade, array $alsouserids = []): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
            'userid' => $userid,
            'grade' => $grade,
            'alsouserids' => $alsouserids,
        ]);

        [$assignment, , , $context] = helper::get_assign($params['assignmentid']);
        self::validate_context($context);
        require_capability('local/fivedays:gradeassignment', $context);
        require_capability('mod/assign:grade', $context);

        $student = $DB->get_record('user', ['id' => $params['userid']], '*', MUST_EXIST);
        if (!is_enrolled($context, $student)) {
            throw new \invalid_parameter_exception('Student is not enrolled in this course');
        }

        $max = (float)$assignment->get_instance()->grade;
        if ($params['grade'] < 0 || ($max > 0 && $params['grade'] > $max)) {
            throw new \invalid_parameter_exception('Grade must be between 0 and ' . $max);
        }

        $also = helper::enrolled_also_userids($context, $params['userid'], $params['alsouserids']);

        $transaction = $DB->start_delegated_transaction();
        $assignment = helper::ensure_marking_workflow($assignment);
        $saved = helper::save_assignment_grade($assignment, $params['userid'], $params['grade']);
        foreach ($also as $alsouserid) {
            helper::save_assignment_grade($assignment, $alsouserid, $params['grade']);
        }
        $transaction->allow_commit();

        return [
            'grade' => $saved['grade'],
            'releasestate' => $saved['releasestate'],
            'applieduserids' => array_merge([$params['userid']], $also),
            'success' => true,
            'message' => 'Grade saved',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'grade' => new external_value(PARAM_FLOAT, 'Stored grade'),
            'releasestate' => new external_value(PARAM_ALPHA, 'notgraded, notreleased, released, or beingedited'),
            'applieduserids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'The primary user and every also-user the grade was saved for'
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
