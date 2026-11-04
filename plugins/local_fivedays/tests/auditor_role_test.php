<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use advanced_testcase;
use local_fivedays\auditor_role;
use local_fivedays\external\assignment\get_assignment_grades;
use local_fivedays\external\assignment\save_assignment_grade;
use local_fivedays\external\course\get_grading_queue;
use local_fivedays\external\provision\set_site_auditors;
use local_fivedays\overseer_role;

class auditor_role_test extends advanced_testcase {

    private \stdClass $course;
    private \stdClass $assign;
    private \stdClass $student;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->assign = $generator->create_module('assign', ['course' => $this->course->id, 'name' => 'Essay', 'grade' => 100]);
        $this->student = $generator->create_and_enrol($this->course, 'student');
    }

    private function role_id(): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => auditor_role::SHORTNAME], MUST_EXIST);
    }

    private function auditor(): \stdClass {
        $user = $this->getDataGenerator()->create_user(['email' => 'qa@example.com']);
        role_assign($this->role_id(), $user->id, \context_system::instance()->id);
        return $user;
    }

    public function test_install_creates_a_system_role_with_the_overseer_list_plus_course_view(): void {
        global $DB;

        $roleid = $this->role_id();
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        $this->assertSame(auditor_role::NAME, $role->name);
        $this->assertSame('', (string) $role->archetype);
        $this->assertSame([CONTEXT_SYSTEM], array_map('intval', array_values(get_role_contextlevels($roleid))));

        $rows = $DB->get_records('role_capabilities', ['roleid' => $roleid], 'capability', 'capability, permission, contextid');
        $expected = [...overseer_role::CAPABILITIES, 'moodle/course:view'];
        sort($expected);
        $this->assertSame($expected, array_keys($rows));
        foreach ($rows as $row) {
            $this->assertSame(CAP_ALLOW, (int) $row->permission);
        }
        $this->assertSame([], auditor_role::sync());
        $this->assertSame([CONTEXT_COURSE],
            array_map('intval', array_values(get_role_contextlevels(
                (int) $DB->get_field('role', 'id', ['shortname' => overseer_role::SHORTNAME])))));
    }

    public function test_auditor_reads_a_course_they_are_not_enrolled_in(): void {
        $auditor = $this->auditor();
        $this->setUser($auditor);

        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $auditor));
        $this->assertTrue(get_assignment_grades::execute((int) $this->assign->id)['success']);
        $this->assertIsArray(get_grading_queue::execute((int) $this->course->id)['items']);
    }

    public function test_auditor_cannot_grade(): void {
        $this->setUser($this->auditor());

        $this->expectException(\required_capability_exception::class);
        save_assignment_grade::execute((int) $this->assign->id, (int) $this->student->id, 50.0);
    }

    public function test_set_site_auditors_assigns_and_removes_once(): void {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user(['email' => 'registry@example.com']);
        $systemid = \context_system::instance()->id;

        $this->assertSame(1, set_site_auditors::execute([['email' => 'Registry@example.com', 'auditor' => true]])['changed']);
        $this->assertTrue(user_has_role_assignment($user->id, $this->role_id(), $systemid));
        $this->assertSame(0, set_site_auditors::execute([['email' => 'registry@example.com', 'auditor' => true]])['changed']);

        $removed = set_site_auditors::execute([
            ['email' => 'registry@example.com', 'auditor' => false],
            ['email' => 'nobody@example.com', 'auditor' => false],
        ]);
        $this->assertSame(1, $removed['changed']);
        $this->assertFalse(user_has_role_assignment($user->id, $this->role_id(), $systemid));
    }

    public function test_set_site_auditors_refuses_an_unknown_email(): void {
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('nobody@example.com');
        set_site_auditors::execute([['email' => 'nobody@example.com', 'auditor' => true]]);
    }
}
