<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_fivedays\activity_approval;
use local_fivedays\external\assignment\update_assignment;
use local_fivedays\external\course\set_activity_approval;

class activity_approval_test extends advanced_testcase {

    private \stdClass $course;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    private function assignment(int $visible): \stdClass {
        return $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'visible' => $visible,
        ]);
    }

    private function visible(int $cmid): int {
        global $DB;
        return (int) $DB->get_field('course_modules', 'visible', ['id' => $cmid]);
    }

    public function test_unapproved_hides_visible_modules_and_reports_missing(): void {
        $assign = $this->assignment(1);

        $result = set_activity_approval::execute([$assign->cmid, 999999], false);

        $this->assertSame([(int) $assign->cmid], $result['updated']);
        $this->assertSame([999999], $result['missing']);
        $this->assertSame(0, $this->visible($assign->cmid));
        $this->assertTrue(activity_approval::is_blocked((int) $assign->cmid));
        $this->assertDebuggingCalled();
    }

    public function test_observer_rehides_an_unapproved_module_shown_in_moodle(): void {
        $assign = $this->assignment(0);
        set_activity_approval::execute([$assign->cmid], false);

        set_coursemodule_visible($assign->cmid, 1);
        $cm = get_coursemodule_from_id('assign', $assign->cmid);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $this->assertSame(0, $this->visible($assign->cmid));
        $this->assertDebuggingCalled();
    }

    public function test_approved_and_unmanaged_modules_stay_visible(): void {
        $approved = $this->assignment(0);
        $unmanaged = $this->assignment(1);
        set_activity_approval::execute([$approved->cmid], false);
        set_activity_approval::execute([$approved->cmid], true);

        foreach ([$approved, $unmanaged] as $assign) {
            set_coursemodule_visible($assign->cmid, 1);
            $cm = get_coursemodule_from_id('assign', $assign->cmid);
            \core\event\course_module_updated::create_from_cm($cm)->trigger();
            $this->assertSame(1, $this->visible($assign->cmid));
        }
    }

    public function test_update_assignment_refuses_to_show_an_unapproved_module(): void {
        $assign = $this->assignment(0);
        set_activity_approval::execute([$assign->cmid], false);

        $this->expectException(\moodle_exception::class);
        update_assignment::execute((int) $assign->id, visible: 1);
    }

    public function test_deleting_the_module_drops_its_row(): void {
        global $DB;
        $assign = $this->assignment(0);
        set_activity_approval::execute([$assign->cmid], false);

        \core_courseformat\formatactions::cm((int) $this->course->id)->delete((int) $assign->cmid);

        $this->assertFalse($DB->record_exists('local_fivedays_activity_approval', ['cmid' => $assign->cmid]));
    }

    public function test_showing_a_hidden_section_keeps_an_unapproved_module_hidden(): void {
        global $DB;
        $assign = $this->assignment(1);
        $cm = get_coursemodule_from_id('assign', $assign->cmid);
        $section = get_fast_modinfo($this->course->id)->get_section_info_by_id((int) $cm->section);
        $actions = \core_courseformat\formatactions::section((int) $this->course->id);
        $actions->set_visibility($section, false);
        $this->assertSame(1, (int) $DB->get_field('course_modules', 'visibleold', ['id' => $assign->cmid]));

        set_activity_approval::execute([$assign->cmid], false);
        $section = get_fast_modinfo($this->course->id)->get_section_info_by_id((int) $cm->section);
        $actions->set_visibility($section, true);

        $this->assertSame(0, $this->visible($assign->cmid));
        $this->assertTrue(activity_approval::is_blocked((int) $assign->cmid));
    }

    public function test_showing_a_section_leaves_approved_modules_alone(): void {
        $assign = $this->assignment(1);
        $cm = get_coursemodule_from_id('assign', $assign->cmid);
        $actions = \core_courseformat\formatactions::section((int) $this->course->id);
        $actions->set_visibility(get_fast_modinfo($this->course->id)->get_section_info_by_id((int) $cm->section), false);
        set_activity_approval::execute([$assign->cmid], true);

        $actions->set_visibility(get_fast_modinfo($this->course->id)->get_section_info_by_id((int) $cm->section), true);

        $this->assertSame(1, $this->visible($assign->cmid));
    }

    public function test_a_duplicate_of_an_unapproved_module_stays_blocked_and_hidden(): void {
        $assign = $this->assignment(0);
        set_activity_approval::execute([$assign->cmid], false);

        $copy = \core_courseformat\formatactions::cm((int) $this->course->id)->duplicate((int) $assign->cmid);

        $this->assertNotNull($copy);
        $this->assertTrue(activity_approval::is_blocked((int) $copy->id));
        $this->assertSame(0, $this->visible((int) $copy->id));
        $this->expectException(\moodle_exception::class);
        update_assignment::execute((int) $copy->instance, visible: 1);
    }

    public function test_a_duplicate_of_an_approved_module_is_not_managed(): void {
        $assign = $this->assignment(1);
        set_activity_approval::execute([$assign->cmid], true);

        $copy = \core_courseformat\formatactions::cm((int) $this->course->id)->duplicate((int) $assign->cmid);

        $this->assertFalse(activity_approval::is_blocked((int) $copy->id));
        $this->assertSame(1, $this->visible((int) $copy->id));
    }
}
