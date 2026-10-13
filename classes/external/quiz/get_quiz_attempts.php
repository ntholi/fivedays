<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class get_quiz_attempts extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
        ]);
    }

    public static function execute(int $quizid): array {
        global $DB, $CFG, $PAGE;
        require_once($CFG->dirroot . '/user/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
        ]);

        [$quiz, $cm] = \local_activity_utils\helper::require_activity('quiz', $params['quizid']);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:viewquizattempts', $context);
        require_capability('mod/quiz:viewreports', $context);

        $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id], 'userid, attempt ASC');

        $attemptsarray = [];
        $usercache = [];

        foreach ($attempts as $attempt) {
            if (!isset($usercache[$attempt->userid])) {
                $user = $DB->get_record('user', ['id' => $attempt->userid], '*', IGNORE_MISSING);
                if ($user) {
                    $userpicture = new \user_picture($user);
                    $userpicture->size = 100;
                    $usercache[$attempt->userid] = [
                        'id' => (int)$user->id,
                        'fullname' => fullname($user),
                        'profileimageurl' => $userpicture->get_url($PAGE)->out(false),
                    ];
                } else {
                    $usercache[$attempt->userid] = [
                        'id' => (int)$attempt->userid,
                        'fullname' => 'Unknown User',
                        'profileimageurl' => '',
                    ];
                }
            }

            $attemptdata = [
                'id' => (int)$attempt->id,
                'userid' => (int)$attempt->userid,
                'attempt' => (int)$attempt->attempt,
                'state' => $attempt->state,
                'timestart' => (int)$attempt->timestart,
                'timefinish' => (int)($attempt->timefinish ?? 0),
                'timemodified' => (int)$attempt->timemodified,
                'user' => $usercache[$attempt->userid],
            ];
            if ($attempt->sumgrades !== null) {
                $attemptdata['sumgrades'] = (float)$attempt->sumgrades;
            }
            $attemptsarray[] = $attemptdata;
        }

        return [
            'success' => true,
            'attempts' => $attemptsarray,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'attempts' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Attempt ID'),
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'attempt' => new external_value(PARAM_INT, 'Attempt number'),
                    'state' => new external_value(PARAM_TEXT, 'Attempt state: inprogress, overdue, finished, abandoned'),
                    'timestart' => new external_value(PARAM_INT, 'Time attempt started'),
                    'timefinish' => new external_value(PARAM_INT, 'Time attempt finished'),
                    'timemodified' => new external_value(PARAM_INT, 'Time attempt was last modified'),
                    'sumgrades' => new external_value(PARAM_FLOAT, 'Sum of grades for this attempt', VALUE_OPTIONAL),
                    'user' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'User ID'),
                        'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                        'profileimageurl' => new external_value(PARAM_URL, 'User profile image URL'),
                    ]),
                ])
            ),
        ]);
    }
}
