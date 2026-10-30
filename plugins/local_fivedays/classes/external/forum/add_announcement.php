<?php
namespace local_fivedays\external\forum;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class add_announcement extends external_api {

    // 2000-01-01 00:00:00 UTC.
    private const EARLIEST_TIMECREATED = 946684800;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'subject' => new external_value(PARAM_TEXT, 'Announcement subject'),
            'message' => new external_value(PARAM_RAW, 'Announcement message (HTML)'),
            'draftitemid' => new external_value(PARAM_INT, 'Draft item ID holding attachments (0 = none)', VALUE_DEFAULT, 0),
            'timecreated' => new external_value(PARAM_INT, 'Posted time as a Unix timestamp, from 2000-01-01 up to now (0 = now)', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Author user ID (0 = current user; another user needs local/fivedays:addannouncementasuser)', VALUE_DEFAULT, 0),
            'notify' => new external_value(PARAM_BOOL, 'Email and notify subscribers (default false: posted silently)', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(
        int $courseid,
        string $subject,
        string $message,
        int $draftitemid = 0,
        int $timecreated = 0,
        int $userid = 0,
        bool $notify = false
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forum/lib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'subject' => $subject,
            'message' => $message,
            'draftitemid' => $draftitemid,
            'timecreated' => $timecreated,
            'userid' => $userid,
            'notify' => $notify,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('local/fivedays:addannouncement', $coursecontext);

        if ($params['timecreated'] !== 0
                && ($params['timecreated'] < self::EARLIEST_TIMECREATED || $params['timecreated'] > time())) {
            throw new \invalid_parameter_exception('timecreated must be 0 (now) or a Unix time from 2000-01-01 up to now');
        }

        if ($params['userid'] && $params['userid'] != $USER->id) {
            require_capability('local/fivedays:addannouncementasuser', $coursecontext);
            $author = \core_user::get_user($params['userid'], '*', MUST_EXIST);
        } else {
            $author = $USER;
        }
        if ($author->deleted || !is_enrolled($coursecontext, $author, '', true)) {
            throw new \moodle_exception('authornotenrolled', 'local_fivedays', '', fullname($author));
        }

        $forum = forum_get_course_forum($course->id, 'news');
        if (!$forum) {
            throw new \moodle_exception('couldnotadd', 'forum');
        }
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $modcontext = \context_module::instance($cm->id);

        if (!has_capability('mod/forum:addnews', $modcontext, $author)) {
            throw new \required_capability_exception($modcontext, 'mod/forum:addnews', 'nopermissions', '');
        }

        $attachmentsid = ($params['draftitemid']
                && has_capability('mod/forum:createattachment', $modcontext)
                && has_capability('mod/forum:createattachment', $modcontext, $author))
            ? $params['draftitemid'] : null;

        $discussion = new \stdClass();
        $discussion->course = $course->id;
        $discussion->forum = $forum->id;
        $discussion->message = $params['message'];
        $discussion->messageformat = FORMAT_HTML;
        $discussion->messagetrust = trusttext_trusted($modcontext);
        $discussion->itemid = 0;
        $discussion->groupid = -1;
        $discussion->mailnow = 0;
        $discussion->subject = $params['subject'];
        $discussion->name = $params['subject'];
        $discussion->timestart = 0;
        $discussion->timeend = 0;
        $discussion->timelocked = 0;
        $discussion->pinned = FORUM_DISCUSSION_UNPINNED;
        $discussion->attachments = $attachmentsid;
        if ($params['timecreated']) {
            $discussion->timenow = $params['timecreated'];
        }

        $transaction = $DB->start_delegated_transaction();
        $discussionid = forum_add_discussion($discussion, $attachmentsid, null, $author->id);
        if (!$discussionid) {
            throw new \moodle_exception('couldnotadd', 'forum');
        }
        $postid = (int) $DB->get_field('forum_discussions', 'firstpost', ['id' => $discussionid], MUST_EXIST);

        // mod_forum\task\cron_task only sends posts with mailed = FORUM_MAILED_PENDING; it is the only
        // sender of forum emails and notifications, so marking the post mailed keeps it silent.
        if (!$params['notify']) {
            $DB->set_field('forum_posts', 'mailed', FORUM_MAILED_SUCCESS, ['id' => $postid]);
        }
        $transaction->allow_commit();

        $discussion->id = $discussionid;
        $event = \mod_forum\event\discussion_created::create([
            'context' => $modcontext,
            'objectid' => $discussionid,
            'relateduserid' => $author->id,
            'other' => ['forumid' => $forum->id],
        ]);
        $event->add_record_snapshot('forum_discussions', $DB->get_record('forum_discussions', ['id' => $discussionid]));
        $event->trigger();

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && ($forum->completiondiscussions || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $author->id);
        }

        return [
            'discussionid' => (int) $discussionid,
            'postid' => $postid,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'discussionid' => new external_value(PARAM_INT, 'Discussion ID'),
            'postid' => new external_value(PARAM_INT, 'First post ID'),
        ]);
    }
}
