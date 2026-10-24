<?php
namespace local_fivedays\external\bigbluebuttonbn;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_bigbluebuttonbn\instance;
use mod_bigbluebuttonbn\logger;
use mod_bigbluebuttonbn\local\helpers\mod_helper;


class update_bigbluebuttonbn extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bigbluebuttonbnid' => new external_value(PARAM_INT, 'BigBlueButton instance ID to update'),
            'name' => new external_value(PARAM_TEXT, 'Activity name', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'Activity description (HTML)', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, null),
            
            
            'type' => new external_value(PARAM_INT, 'Instance type: 0=room with recordings, 1=room only, 2=recordings only', VALUE_DEFAULT, null),
            
            
            'welcome' => new external_value(PARAM_RAW, 'Welcome message displayed in the room', VALUE_DEFAULT, null),
            'voicebridge' => new external_value(PARAM_INT, 'Voice bridge number (4 digits)', VALUE_DEFAULT, null),
            'wait' => new external_value(PARAM_INT, 'Wait for moderator before joining (1=yes, 0=no)', VALUE_DEFAULT, null),
            'userlimit' => new external_value(PARAM_INT, 'Maximum number of participants (0=unlimited)', VALUE_DEFAULT, null),
            'record' => new external_value(PARAM_INT, 'Enable recording (1=yes, 0=no)', VALUE_DEFAULT, null),
            'muteonstart' => new external_value(PARAM_INT, 'Mute participants on start (1=yes, 0=no)', VALUE_DEFAULT, null),
            
            
            'disablecam' => new external_value(PARAM_INT, 'Disable webcams (1=yes, 0=no)', VALUE_DEFAULT, null),
            'disablemic' => new external_value(PARAM_INT, 'Disable microphones (1=yes, 0=no)', VALUE_DEFAULT, null),
            'disableprivatechat' => new external_value(PARAM_INT, 'Disable private chat (1=yes, 0=no)', VALUE_DEFAULT, null),
            'disablepublicchat' => new external_value(PARAM_INT, 'Disable public chat (1=yes, 0=no)', VALUE_DEFAULT, null),
            'disablenote' => new external_value(PARAM_INT, 'Disable shared notes (1=yes, 0=no)', VALUE_DEFAULT, null),
            'hideuserlist' => new external_value(PARAM_INT, 'Hide user list (1=yes, 0=no)', VALUE_DEFAULT, null),
            
            
            'openingtime' => new external_value(PARAM_INT, 'Opening time (Unix timestamp, 0=no restriction)', VALUE_DEFAULT, null),
            'closingtime' => new external_value(PARAM_INT, 'Closing time (Unix timestamp, 0=no restriction)', VALUE_DEFAULT, null),
            
            
            'guestallowed' => new external_value(PARAM_INT, 'Allow guest access (1=yes, 0=no)', VALUE_DEFAULT, null),
            'mustapproveuser' => new external_value(PARAM_INT, 'Moderator must approve guests (1=yes, 0=no)', VALUE_DEFAULT, null),
            
            
            'recordings_deleted' => new external_value(PARAM_INT, 'Show deleted recordings (1=yes, 0=no)', VALUE_DEFAULT, null),
            'recordings_imported' => new external_value(PARAM_INT, 'Show imported recordings (1=yes, 0=no)', VALUE_DEFAULT, null),
            'recordings_preview' => new external_value(PARAM_INT, 'Show recording preview (1=yes, 0=no)', VALUE_DEFAULT, null),
            
            
            'showpresentation' => new external_value(PARAM_INT, 'Show presentation on activity page (1=yes, 0=no)', VALUE_DEFAULT, null),
            
            
            'completionattendance' => new external_value(PARAM_INT, 'Required attendance time in minutes for completion', VALUE_DEFAULT, null),
            'completionengagementchats' => new external_value(PARAM_INT, 'Required number of chat messages for completion', VALUE_DEFAULT, null),
            'completionengagementtalks' => new external_value(PARAM_INT, 'Required number of talk time for completion', VALUE_DEFAULT, null),
            'completionengagementraisehand' => new external_value(PARAM_INT, 'Required number of raise hand for completion', VALUE_DEFAULT, null),
            'completionengagementpollvotes' => new external_value(PARAM_INT, 'Required number of poll votes for completion', VALUE_DEFAULT, null),
            'completionengagementemojis' => new external_value(PARAM_INT, 'Required number of emojis for completion', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $bigbluebuttonbnid,
        ?string $name = null,
        ?string $intro = null,
        ?int $visible = null,
        ?int $type = null,
        ?string $welcome = null,
        ?int $voicebridge = null,
        ?int $wait = null,
        ?int $userlimit = null,
        ?int $record = null,
        ?int $muteonstart = null,
        ?int $disablecam = null,
        ?int $disablemic = null,
        ?int $disableprivatechat = null,
        ?int $disablepublicchat = null,
        ?int $disablenote = null,
        ?int $hideuserlist = null,
        ?int $openingtime = null,
        ?int $closingtime = null,
        ?int $guestallowed = null,
        ?int $mustapproveuser = null,
        ?int $recordings_deleted = null,
        ?int $recordings_imported = null,
        ?int $recordings_preview = null,
        ?int $showpresentation = null,
        ?int $completionattendance = null,
        ?int $completionengagementchats = null,
        ?int $completionengagementtalks = null,
        ?int $completionengagementraisehand = null,
        ?int $completionengagementpollvotes = null,
        ?int $completionengagementemojis = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/bigbluebuttonbn/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'bigbluebuttonbnid' => $bigbluebuttonbnid,
            'name' => $name,
            'intro' => $intro,
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

        
        $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', ['id' => $params['bigbluebuttonbnid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('bigbluebuttonbn', $bigbluebuttonbn->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/fivedays:updatebigbluebuttonbn', $context);
        require_capability('mod/bigbluebuttonbn:addinstance', $context);

        
        $updated = false;
        $cmupdated = false;

        
        if ($params['name'] !== null) {
            $bigbluebuttonbn->name = $params['name'];
            $updated = true;
        }
        if ($params['intro'] !== null) {
            $bigbluebuttonbn->intro = $params['intro'];
            $updated = true;
        }
        if ($params['type'] !== null) {
            $bigbluebuttonbn->type = $params['type'];
            $updated = true;
        }
        if ($params['welcome'] !== null) {
            $bigbluebuttonbn->welcome = $params['welcome'];
            $updated = true;
        }
        if ($params['voicebridge'] !== null) {
            $bigbluebuttonbn->voicebridge = $params['voicebridge'];
            $updated = true;
        }
        if ($params['wait'] !== null) {
            $bigbluebuttonbn->wait = $params['wait'];
            $updated = true;
        }
        if ($params['userlimit'] !== null) {
            $bigbluebuttonbn->userlimit = $params['userlimit'];
            $updated = true;
        }
        if ($params['record'] !== null) {
            $bigbluebuttonbn->record = $params['record'];
            $updated = true;
        }
        if ($params['muteonstart'] !== null) {
            $bigbluebuttonbn->muteonstart = $params['muteonstart'];
            $updated = true;
        }
        if ($params['disablecam'] !== null) {
            $bigbluebuttonbn->disablecam = $params['disablecam'];
            $updated = true;
        }
        if ($params['disablemic'] !== null) {
            $bigbluebuttonbn->disablemic = $params['disablemic'];
            $updated = true;
        }
        if ($params['disableprivatechat'] !== null) {
            $bigbluebuttonbn->disableprivatechat = $params['disableprivatechat'];
            $updated = true;
        }
        if ($params['disablepublicchat'] !== null) {
            $bigbluebuttonbn->disablepublicchat = $params['disablepublicchat'];
            $updated = true;
        }
        if ($params['disablenote'] !== null) {
            $bigbluebuttonbn->disablenote = $params['disablenote'];
            $updated = true;
        }
        if ($params['hideuserlist'] !== null) {
            $bigbluebuttonbn->hideuserlist = $params['hideuserlist'];
            $updated = true;
        }
        if ($params['openingtime'] !== null) {
            $bigbluebuttonbn->openingtime = $params['openingtime'];
            $updated = true;
        }
        if ($params['closingtime'] !== null) {
            $bigbluebuttonbn->closingtime = $params['closingtime'];
            $updated = true;
        }
        if ($params['guestallowed'] !== null) {
            $bigbluebuttonbn->guestallowed = $params['guestallowed'];
            
            if ($params['guestallowed'] && empty($bigbluebuttonbn->guestlinkuid)) {
                [$bigbluebuttonbn->guestlinkuid, $bigbluebuttonbn->guestpassword] = 
                    \mod_bigbluebuttonbn\plugin::generate_guest_meeting_credentials();
            }
            $updated = true;
        }
        if ($params['mustapproveuser'] !== null) {
            $bigbluebuttonbn->mustapproveuser = $params['mustapproveuser'];
            $updated = true;
        }
        if ($params['recordings_deleted'] !== null) {
            $bigbluebuttonbn->recordings_deleted = $params['recordings_deleted'];
            $updated = true;
        }
        if ($params['recordings_imported'] !== null) {
            $bigbluebuttonbn->recordings_imported = $params['recordings_imported'];
            $updated = true;
        }
        if ($params['recordings_preview'] !== null) {
            $bigbluebuttonbn->recordings_preview = $params['recordings_preview'];
            $updated = true;
        }
        if ($params['showpresentation'] !== null) {
            $bigbluebuttonbn->showpresentation = $params['showpresentation'];
            $updated = true;
        }
        if ($params['completionattendance'] !== null) {
            $bigbluebuttonbn->completionattendance = $params['completionattendance'];
            $updated = true;
        }
        if ($params['completionengagementchats'] !== null) {
            $bigbluebuttonbn->completionengagementchats = $params['completionengagementchats'];
            $updated = true;
        }
        if ($params['completionengagementtalks'] !== null) {
            $bigbluebuttonbn->completionengagementtalks = $params['completionengagementtalks'];
            $updated = true;
        }
        if ($params['completionengagementraisehand'] !== null) {
            $bigbluebuttonbn->completionengagementraisehand = $params['completionengagementraisehand'];
            $updated = true;
        }
        if ($params['completionengagementpollvotes'] !== null) {
            $bigbluebuttonbn->completionengagementpollvotes = $params['completionengagementpollvotes'];
            $updated = true;
        }
        if ($params['completionengagementemojis'] !== null) {
            $bigbluebuttonbn->completionengagementemojis = $params['completionengagementemojis'];
            $updated = true;
        }

        
        if ($bigbluebuttonbn->openingtime && $bigbluebuttonbn->closingtime
                && $bigbluebuttonbn->closingtime <= $bigbluebuttonbn->openingtime) {
            throw new \invalid_parameter_exception('closingtime must be after openingtime');
        }

        // Not bigbluebuttonbn_update_instance(): it treats the stored presentation path as a draft
        // itemid and rotates guest credentials, so only its post-save side effects are replayed here.
        if ($updated) {
            $bigbluebuttonbn->timemodified = time();
            $DB->update_record('bigbluebuttonbn', $bigbluebuttonbn);

            $bigbluebuttonbn->coursemodule = $cm->id;
            mod_helper::process_post_save($bigbluebuttonbn);
            bigbluebuttonbn_grade_item_update($bigbluebuttonbn);
            logger::log_instance_updated(instance::get_from_instanceid($bigbluebuttonbn->id));
        }

        
        if ($params['visible'] !== null) {
            \core_courseformat\formatactions::cm((int) $cm->course)->set_visibility((int) $cm->id, $params['visible'], 1, false);
            $cmupdated = true;
        }

        
        if ($updated || $cmupdated) {
            rebuild_course_cache($course->id, true);
        }

        return [
            'id' => $bigbluebuttonbn->id,
            'coursemoduleid' => $cm->id,
            'name' => $bigbluebuttonbn->name,
            'success' => true,
            'message' => 'BigBlueButton activity updated successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'BigBlueButton instance ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
