<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_fivedays\external\course\copy_activities;
use local_fivedays\external\course\get_activities_status;

class reuse_activities_test extends advanced_testcase {

    private \stdClass $source;
    private \stdClass $target;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->source = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $this->target = $this->getDataGenerator()->create_course(['numsections' => 1]);
    }

    private function module_files(int $cmid, string $component, string $filearea): array {
        $context = \context_module::instance($cmid);
        $files = get_file_storage()->get_area_files($context->id, $component, $filearea, false, 'filename', false);
        $out = [];
        foreach ($files as $file) {
            $out[$file->get_filename()] = $file->get_content();
        }
        return $out;
    }

    private function add_folder_file(int $cmid, string $filename, string $content, int $timemodified): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($cmid)->id,
            'component' => 'mod_folder',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'timemodified' => $timemodified,
        ], $content);
    }

    public function test_status_reports_newest_edit_and_missing_activities(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->source->id]);
        $DB->set_field('page', 'timemodified', 2000000000, ['id' => $page->id]);
        $book = $this->getDataGenerator()->create_module('book', ['course' => $this->source->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_book')->create_chapter(['bookid' => $book->id]);
        $DB->set_field('book_chapters', 'timemodified', 2000000100, ['bookid' => $book->id]);
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $this->source->id]);
        $this->add_folder_file($folder->cmid, 'late.txt', 'L', 2000000200);

        $result = get_activities_status::execute([(int) $book->cmid, 999999, (int) $page->cmid, (int) $folder->cmid]);
        $result = \core_external\external_api::clean_returnvalue(get_activities_status::execute_returns(), $result);

        $this->assertSame([
            ['cmid' => (int) $book->cmid, 'exists' => true, 'courseid' => (int) $this->source->id,
                'modname' => 'book', 'timemodified' => 2000000100],
            ['cmid' => 999999, 'exists' => false, 'courseid' => 0, 'modname' => '', 'timemodified' => 0],
            ['cmid' => (int) $page->cmid, 'exists' => true, 'courseid' => (int) $this->source->id,
                'modname' => 'page', 'timemodified' => 2000000000],
            ['cmid' => (int) $folder->cmid, 'exists' => true, 'courseid' => (int) $this->source->id,
                'modname' => 'folder', 'timemodified' => 2000000200],
        ], $result['activities']);

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $page->cmid]);
        $result = get_activities_status::execute([(int) $page->cmid]);
        $this->assertFalse($result['activities'][0]['exists']);
    }

    public function test_copy_creates_section_and_appends_in_order_with_files(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->source->id, 'section' => 2,
            'name' => 'Intro page']);
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $this->source->id, 'name' => 'Notes']);
        $this->add_folder_file($folder->cmid, 'a.txt', 'A', time());

        $result = copy_activities::execute($this->target->id, 'Material', '<p>Reused</p>', [
            ['cmid' => (int) $folder->cmid, 'reusekey' => 'r1'],
            ['cmid' => 999999, 'reusekey' => 'r2'],
            ['cmid' => (int) $page->cmid, 'reusekey' => 'r3'],
        ]);
        $result = \core_external\external_api::clean_returnvalue(copy_activities::execute_returns(), $result);

        [$foldercopy, $missing, $pagecopy] = $result['copies'];
        $this->assertSame('', $foldercopy['error']);
        $this->assertFalse($foldercopy['existing']);
        $this->assertSame('', $pagecopy['error']);
        $this->assertSame(0, $missing['cmid']);
        $this->assertNotSame('', $missing['error']);

        $section = $DB->get_record('course_sections', ['course' => $this->target->id, 'name' => 'Material'], '*', MUST_EXIST);
        $this->assertSame('<p>Reused</p>', $section->summary);
        $this->assertSame($foldercopy['cmid'] . ',' . $pagecopy['cmid'], $section->sequence);

        $modinfo = get_fast_modinfo($this->target->id);
        $this->assertSame('Notes', $modinfo->get_cm($foldercopy['cmid'])->name);
        $this->assertSame('Intro page', $modinfo->get_cm($pagecopy['cmid'])->name);
        $this->assertSame(['a.txt' => 'A'], $this->module_files($foldercopy['cmid'], 'mod_folder', 'content'));
        $this->assertSame(2, $DB->count_records('local_fivedays_reuse_copies', ['courseid' => $this->target->id]));
    }

    public function test_retry_with_same_reusekey_returns_existing_copy(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->source->id]);
        $items = [['cmid' => (int) $page->cmid, 'reusekey' => 'family-7']];

        $first = copy_activities::execute($this->target->id, 'Material', '', $items)['copies'][0];
        $cms = $DB->count_records('course_modules', ['course' => $this->target->id]);

        $second = copy_activities::execute($this->target->id, 'Material', '', $items)['copies'][0];
        $this->assertSame(['sourcecmid' => (int) $page->cmid, 'cmid' => $first['cmid'], 'error' => '', 'existing' => true],
            $second);
        $this->assertSame($cms, $DB->count_records('course_modules', ['course' => $this->target->id]));
        $this->assertSame(1, $DB->count_records('course_sections', ['course' => $this->target->id, 'name' => 'Material']));

        \core_courseformat\formatactions::cm($this->target->id)->delete($first['cmid']);
        $third = copy_activities::execute($this->target->id, 'Material', '', $items)['copies'][0];
        $this->assertFalse($third['existing']);
        $this->assertNotSame($first['cmid'], $third['cmid']);
        $this->assertSame($third['cmid'], (int) $DB->get_field('local_fivedays_reuse_copies', 'cmid',
            ['courseid' => $this->target->id, 'reusekey' => 'family-7']));

        delete_course($this->target->id, false);
        $this->assertSame(0, $DB->count_records('local_fivedays_reuse_copies', ['courseid' => $this->target->id]));
    }

    public function test_copy_refuses_empty_reusekey(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->source->id]);

        $copy = copy_activities::execute($this->target->id, 'Material', '',
            [['cmid' => (int) $page->cmid, 'reusekey' => ' ']])['copies'][0];

        $this->assertSame(0, $copy['cmid']);
        $this->assertNotSame('', $copy['error']);
    }
}
