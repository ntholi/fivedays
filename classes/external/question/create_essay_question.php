<?php
namespace local_activity_utils\external\question;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;


class create_essay_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Question category ID'),
            'name' => new external_value(PARAM_TEXT, 'Question name'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text (HTML)'),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark (points)', VALUE_DEFAULT, 1.0),
            'responseformat' => new external_value(PARAM_ALPHA, 'Response format: editor, editorfilepicker, plain, monospaced, noinline', VALUE_DEFAULT, 'editor'),
            'responserequired' => new external_value(PARAM_INT, 'Response required: 1 = yes, 0 = no', VALUE_DEFAULT, 1),
            'responsefieldlines' => new external_value(PARAM_INT, 'Number of lines for response field', VALUE_DEFAULT, 15),
            'minwordlimit' => new external_value(PARAM_INT, 'Minimum word limit (0 = no limit)', VALUE_DEFAULT, 0),
            'maxwordlimit' => new external_value(PARAM_INT, 'Maximum word limit (0 = no limit)', VALUE_DEFAULT, 0),
            'attachments' => new external_value(PARAM_INT, 'Number of attachments allowed: 0, 1, 2, 3, or -1 for unlimited', VALUE_DEFAULT, 0),
            'attachmentsrequired' => new external_value(PARAM_INT, 'Number of attachments required', VALUE_DEFAULT, 0),
            'maxbytes' => new external_value(PARAM_INT, 'Maximum file size in bytes (0 = site default)', VALUE_DEFAULT, 0),
            'filetypeslist' => new external_value(PARAM_RAW, 'Accepted file types (comma-separated, e.g., ".pdf,.doc")', VALUE_DEFAULT, ''),
            'graderinfo' => new external_value(PARAM_RAW, 'Information for graders (HTML)', VALUE_DEFAULT, ''),
            'responsetemplate' => new external_value(PARAM_RAW, 'Response template (HTML)', VALUE_DEFAULT, ''),
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
        float $defaultmark = 1.0,
        string $responseformat = 'editor',
        int $responserequired = 1,
        int $responsefieldlines = 15,
        int $minwordlimit = 0,
        int $maxwordlimit = 0,
        int $attachments = 0,
        int $attachmentsrequired = 0,
        int $maxbytes = 0,
        string $filetypeslist = '',
        string $graderinfo = '',
        string $responsetemplate = '',
        string $generalfeedback = '',
        string $idnumber = '',
        string $tags = '[]',
        int $questionbankentryid = 0
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->libdir . '/questionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'name' => $name,
            'questiontext' => $questiontext,
            'defaultmark' => $defaultmark,
            'responseformat' => $responseformat,
            'responserequired' => $responserequired,
            'responsefieldlines' => $responsefieldlines,
            'minwordlimit' => $minwordlimit,
            'maxwordlimit' => $maxwordlimit,
            'attachments' => $attachments,
            'attachmentsrequired' => $attachmentsrequired,
            'maxbytes' => $maxbytes,
            'filetypeslist' => $filetypeslist,
            'graderinfo' => $graderinfo,
            'responsetemplate' => $responsetemplate,
            'generalfeedback' => $generalfeedback,
            'idnumber' => $idnumber,
            'tags' => $tags,
            'questionbankentryid' => $questionbankentryid,
        ]);

        
        $tagsarray = json_decode($params['tags'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $tagsarray = [];
        }

        
        $category = $DB->get_record('question_categories', ['id' => $params['categoryid']], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);

        self::validate_context($context);
        require_capability('local/activity_utils:createquestion', $context);
        require_capability('moodle/question:add', $context);
        \local_activity_utils\helper::require_question_entry($params['questionbankentryid']);

        
        $validformats = ['editor', 'editorfilepicker', 'plain', 'monospaced', 'noinline'];
        if (!in_array($params['responseformat'], $validformats)) {
            $params['responseformat'] = 'editor';
        }

        
        $question = new \stdClass();
        $question->category = $params['categoryid'];
        $question->parent = 0;
        $question->name = $params['name'];
        $question->questiontext = $params['questiontext'];
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = $params['generalfeedback'];
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = $params['defaultmark'];
        $question->penalty = 0;
        $question->qtype = 'essay';
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

        
        $options = new \stdClass();
        $options->questionid = $questionid;
        $options->responseformat = $params['responseformat'];
        $options->responserequired = $params['responserequired'] ? 1 : 0;
        $options->responsefieldlines = max(1, $params['responsefieldlines']);
        $options->minwordlimit = max(0, $params['minwordlimit']);
        $options->maxwordlimit = max(0, $params['maxwordlimit']);
        $options->attachments = $params['attachments'];
        $options->attachmentsrequired = max(0, $params['attachmentsrequired']);
        $options->maxbytes = max(0, $params['maxbytes']);
        $options->filetypeslist = $params['filetypeslist'];
        $options->graderinfo = $params['graderinfo'];
        $options->graderinfoformat = FORMAT_HTML;
        $options->responsetemplate = $params['responsetemplate'];
        $options->responsetemplateformat = FORMAT_HTML;

        $DB->insert_record('qtype_essay_options', $options);

        
        if (!empty($tagsarray)) {
            \core_tag_tag::set_item_tags('core_question', 'question', $questionid, $context, $tagsarray);
        }

        return [
            'questionid' => (int)$questionid,
            'questionbankentryid' => (int)$qbeid,
            'name' => $params['name'],
            'success' => true,
            'message' => 'Essay question created successfully',
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
