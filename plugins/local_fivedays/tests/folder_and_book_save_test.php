<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_fivedays\external\book\save_book;
use local_fivedays\external\folder\create_folder;
use local_fivedays\external\folder\update_folder;

class folder_and_book_save_test extends advanced_testcase {

    private \stdClass $course;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);
    }

    private function draft(array $files): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        foreach ($files as $filename => $content) {
            $fs->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $filename,
            ], $content);
        }
        return $draftitemid;
    }

    private function folder_files(int $cmid): array {
        $context = \context_module::instance($cmid);
        $files = get_file_storage()->get_area_files($context->id, 'mod_folder', 'content', 0, 'filename', false);
        $out = [];
        foreach ($files as $file) {
            $out[$file->get_filename()] = $file->get_content();
        }
        return $out;
    }

    public function test_create_folder_copies_draft_files(): void {
        global $DB;

        $result = create_folder::execute($this->course->id, 'Week 1', $this->draft(['a.txt' => 'A', 'b.txt' => 'B']), 0, 1);

        $folder = $DB->get_record('folder', ['id' => $result['id']], '*', MUST_EXIST);
        $this->assertSame('Week 1', $folder->name);
        $this->assertSame(0, (int) $folder->display);
        $this->assertSame(1, (int) $folder->showexpanded);
        $this->assertSame(1, (int) $folder->showdownloadfolder);
        $this->assertSame(['a.txt' => 'A', 'b.txt' => 'B'], $this->folder_files($result['coursemoduleid']));
    }

    public function test_create_folder_refuses_empty_draft(): void {
        $this->expectException(\moodle_exception::class);
        create_folder::execute($this->course->id, 'Empty', file_get_unused_draft_itemid(), 0, 1);
    }

    public function test_update_folder_renames_removes_and_replaces(): void {
        global $DB;

        $created = create_folder::execute($this->course->id, 'Week 1', $this->draft(['a.txt' => 'A', 'b.txt' => 'B']), 0, 1);
        $revision = (int) $DB->get_field('folder', 'revision', ['id' => $created['id']]);

        $result = update_folder::execute($created['id'], 'Week 1 notes', $this->draft(['b.txt' => 'B2', 'c.txt' => 'C']),
            [['filepath' => '/', 'filename' => 'a.txt']]);

        $this->assertTrue($result['success']);
        $folder = $DB->get_record('folder', ['id' => $created['id']], '*', MUST_EXIST);
        $this->assertSame('Week 1 notes', $folder->name);
        $this->assertSame($revision + 1, (int) $folder->revision);
        $this->assertSame(['b.txt' => 'B2', 'c.txt' => 'C'], $this->folder_files($created['coursemoduleid']));
    }

    private function book_with_chapters(): \stdClass {
        $book = $this->getDataGenerator()->create_module('book', ['course' => $this->course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $generator->create_chapter(['bookid' => $book->id, 'title' => 'One', 'content' => 'c1', 'pagenum' => 1]);
        $generator->create_chapter(['bookid' => $book->id, 'title' => 'Two', 'content' => 'c2', 'pagenum' => 2]);
        $generator->create_chapter(['bookid' => $book->id, 'title' => 'Three', 'content' => 'c3', 'pagenum' => 3]);
        return $book;
    }

    public function test_save_book_updates_adds_deletes_and_reorders(): void {
        global $DB;

        $book = $this->book_with_chapters();
        $ids = $DB->get_records_menu('book_chapters', ['bookid' => $book->id], 'pagenum', 'title, id');
        $sink = $this->redirectEvents();

        $result = save_book::execute($book->id, 'Renamed', [
            ['id' => $ids['Three'], 'title' => 'Three', 'content' => 'c3', 'subchapter' => 0],
            ['id' => 0, 'title' => 'New', 'content' => '<p>@@PLUGINFILE@@/x.png</p>', 'subchapter' => 1],
            ['id' => $ids['One'], 'title' => 'One edited', 'content' => 'c1', 'subchapter' => 0],
        ]);

        $events = array_map(fn($event) => get_class($event), $sink->get_events());
        $sink->close();

        $this->assertSame('Renamed', $DB->get_field('book', 'name', ['id' => $book->id]));
        $rows = array_values($DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum'));
        $this->assertSame(['Three', 'New', 'One edited'], array_column($rows, 'title'));
        $this->assertSame([1, 2, 3], array_map('intval', array_column($rows, 'pagenum')));
        $this->assertSame('<p>@@PLUGINFILE@@/x.png</p>', $rows[1]->content);
        $this->assertSame(1, (int) $rows[1]->subchapter);
        $this->assertFalse($DB->record_exists('book_chapters', ['id' => $ids['Two']]));
        $this->assertSame(array_map('intval', array_column($rows, 'id')), array_column($result['chapters'], 'id'));
        $this->assertContains(\mod_book\event\chapter_created::class, $events);
        $this->assertContains(\mod_book\event\chapter_updated::class, $events);
        $this->assertContains(\mod_book\event\chapter_deleted::class, $events);
    }

    public function test_save_book_refuses_leading_subchapter(): void {
        $book = $this->book_with_chapters();
        $this->expectException(\moodle_exception::class);
        save_book::execute($book->id, 'B', [['id' => 0, 'title' => 'X', 'content' => '', 'subchapter' => 1]]);
    }

    public function test_save_book_refuses_empty_list(): void {
        $book = $this->book_with_chapters();
        $this->expectException(\moodle_exception::class);
        save_book::execute($book->id, 'B', []);
    }

    public function test_save_book_refuses_chapter_of_another_book(): void {
        global $DB;

        $book = $this->book_with_chapters();
        $other = $this->book_with_chapters();
        $foreign = (int) $DB->get_field('book_chapters', 'id', ['bookid' => $other->id, 'pagenum' => 1]);

        try {
            save_book::execute($book->id, 'B', [['id' => $foreign, 'title' => 'X', 'content' => '', 'subchapter' => 0]]);
            $this->fail('Expected a moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('bookchapternotinbook', $e->errorcode);
        }
        $this->assertSame(3, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }
}
