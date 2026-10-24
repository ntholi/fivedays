<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class update_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID to update'),
            'name' => new external_value(PARAM_TEXT, 'Quiz name', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'Quiz description/introduction (HTML)', VALUE_DEFAULT, null),
            'idnumber' => new external_value(PARAM_RAW, 'ID number for gradebook', VALUE_DEFAULT, null),

            
            'timeopen' => new external_value(PARAM_INT, 'Quiz open timestamp (0 = no restriction)', VALUE_DEFAULT, null),
            'timeclose' => new external_value(PARAM_INT, 'Quiz close timestamp (0 = no restriction)', VALUE_DEFAULT, null),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds (0 = no limit)', VALUE_DEFAULT, null),
            'overduehandling' => new external_value(PARAM_ALPHA, 'How to handle overdue attempts: autosubmit, graceperiod, autoabandon', VALUE_DEFAULT, null),
            'graceperiod' => new external_value(PARAM_INT, 'Grace period in seconds', VALUE_DEFAULT, null),

            
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade for the quiz', VALUE_DEFAULT, null),
            'grademethod' => new external_value(PARAM_INT, 'Grade method: 1=highest, 2=average, 3=first, 4=last', VALUE_DEFAULT, null),
            'decimalpoints' => new external_value(PARAM_INT, 'Number of decimal places for grades', VALUE_DEFAULT, null),
            'questiondecimalpoints' => new external_value(PARAM_INT, 'Decimal places for question grades', VALUE_DEFAULT, null),

            
            'questionsperpage' => new external_value(PARAM_INT, 'Questions per page (0 = unlimited)', VALUE_DEFAULT, null),
            'navmethod' => new external_value(PARAM_ALPHA, 'Navigation method: free or sequential', VALUE_DEFAULT, null),
            'shuffleanswers' => new external_value(PARAM_INT, 'Shuffle answer options (1=yes, 0=no)', VALUE_DEFAULT, null),

            
            'preferredbehaviour' => new external_value(PARAM_ALPHANUMEXT, 'Question behaviour', VALUE_DEFAULT, null),
            'canredoquestions' => new external_value(PARAM_INT, 'Allow redo of individual questions', VALUE_DEFAULT, null),

            
            'attempts' => new external_value(PARAM_INT, 'Number of allowed attempts (0 = unlimited)', VALUE_DEFAULT, null),
            'attemptonlast' => new external_value(PARAM_INT, 'Each attempt builds on last', VALUE_DEFAULT, null),

            
            'reviewattempt' => new external_value(PARAM_INT, 'Review attempt options bitmask', VALUE_DEFAULT, null),
            'reviewcorrectness' => new external_value(PARAM_INT, 'Review correctness options bitmask', VALUE_DEFAULT, null),
            'reviewmarks' => new external_value(PARAM_INT, 'Review marks options bitmask', VALUE_DEFAULT, null),
            'reviewspecificfeedback' => new external_value(PARAM_INT, 'Review specific feedback options bitmask', VALUE_DEFAULT, null),
            'reviewgeneralfeedback' => new external_value(PARAM_INT, 'Review general feedback options bitmask', VALUE_DEFAULT, null),
            'reviewrightanswer' => new external_value(PARAM_INT, 'Review right answer options bitmask', VALUE_DEFAULT, null),
            'reviewoverallfeedback' => new external_value(PARAM_INT, 'Review overall feedback options bitmask', VALUE_DEFAULT, null),
            'reviewmaxmarks' => new external_value(PARAM_INT, 'Review max marks options bitmask', VALUE_DEFAULT, null),

            
            'password' => new external_value(PARAM_RAW, 'Password to access the quiz', VALUE_DEFAULT, null),
            'subnet' => new external_value(PARAM_RAW, 'IP addresses allowed', VALUE_DEFAULT, null),
            'browsersecurity' => new external_value(PARAM_ALPHANUMEXT, 'Browser security mode', VALUE_DEFAULT, null),
            'delay1' => new external_value(PARAM_INT, 'Delay between 1st and 2nd attempt', VALUE_DEFAULT, null),
            'delay2' => new external_value(PARAM_INT, 'Delay between subsequent attempts', VALUE_DEFAULT, null),

            
            'showuserpicture' => new external_value(PARAM_INT, 'Show user picture', VALUE_DEFAULT, null),
            'showblocks' => new external_value(PARAM_INT, 'Show blocks during quiz', VALUE_DEFAULT, null),

            
            'completionattemptsexhausted' => new external_value(PARAM_INT, 'Complete when attempts exhausted', VALUE_DEFAULT, null),
            'completionminattempts' => new external_value(PARAM_INT, 'Minimum attempts for completion', VALUE_DEFAULT, null),

            
            'visible' => new external_value(PARAM_INT, 'Module visibility (1=visible, 0=hidden)', VALUE_DEFAULT, null),

            
            'allowofflineattempts' => new external_value(PARAM_INT, 'Allow offline attempts', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $quizid,
        ?string $name = null,
        ?string $intro = null,
        ?string $idnumber = null,
        ?int $timeopen = null,
        ?int $timeclose = null,
        ?int $timelimit = null,
        ?string $overduehandling = null,
        ?int $graceperiod = null,
        ?float $grade = null,
        ?int $grademethod = null,
        ?int $decimalpoints = null,
        ?int $questiondecimalpoints = null,
        ?int $questionsperpage = null,
        ?string $navmethod = null,
        ?int $shuffleanswers = null,
        ?string $preferredbehaviour = null,
        ?int $canredoquestions = null,
        ?int $attempts = null,
        ?int $attemptonlast = null,
        ?int $reviewattempt = null,
        ?int $reviewcorrectness = null,
        ?int $reviewmarks = null,
        ?int $reviewspecificfeedback = null,
        ?int $reviewgeneralfeedback = null,
        ?int $reviewrightanswer = null,
        ?int $reviewoverallfeedback = null,
        ?int $reviewmaxmarks = null,
        ?string $password = null,
        ?string $subnet = null,
        ?string $browsersecurity = null,
        ?int $delay1 = null,
        ?int $delay2 = null,
        ?int $showuserpicture = null,
        ?int $showblocks = null,
        ?int $completionattemptsexhausted = null,
        ?int $completionminattempts = null,
        ?int $visible = null,
        ?int $allowofflineattempts = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/lib/gradelib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'name' => $name,
            'intro' => $intro,
            'idnumber' => $idnumber,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
            'overduehandling' => $overduehandling,
            'graceperiod' => $graceperiod,
            'grade' => $grade,
            'grademethod' => $grademethod,
            'decimalpoints' => $decimalpoints,
            'questiondecimalpoints' => $questiondecimalpoints,
            'questionsperpage' => $questionsperpage,
            'navmethod' => $navmethod,
            'shuffleanswers' => $shuffleanswers,
            'preferredbehaviour' => $preferredbehaviour,
            'canredoquestions' => $canredoquestions,
            'attempts' => $attempts,
            'attemptonlast' => $attemptonlast,
            'reviewattempt' => $reviewattempt,
            'reviewcorrectness' => $reviewcorrectness,
            'reviewmarks' => $reviewmarks,
            'reviewspecificfeedback' => $reviewspecificfeedback,
            'reviewgeneralfeedback' => $reviewgeneralfeedback,
            'reviewrightanswer' => $reviewrightanswer,
            'reviewoverallfeedback' => $reviewoverallfeedback,
            'reviewmaxmarks' => $reviewmaxmarks,
            'password' => $password,
            'subnet' => $subnet,
            'browsersecurity' => $browsersecurity,
            'delay1' => $delay1,
            'delay2' => $delay2,
            'showuserpicture' => $showuserpicture,
            'showblocks' => $showblocks,
            'completionattemptsexhausted' => $completionattemptsexhausted,
            'completionminattempts' => $completionminattempts,
            'visible' => $visible,
            'allowofflineattempts' => $allowofflineattempts,
        ]);

        
        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], '*', MUST_EXIST);

        
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);

        
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/fivedays:updatequiz', $context);
        require_capability('mod/quiz:manage', $context);

        $updated = false;
        $oldquiz = clone $quiz;

        
        if ($params['name'] !== null) {
            $quiz->name = $params['name'];
            $updated = true;
        }
        if ($params['intro'] !== null) {
            $quiz->intro = $params['intro'];
            $updated = true;
        }
        if ($params['timeopen'] !== null) {
            $quiz->timeopen = $params['timeopen'];
            $updated = true;
        }
        if ($params['timeclose'] !== null) {
            $quiz->timeclose = $params['timeclose'];
            $updated = true;
        }
        if ($params['timelimit'] !== null) {
            $quiz->timelimit = $params['timelimit'];
            $updated = true;
        }
        if ($params['overduehandling'] !== null) {
            $validoverduehandling = ['autosubmit', 'graceperiod', 'autoabandon'];
            if (in_array($params['overduehandling'], $validoverduehandling)) {
                $quiz->overduehandling = $params['overduehandling'];
                $updated = true;
            }
        }
        if ($params['graceperiod'] !== null) {
            $quiz->graceperiod = $params['graceperiod'];
            $updated = true;
        }
        if ($params['grademethod'] !== null && $params['grademethod'] >= 1 && $params['grademethod'] <= 4) {
            $quiz->grademethod = $params['grademethod'];
            $updated = true;
        }
        if ($params['decimalpoints'] !== null && $params['decimalpoints'] >= 0 && $params['decimalpoints'] <= 5) {
            $quiz->decimalpoints = $params['decimalpoints'];
            $updated = true;
        }
        if ($params['questiondecimalpoints'] !== null) {
            $quiz->questiondecimalpoints = $params['questiondecimalpoints'];
            $updated = true;
        }
        if ($params['questionsperpage'] !== null) {
            $quiz->questionsperpage = $params['questionsperpage'];
            $updated = true;
        }
        if ($params['navmethod'] !== null) {
            $validnavmethods = ['free', 'sequential'];
            if (in_array($params['navmethod'], $validnavmethods)) {
                $quiz->navmethod = $params['navmethod'];
                $updated = true;
            }
        }
        if ($params['shuffleanswers'] !== null) {
            $quiz->shuffleanswers = $params['shuffleanswers'] ? 1 : 0;
            $updated = true;
        }
        if ($params['preferredbehaviour'] !== null) {
            $quiz->preferredbehaviour = $params['preferredbehaviour'];
            $updated = true;
        }
        if ($params['canredoquestions'] !== null) {
            $quiz->canredoquestions = $params['canredoquestions'] ? 1 : 0;
            $updated = true;
        }
        if ($params['attempts'] !== null) {
            $quiz->attempts = $params['attempts'];
            $updated = true;
        }
        if ($params['attemptonlast'] !== null) {
            $quiz->attemptonlast = $params['attemptonlast'] ? 1 : 0;
            $updated = true;
        }

        
        if ($params['reviewattempt'] !== null) {
            $quiz->reviewattempt = $params['reviewattempt'];
            $updated = true;
        }
        if ($params['reviewcorrectness'] !== null) {
            $quiz->reviewcorrectness = $params['reviewcorrectness'];
            $updated = true;
        }
        if ($params['reviewmarks'] !== null) {
            $quiz->reviewmarks = $params['reviewmarks'];
            $updated = true;
        }
        if ($params['reviewspecificfeedback'] !== null) {
            $quiz->reviewspecificfeedback = $params['reviewspecificfeedback'];
            $updated = true;
        }
        if ($params['reviewgeneralfeedback'] !== null) {
            $quiz->reviewgeneralfeedback = $params['reviewgeneralfeedback'];
            $updated = true;
        }
        if ($params['reviewrightanswer'] !== null) {
            $quiz->reviewrightanswer = $params['reviewrightanswer'];
            $updated = true;
        }
        if ($params['reviewoverallfeedback'] !== null) {
            $quiz->reviewoverallfeedback = $params['reviewoverallfeedback'];
            $updated = true;
        }
        if ($params['reviewmaxmarks'] !== null) {
            $quiz->reviewmaxmarks = $params['reviewmaxmarks'];
            $updated = true;
        }

        
        if ($params['password'] !== null) {
            $quiz->password = $params['password'];
            $updated = true;
        }
        if ($params['subnet'] !== null) {
            $quiz->subnet = $params['subnet'];
            $updated = true;
        }
        if ($params['browsersecurity'] !== null) {
            $validbrowsersecurity = ['-', 'securewindow'];
            if (in_array($params['browsersecurity'], $validbrowsersecurity)) {
                $quiz->browsersecurity = $params['browsersecurity'];
                $updated = true;
            }
        }
        if ($params['delay1'] !== null) {
            $quiz->delay1 = $params['delay1'];
            $updated = true;
        }
        if ($params['delay2'] !== null) {
            $quiz->delay2 = $params['delay2'];
            $updated = true;
        }

        
        if ($params['showuserpicture'] !== null) {
            $quiz->showuserpicture = $params['showuserpicture'];
            $updated = true;
        }
        if ($params['showblocks'] !== null) {
            $quiz->showblocks = $params['showblocks'] ? 1 : 0;
            $updated = true;
        }

        
        if ($params['completionattemptsexhausted'] !== null) {
            $quiz->completionattemptsexhausted = $params['completionattemptsexhausted'] ? 1 : 0;
            $updated = true;
        }
        if ($params['completionminattempts'] !== null) {
            $quiz->completionminattempts = $params['completionminattempts'];
            $updated = true;
        }

        
        if ($params['allowofflineattempts'] !== null) {
            $quiz->allowofflineattempts = $params['allowofflineattempts'] ? 1 : 0;
            $updated = true;
        }

        $cmchanged = $params['visible'] !== null || $params['idnumber'] !== null;
        if (!$updated && !$cmchanged && $params['grade'] === null) {
            return [
                'id' => $quiz->id,
                'coursemoduleid' => $cm->id,
                'success' => true,
                'message' => 'No changes were made',
            ];
        }

        if ($updated) {
            $quiz->timemodified = time();
            $DB->update_record('quiz', $quiz);
        }

        if ($params['visible'] !== null) {
            set_coursemodule_visible($cm->id, $params['visible'] ? 1 : 0, 1, false);
        }

        if ($params['idnumber'] !== null) {
            $DB->set_field('course_modules', 'idnumber', $params['idnumber'], ['id' => $cm->id]);
        }

        $gradecalculator = \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator();

        if ($params['grade'] !== null) {
            $gradecalculator->update_quiz_maximum_grade($params['grade']);
            $quiz->grade = $params['grade'];
        }

        if ($oldquiz->grademethod != $quiz->grademethod) {
            $gradecalculator->recompute_all_final_grades();
            quiz_update_grades($quiz);
        }

        $timingchanged = $oldquiz->timelimit != $quiz->timelimit
            || $oldquiz->timeclose != $quiz->timeclose
            || $oldquiz->graceperiod != $quiz->graceperiod;
        if ($timingchanged || $oldquiz->timeopen != $quiz->timeopen) {
            quiz_update_events($quiz);
        }
        if ($timingchanged) {
            quiz_update_open_attempts(['quizid' => $quiz->id]);
        }

        if ($params['name'] !== null || $params['idnumber'] !== null) {
            $gradeitem = [];
            if ($params['name'] !== null) {
                $gradeitem['itemname'] = $params['name'];
            }
            if ($params['idnumber'] !== null) {
                $gradeitem['idnumber'] = $params['idnumber'];
            }
            grade_update('mod/quiz', $cm->course, 'mod', 'quiz', $quiz->id, 0, null, $gradeitem);
        }

        rebuild_course_cache($cm->course, true);

        return [
            'id' => $quiz->id,
            'coursemoduleid' => $cm->id,
            'success' => true,
            'message' => 'Quiz updated successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Quiz ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
