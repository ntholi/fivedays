<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class get_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
        ]);
    }

    public static function execute(int $quizid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
        ]);

        
        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], '*', MUST_EXIST);

        
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);

        
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:viewquiz', $context);
        require_capability('mod/quiz:view', $context);

        
        $course = $DB->get_record('course', ['id' => $cm->course], 'id, fullname, shortname', MUST_EXIST);

        
        $sections = $DB->get_records('quiz_sections', ['quizid' => $quiz->id], 'firstslot ASC');
        $sectionsarray = [];
        foreach ($sections as $section) {
            $sectionsarray[] = [
                'id' => (int)$section->id,
                'firstslot' => (int)$section->firstslot,
                'heading' => $section->heading ?? '',
                'shufflequestions' => (int)$section->shufflequestions,
            ];
        }

        
        
        $sql = "SELECT qs.id AS slotid, qs.slot, qs.page, qs.maxmark, qs.requireprevious, qs.displaynumber,
                       qr.questionbankentryid, qr.version AS refversion,
                       qbe.idnumber AS questionidnumber,
                       qv.questionid, qv.version, qv.status,
                       q.name AS questionname, q.qtype, q.questiontext, q.questiontextformat,
                       q.defaultmark, q.generalfeedback, q.generalfeedbackformat
                  FROM {quiz_slots} qs
                  JOIN {question_references} qr ON qr.component = 'mod_quiz'
                       AND qr.questionarea = 'slot'
                       AND qr.itemid = qs.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qs.quizid = ?
                   AND qv.version = (
                       SELECT MAX(qv2.version)
                         FROM {question_versions} qv2
                        WHERE qv2.questionbankentryid = qbe.id
                          AND qv2.status = 'ready'
                   )
              ORDER BY qs.slot ASC";

        $slots = $DB->get_records_sql($sql, [$quiz->id]);

        $questionsarray = [];
        foreach ($slots as $slot) {
            $questionid = (int)$slot->questionid;

            $answers = $DB->get_records('question_answers', ['question' => $questionid], 'id ASC');
            $tolerances = [];
            if ($slot->qtype === 'numerical') {
                $tolerances = $DB->get_records_menu('question_numerical', ['question' => $questionid], '', 'answer, tolerance');
            }
            $answersarray = [];
            foreach ($answers as $answer) {
                $answerdata = [
                    'id' => (int)$answer->id,
                    'answer' => $answer->answer ?? '',
                    'answerformat' => (int)$answer->answerformat,
                    'fraction' => (float)$answer->fraction,
                    'feedback' => $answer->feedback ?? '',
                    'feedbackformat' => (int)$answer->feedbackformat,
                ];
                if (isset($tolerances[$answer->id])) {
                    $answerdata['tolerance'] = (float)$tolerances[$answer->id];
                }
                $answersarray[] = $answerdata;
            }

            $options = null;
            if ($slot->qtype === 'shortanswer') {
                $saopt = $DB->get_record('qtype_shortanswer_options', ['questionid' => $questionid]);
                if ($saopt) {
                    $options = [
                        'usecase' => (int)$saopt->usecase,
                    ];
                }
            } else if ($slot->qtype === 'truefalse') {
                $tfopt = $DB->get_record('question_truefalse', ['question' => $questionid]);
                if ($tfopt) {
                    $options = [
                        'trueanswer' => (int)$tfopt->trueanswer,
                        'falseanswer' => (int)$tfopt->falseanswer,
                    ];
                }
            } else if ($slot->qtype === 'multichoice') {
                $mcopt = $DB->get_record('qtype_multichoice_options', ['questionid' => $questionid]);
                if ($mcopt) {
                    $options = [
                        'single' => (int)$mcopt->single,
                        'shuffleanswers' => (int)$mcopt->shuffleanswers,
                        'answernumbering' => $mcopt->answernumbering ?? 'abc',
                        'correctfeedback' => $mcopt->correctfeedback ?? '',
                        'correctfeedbackformat' => (int)$mcopt->correctfeedbackformat,
                        'partiallycorrectfeedback' => $mcopt->partiallycorrectfeedback ?? '',
                        'partiallycorrectfeedbackformat' => (int)$mcopt->partiallycorrectfeedbackformat,
                        'incorrectfeedback' => $mcopt->incorrectfeedback ?? '',
                        'incorrectfeedbackformat' => (int)$mcopt->incorrectfeedbackformat,
                    ];
                }
            } else if ($slot->qtype === 'essay') {
                $essayopt = $DB->get_record('qtype_essay_options', ['questionid' => $questionid]);
                if ($essayopt) {
                    $options = [
                        'responseformat' => $essayopt->responseformat,
                        'responserequired' => (int)$essayopt->responserequired,
                        'responsefieldlines' => (int)$essayopt->responsefieldlines,
                        'minwordlimit' => (int)$essayopt->minwordlimit,
                        'maxwordlimit' => (int)$essayopt->maxwordlimit,
                        'attachments' => (int)$essayopt->attachments,
                        'attachmentsrequired' => (int)$essayopt->attachmentsrequired,
                        'graderinfo' => $essayopt->graderinfo ?? '',
                        'responsetemplate' => $essayopt->responsetemplate ?? '',
                    ];
                }
            }

            $questiondata = [
                'slotid' => (int)$slot->slotid,
                'slot' => (int)$slot->slot,
                'page' => (int)$slot->page,
                'maxmark' => (float)$slot->maxmark,
                'requireprevious' => (int)$slot->requireprevious,
                'displaynumber' => $slot->displaynumber ?? '',
                'questionbankentryid' => (int)$slot->questionbankentryid,
                'questionid' => $questionid,
                'questionidnumber' => $slot->questionidnumber ?? '',
                'questionname' => $slot->questionname,
                'qtype' => $slot->qtype,
                'questiontext' => $slot->questiontext ?? '',
                'questiontextformat' => (int)$slot->questiontextformat,
                'defaultmark' => (float)$slot->defaultmark,
                'generalfeedback' => $slot->generalfeedback ?? '',
                'generalfeedbackformat' => (int)$slot->generalfeedbackformat,
                'version' => (int)$slot->version,
                'status' => $slot->status,
                'answers' => $answersarray,
            ];

            if ($options !== null) {
                $questiondata['options'] = $options;
            }

            $questionsarray[] = $questiondata;
        }

        
        $attemptcount = $DB->count_records('quiz_attempts', ['quiz' => $quiz->id, 'preview' => 0]);

        return [
            'id' => (int)$quiz->id,
            'coursemoduleid' => (int)$cm->id,
            'courseid' => (int)$cm->course,
            'coursename' => $course->fullname,
            'name' => $quiz->name,
            'intro' => $quiz->intro ?? '',
            'introformat' => (int)$quiz->introformat,
            'timeopen' => (int)$quiz->timeopen,
            'timeclose' => (int)$quiz->timeclose,
            'timelimit' => (int)$quiz->timelimit,
            'overduehandling' => $quiz->overduehandling,
            'graceperiod' => (int)$quiz->graceperiod,
            'preferredbehaviour' => $quiz->preferredbehaviour,
            'canredoquestions' => (int)$quiz->canredoquestions,
            'attempts' => (int)$quiz->attempts,
            'attemptonlast' => (int)$quiz->attemptonlast,
            'grademethod' => (int)$quiz->grademethod,
            'decimalpoints' => (int)$quiz->decimalpoints,
            'questiondecimalpoints' => (int)$quiz->questiondecimalpoints,
            'reviewattempt' => (int)$quiz->reviewattempt,
            'reviewcorrectness' => (int)$quiz->reviewcorrectness,
            'reviewmarks' => (int)$quiz->reviewmarks,
            'reviewspecificfeedback' => (int)$quiz->reviewspecificfeedback,
            'reviewgeneralfeedback' => (int)$quiz->reviewgeneralfeedback,
            'reviewrightanswer' => (int)$quiz->reviewrightanswer,
            'reviewoverallfeedback' => (int)$quiz->reviewoverallfeedback,
            'reviewmaxmarks' => (int)$quiz->reviewmaxmarks,
            'questionsperpage' => (int)$quiz->questionsperpage,
            'navmethod' => $quiz->navmethod,
            'shuffleanswers' => (int)$quiz->shuffleanswers,
            'sumgrades' => (float)$quiz->sumgrades,
            'grade' => (float)$quiz->grade,
            'timecreated' => (int)$quiz->timecreated,
            'timemodified' => (int)$quiz->timemodified,
            'password' => $quiz->password ?? '',
            'subnet' => $quiz->subnet ?? '',
            'browsersecurity' => $quiz->browsersecurity ?? '-',
            'delay1' => (int)$quiz->delay1,
            'delay2' => (int)$quiz->delay2,
            'showuserpicture' => (int)$quiz->showuserpicture,
            'showblocks' => (int)$quiz->showblocks,
            'completionattemptsexhausted' => (int)$quiz->completionattemptsexhausted,
            'completionminattempts' => (int)$quiz->completionminattempts,
            'allowofflineattempts' => (int)$quiz->allowofflineattempts,
            'visible' => (int)$cm->visible,
            'attemptcount' => $attemptcount,
            'sections' => $sectionsarray,
            'questions' => $questionsarray,
            'success' => true,
            'message' => 'Quiz retrieved successfully with ' . count($questionsarray) . ' question(s)',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Quiz ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
            'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            'intro' => new external_value(PARAM_RAW, 'Quiz introduction'),
            'introformat' => new external_value(PARAM_INT, 'Intro format'),
            'timeopen' => new external_value(PARAM_INT, 'Quiz open time'),
            'timeclose' => new external_value(PARAM_INT, 'Quiz close time'),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds'),
            'overduehandling' => new external_value(PARAM_TEXT, 'Overdue handling method'),
            'graceperiod' => new external_value(PARAM_INT, 'Grace period in seconds'),
            'preferredbehaviour' => new external_value(PARAM_TEXT, 'Question behaviour'),
            'canredoquestions' => new external_value(PARAM_INT, 'Can redo questions'),
            'attempts' => new external_value(PARAM_INT, 'Number of allowed attempts'),
            'attemptonlast' => new external_value(PARAM_INT, 'Build on last attempt'),
            'grademethod' => new external_value(PARAM_INT, 'Grade method'),
            'decimalpoints' => new external_value(PARAM_INT, 'Decimal points for grades'),
            'questiondecimalpoints' => new external_value(PARAM_INT, 'Question decimal points'),
            'reviewattempt' => new external_value(PARAM_INT, 'Review attempt options'),
            'reviewcorrectness' => new external_value(PARAM_INT, 'Review correctness options'),
            'reviewmarks' => new external_value(PARAM_INT, 'Review marks options'),
            'reviewspecificfeedback' => new external_value(PARAM_INT, 'Review specific feedback options'),
            'reviewgeneralfeedback' => new external_value(PARAM_INT, 'Review general feedback options'),
            'reviewrightanswer' => new external_value(PARAM_INT, 'Review right answer options'),
            'reviewoverallfeedback' => new external_value(PARAM_INT, 'Review overall feedback options'),
            'reviewmaxmarks' => new external_value(PARAM_INT, 'Review max marks options'),
            'questionsperpage' => new external_value(PARAM_INT, 'Questions per page'),
            'navmethod' => new external_value(PARAM_TEXT, 'Navigation method'),
            'shuffleanswers' => new external_value(PARAM_INT, 'Shuffle answers'),
            'sumgrades' => new external_value(PARAM_FLOAT, 'Sum of question grades'),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade'),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            'password' => new external_value(PARAM_TEXT, 'Quiz password'),
            'subnet' => new external_value(PARAM_TEXT, 'Allowed IP subnet'),
            'browsersecurity' => new external_value(PARAM_TEXT, 'Browser security'),
            'delay1' => new external_value(PARAM_INT, 'Delay between attempts 1-2'),
            'delay2' => new external_value(PARAM_INT, 'Delay between later attempts'),
            'showuserpicture' => new external_value(PARAM_INT, 'Show user picture'),
            'showblocks' => new external_value(PARAM_INT, 'Show blocks'),
            'completionattemptsexhausted' => new external_value(PARAM_INT, 'Complete when attempts exhausted'),
            'completionminattempts' => new external_value(PARAM_INT, 'Minimum attempts for completion'),
            'allowofflineattempts' => new external_value(PARAM_INT, 'Allow offline attempts'),
            'visible' => new external_value(PARAM_INT, 'Module visibility'),
            'attemptcount' => new external_value(PARAM_INT, 'Total number of attempts'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Section ID'),
                    'firstslot' => new external_value(PARAM_INT, 'First slot number in section'),
                    'heading' => new external_value(PARAM_TEXT, 'Section heading'),
                    'shufflequestions' => new external_value(PARAM_INT, 'Shuffle questions in section'),
                ]),
                'Quiz sections'
            ),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'slotid' => new external_value(PARAM_INT, 'Slot ID'),
                    'slot' => new external_value(PARAM_INT, 'Slot number'),
                    'page' => new external_value(PARAM_INT, 'Page number'),
                    'maxmark' => new external_value(PARAM_FLOAT, 'Maximum mark for this question'),
                    'requireprevious' => new external_value(PARAM_INT, 'Require previous question'),
                    'displaynumber' => new external_value(PARAM_TEXT, 'Custom display number'),
                    'questionbankentryid' => new external_value(PARAM_INT, 'Question bank entry ID'),
                    'questionid' => new external_value(PARAM_INT, 'Question ID'),
                    'questionidnumber' => new external_value(PARAM_TEXT, 'Question ID number'),
                    'questionname' => new external_value(PARAM_TEXT, 'Question name'),
                    'qtype' => new external_value(PARAM_TEXT, 'Question type'),
                    'questiontext' => new external_value(PARAM_RAW, 'Question text'),
                    'questiontextformat' => new external_value(PARAM_INT, 'Question text format'),
                    'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark'),
                    'generalfeedback' => new external_value(PARAM_RAW, 'General feedback'),
                    'generalfeedbackformat' => new external_value(PARAM_INT, 'General feedback format'),
                    'version' => new external_value(PARAM_INT, 'Question version'),
                    'status' => new external_value(PARAM_TEXT, 'Question status'),
                    'answers' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Answer ID'),
                            'answer' => new external_value(PARAM_RAW, 'Answer text'),
                            'answerformat' => new external_value(PARAM_INT, 'Answer format'),
                            'fraction' => new external_value(PARAM_FLOAT, 'Fraction (1.0=correct, 0.0=incorrect)'),
                            'feedback' => new external_value(PARAM_RAW, 'Feedback for this answer'),
                            'feedbackformat' => new external_value(PARAM_INT, 'Feedback format'),
                            'tolerance' => new external_value(PARAM_FLOAT, 'Accepted error (numerical only)', VALUE_OPTIONAL),
                        ]),
                        'Question answers'
                    ),
                    'options' => new external_single_structure([
                        'usecase' => new external_value(PARAM_INT, 'Case sensitive (shortanswer only)', VALUE_OPTIONAL),
                        'trueanswer' => new external_value(PARAM_INT, 'True answer ID (truefalse only)', VALUE_OPTIONAL),
                        'falseanswer' => new external_value(PARAM_INT, 'False answer ID (truefalse only)', VALUE_OPTIONAL),
                        'single' => new external_value(PARAM_INT, 'Single answer mode (multichoice only)', VALUE_OPTIONAL),
                        'shuffleanswers' => new external_value(PARAM_INT, 'Shuffle answers (multichoice only)', VALUE_OPTIONAL),
                        'answernumbering' => new external_value(PARAM_TEXT, 'Answer numbering style (multichoice only)', VALUE_OPTIONAL),
                        'correctfeedback' => new external_value(PARAM_RAW, 'Correct feedback (multichoice only)', VALUE_OPTIONAL),
                        'correctfeedbackformat' => new external_value(PARAM_INT, 'Correct feedback format (multichoice only)', VALUE_OPTIONAL),
                        'partiallycorrectfeedback' => new external_value(PARAM_RAW, 'Partially correct feedback (multichoice only)', VALUE_OPTIONAL),
                        'partiallycorrectfeedbackformat' => new external_value(PARAM_INT, 'Partially correct feedback format (multichoice only)', VALUE_OPTIONAL),
                        'incorrectfeedback' => new external_value(PARAM_RAW, 'Incorrect feedback (multichoice only)', VALUE_OPTIONAL),
                        'incorrectfeedbackformat' => new external_value(PARAM_INT, 'Incorrect feedback format (multichoice only)', VALUE_OPTIONAL),
                        'responseformat' => new external_value(PARAM_ALPHA, 'Response format (essay only)', VALUE_OPTIONAL),
                        'responserequired' => new external_value(PARAM_INT, 'Response required (essay only)', VALUE_OPTIONAL),
                        'responsefieldlines' => new external_value(PARAM_INT, 'Response field lines (essay only)', VALUE_OPTIONAL),
                        'minwordlimit' => new external_value(PARAM_INT, 'Minimum word limit (essay only)', VALUE_OPTIONAL),
                        'maxwordlimit' => new external_value(PARAM_INT, 'Maximum word limit (essay only)', VALUE_OPTIONAL),
                        'attachments' => new external_value(PARAM_INT, 'Allowed attachments (essay only)', VALUE_OPTIONAL),
                        'attachmentsrequired' => new external_value(PARAM_INT, 'Required attachments (essay only)', VALUE_OPTIONAL),
                        'graderinfo' => new external_value(PARAM_RAW, 'Information for graders (essay only)', VALUE_OPTIONAL),
                        'responsetemplate' => new external_value(PARAM_RAW, 'Response template (essay only)', VALUE_OPTIONAL),
                    ], 'Question type-specific options', VALUE_OPTIONAL),
                ]),
                'Quiz questions'
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
