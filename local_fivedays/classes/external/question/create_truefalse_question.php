<?php
namespace local_activity_utils\external\question;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;


class create_truefalse_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Question category ID'),
            'name' => new external_value(PARAM_TEXT, 'Question name'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text (HTML)'),
            'correctanswer' => new external_value(PARAM_INT, 'Correct answer: 1 = True, 0 = False'),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark (points)', VALUE_DEFAULT, 1.0),
            'feedbacktrue' => new external_value(PARAM_RAW, 'Feedback when True is selected', VALUE_DEFAULT, ''),
            'feedbackfalse' => new external_value(PARAM_RAW, 'Feedback when False is selected', VALUE_DEFAULT, ''),
            'generalfeedback' => new external_value(PARAM_RAW, 'General feedback shown after attempt', VALUE_DEFAULT, ''),
            'idnumber' => new external_value(PARAM_RAW, 'ID number for the question', VALUE_DEFAULT, ''),
            'tags' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Tag name'),
                'Tags for the question',
                VALUE_DEFAULT,
                []
            ),
            'questionbankentryid' => new external_value(PARAM_INT, 'Existing question bank entry ID; when set, saves a new version of that question', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $categoryid,
        string $name,
        string $questiontext,
        int $correctanswer,
        float $defaultmark = 1.0,
        string $feedbacktrue = '',
        string $feedbackfalse = '',
        string $generalfeedback = '',
        string $idnumber = '',
        array $tags = [],
        int $questionbankentryid = 0
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->libdir . '/questionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'name' => $name,
            'questiontext' => $questiontext,
            'correctanswer' => $correctanswer,
            'defaultmark' => $defaultmark,
            'feedbacktrue' => $feedbacktrue,
            'feedbackfalse' => $feedbackfalse,
            'generalfeedback' => $generalfeedback,
            'idnumber' => $idnumber,
            'tags' => $tags,
            'questionbankentryid' => $questionbankentryid,
        ]);

        
        $category = $DB->get_record('question_categories', ['id' => $params['categoryid']], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);

        self::validate_context($context);
        require_capability('local/activity_utils:createquestion', $context);
        require_capability('moodle/question:add', $context);
        \local_activity_utils\helper::require_question_entry($params['questionbankentryid']);

        
        $correctanswer = $params['correctanswer'] ? 1 : 0;

        
        $question = new \stdClass();
        $question->category = $params['categoryid'];
        $question->parent = 0;
        $question->name = $params['name'];
        $question->questiontext = $params['questiontext'];
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = $params['generalfeedback'];
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = $params['defaultmark'];
        $question->penalty = 1.0;
        $question->qtype = 'truefalse';
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        $questionid = $DB->insert_record('question', $question);

        
        $qbeid = \local_activity_utils\helper::save_question_version(
            $params['categoryid'],
            (int)$questionid,
            $params['idnumber'],
            $params['questionbankentryid']
        );

        
        $trueanswer = new \stdClass();
        $trueanswer->question = $questionid;
        $trueanswer->answer = get_string('true', 'qtype_truefalse');
        $trueanswer->answerformat = FORMAT_MOODLE;
        $trueanswer->fraction = $correctanswer ? 1.0 : 0.0;
        $trueanswer->feedback = $params['feedbacktrue'];
        $trueanswer->feedbackformat = FORMAT_HTML;

        $trueanswerid = $DB->insert_record('question_answers', $trueanswer);

        
        $falseanswer = new \stdClass();
        $falseanswer->question = $questionid;
        $falseanswer->answer = get_string('false', 'qtype_truefalse');
        $falseanswer->answerformat = FORMAT_MOODLE;
        $falseanswer->fraction = $correctanswer ? 0.0 : 1.0;
        $falseanswer->feedback = $params['feedbackfalse'];
        $falseanswer->feedbackformat = FORMAT_HTML;

        $falseanswerid = $DB->insert_record('question_answers', $falseanswer);

        
        $options = new \stdClass();
        $options->question = $questionid;
        $options->trueanswer = $trueanswerid;
        $options->falseanswer = $falseanswerid;

        $DB->insert_record('question_truefalse', $options);

        
        if (!empty($params['tags'])) {
            \core_tag_tag::set_item_tags('core_question', 'question', $questionid, $context, $params['tags']);
        }

        return [
            'questionid' => (int)$questionid,
            'questionbankentryid' => (int)$qbeid,
            'name' => $params['name'],
            'success' => true,
            'message' => 'True/false question created successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
            'questionbankentryid' => new external_value(PARAM_INT, 'Question bank entry ID (use this to add to quiz)'),
            'name' => new external_value(PARAM_TEXT, 'Question name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
