<?php
namespace local_activity_utils\external\section;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_section extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number to delete'),
        ]);
    }

    public static function execute(int $courseid, int $sectionnum): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']]);
        if (!$course) {
            return [
                'success' => false,
                'message' => 'Course not found'
            ];
        }

        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:deletesection', $context);
        require_capability('moodle/course:update', $context);

        $section = $DB->get_record('course_sections', [
            'course' => $params['courseid'],
            'section' => $params['sectionnum']
        ]);

        if (!$section) {
            return [
                'success' => false,
                'message' => 'Section not found'
            ];
        }

        
        if ($section->section == 0) {
            return [
                'success' => false,
                'message' => 'Cannot delete the general section (section 0)'
            ];
        }

        $sectionname = get_section_name($course, $section);

        
        course_delete_section($course, $section, true);

        return [
            'success' => true,
            'message' => 'Section "' . $sectionname . '" deleted successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
