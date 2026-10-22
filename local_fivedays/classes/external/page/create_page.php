<?php
namespace local_activity_utils\external\page;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class create_page extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Page name'),
            'intro' => new external_value(PARAM_RAW, 'Page introduction/description', VALUE_DEFAULT, ''),
            'content' => new external_value(PARAM_RAW, 'Page content (HTML)', VALUE_DEFAULT, ''),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $intro = '',
        string $content = '',
        int $section = 0,
        int $visible = 1
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/page/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'content' => $content,
            'section' => $section,
            'visible' => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createpage', $context);
        require_capability('mod/page:addinstance', $context);

        $moduleinfo = helper::create_module($course, 'page', $params['section'], $params['name'], $params['visible'], [
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'content' => $params['content'],
            'contentformat' => FORMAT_HTML,
            'legacyfiles' => 0,
            'legacyfileslast' => null,
            'display' => 5,
            'printintro' => 0,
            'printlastmodified' => 0,
            'revision' => 1,
        ]);

        rebuild_course_cache($params['courseid'], true);

        return [
            'id' => $moduleinfo->instance,
            'coursemoduleid' => $moduleinfo->coursemodule,
            'name' => $params['name'],
            'success' => true,
            'message' => 'Page created successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Page ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Page name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
