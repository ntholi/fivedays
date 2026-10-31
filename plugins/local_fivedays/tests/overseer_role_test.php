<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/forum/externallib.php');

use advanced_testcase;
use local_fivedays\external\assignment\get_assignment_comments;
use local_fivedays\external\assignment\get_assignment_grades;
use local_fivedays\external\assignment\save_assignment_grade;
use local_fivedays\external\assignment\update_assignment;
use local_fivedays\external\course\get_grading_queue;
use local_fivedays\external\provision\enrol_users;
use local_fivedays\external\provision\unenrol_users;
use local_fivedays\external\rubric\get_rubric;
use local_fivedays\overseer_role;

class overseer_role_test extends advanced_testcase {

    private \stdClass $course;
    private \stdClass $assign;
    private \stdClass $student;
    private \stdClass $overseer;

    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->assign = $generator->create_module('assign', ['course' => $this->course->id, 'name' => 'Essay', 'grade' => 100]);
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->overseer = $generator->create_and_enrol($this->course, overseer_role::SHORTNAME);

        $cm = get_coursemodule_from_instance('assign', $this->assign->id, $this->course->id);
        $assign = new \assign(\context_module::instance($cm->id), $cm, $this->course);
        $submission = $assign->get_user_submission($this->student->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->timemodified = 1000;
        $DB->update_record('assign_submission', $submission);
    }

