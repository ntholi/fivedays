<?php
namespace local_fivedays\external\bigbluebuttonbn;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;


class create_bigbluebuttonbn extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'intro' => new external_value(PARAM_RAW, 'Activity description (HTML)', VALUE_DEFAULT, ''),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
            
            
            'type' => new external_value(PARAM_INT, 'Instance type: 0=room with recordings, 1=room only, 2=recordings only', VALUE_DEFAULT, 0),
            
            
            'welcome' => new external_value(PARAM_RAW, 'Welcome message displayed in the room', VALUE_DEFAULT, ''),
            'voicebridge' => new external_value(PARAM_INT, 'Voice bridge number (4 digits)', VALUE_DEFAULT, 0),
            'wait' => new external_value(PARAM_INT, 'Wait for moderator before joining (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'userlimit' => new external_value(PARAM_INT, 'Maximum number of participants (0=unlimited)', VALUE_DEFAULT, 0),
            'record' => new external_value(PARAM_INT, 'Enable recording (1=yes, 0=no)', VALUE_DEFAULT, 1),
            'muteonstart' => new external_value(PARAM_INT, 'Mute participants on start (1=yes, 0=no)', VALUE_DEFAULT, 0),
            
            
            'disablecam' => new external_value(PARAM_INT, 'Disable webcams (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'disablemic' => new external_value(PARAM_INT, 'Disable microphones (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'disableprivatechat' => new external_value(PARAM_INT, 'Disable private chat (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'disablepublicchat' => new external_value(PARAM_INT, 'Disable public chat (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'disablenote' => new external_value(PARAM_INT, 'Disable shared notes (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'hideuserlist' => new external_value(PARAM_INT, 'Hide user list (1=yes, 0=no)', VALUE_DEFAULT, 0),
            
            
            'openingtime' => new external_value(PARAM_INT, 'Opening time (Unix timestamp, 0=no restriction)', VALUE_DEFAULT, 0),
            'closingtime' => new external_value(PARAM_INT, 'Closing time (Unix timestamp, 0=no restriction)', VALUE_DEFAULT, 0),
            
            
            'guestallowed' => new external_value(PARAM_INT, 'Allow guest access (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'mustapproveuser' => new external_value(PARAM_INT, 'Moderator must approve guests (1=yes, 0=no)', VALUE_DEFAULT, 1),
            
            
            'recordings_deleted' => new external_value(PARAM_INT, 'Show deleted recordings (1=yes, 0=no)', VALUE_DEFAULT, 1),
            'recordings_imported' => new external_value(PARAM_INT, 'Show imported recordings (1=yes, 0=no)', VALUE_DEFAULT, 0),
            'recordings_preview' => new external_value(PARAM_INT, 'Show recording preview (1=yes, 0=no)', VALUE_DEFAULT, 0),
            
            
            'showpresentation' => new external_value(PARAM_INT, 'Show presentation on activity page (1=yes, 0=no)', VALUE_DEFAULT, 1),
            
            
            'completionattendance' => new external_value(PARAM_INT, 'Required attendance time in minutes for completion', VALUE_DEFAULT, 0),
            'completionengagementchats' => new external_value(PARAM_INT, 'Required number of chat messages for completion', VALUE_DEFAULT, 0),
            'completionengagementtalks' => new external_value(PARAM_INT, 'Required number of talk time for completion', VALUE_DEFAULT, 0),
            'completionengagementraisehand' => new external_value(PARAM_INT, 'Required number of raise hand for completion', VALUE_DEFAULT, 0),
            'completionengagementpollvotes' => new external_value(PARAM_INT, 'Required number of poll votes for completion', VALUE_DEFAULT, 0),
            'completionengagementemojis' => new external_value(PARAM_INT, 'Required number of emojis for completion', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $intro = '',
        int $section = 0,
        int $visible = 1,
        int $type = 0,
        string $welcome = '',
        int $voicebridge = 0,
        int $wait = 0,
        int $userlimit = 0,
        int $record = 1,
        int $muteonstart = 0,
        int $disablecam = 0,
        int $disablemic = 0,
        int $disableprivatechat = 0,
        int $disablepublicchat = 0,
        int $disablenote = 0,
        int $hideuserlist = 0,
        int $openingtime = 0,
        int $closingtime = 0,
        int $guestallowed = 0,
        int $mustapproveuser = 1,
        int $recordings_deleted = 1,
        int $recordings_imported = 0,
        int $recordings_preview = 0,
        int $showpresentation = 1,
        int $completionattendance = 0,
        int $completionengagementchats = 0,
        int $completionengagementtalks = 0,
        int $completionengagementraisehand = 0,
        int $completionengagementpollvotes = 0,
        int $completionengagementemojis = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/bigbluebuttonbn/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'section' => $section,
            'visible' => $visible,
            'type' => $type,
            'welcome' => $welcome,
            'voicebridge' => $voicebridge,
            'wait' => $wait,
            'userlimit' => $userlimit,
            'record' => $record,
            'muteonstart' => $muteonstart,
            'disablecam' => $disablecam,
            'disablemic' => $disablemic,
            'disableprivatechat' => $disableprivatechat,
            'disablepublicchat' => $disablepublicchat,
            'disablenote' => $disablenote,
            'hideuserlist' => $hideuserlist,
            'openingtime' => $openingtime,
            'closingtime' => $closingtime,
            'guestallowed' => $guestallowed,
            'mustapproveuser' => $mustapproveuser,
            'recordings_deleted' => $recordings_deleted,
            'recordings_imported' => $recordings_imported,
            'recordings_preview' => $recordings_preview,
            'showpresentation' => $showpresentation,
            'completionattendance' => $completionattendance,
            'completionengagementchats' => $completionengagementchats,
            'completionengagementtalks' => $completionengagementtalks,
            'completionengagementraisehand' => $completionengagementraisehand,
            'completionengagementpollvotes' => $completionengagementpollvotes,
            'completionengagementemojis' => $completionengagementemojis,
        ]);

        
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/fivedays:createbigbluebuttonbn', $context);
        require_capability('mod/bigbluebuttonbn:addinstance', $context);

        if ($params['openingtime'] && $params['closingtime'] && $params['closingtime'] <= $params['openingtime']) {
            throw new \invalid_parameter_exception('closingtime must be after openingtime');
        }

        $moduleinfo = helper::create_module($course, 'bigbluebuttonbn', $params['section'], $params['name'], $params['visible'], [
            'instance' => 0,
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'type' => $params['type'],
            'welcome' => $params['welcome'],
            'voicebridge' => $params['voicebridge'],
            'wait' => $params['wait'],
            'userlimit' => $params['userlimit'],
            'record' => $params['record'],
            'muteonstart' => $params['muteonstart'],
            'disablecam' => $params['disablecam'],
            'disablemic' => $params['disablemic'],
            'disableprivatechat' => $params['disableprivatechat'],
            'disablepublicchat' => $params['disablepublicchat'],
            'disablenote' => $params['disablenote'],
            'hideuserlist' => $params['hideuserlist'],
            'openingtime' => $params['openingtime'],
            'closingtime' => $params['closingtime'],
            'guestallowed' => $params['guestallowed'],
            'mustapproveuser' => $params['mustapproveuser'],
            'recordings_deleted' => $params['recordings_deleted'],
            'recordings_imported' => $params['recordings_imported'],
            'recordings_preview' => $params['recordings_preview'],
            'showpresentation' => $params['showpresentation'],
            'presentation' => '',
            'participants' => '[]',
            'grade' => 0,
            'completionattendance' => $params['completionattendance'],
            'completionengagementchats' => $params['completionengagementchats'],
            'completionengagementtalks' => $params['completionengagementtalks'],
            'completionengagementraisehand' => $params['completionengagementraisehand'],
            'completionengagementpollvotes' => $params['completionengagementpollvotes'],
            'completionengagementemojis' => $params['completionengagementemojis'],
        ]);

        rebuild_course_cache($params['courseid'], true);

        $bigbluebuttonbnid = (int)$moduleinfo->instance;
        $cmid = (int)$moduleinfo->coursemodule;
        $meetingid = (string)$DB->get_field('bigbluebuttonbn', 'meetingid', ['id' => $bigbluebuttonbnid], MUST_EXIST);

        return [
            'id' => $bigbluebuttonbnid,
            'coursemoduleid' => $cmid,
            'meetingid' => $meetingid,
            'name' => $params['name'],
            'success' => true,
            'message' => 'BigBlueButton activity created successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'BigBlueButton instance ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'meetingid' => new external_value(PARAM_TEXT, 'Meeting ID'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
