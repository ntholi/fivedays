<?php
namespace local_activity_utils\external\question;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class delete_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionbankentryid' => new external_value(PARAM_INT, 'Question bank entry ID'),
        ]);
    }

    public static function execute(int $questionbankentryid): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'questionbankentryid' => $questionbankentryid,
        ]);

        
        $qbe = $DB->get_record('question_bank_entries', ['id' => $params['questionbankentryid']]);
        if (!$qbe) {
            return [
                'success' => false,
                'message' => 'Question bank entry not found',
            ];
        }

        
        $category = $DB->get_record('question_categories', ['id' => $qbe->questioncategoryid], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);

        self::validate_context($context);
        require_capability('local/activity_utils:deletequestion', $context);
        require_capability('moodle/question:editall', $context);

        
        $sql = "SELECT qv.questionid, q.name
                  FROM {question_versions} qv
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qv.questionbankentryid = ?
              ORDER BY qv.version DESC
                 LIMIT 1";
        $latestversion = $DB->get_record_sql($sql, [$params['questionbankentryid']]);

        if (!$latestversion) {
            return [
                'success' => false,
                'message' => 'No question version found',
            ];
        }

        $questionname = $latestversion->name;

        
        $inuse = $DB->record_exists_sql(
            "SELECT 1
               FROM {question_references} qr
              WHERE qr.questionbankentryid = ?
                AND qr.component = 'mod_quiz'",
            [$params['questionbankentryid']]
        );

        if ($inuse) {
            return [
                'success' => false,
                'message' => 'Cannot delete question "' . $questionname . '" because it is used in one or more quizzes. Remove it from all quizzes first.',
            ];
        }

        
        $inset = $DB->record_exists_sql(
            "SELECT 1
               FROM {question_set_references} qsr
              WHERE qsr.questionscontextid = ?",
            [$context->id]
        );

        
        $versions = $DB->get_records('question_versions', ['questionbankentryid' => $params['questionbankentryid']]);

        
        foreach ($versions as $version) {
            
            $DB->delete_records('question_answers', ['question' => $version->questionid]);

            
            $question = $DB->get_record('question', ['id' => $version->questionid]);
            if ($question) {
                
                switch ($question->qtype) {
                    case 'multichoice':
                        $DB->delete_records('qtype_multichoice_options', ['questionid' => $version->questionid]);
                        break;
                    case 'truefalse':
                        $DB->delete_records('question_truefalse', ['question' => $version->questionid]);
                        break;
                    case 'shortanswer':
                        $DB->delete_records('qtype_shortanswer_options', ['questionid' => $version->questionid]);
                        break;
                    case 'essay':
                        $DB->delete_records('qtype_essay_options', ['questionid' => $version->questionid]);
                        break;
                    case 'numerical':
                        $DB->delete_records('question_numerical', ['question' => $version->questionid]);
                        $DB->delete_records('question_numerical_options', ['question' => $version->questionid]);
                        $DB->delete_records('question_numerical_units', ['question' => $version->questionid]);
                        break;
                }

                
                $DB->delete_records('question_hints', ['questionid' => $version->questionid]);

                
                \core_tag_tag::remove_all_item_tags('core_question', 'question', $version->questionid);

                
                $DB->delete_records('question', ['id' => $version->questionid]);
            }
        }

        
        $DB->delete_records('question_versions', ['questionbankentryid' => $params['questionbankentryid']]);

        
        $DB->delete_records('question_bank_entries', ['id' => $params['questionbankentryid']]);

        return [
            'success' => true,
            'message' => 'Question "' . $questionname . '" deleted successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
