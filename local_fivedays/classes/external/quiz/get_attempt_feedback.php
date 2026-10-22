<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class get_attempt_feedback extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
        ]);
    }

    public static function execute(int $attemptid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
        ]);

        $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:viewquizattempts', $context);
        require_capability('mod/quiz:viewreports', $context);

        $feedbackrecord = $DB->get_record('quiz_attempt_feedback', ['attemptid' => $params['attemptid']]);

        $result = [
            'success' => true,
        ];
        if ($feedbackrecord) {
            $result['feedback'] = $feedbackrecord->feedback;
        }

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback text', VALUE_OPTIONAL),
        ]);
    }
}
