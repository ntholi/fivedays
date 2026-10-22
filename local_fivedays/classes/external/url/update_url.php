<?php
namespace local_activity_utils\external\url;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class update_url extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'urlid' => new external_value(PARAM_INT, 'URL resource ID to update'),
            'name' => new external_value(PARAM_TEXT, 'URL resource name', VALUE_DEFAULT, null),
            'externalurl' => new external_value(PARAM_URL, 'The external URL', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'URL resource description', VALUE_DEFAULT, null),
            'display' => new external_value(PARAM_INT, 'Display type (0=auto, 1=embed, 2=frame, 5=open, 6=popup)', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $urlid,
        ?string $name = null,
        ?string $externalurl = null,
        ?string $intro = null,
        ?int $display = null,
        ?int $visible = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/url/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'urlid' => $urlid,
            'name' => $name,
            'externalurl' => $externalurl,
            'intro' => $intro,
            'display' => $display,
            'visible' => $visible,
        ]);

        
        $url = $DB->get_record('url', ['id' => $params['urlid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('url', $url->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:updateurl', $context);
        require_capability('mod/url:addinstance', $context);

        
        $updated = false;

        if ($params['name'] !== null) {
            $url->name = $params['name'];
            $updated = true;
        }
        if ($params['externalurl'] !== null) {
            $url->externalurl = $params['externalurl'];
            $updated = true;
        }
        if ($params['intro'] !== null) {
            $url->intro = $params['intro'];
            $updated = true;
        }
        if ($params['display'] !== null) {
            $url->display = $params['display'];
            $updated = true;
        }

        if ($updated) {
            $url->timemodified = time();
            $DB->update_record('url', $url);
        }

        
        if ($params['visible'] !== null) {
            $cm->visible = $params['visible'];
            $cm->visibleold = $params['visible'];
            $DB->update_record('course_modules', $cm);
        }

        rebuild_course_cache($course->id, true);

        return [
            'id' => $url->id,
            'coursemoduleid' => $cm->id,
            'name' => $url->name,
            'externalurl' => $url->externalurl,
            'success' => true,
            'message' => 'URL resource updated successfully'
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
