<?php
namespace local_fivedays\external\book;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Saves a whole book in one transaction: name plus the full ordered chapter list.
 * Listed chapters with an id are updated, id 0 is added, chapters left out are deleted.
 */
class save_book extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bookid' => new external_value(PARAM_INT, 'Book instance ID'),
            'name' => new external_value(PARAM_TEXT, 'Book name'),
            'chapters' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Existing chapter ID, 0 for a new chapter', VALUE_DEFAULT, 0),
                    'title' => new external_value(PARAM_TEXT, 'Chapter title'),
                    'content' => new external_value(PARAM_RAW, 'Chapter content (HTML, may hold @@PLUGINFILE@@ links)'),
                    'subchapter' => new external_value(PARAM_INT, 'Is subchapter (0=main chapter, 1=subchapter)', VALUE_DEFAULT, 0),
                ]),
                'All chapters in book order'
            ),
        ]);
    }

    public static function execute(int $bookid, string $name, array $chapters): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/book/lib.php');
        require_once($CFG->dirroot . '/mod/book/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'bookid' => $bookid,
            'name' => $name,
            'chapters' => $chapters,
        ]);

        $book = $DB->get_record('book', ['id' => $params['bookid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/fivedays:updatebook', \context_course::instance($cm->course));
        require_capability('mod/book:edit', $context);

        $input = $params['chapters'];
        if (!$input) {
            throw new \moodle_exception('bookchaptersrequired', 'local_fivedays');
        }
        if (!empty($input[0]['subchapter'])) {
            throw new \moodle_exception('bookfirstchaptersubchapter', 'local_fivedays');
        }

        $existing = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum');
        $keptids = [];
        foreach ($input as $chapter) {
            $id = (int) $chapter['id'];
            if ($id === 0) {
                continue;
            }
            if (!isset($existing[$id]) || isset($keptids[$id])) {
                throw new \moodle_exception('bookchapternotinbook', 'local_fivedays', '', $id);
            }
            $keptids[$id] = true;
        }

        $fs = get_file_storage();
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        foreach ($existing as $id => $chapter) {
            if (isset($keptids[$id])) {
                continue;
            }
            \core_tag_tag::remove_all_item_tags('mod_book', 'book_chapters', $chapter->id);
            $fs->delete_area_files($context->id, 'mod_book', 'chapter', $chapter->id);
            $DB->delete_records('book_chapters', ['id' => $chapter->id]);
            \mod_book\event\chapter_deleted::create_from_chapter($book, $context, $chapter)->trigger();
        }

        $saved = [];
        $pagenum = 0;
        foreach ($input as $chapter) {
            $pagenum++;
            $subchapter = $chapter['subchapter'] ? 1 : 0;
            $id = (int) $chapter['id'];

            if ($id === 0) {
                $record = (object) [
                    'bookid' => $book->id,
                    'pagenum' => $pagenum,
                    'subchapter' => $subchapter,
                    'title' => $chapter['title'],
                    'content' => $chapter['content'],
                    'contentformat' => FORMAT_HTML,
                    'hidden' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                    'importsrc' => '',
                ];
                $record->id = $DB->insert_record('book_chapters', $record);
                \mod_book\event\chapter_created::create_from_chapter($book, $context, $record)->trigger();
            } else {
                $record = $existing[$id];
                $changed = (int) $record->pagenum !== $pagenum
                    || (int) $record->subchapter !== $subchapter
                    || $record->title !== $chapter['title']
                    || $record->content !== $chapter['content'];
                if ($changed) {
                    $record->pagenum = $pagenum;
                    $record->subchapter = $subchapter;
                    $record->title = $chapter['title'];
                    $record->content = $chapter['content'];
                    $record->timemodified = $now;
                    $DB->update_record('book_chapters', $record);
                    \mod_book\event\chapter_updated::create_from_chapter($book, $context, $record)->trigger();
                }
            }

            $saved[] = [
                'id' => (int) $record->id,
                'pagenum' => $pagenum,
                'title' => $record->title,
                'subchapter' => $subchapter,
            ];
        }

        $book->name = $params['name'];
        $book->revision = $book->revision + 1;
        $book->timemodified = $now;
        $DB->update_record('book', $book);

        // Same structure fix-up mod_book runs after every edit (subchapters of hidden chapters stay hidden).
        book_preload_chapters($book);

        rebuild_course_cache($cm->course, true);
        $transaction->allow_commit();

        return [
            'id' => (int) $book->id,
            'coursemoduleid' => (int) $cm->id,
            'chapters' => $saved,
            'success' => true,
            'message' => 'Book saved with ' . count($saved) . ' chapter(s)',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Book ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'chapters' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Chapter ID'),
                    'pagenum' => new external_value(PARAM_INT, 'Page number (position in book)'),
                    'title' => new external_value(PARAM_TEXT, 'Chapter title'),
                    'subchapter' => new external_value(PARAM_INT, 'Is subchapter (0=main chapter, 1=subchapter)'),
                ]),
                'Chapters in book order'
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
