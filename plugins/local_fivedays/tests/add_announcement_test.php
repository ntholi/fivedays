<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/forum/lib.php');

use advanced_testcase;
use local_fivedays\external\forum\add_announcement;

class add_announcement_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    public function test_posts_dated_silent_announcement_as_author(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $posted = mktime(9, 30, 0, 3, 15, 2021);

        $result = add_announcement::execute(
            courseid: $course->id,
            subject: 'Welcome',
            message: '<p>Hello class</p>',
            timecreated: $posted,
            userid: $teacher->id,
            notify: false
        );

        $post = $DB->get_record('forum_posts', ['id' => $result['postid']], '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', ['id' => $result['discussionid']], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);

        $this->assertSame('news', $forum->type);
        $this->assertSame((int) $course->id, (int) $forum->course);
        $this->assertSame((int) $post->id, (int) $discussion->firstpost);
        $this->assertSame((int) $teacher->id, (int) $post->userid);
        $this->assertSame((int) $teacher->id, (int) $discussion->userid);
        $this->assertSame($posted, (int) $post->created);
        $this->assertSame($posted, (int) $post->modified);
        $this->assertSame($posted, (int) $discussion->timemodified);
        $this->assertSame(FORUM_MAILED_SUCCESS, (int) $post->mailed);
        $this->assertSame('Welcome', $post->subject);
    }

    public function test_notify_leaves_post_pending_and_reuses_news_forum(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $news = forum_get_course_forum($course->id, 'news');

        $result = add_announcement::execute(courseid: $course->id, subject: 'Now', message: 'Body',
            userid: $this->getDataGenerator()->create_and_enrol($course, 'editingteacher')->id);

        $post = $DB->get_record('forum_posts', ['id' => $result['postid']], '*', MUST_EXIST);
        $this->assertSame(FORUM_MAILED_PENDING, (int) $post->mailed);
        $this->assertSame((int) $news->id, (int) $DB->get_field('forum_discussions', 'forum',
            ['id' => $result['discussionid']]));
        $this->assertSame(1, $DB->count_records('forum', ['course' => $course->id, 'type' => 'news']));
        $this->assertGreaterThan(time() - 60, (int) $post->created);
    }

    public function test_author_must_be_enrolled(): void {
        $course = $this->getDataGenerator()->create_course();
        $outsider = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        add_announcement::execute(courseid: $course->id, subject: 'x', message: 'y', userid: $outsider->id);
    }

    public function test_author_must_be_able_to_post_news(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->expectException(\required_capability_exception::class);
        add_announcement::execute(courseid: $course->id, subject: 'x', message: 'y', userid: $student->id);
    }
}
