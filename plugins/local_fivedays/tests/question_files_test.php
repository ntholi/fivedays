<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/local/fivedays/lib.php');

use advanced_testcase;
use local_fivedays\external\question\create_essay_question;
use local_fivedays\external\question\create_multichoice_question;
use local_fivedays\external\question\get_or_create_category;
use local_fivedays\external\question\get_questions;
use local_fivedays\external\quiz\get_quiz;
use local_fivedays\question_files;

// Files attached to quiz questions: stored per question id, listed on reads, stripped from the text.
class question_files_test extends advanced_testcase {

    private \stdClass $course;
    private int $categoryid;
    private int $contextid;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $category = get_or_create_category::execute($this->course->id, 'Files');
        $this->categoryid = (int) $category['id'];
        $this->contextid = (int) $category['contextid'];
    }

    private function inline(string ...$names): string {
        return json_encode(array_map(
            fn($name) => ['filename' => $name, 'content' => base64_encode("bytes of {$name}")],
            $names
        ));
    }

    private function draft(string $filename, string $content): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    private function essay(string $files, int $entryid = 0): array {
        return create_essay_question::execute(
            categoryid: $this->categoryid,
            name: 'Essay',
            questiontext: '<p>Read this</p>',
            questionbankentryid: $entryid,
            questionfiles: $files
        );
    }

    private function stored(int $questionid): array {
        global $DB;

        return array_values($DB->get_fieldset_select(
            'files',
            'filename',
            "component = 'question' AND filearea = 'questiontext' AND itemid = ? AND filename <> '.' ORDER BY id",
            [$questionid]
        ));
    }

    public function test_strip_removes_only_the_marker_block_at_the_end(): void {
        $block = '<div class="fivedays-qfiles" data-fivedays-qfiles="1"><p><a href="@@PLUGINFILE@@/a.pdf">a.pdf</a></p></div>';

        $this->assertSame('<p>Text</p>', question_files::strip('<p>Text</p>' . $block));
        $this->assertSame('<p>Text</p>', question_files::strip('<p>Text</p>'));
        $this->assertSame('', question_files::strip(''));
        $inside = $block . '<p>after</p>';
        $this->assertSame($inside, question_files::strip($inside));
        $this->assertSame('<div class="x"><p>kept</p></div>', question_files::strip('<div class="x"><p>kept</p></div>'));
    }

    public function test_create_stores_files_from_draft_and_inline_and_appends_the_block(): void {
        global $DB;

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $draft = $this->draft('my picture.png', $png);
        $result = $this->essay(json_encode([
            ['filename' => 'my picture.png', 'draftitemid' => $draft],
            ['filename' => 'doc.pdf', 'content' => base64_encode('%PDF-1.4')],
        ]));

        $this->assertTrue($result['success']);
        $this->assertSame(['my picture.png', 'doc.pdf'], array_column($result['files'], 'filename'));
        $this->assertSame([1, 0], array_column($result['files'], 'isimage'));
        $this->assertSame($result['questionid'], $result['files'][0]['questionid']);
        $this->assertStringEndsWith(
            "/webservice/pluginfile.php/{$this->contextid}/local_fivedays/qfiles/{$result['questionid']}/my%20picture.png",
            $result['files'][0]['fileurl']
        );

        $text = $DB->get_field('question', 'questiontext', ['id' => $result['questionid']]);
        $this->assertSame(
            '<p>Read this</p><div class="fivedays-qfiles" data-fivedays-qfiles="1">'
                . '<p><img src="@@PLUGINFILE@@/my%20picture.png" alt="my picture.png" class="img-fluid"></p>'
                . '<p><a href="@@PLUGINFILE@@/doc.pdf">doc.pdf</a></p></div>',
            $text
        );
        $this->assertEquals(FORMAT_HTML, $DB->get_field('question', 'questiontextformat', ['id' => $result['questionid']]));
    }

    public function test_reads_return_stripped_text_and_files_and_new_versions_keep_their_own(): void {
        $first = $this->essay($this->inline('one.txt', 'two.txt'));
        $second = $this->essay($this->inline('three.txt'), $first['questionbankentryid']);
        $third = $this->essay('[]', $first['questionbankentryid']);

        $this->assertSame(['one.txt', 'two.txt'], $this->stored($first['questionid']));
        $this->assertSame(['three.txt'], $this->stored($second['questionid']));
        $this->assertSame([], $this->stored($third['questionid']));
        $this->assertSame([], $third['files']);

        $questions = get_questions::execute($this->categoryid);
        $this->assertCount(1, $questions['questions']);
        $this->assertSame($third['questionid'], $questions['questions'][0]['questionid']);
        $this->assertSame('<p>Read this</p>', $questions['questions'][0]['questiontext']);
        $this->assertSame([], $questions['questions'][0]['files']);

        $other = $this->essay($this->inline('x.txt', 'y.txt'));
        $questions = get_questions::execute($this->categoryid);
        $byid = array_column($questions['questions'], null, 'questionid');
        $this->assertSame(['x.txt', 'y.txt'], array_column($byid[$other['questionid']]['files'], 'filename'));
        $this->assertSame('<p>Read this</p>', $byid[$other['questionid']]['questiontext']);
    }

    public function test_get_quiz_lists_the_files_of_each_question(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $with = $this->essay($this->inline('a.txt'));
        $without = $this->essay('[]');
        quiz_add_quiz_question($with['questionid'], $quiz);
        quiz_add_quiz_question($without['questionid'], $quiz);

        $result = get_quiz::execute($quiz->id);

        $this->assertSame('<p>Read this</p>', $result['questions'][0]['questiontext']);
        $this->assertSame(['a.txt'], array_column($result['questions'][0]['files'], 'filename'));
        $this->assertSame([], $result['questions'][1]['files']);
    }

    public function test_fromquestionid_copies_a_file_into_the_new_version(): void {
        $first = $this->essay($this->inline('keep.txt', 'drop.txt'));
        $second = $this->essay(
            json_encode([['filename' => 'keep.txt', 'fromquestionid' => $first['questionid']]]),
            $first['questionbankentryid']
        );

        $this->assertSame(['keep.txt'], array_column($second['files'], 'filename'));
        $this->assertSame(['keep.txt', 'drop.txt'], $this->stored($first['questionid']));
        $file = get_file_storage()->get_file($this->contextid, 'question', 'questiontext', $second['questionid'], '/', 'keep.txt');
        $this->assertSame('bytes of keep.txt', $file->get_content());
    }

    public function test_fromquestionid_needs_a_question_capability_in_the_source_category(): void {
        $first = $this->essay($this->inline('keep.txt'));
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $this->expectException(\required_capability_exception::class);
        question_files::parse(json_encode([['filename' => 'keep.txt', 'fromquestionid' => $first['questionid']]]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bad_attachments')]
    public function test_a_bad_attachment_fails_and_leaves_no_question(string $files, string $errorcode): void {
        global $DB;

        $before = $DB->count_records('question');
        try {
            $this->essay($files === 'draftmissing' ? json_encode([['filename' => 'x.txt', 'draftitemid' => 999999]]) : $files);
            $this->fail('expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }

        $this->assertSame($before, $DB->count_records('question'));
        $this->assertSame(0, $DB->count_records('question_bank_entries'));
    }

    public static function bad_attachments(): array {
        $item = fn($name) => ['filename' => $name, 'content' => base64_encode('x')];
        return [
            'six files' => [json_encode(array_map($item, ['1', '2', '3', '4', '5', '6'])), 'toomanyattachments'],
            'duplicate names' => [json_encode([$item('A.txt'), $item('a.TXT')]), 'attachmentinvalid'],
            'missing draft file' => ['draftmissing', 'attachmentmissing'],
            'two sources' => [json_encode([['filename' => 'a.txt', 'content' => 'eA==', 'draftitemid' => 1]]), 'attachmentinvalid'],
            'no source' => [json_encode([['filename' => 'a.txt']]), 'attachmentinvalid'],
            'empty filename' => [json_encode([$item('')]), 'attachmentinvalid'],
            'not base64' => [json_encode([['filename' => 'a.txt', 'content' => '!!!']]), 'attachmentinvalid'],
            'not a list' => ['{"filename":"a.txt"}', 'attachmentinvalid'],
            'unknown source question' => [json_encode([['filename' => 'a.txt', 'fromquestionid' => 99999]]), 'attachmentmissing'],
        ];
    }

    public function test_a_file_over_20_mb_is_refused_before_the_question_is_saved(): void {
        global $DB;

        $draft = $this->draft('big.bin', str_repeat('a', question_files::MAX_BYTES + 1));
        $before = $DB->count_records('question');

        try {
            $this->essay(json_encode([['filename' => 'big.bin', 'draftitemid' => $draft]]));
            $this->fail('expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('attachmenttoolarge', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('question'));
    }

    public function test_the_other_create_functions_take_the_same_input(): void {
        $result = create_multichoice_question::execute(
            categoryid: $this->categoryid,
            name: 'Choice',
            questiontext: '<p>Pick</p>',
            answers: json_encode([['text' => 'A', 'fraction' => 1, 'feedback' => ''], ['text' => 'B', 'fraction' => 0, 'feedback' => '']]),
            questionfiles: $this->inline('c.txt')
        );

        $this->assertSame(['c.txt'], array_column($result['files'], 'filename'));
    }

    public function test_pluginfile_serves_only_files_of_a_question_in_that_category(): void {
        $question = $this->essay($this->inline('a.txt'));
        $othercontext = \context_course::instance($this->course->id);

        $this->assertFalse(local_fivedays_pluginfile(null, null, $othercontext, 'qfiles', [$question['questionid'], 'a.txt'], false));
        $context = \context::instance_by_id($this->contextid);
        $this->assertFalse(local_fivedays_pluginfile(null, null, $context, 'other', [$question['questionid'], 'a.txt'], false));
        $this->assertFalse(local_fivedays_pluginfile(null, null, $context, 'qfiles', [$question['questionid'] + 1, 'a.txt'], false));
        $this->assertFalse(local_fivedays_pluginfile(null, null, $context, 'qfiles', [$question['questionid'], 'nope.txt'], false));
        $this->assertFalse(local_fivedays_pluginfile(null, null, $context, 'qfiles', [$question['questionid']], false));
    }
}
