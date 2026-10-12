<?php
namespace local_activity_utils\external\question;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_question\local\bank\question_bank_helper;


class get_or_create_category extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Category name'),
            'info' => new external_value(PARAM_RAW, 'Category description/info', VALUE_DEFAULT, ''),
            'parentcategoryid' => new external_value(PARAM_INT, 'Parent category ID (0 = course top-level)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $info = '',
        int $parentcategoryid = 0
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'info' => $info,
            'parentcategoryid' => $parentcategoryid,
        ]);

        
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('local/activity_utils:managequestioncategory', $coursecontext);
        require_capability('moodle/question:managecategory', $coursecontext);

        
        
        $qbank = question_bank_helper::get_default_open_instance_system_type($course, true);
        if (!$qbank) {
            return [
                'id' => 0,
                'name' => '',
                'contextid' => 0,
                'created' => false,
                'success' => false,
                'message' => 'Failed to get or create question bank for course',
            ];
        }

        
        $context = \context_module::instance($qbank->id);

        
        if ($params['parentcategoryid'] > 0) {
            
            $parent = $DB->get_record('question_categories', ['id' => $params['parentcategoryid']]);
            if (!$parent) {
                return [
                    'id' => 0,
                    'name' => '',
                    'contextid' => 0,
                    'created' => false,
                    'success' => false,
                    'message' => 'Parent category not found',
                ];
            }
            $parentid = $parent->id;
            $context = \context::instance_by_id($parent->contextid);
            require_capability('moodle/question:managecategory', $context);
        } else {
            
            $topcat = $DB->get_record('question_categories', [
                'contextid' => $context->id,
                'parent' => 0,
            ]);

            if (!$topcat) {
                
                $topcat = new \stdClass();
                $topcat->name = 'top';
                $topcat->info = '';
                $topcat->infoformat = FORMAT_HTML;
                $topcat->contextid = $context->id;
                $topcat->parent = 0;
                $topcat->sortorder = 0;
                $topcat->stamp = make_unique_id_code();
                $topcat->idnumber = null;
                $topcat->id = $DB->insert_record('question_categories', $topcat);
            }
            $parentid = $topcat->id;
        }

        
        $existing = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'parent' => $parentid,
            'name' => $params['name'],
        ]);

        if ($existing) {
            return [
                'id' => (int)$existing->id,
                'name' => $existing->name,
                'contextid' => (int)$existing->contextid,
                'created' => false,
                'success' => true,
                'message' => 'Category already exists',
            ];
        }

        
        $category = new \stdClass();
        $category->name = $params['name'];
        $category->info = $params['info'];
        $category->infoformat = FORMAT_HTML;
        $category->contextid = $context->id;
        $category->parent = $parentid;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();
        $category->idnumber = null;

        $categoryid = $DB->insert_record('question_categories', $category);

        return [
            'id' => (int)$categoryid,
            'name' => $params['name'],
            'contextid' => (int)$context->id,
            'created' => true,
            'success' => true,
            'message' => 'Category created successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Category ID'),
            'name' => new external_value(PARAM_TEXT, 'Category name'),
            'contextid' => new external_value(PARAM_INT, 'Context ID'),
            'created' => new external_value(PARAM_BOOL, 'True if newly created, false if existing'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
