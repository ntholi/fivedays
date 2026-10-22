<?php
namespace local_activity_utils\external\question;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_question\local\bank\question_bank_helper;


class list_categories extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('local/activity_utils:managequestioncategory', $coursecontext);
        require_capability('moodle/question:managecategory', $coursecontext);

        
        $qbank = question_bank_helper::get_default_open_instance_system_type($course, false);
        if (!$qbank) {
            
            return [
                'categories' => [],
                'success' => true,
                'message' => 'No question bank found for course. Create a category first.',
            ];
        }

        
        $context = \context_module::instance($qbank->id);

        
        $categories = $DB->get_records('question_categories', [
            'contextid' => $context->id,
        ], 'parent ASC, sortorder ASC, name ASC');

        $result = [];
        foreach ($categories as $cat) {
            
            $questioncount = $DB->count_records_sql(
                "SELECT COUNT(qbe.id)
                   FROM {question_bank_entries} qbe
                  WHERE qbe.questioncategoryid = ?",
                [$cat->id]
            );

            $result[] = [
                'id' => (int)$cat->id,
                'name' => $cat->name,
                'info' => $cat->info ?? '',
                'parent' => (int)$cat->parent,
                'contextid' => (int)$cat->contextid,
                'sortorder' => (int)$cat->sortorder,
                'questioncount' => (int)$questioncount,
                'idnumber' => $cat->idnumber ?? '',
            ];
        }

        return [
            'categories' => $result,
            'success' => true,
            'message' => 'Found ' . count($result) . ' category(ies)',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Category ID'),
                    'name' => new external_value(PARAM_TEXT, 'Category name'),
                    'info' => new external_value(PARAM_RAW, 'Category description'),
                    'parent' => new external_value(PARAM_INT, 'Parent category ID (0 = top level)'),
                    'contextid' => new external_value(PARAM_INT, 'Context ID'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                    'questioncount' => new external_value(PARAM_INT, 'Number of questions in category'),
                    'idnumber' => new external_value(PARAM_RAW, 'ID number'),
                ])
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
