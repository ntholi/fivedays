<?php
namespace local_activity_utils\external\question;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;


class get_questions extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Question category ID'),
            'includesubcategories' => new external_value(PARAM_INT, 'Include questions from subcategories: 1 = yes, 0 = no', VALUE_DEFAULT, 0),
            'qtype' => new external_value(PARAM_ALPHANUMEXT, 'Filter by question type (e.g., multichoice, truefalse, shortanswer, essay, numerical). Empty = all types', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Maximum number of questions to return (0 = no limit)', VALUE_DEFAULT, 0),
            'offset' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $categoryid,
        int $includesubcategories = 0,
        string $qtype = '',
        int $limit = 0,
        int $offset = 0
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'includesubcategories' => $includesubcategories,
            'qtype' => $qtype,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        
        $category = $DB->get_record('question_categories', ['id' => $params['categoryid']], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);

        self::validate_context($context);
        require_capability('local/activity_utils:viewquestions', $context);
        require_capability('moodle/question:viewall', $context);

        
        $categoryids = [$params['categoryid']];

        if ($params['includesubcategories']) {
            
            $subcats = self::get_subcategories($params['categoryid']);
            $categoryids = array_merge($categoryids, $subcats);
        }

        
        list($insql, $inparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');

        $sql = "SELECT q.id AS questionid,
                       q.name,
                       q.questiontext,
                       q.questiontextformat,
                       q.qtype,
                       q.defaultmark,
                       q.timecreated,
                       q.timemodified,
                       qbe.id AS questionbankentryid,
                       qbe.idnumber,
                       qbe.questioncategoryid,
                       qv.version,
                       qv.status
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.questioncategoryid {$insql}
                   AND qv.status = 'ready'";

        
        if (!empty($params['qtype'])) {
            $sql .= " AND q.qtype = :qtype";
            $inparams['qtype'] = $params['qtype'];
        }

        
        $sql .= " AND qv.version = (
                    SELECT MAX(qv2.version)
                      FROM {question_versions} qv2
                     WHERE qv2.questionbankentryid = qbe.id
                       AND qv2.status = 'ready'
                  )";

        $sql .= " ORDER BY q.name ASC, q.id ASC";

        
        $limitfrom = $params['offset'];
        $limitnum = $params['limit'] > 0 ? $params['limit'] : 0;

        $questions = $DB->get_records_sql($sql, $inparams, $limitfrom, $limitnum);

        
        $countsql = "SELECT COUNT(DISTINCT qbe.id)
                       FROM {question} q
                       JOIN {question_versions} qv ON qv.questionid = q.id
                       JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                      WHERE qbe.questioncategoryid {$insql}
                        AND qv.status = 'ready'";

        if (!empty($params['qtype'])) {
            $countsql .= " AND q.qtype = :qtype";
        }

        $totalcount = $DB->count_records_sql($countsql, $inparams);

        $result = [];
        foreach ($questions as $q) {
            $result[] = [
                'questionid' => (int)$q->questionid,
                'questionbankentryid' => (int)$q->questionbankentryid,
                'name' => $q->name,
                'questiontext' => $q->questiontext,
                'qtype' => $q->qtype,
                'defaultmark' => (float)$q->defaultmark,
                'categoryid' => (int)$q->questioncategoryid,
                'idnumber' => $q->idnumber ?? '',
                'version' => (int)$q->version,
                'status' => $q->status,
                'timecreated' => (int)$q->timecreated,
                'timemodified' => (int)$q->timemodified,
            ];
        }

        return [
            'questions' => $result,
            'totalcount' => (int)$totalcount,
            'success' => true,
            'message' => 'Found ' . count($result) . ' question(s)',
        ];
    }

    
    private static function get_subcategories(int $parentid): array {
        global $DB;

        $subcats = $DB->get_records('question_categories', ['parent' => $parentid], '', 'id');
        $result = array_keys($subcats);

        foreach ($subcats as $subcat) {
            $result = array_merge($result, self::get_subcategories($subcat->id));
        }

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'questionid' => new external_value(PARAM_INT, 'Question ID'),
                    'questionbankentryid' => new external_value(PARAM_INT, 'Question bank entry ID (use this to add to quiz)'),
                    'name' => new external_value(PARAM_TEXT, 'Question name'),
                    'questiontext' => new external_value(PARAM_RAW, 'Question text'),
                    'qtype' => new external_value(PARAM_ALPHANUMEXT, 'Question type'),
                    'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark'),
                    'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                    'idnumber' => new external_value(PARAM_RAW, 'ID number'),
                    'version' => new external_value(PARAM_INT, 'Question version'),
                    'status' => new external_value(PARAM_ALPHA, 'Question status'),
                    'timecreated' => new external_value(PARAM_INT, 'Time created'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                ])
            ),
            'totalcount' => new external_value(PARAM_INT, 'Total number of questions matching criteria'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