    private function role_id(): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => overseer_role::SHORTNAME], MUST_EXIST);
    }

    private function cmid(): int {
        return (int) get_coursemodule_from_instance('assign', $this->assign->id, $this->course->id)->id;
    }

    public function test_install_creates_role_with_exactly_the_whitelist(): void {
        global $DB;

        $roleid = $this->role_id();
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        $this->assertSame(overseer_role::NAME, $role->name);
        $this->assertSame('', (string) $role->archetype);
        $this->assertSame([CONTEXT_COURSE], array_map('intval', array_values(get_role_contextlevels($roleid))));

        $rows = $DB->get_records('role_capabilities', ['roleid' => $roleid], 'capability', 'capability, permission, contextid');
        $expected = overseer_role::CAPABILITIES;
        sort($expected);
        $this->assertSame($expected, array_keys($rows));
        foreach ($rows as $row) {
            $this->assertSame(CAP_ALLOW, (int) $row->permission);
            $this->assertSame(\context_system::instance()->id, (int) $row->contextid);
        }
        $this->assertSame([], overseer_role::sync());
    }

    public function test_sync_repairs_drift_and_lets_the_ws_role_assign_it(): void {
        global $DB;

        $roleid = $this->role_id();
        $systemcontextid = \context_system::instance()->id;
        assign_capability('mod/assign:grade', CAP_ALLOW, $roleid, $systemcontextid, true);
        assign_capability('mod/assign:view', CAP_PREVENT, $roleid, $systemcontextid, true);
        $wsroleid = create_role('FiveDays web service', 'fivedays_ws', '', 'manager');

        $changes = overseer_role::sync();

        $this->assertContains('removed mod/assign:grade', $changes);
        $this->assertContains('allowed mod/assign:view', $changes);
        $this->assertContains('assignable by fivedays_ws', $changes);
        $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => 'mod/assign:grade']));
        $this->assertTrue($DB->record_exists('role_allow_assign', ['roleid' => $wsroleid, 'allowassign' => $roleid]));
        $this->assertSame([], overseer_role::sync());
    }

    public function test_overseer_reads_grades_comments_rubric_and_grading_queue(): void {
        $this->setUser($this->overseer);

        $grades = get_assignment_grades::execute((int) $this->assign->id);
        $this->assertTrue($grades['success']);

        $comments = get_assignment_comments::execute((int) $this->assign->id, (int) $this->student->id);
        $this->assertTrue($comments['success']);

        $rubric = get_rubric::execute($this->cmid());
        $this->assertSame(0, $rubric['definitionid']);

        $queue = get_grading_queue::execute((int) $this->course->id)['items'];
        $this->assertCount(1, $queue);
        $this->assertSame(1, $queue[0]['waiting']);
    }

    public function test_overseer_cannot_grade(): void {
        $this->setUser($this->overseer);

        $this->expectException(\required_capability_exception::class);
        save_assignment_grade::execute((int) $this->assign->id, (int) $this->student->id, 50.0);
    }

    public function test_overseer_cannot_edit_assignment(): void {
        $this->setUser($this->overseer);

        $this->expectException(\required_capability_exception::class);
        update_assignment::execute((int) $this->assign->id, 'Renamed');
    }

    public function test_overseer_cannot_post_in_forum(): void {
        $generator = $this->getDataGenerator();
        $forum = $generator->create_module('forum', ['course' => $this->course->id]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id,
            'forum' => $forum->id,
            'userid' => $this->student->id,
        ]);
        $this->setUser($this->overseer);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('nopostforum', 'forum'));
        \mod_forum_external::add_discussion_post((int) $discussion->firstpost, 'Re: hello', 'Reply');
    }

    public function test_teacher_who_also_oversees_keeps_grading(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        role_assign($this->role_id(), $teacher->id, \context_course::instance($this->course->id)->id);
        $this->setUser($teacher);

        $result = save_assignment_grade::execute((int) $this->assign->id, (int) $this->student->id, 50.0);
        $this->assertTrue($result['success']);
    }

    public function test_enrol_users_accepts_the_overseer_role(): void {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user(['email' => 'pl@example.com']);

        $result = enrol_users::execute([
            ['email' => 'pl@example.com', 'courseid' => (int) $this->course->id, 'roleshortname' => overseer_role::SHORTNAME],
        ]);

        $this->assertSame(1, $result['enrolled']);
        $this->assertTrue(user_has_role_assignment($user->id, $this->role_id(), \context_course::instance($this->course->id)->id));
    }

    public function test_reading_comments_does_not_turn_the_comments_plugin_on(): void {
        global $DB;

        // With site comments off the plugin reports itself disabled, which is when enable() writes settings.
        set_config('usecomments', 0);
        $cm = get_coursemodule_from_instance('assign', $this->assign->id, $this->course->id);
        $assign = new \assign(\context_module::instance($cm->id), $cm, $this->course);
        $assign->get_submission_plugin_by_type('comments')->disable();
        $this->setUser($this->overseer);

        get_assignment_comments::execute((int) $this->assign->id, (int) $this->student->id);

        $enabled = $DB->get_field('assign_plugin_config', 'value', [
            'assignment' => $this->assign->id,
            'plugin' => 'comments',
            'subtype' => 'assignsubmission',
            'name' => 'enabled',
        ]);
        $this->assertSame('0', (string) $enabled);
    }

    public function test_unenrol_users_with_overseer_role_keeps_a_teaching_enrolment(): void {
        $this->setAdminUser();
        $context = \context_course::instance($this->course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher', ['email' => 'pl@example.com']);
        role_assign($this->role_id(), $teacher->id, $context->id);

        $result = unenrol_users::execute([
            ['email' => 'pl@example.com', 'courseid' => (int) $this->course->id, 'roleshortname' => overseer_role::SHORTNAME],
        ]);

        $this->assertSame(1, $result['unenrolled']);
        $this->assertFalse(user_has_role_assignment($teacher->id, $this->role_id(), $context->id));
        $this->assertTrue(is_enrolled($context, $teacher));
    }

    public function test_unenrol_users_with_overseer_role_unenrols_an_overseer_only(): void {
        $this->setAdminUser();
        $context = \context_course::instance($this->course->id);

        $result = unenrol_users::execute([
            ['email' => $this->overseer->email, 'courseid' => (int) $this->course->id,
                'roleshortname' => overseer_role::SHORTNAME],
        ]);

        $this->assertSame(1, $result['unenrolled']);
        $this->assertFalse(is_enrolled($context, $this->overseer));
    }
}
