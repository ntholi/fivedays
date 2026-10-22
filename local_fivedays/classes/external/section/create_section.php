<?php
namespace local_activity_utils\external\section;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class create_section extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Section name', VALUE_DEFAULT, ''),
            'summary' => new external_value(PARAM_RAW, 'Section summary/description', VALUE_DEFAULT, ''),
            'sectionnum' => new external_value(PARAM_INT, 'Section number (position)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name = '',
        string $summary = '',
        ?int $sectionnum = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'summary' => $summary,
            'sectionnum' => $sectionnum,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createsection', $context);
        require_capability('moodle/course:update', $context);

        if ($params['sectionnum'] === null) {
            $maxsection = $DB->get_field_sql(
                'SELECT MAX(section) FROM {course_sections} WHERE course = ?',
                [$params['courseid']]
            );
            $params['sectionnum'] = $maxsection + 1;
        }

        course_create_sections_if_missing($course, $params['sectionnum']);

        $section = $DB->get_record('course_sections', [
            'course' => $params['courseid'],
            'section' => $params['sectionnum']
        ], '*', MUST_EXIST);

        $section->name = $params['name'];
        $section->summary = $params['summary'];
        $section->summaryformat = FORMAT_HTML;

        $DB->update_record('course_sections', $section);

        rebuild_course_cache($params['courseid'], true);

        return [
            'id' => $section->id,
            'sectionnum' => $section->section,
            'name' => $section->name,
            'success' => true,
            'message' => 'Section created successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Section ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Section name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
