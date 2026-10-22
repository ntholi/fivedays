<?php
namespace local_activity_utils\external\section;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class update_section extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'Section ID to update'),
            'name' => new external_value(PARAM_TEXT, 'Section name', VALUE_DEFAULT, null),
            'summary' => new external_value(PARAM_RAW, 'Section summary/description', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $sectionid,
        ?string $name = null,
        ?string $summary = null,
        ?int $visible = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'sectionid' => $sectionid,
            'name' => $name,
            'summary' => $summary,
            'visible' => $visible,
        ]);

        
        $section = $DB->get_record('course_sections', ['id' => $params['sectionid']], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $section->course], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:updatesection', $context);
        require_capability('moodle/course:update', $context);

        
        $updated = false;

        if ($params['name'] !== null) {
            $section->name = $params['name'];
            $updated = true;
        }
        if ($params['summary'] !== null) {
            $section->summary = $params['summary'];
            $section->summaryformat = FORMAT_HTML;
            $updated = true;
        }
        if ($params['visible'] !== null) {
            $section->visible = $params['visible'];
            $updated = true;
        }

        if ($updated) {
            $section->timemodified = time();
            $DB->update_record('course_sections', $section);
        }

        rebuild_course_cache($course->id, true);

        return [
            'id' => $section->id,
            'sectionnum' => $section->section,
            'name' => $section->name ?? '',
            'success' => true,
            'message' => 'Section updated successfully'
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
