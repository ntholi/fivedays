<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

use advanced_testcase;
use local_fivedays\external\assignment\delete_assignment;
use local_fivedays\external\bigbluebuttonbn\delete_bigbluebuttonbn;
use local_fivedays\external\bigbluebuttonbn\update_bigbluebuttonbn;
use local_fivedays\external\book\delete_book;
use local_fivedays\external\file\delete_file;
use local_fivedays\external\file\update_file;
use local_fivedays\external\forum\delete_forum;
use local_fivedays\external\page\delete_page;
use local_fivedays\external\page\update_page;
use local_fivedays\external\quiz\delete_quiz;
use local_fivedays\external\quiz\update_quiz;
use local_fivedays\external\section\create_subsection;
use local_fivedays\external\section\delete_subsection;
use local_fivedays\external\url\delete_url;

// Deleting, hiding and showing activities through core_courseformat\local\cmactions.
class module_actions_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 1);
    }

    public static function delete_provider(): array {
        return [
            'page' => ['page', delete_page::class],
            'file' => ['resource', delete_file::class],
            'url' => ['url', delete_url::class],
            'book' => ['book', delete_book::class],
            'forum' => ['forum', delete_forum::class],
            'quiz' => ['quiz', delete_quiz::class],
            'assignment' => ['assign', delete_assignment::class],
            'bigbluebuttonbn' => ['bigbluebuttonbn', delete_bigbluebuttonbn::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('delete_provider')]
    public function test_delete_removes_module_now(string $modname, string $class): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module($modname, ['course' => $course->id]);
        $sink = $this->redirectEvents();

        $result = $class::execute($module->cmid);

        $events = array_values(array_filter($sink->get_events(),
            fn($event) => $event instanceof \core\event\course_module_deleted));
        $sink->close();
        $this->assertDebuggingNotCalled();
        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $module->cmid]));
        $this->assertFalse($DB->record_exists($modname, ['id' => $module->id]));
        $this->assertCount(1, $events);
        $this->assertSame((int) $module->cmid, (int) $events[0]->objectid);
        $this->assertSame($modname, $events[0]->other['modulename']);
        $this->assertArrayNotHasKey($module->cmid, get_fast_modinfo($course->id)->get_cms());
        $this->assertSame(0, $this->count_deletion_tasks());
    }

    public function test_delete_subsection_removes_module_and_section(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $subsection = create_subsection::execute(courseid: $course->id, parentsection: 1, name: 'Week 1.1');

        $result = delete_subsection::execute($subsection['coursemoduleid']);

        $this->assertDebuggingNotCalled();
        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $subsection['coursemoduleid']]));
        $this->assertFalse($DB->record_exists('course_sections', ['id' => $subsection['id']]));
    }

    public function test_delete_with_recycle_bin_keeps_a_copy_and_deletes_now(): void {
        global $DB;

        set_config('coursebinenable', 1, 'tool_recyclebin');
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        delete_page::execute($page->cmid);

        $this->assertDebuggingNotCalled();
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $page->cmid]));
        $this->assertSame(1, $DB->count_records('tool_recyclebin_course', ['courseid' => $course->id]));
        $this->assertSame(0, $this->count_deletion_tasks());
    }

    public static function visibility_provider(): array {
        return [
            'page' => ['page', fn(\stdClass $m, int $visible) => update_page::execute(pageid: $m->id, visible: $visible)],
            'file' => ['resource', fn(\stdClass $m, int $visible) => update_file::execute(resourceid: $m->id, visible: $visible)],
            'quiz' => ['quiz', fn(\stdClass $m, int $visible) => update_quiz::execute(quizid: $m->id, visible: $visible)],
            'bigbluebuttonbn' => ['bigbluebuttonbn',
                fn(\stdClass $m, int $visible) => update_bigbluebuttonbn::execute(bigbluebuttonbnid: $m->id, visible: $visible)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('visibility_provider')]
    public function test_update_hides_and_shows_module(string $modname, \Closure $update): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module($modname, ['course' => $course->id]);

        foreach ([0, 1] as $visible) {
            $result = $update($module, $visible);

            $this->assertDebuggingNotCalled();
            $this->assertTrue($result['success']);
            $cm = $DB->get_record('course_modules', ['id' => $module->cmid], 'visible, visibleold, visibleoncoursepage');
            $this->assertEquals($visible, $cm->visible);
            $this->assertEquals($visible, $cm->visibleold);
            $this->assertEquals(1, $cm->visibleoncoursepage);
            $this->assertEquals($visible, get_fast_modinfo($course->id)->get_cm($module->cmid)->visible);
        }
    }

    public function test_hiding_a_quiz_hides_its_grade_item(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'grade' => 10]);
        $gradeitem = fn() => \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id, 'courseid' => $course->id]);

        update_quiz::execute(quizid: $quiz->id, visible: 0);
        $this->assertEquals(1, $gradeitem()->hidden);

        update_quiz::execute(quizid: $quiz->id, visible: 1);
        $this->assertEquals(0, $gradeitem()->hidden);
    }

    private function count_deletion_tasks(): int {
        global $DB;
        return $DB->count_records('task_adhoc', ['classname' => '\\core_course\\task\\course_delete_modules']);
    }
}
