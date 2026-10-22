<?php
namespace local_activity_utils\external\page;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class update_page extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID to update'),
            'name' => new external_value(PARAM_TEXT, 'Page name', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'Page introduction/description', VALUE_DEFAULT, null),
            'content' => new external_value(PARAM_RAW, 'Page content (HTML)', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $pageid,
        ?string $name = null,
        ?string $intro = null,
        ?string $content = null,
        ?int $visible = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/page/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'pageid' => $pageid,
            'name' => $name,
            'intro' => $intro,
            'content' => $content,
            'visible' => $visible,
        ]);

        
        $page = $DB->get_record('page', ['id' => $params['pageid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('page', $page->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/activity_utils:updatepage', \context_course::instance($course->id));
        require_capability('moodle/course:manageactivities', $context);

        
        $updated = false;

        if ($params['name'] !== null) {
            $page->name = $params['name'];
            $updated = true;
        }
        if ($params['intro'] !== null) {
            $page->intro = $params['intro'];
            $updated = true;
        }
        if ($params['content'] !== null) {
            $page->content = $params['content'];
            $page->revision = $page->revision + 1;
            $updated = true;
        }

        if ($updated) {
            $page->timemodified = time();
            $DB->update_record('page', $page);
        }

        
        if ($params['visible'] !== null) {
            set_coursemodule_visible($cm->id, $params['visible'], 1, false);
        }

        rebuild_course_cache($course->id, true);

        return [
            'id' => $page->id,
            'coursemoduleid' => $cm->id,
            'name' => $page->name,
            'success' => true,
            'message' => 'Page updated successfully'
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
