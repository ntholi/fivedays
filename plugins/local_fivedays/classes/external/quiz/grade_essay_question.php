<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class grade_essay_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'slot' => new external_value(PARAM_INT, 'Question slot number'),
            'mark' => new external_value(PARAM_FLOAT, 'Mark to assign'),
            'comment' => new external_value(PARAM_RAW, 'Feedback comment', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $attemptid, int $slot, float $mark, string $comment = ''): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'slot' => $slot,
            'mark' => $mark,
            'comment' => $comment,
        ]);

        $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/fivedays:gradequizattempts', $context);
        require_capability('mod/quiz:grade', $context);

        if ($attempt->state !== 'finished') {
            throw new \moodle_exception('attemptnotfinished', 'quiz');
        }

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        $slots = $quba->get_slots();
        if (!in_array($params['slot'], $slots)) {
            throw new \invalid_parameter_exception('Invalid slot number');
        }

        $qa = $quba->get_question_attempt($params['slot']);
        $maxmark = $qa->get_max_mark();

        if ($params['mark'] < 0 || $params['mark'] > $maxmark) {
            throw new \invalid_parameter_exception("Mark must be between 0 and {$maxmark}");
        }

        $quba->manual_grade($params['slot'], $params['comment'], $params['mark'], FORMAT_HTML);

        \question_engine::save_questions_usage_by_activity($quba);

        $attempt->sumgrades = $quba->get_total_mark();
        $attempt->timemodified = time();
        $DB->update_record('quiz_attempts', $attempt);

        if (empty($attempt->preview)) {
            \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_final_grade((int)$attempt->userid);
        }

        return [
            'success' => true,
            'message' => 'Question graded successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
