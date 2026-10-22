<?php
namespace local_activity_utils\external\forum;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class create_forum extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Forum name'),
            'intro' => new external_value(PARAM_RAW, 'Forum description', VALUE_DEFAULT, ''),
            'type' => new external_value(PARAM_TEXT, 'Forum type (general, news, social, eachuser, single, qanda, blog)', VALUE_DEFAULT, 'general'),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_RAW, 'ID number', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $intro = '',
        string $type = 'general',
        int $section = 0,
        string $idnumber = ''
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'type' => $type,
            'section' => $section,
            'idnumber' => $idnumber,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createforum', $context);
        require_capability('mod/forum:addinstance', $context);

        $moduleinfo = helper::create_module($course, 'forum', $params['section'], $params['name'], 1, [
            'cmidnumber' => $params['idnumber'],
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'type' => $params['type'],
            'assessed' => 0,
            'assesstimestart' => 0,
            'assesstimefinish' => 0,
            'scale' => 0,
            'maxbytes' => 0,
            'maxattachments' => 9,
            'forcesubscribe' => 0,
            'trackingtype' => 1,
            'rsstype' => 0,
            'rssarticles' => 0,
            'warnafter' => 0,
            'blockafter' => 0,
            'blockperiod' => 0,
            'completiondiscussions' => 0,
            'completionreplies' => 0,
            'completionposts' => 0,
            'displaywordcount' => 0,
            'lockdiscussionafter' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
        ]);

        rebuild_course_cache($params['courseid'], true);

        return [
            'id' => $moduleinfo->instance,
            'coursemoduleid' => $moduleinfo->coursemodule,
            'name' => $params['name'],
            'success' => true,
            'message' => 'Forum created successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Forum ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Forum name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
