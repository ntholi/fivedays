<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class update_quiz_slot_mark extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
            'slot' => new external_value(PARAM_INT, 'Slot number'),
            'maxmark' => new external_value(PARAM_FLOAT, 'New maximum mark for the slot'),
        ]);
    }

    public static function execute(int $quizid, int $slot, float $maxmark): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'slot' => $slot,
            'maxmark' => $maxmark,
        ]);

        $quizobj = \mod_quiz\quiz_settings::create($params['quizid']);
        $context = $quizobj->get_context();
        self::validate_context($context);
        require_capability('local/fivedays:managequizquestions', $context);
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

        if ($params['maxmark'] != 0 && \local_fivedays\helper::get_slot_qtype((int)$slotrecord->id) === 'description') {
            return [
                'success' => false,
                'message' => 'A description is not marked',
            ];
        }

        if ($params['maxmark'] < 0) {
            return [
                'success' => false,
                'message' => 'Mark cannot be negative',
            ];
        }

        if ($quizobj->get_structure()->update_slot_maxmark($slotrecord, $params['maxmark'])) {
            $quiz = $quizobj->get_quiz();
            $gradecalculator = $quizobj->get_grade_calculator();
            quiz_delete_previews($quiz);
            $gradecalculator->recompute_quiz_sumgrades();
            $gradecalculator->recompute_all_attempt_sumgrades();
            $gradecalculator->recompute_all_final_grades();
            quiz_update_grades($quiz, 0, true);
        }

        return [
            'success' => true,
            'message' => 'Slot ' . $params['slot'] . ' mark updated',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
