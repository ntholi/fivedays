<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class remove_question_from_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
            'slot' => new external_value(PARAM_INT, 'Slot number to remove'),
        ]);
    }

    public static function execute(int $quizid, int $slot): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'slot' => $slot,
        ]);

        $quizobj = \mod_quiz\quiz_settings::create($params['quizid']);
        $context = $quizobj->get_context();
        self::validate_context($context);
        require_capability('local/activity_utils:managequizquestions', $context);
        require_capability('mod/quiz:manage', $context);

        $slotrecord = $DB->get_record('quiz_slots', [
            'quizid' => $params['quizid'],
            'slot' => $params['slot'],
        ]);

        if (!$slotrecord) {
            return [
                'success' => false,
                'message' => 'Slot not found in quiz',
            ];
        }

        $sql = "SELECT q.name
                  FROM {question_references} qr
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qr.component = 'mod_quiz'
                   AND qr.questionarea = 'slot'
                   AND qr.itemid = ?
                   AND qv.version = (
                       SELECT MAX(qv2.version)
                         FROM {question_versions} qv2
                        WHERE qv2.questionbankentryid = qbe.id
                          AND qv2.status = 'ready'
                   )";
        $questionname = $DB->get_field_sql($sql, [$slotrecord->id]);
        $questionname = $questionname ?: 'Unknown question';

        $quizobj->get_structure()->remove_slot($params['slot']);
        quiz_delete_previews($quizobj->get_quiz());
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        return [
            'success' => true,
            'message' => 'Question "' . $questionname . '" removed from slot ' . $params['slot'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
