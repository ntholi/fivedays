<?php
namespace local_activity_utils\external\url;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class create_url extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'URL resource name'),
            'externalurl' => new external_value(PARAM_URL, 'The external URL'),
            'intro' => new external_value(PARAM_RAW, 'URL resource description', VALUE_DEFAULT, ''),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
            'display' => new external_value(PARAM_INT, 'Display type (0=auto, 1=embed, 2=frame, 5=open, 6=popup)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $externalurl,
        string $intro = '',
        int $section = 0,
        int $visible = 1,
        int $display = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/url/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'externalurl' => $externalurl,
            'intro' => $intro,
            'section' => $section,
            'visible' => $visible,
            'display' => $display,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createurl', $context);
        require_capability('mod/url:addinstance', $context);

        $moduleinfo = helper::create_module($course, 'url', $params['section'], $params['name'], $params['visible'], [
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'externalurl' => $params['externalurl'],
            'display' => $params['display'],
            'printintro' => 1,
            'popupwidth' => 620,
            'popupheight' => 450,
        ]);

        rebuild_course_cache($params['courseid'], true);

        return [
            'id' => $moduleinfo->instance,
            'coursemoduleid' => $moduleinfo->coursemodule,
            'name' => $params['name'],
            'externalurl' => $params['externalurl'],
            'success' => true,
            'message' => 'URL resource created successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'URL resource ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'URL resource name'),
            'externalurl' => new external_value(PARAM_URL, 'The external URL'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
