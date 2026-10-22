<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class add_attempt_feedback extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback text'),
        ]);
    }

    public static function execute(int $attemptid, string $feedback): array {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'feedback' => $feedback,
        ]);

        $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:gradequizattempts', $context);
        require_capability('mod/quiz:grade', $context);

        if ($attempt->state !== 'finished') {
            throw new \moodle_exception('attemptnotfinished', 'quiz');
        }

        $existing = $DB->get_record('quiz_attempt_feedback', ['attemptid' => $params['attemptid']]);

        if ($existing) {
            $existing->feedback = $params['feedback'];
            $existing->feedbackformat = FORMAT_HTML;
            $existing->timemodified = time();
            $existing->userid = $USER->id;
            $DB->update_record('quiz_attempt_feedback', $existing);
        } else {
            $record = new \stdClass();
            $record->attemptid = $params['attemptid'];
            $record->feedback = $params['feedback'];
            $record->feedbackformat = FORMAT_HTML;
            $record->timecreated = time();
            $record->timemodified = time();
            $record->userid = $USER->id;
            $DB->insert_record('quiz_attempt_feedback', $record);
        }

        return [
            'success' => true,
            'message' => 'Feedback added successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
