<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class get_quiz_attempt_details extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
        ]);
    }

    public static function execute(int $attemptid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');

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

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        $grade = null;
        if ($attempt->sumgrades !== null && $quiz->sumgrades > 0) {
            $grade = ($attempt->sumgrades / $quiz->sumgrades) * 100;
        }

        $questionsarray = [];
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            $question = $qa->get_question();

            $mark = $qa->get_mark();
            $maxmark = $qa->get_max_mark();

            $state = $qa->get_state();
            $statename = self::map_question_state($state);

            $response = $qa->get_response_summary();

            $rightanswer = $qa->get_right_answer_summary();

            $feedback = $state->is_graded() ? $question->generalfeedback : null;
            [$comment] = $qa->get_manual_comment();

            $questiondata = [
                'slot' => (int)$slot,
                'type' => $question->qtype->name(),
                'name' => $question->name,
                'questiontext' => $question->questiontext,
                'maxmark' => (float)$maxmark,
                'response' => $response ?? '',
                'rightanswer' => $rightanswer ?? '',
                'state' => $statename,
            ];
            if ($mark !== null) {
                $questiondata['mark'] = (float)$mark;
            }
            if ($feedback !== null && $feedback !== '') {
                $questiondata['feedback'] = $feedback;
            }
            if ($comment !== null && $comment !== '') {
                $questiondata['comment'] = $comment;
            }
            $questionsarray[] = $questiondata;
        }

        $attemptdata = [
            'id' => (int)$attempt->id,
            'userid' => (int)$attempt->userid,
            'state' => $attempt->state,
            'timestart' => (int)$attempt->timestart,
            'timefinish' => (int)($attempt->timefinish ?? 0),
            'questions' => $questionsarray,
        ];
        if ($attempt->sumgrades !== null) {
            $attemptdata['sumgrades'] = (float)$attempt->sumgrades;
        }
        if ($grade !== null) {
            $attemptdata['grade'] = (float)$grade;
        }

        return [
            'success' => true,
            'attempt' => $attemptdata,
        ];
    }

    private static function map_question_state(\question_state $state): string {
        if ($state == \question_state::$gradedright) {
            return 'gradedright';
        } else if ($state == \question_state::$gradedwrong) {
            return 'gradedwrong';
        } else if ($state == \question_state::$gradedpartial) {
            return 'gradedpartial';
        } else if ($state == \question_state::$needsgrading) {
            return 'needsgrading';
        } else if ($state == \question_state::$gaveup) {
            return 'gaveup';
        } else if ($state == \question_state::$mangrright) {
            return 'gradedright';
        } else if ($state == \question_state::$mangrwrong) {
            return 'gradedwrong';
        } else if ($state == \question_state::$mangrpartial) {
            return 'gradedpartial';
        } else if ($state == \question_state::$complete) {
            return 'complete';
        } else if ($state == \question_state::$todo) {
            return 'todo';
        }
        return 'unknown';
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'attempt' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Attempt ID'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'state' => new external_value(PARAM_TEXT, 'Attempt state'),
                'timestart' => new external_value(PARAM_INT, 'Time attempt started'),
                'timefinish' => new external_value(PARAM_INT, 'Time attempt finished'),
                'sumgrades' => new external_value(PARAM_FLOAT, 'Sum of grades', VALUE_OPTIONAL),
                'grade' => new external_value(PARAM_FLOAT, 'Grade percentage', VALUE_OPTIONAL),
                'questions' => new external_multiple_structure(
                    new external_single_structure([
                        'slot' => new external_value(PARAM_INT, 'Question slot number'),
                        'type' => new external_value(PARAM_TEXT, 'Question type'),
                        'name' => new external_value(PARAM_TEXT, 'Question name'),
                        'questiontext' => new external_value(PARAM_RAW, 'Question text'),
                        'maxmark' => new external_value(PARAM_FLOAT, 'Maximum mark'),
                        'mark' => new external_value(PARAM_FLOAT, 'Actual mark', VALUE_OPTIONAL),
                        'response' => new external_value(PARAM_RAW, 'Student response'),
                        'rightanswer' => new external_value(PARAM_RAW, 'Right answer'),
                        'state' => new external_value(PARAM_TEXT, 'Question state'),
                        'feedback' => new external_value(PARAM_RAW, 'General feedback', VALUE_OPTIONAL),
                        'comment' => new external_value(PARAM_RAW, 'Teacher comment', VALUE_OPTIONAL),
                    ])
                ),
            ]),
        ]);
    }
}
