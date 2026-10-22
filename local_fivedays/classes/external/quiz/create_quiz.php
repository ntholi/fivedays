<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class create_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            'intro' => new external_value(PARAM_RAW, 'Quiz description/introduction (HTML)', VALUE_DEFAULT, ''),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_RAW, 'ID number for gradebook and external system reference', VALUE_DEFAULT, ''),

            
            'timeopen' => new external_value(PARAM_INT, 'Quiz open timestamp (0 = no restriction)', VALUE_DEFAULT, 0),
            'timeclose' => new external_value(PARAM_INT, 'Quiz close timestamp (0 = no restriction)', VALUE_DEFAULT, 0),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds (0 = no limit)', VALUE_DEFAULT, 0),
            'overduehandling' => new external_value(PARAM_ALPHA, 'How to handle overdue attempts: autosubmit, graceperiod, autoabandon', VALUE_DEFAULT, 'autosubmit'),
            'graceperiod' => new external_value(PARAM_INT, 'Grace period in seconds for graceperiod handling', VALUE_DEFAULT, 0),

            
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade for the quiz', VALUE_DEFAULT, 10.0),
            'grademethod' => new external_value(PARAM_INT, 'Grade method: 1=highest, 2=average, 3=first, 4=last', VALUE_DEFAULT, 1),
            'decimalpoints' => new external_value(PARAM_INT, 'Number of decimal places for grades (0-5)', VALUE_DEFAULT, 2),
            'questiondecimalpoints' => new external_value(PARAM_INT, 'Decimal places for question grades (-1 = same as quiz)', VALUE_DEFAULT, -1),

            
            'questionsperpage' => new external_value(PARAM_INT, 'Questions per page (0 = unlimited)', VALUE_DEFAULT, 1),
            'navmethod' => new external_value(PARAM_ALPHA, 'Navigation method: free or sequential', VALUE_DEFAULT, 'free'),
            'shuffleanswers' => new external_value(PARAM_INT, 'Shuffle answer options within questions (1=yes, 0=no)', VALUE_DEFAULT, 1),

            
            'preferredbehaviour' => new external_value(PARAM_ALPHANUMEXT, 'Question behaviour: deferredfeedback, adaptivenopenalty, adaptive, interactive, etc.', VALUE_DEFAULT, 'deferredfeedback'),
            'canredoquestions' => new external_value(PARAM_INT, 'Allow redo of individual questions (1=yes, 0=no)', VALUE_DEFAULT, 0),

            
            'attempts' => new external_value(PARAM_INT, 'Number of allowed attempts (0 = unlimited)', VALUE_DEFAULT, 0),
            'attemptonlast' => new external_value(PARAM_INT, 'Each attempt builds on last (1=yes, 0=no)', VALUE_DEFAULT, 0),

            
            
            'reviewattempt' => new external_value(PARAM_INT, 'Review attempt options bitmask', VALUE_DEFAULT, 69904),
            'reviewcorrectness' => new external_value(PARAM_INT, 'Review correctness options bitmask', VALUE_DEFAULT, 69904),
            'reviewmarks' => new external_value(PARAM_INT, 'Review marks options bitmask', VALUE_DEFAULT, 69904),
            'reviewspecificfeedback' => new external_value(PARAM_INT, 'Review specific feedback options bitmask', VALUE_DEFAULT, 69904),
            'reviewgeneralfeedback' => new external_value(PARAM_INT, 'Review general feedback options bitmask', VALUE_DEFAULT, 69904),
            'reviewrightanswer' => new external_value(PARAM_INT, 'Review right answer options bitmask', VALUE_DEFAULT, 69904),
            'reviewoverallfeedback' => new external_value(PARAM_INT, 'Review overall feedback options bitmask', VALUE_DEFAULT, 4368),
            'reviewmaxmarks' => new external_value(PARAM_INT, 'Review max marks options bitmask', VALUE_DEFAULT, 69904),

            
            'password' => new external_value(PARAM_RAW, 'Password to access the quiz', VALUE_DEFAULT, ''),
            'subnet' => new external_value(PARAM_RAW, 'IP addresses allowed to access (comma-separated)', VALUE_DEFAULT, ''),
            'browsersecurity' => new external_value(PARAM_ALPHANUMEXT, 'Browser security: - (none) or securewindow', VALUE_DEFAULT, '-'),
            'delay1' => new external_value(PARAM_INT, 'Delay between 1st and 2nd attempt in seconds', VALUE_DEFAULT, 0),
            'delay2' => new external_value(PARAM_INT, 'Delay between subsequent attempts in seconds', VALUE_DEFAULT, 0),

            
            'showuserpicture' => new external_value(PARAM_INT, 'Show user picture (0=no, 1=small, 2=large)', VALUE_DEFAULT, 0),
            'showblocks' => new external_value(PARAM_INT, 'Show blocks during quiz attempts (1=yes, 0=no)', VALUE_DEFAULT, 0),

            
            'completionattemptsexhausted' => new external_value(PARAM_INT, 'Complete when attempts exhausted (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'completionminattempts' => new external_value(PARAM_INT, 'Minimum number of attempts required for completion', VALUE_DEFAULT, 0),

            
            'visible' => new external_value(PARAM_INT, 'Module visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),

            
            'allowofflineattempts' => new external_value(PARAM_INT, 'Allow offline attempts in mobile app (1=yes, 0=no)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $intro = '',
        int $section = 0,
        string $idnumber = '',
        int $timeopen = 0,
        int $timeclose = 0,
        int $timelimit = 0,
        string $overduehandling = 'autosubmit',
        int $graceperiod = 0,
        float $grade = 10.0,
        int $grademethod = 1,
        int $decimalpoints = 2,
        int $questiondecimalpoints = -1,
        int $questionsperpage = 1,
        string $navmethod = 'free',
        int $shuffleanswers = 1,
        string $preferredbehaviour = 'deferredfeedback',
        int $canredoquestions = 0,
        int $attempts = 0,
        int $attemptonlast = 0,
        int $reviewattempt = 69904,
        int $reviewcorrectness = 69904,
        int $reviewmarks = 69904,
        int $reviewspecificfeedback = 69904,
        int $reviewgeneralfeedback = 69904,
        int $reviewrightanswer = 69904,
        int $reviewoverallfeedback = 4368,
        int $reviewmaxmarks = 69904,
        string $password = '',
        string $subnet = '',
        string $browsersecurity = '-',
        int $delay1 = 0,
        int $delay2 = 0,
        int $showuserpicture = 0,
        int $showblocks = 0,
        int $completionattemptsexhausted = 0,
        int $completionminattempts = 0,
        int $visible = 1,
        int $allowofflineattempts = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'section' => $section,
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

        
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createquiz', $context);
        require_capability('mod/quiz:addinstance', $context);

        
        $validoverduehandling = ['autosubmit', 'graceperiod', 'autoabandon'];
        if (!in_array($params['overduehandling'], $validoverduehandling)) {
            $params['overduehandling'] = 'autosubmit';
        }

        
        $validnavmethods = ['free', 'sequential'];
        if (!in_array($params['navmethod'], $validnavmethods)) {
            $params['navmethod'] = 'free';
        }

        
        if ($params['grademethod'] < 1 || $params['grademethod'] > 4) {
            $params['grademethod'] = 1;
        }

        
        if ($params['decimalpoints'] < 0 || $params['decimalpoints'] > 5) {
            $params['decimalpoints'] = 2;
        }

        
        $validbrowsersecurity = ['-', 'securewindow'];
        if (!in_array($params['browsersecurity'], $validbrowsersecurity)) {
            $params['browsersecurity'] = '-';
        }

        $moduleproperties = [
            'cmidnumber' => $params['idnumber'],
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'timeopen' => $params['timeopen'],
            'timeclose' => $params['timeclose'],
            'timelimit' => $params['timelimit'],
            'overduehandling' => $params['overduehandling'],
            'graceperiod' => $params['graceperiod'],
            'preferredbehaviour' => $params['preferredbehaviour'],
            'canredoquestions' => $params['canredoquestions'],
            'attempts' => $params['attempts'],
            'attemptonlast' => $params['attemptonlast'],
            'grademethod' => $params['grademethod'],
            'decimalpoints' => $params['decimalpoints'],
            'questiondecimalpoints' => $params['questiondecimalpoints'],
            'questionsperpage' => $params['questionsperpage'],
            'navmethod' => $params['navmethod'],
            'shuffleanswers' => $params['shuffleanswers'],
            'sumgrades' => 0,
            'grade' => $params['grade'],
            'quizpassword' => $params['password'],
            'subnet' => $params['subnet'],
            'browsersecurity' => $params['browsersecurity'],
            'delay1' => $params['delay1'],
            'delay2' => $params['delay2'],
            'showuserpicture' => $params['showuserpicture'],
            'showblocks' => $params['showblocks'],
            'completionattemptsexhausted' => $params['completionattemptsexhausted'],
            'completionminattempts' => $params['completionminattempts'],
            'allowofflineattempts' => $params['allowofflineattempts'],
        ];
        self::add_review_options($moduleproperties, 'attempt', $params['reviewattempt']);
        self::add_review_options($moduleproperties, 'correctness', $params['reviewcorrectness']);
        self::add_review_options($moduleproperties, 'marks', $params['reviewmarks']);
        self::add_review_options($moduleproperties, 'specificfeedback', $params['reviewspecificfeedback']);
        self::add_review_options($moduleproperties, 'generalfeedback', $params['reviewgeneralfeedback']);
        self::add_review_options($moduleproperties, 'rightanswer', $params['reviewrightanswer']);
        self::add_review_options($moduleproperties, 'overallfeedback', $params['reviewoverallfeedback']);
        self::add_review_options($moduleproperties, 'maxmarks', $params['reviewmaxmarks']);

        $moduleinfo = helper::create_module($course, 'quiz', $params['section'], $params['name'], $params['visible'],
            $moduleproperties);
        $quizid = $moduleinfo->instance;
        $cmid = $moduleinfo->coursemodule;

        rebuild_course_cache($params['courseid'], true);

        return [
            'id' => $quizid,
            'coursemoduleid' => $cmid,
            'name' => $params['name'],
            'success' => true,
            'message' => 'Quiz created successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Quiz ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }

    private static function add_review_options(array &$properties, string $field, int $value): void {
        $times = [
            'during' => \mod_quiz\question\display_options::DURING,
            'immediately' => \mod_quiz\question\display_options::IMMEDIATELY_AFTER,
            'open' => \mod_quiz\question\display_options::LATER_WHILE_OPEN,
            'closed' => \mod_quiz\question\display_options::AFTER_CLOSE,
        ];

        foreach ($times as $name => $bit) {
            $properties[$field . $name] = ($value & $bit) ? 1 : 0;
        }
    }
}
