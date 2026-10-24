<?php
namespace local_fivedays\external\question;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class create_gapselect_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Question category ID'),
            'name' => new external_value(PARAM_TEXT, 'Question name'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text (HTML)'),
            'choices' => new external_value(PARAM_RAW, 'JSON array of choices: [{"answer":"word","group":1}]. In the question text, [[1]], [[2]] ... mark the gaps and point at a choice by its 1-based position in this array. Group is 1-20.'),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark (points)', VALUE_DEFAULT, 1.0),
            'shuffleanswers' => new external_value(PARAM_INT, 'Shuffle the choices (1=yes, 0=no)', VALUE_DEFAULT, 1),
            'correctfeedback' => new external_value(PARAM_RAW, 'Feedback for correct answer', VALUE_DEFAULT, ''),
            'partiallycorrectfeedback' => new external_value(PARAM_RAW, 'Feedback for partially correct answer', VALUE_DEFAULT, ''),
            'incorrectfeedback' => new external_value(PARAM_RAW, 'Feedback for incorrect answer', VALUE_DEFAULT, ''),
            'generalfeedback' => new external_value(PARAM_RAW, 'General feedback shown after attempt', VALUE_DEFAULT, ''),
            'idnumber' => new external_value(PARAM_RAW, 'ID number for the question', VALUE_DEFAULT, ''),
            'tags' => new external_value(PARAM_RAW, 'JSON array of tag names: ["tag1","tag2"]', VALUE_DEFAULT, '[]'),
            'questionbankentryid' => new external_value(PARAM_INT, 'Existing question bank entry ID; when set, saves a new version of that question', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $categoryid,
        string $name,
        string $questiontext,
        string $choices,
        float $defaultmark = 1.0,
        int $shuffleanswers = 1,
        string $correctfeedback = '',
        string $partiallycorrectfeedback = '',
        string $incorrectfeedback = '',
        string $generalfeedback = '',
        string $idnumber = '',
        string $tags = '[]',
        int $questionbankentryid = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->dirroot . '/question/engine/bank.php');
        require_once($CFG->libdir . '/questionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'name' => $name,
            'questiontext' => $questiontext,
            'choices' => $choices,
            'defaultmark' => $defaultmark,
            'shuffleanswers' => $shuffleanswers,
            'correctfeedback' => $correctfeedback,
            'partiallycorrectfeedback' => $partiallycorrectfeedback,
            'incorrectfeedback' => $incorrectfeedback,
            'generalfeedback' => $generalfeedback,
            'idnumber' => $idnumber,
            'tags' => $tags,
            'questionbankentryid' => $questionbankentryid,
        ]);

        $category = $DB->get_record('question_categories', ['id' => $params['categoryid']], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);

        self::validate_context($context);
        require_capability('local/fivedays:createquestion', $context);
        require_capability('moodle/question:add', $context);
        \local_fivedays\helper::require_question_entry($params['questionbankentryid']);

        $choicesarray = \local_fivedays\helper::parse_gap_choices($params['choices'], $params['questiontext'], false);

        $tagsarray = json_decode($params['tags'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $tagsarray = [];
        }

        $transaction = $DB->start_delegated_transaction();

        $questionid = \local_fivedays\helper::insert_question(
            'gapselect',
            $params['categoryid'],
            $params['name'],
            $params['questiontext'],
            $params['defaultmark'],
            $params['generalfeedback']
        );

        $qbeid = \local_fivedays\helper::save_question_version(
            $params['categoryid'],
            $questionid,
            $params['idnumber'],
            $params['questionbankentryid']
        );

        \local_fivedays\helper::insert_gap_question_options(
            'gapselect',
            $questionid,
            $choicesarray,
            $params['shuffleanswers'],
            $params['correctfeedback'],
            $params['partiallycorrectfeedback'],
            $params['incorrectfeedback']
        );

        if (!empty($tagsarray)) {
            \core_tag_tag::set_item_tags('core_question', 'question', $questionid, $context, $tagsarray);
        }

        $transaction->allow_commit();

        return [
            'questionid' => $questionid,
            'questionbankentryid' => (int)$qbeid,
            'name' => $params['name'],
            'success' => true,
            'message' => 'Select missing words question created successfully',
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
