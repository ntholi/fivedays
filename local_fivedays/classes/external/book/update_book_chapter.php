<?php
namespace local_activity_utils\external\book;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class update_book_chapter extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'chapterid' => new external_value(PARAM_INT, 'Chapter ID to update'),
            'title' => new external_value(PARAM_TEXT, 'Chapter title', VALUE_DEFAULT, null),
            'content' => new external_value(PARAM_RAW, 'Chapter content (HTML)', VALUE_DEFAULT, null),
            'subchapter' => new external_value(PARAM_INT, 'Is subchapter (0=main chapter, 1=subchapter)', VALUE_DEFAULT, null),
            'hidden' => new external_value(PARAM_INT, 'Hidden (0=visible, 1=hidden)', VALUE_DEFAULT, null),
            'tags' => new external_value(PARAM_RAW, 'Comma-separated tags for the chapter (empty string to clear)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $chapterid,
        ?string $title = null,
        ?string $content = null,
        ?int $subchapter = null,
        ?int $hidden = null,
        ?string $tags = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/book/lib.php');
        require_once($CFG->dirroot . '/mod/book/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'chapterid' => $chapterid,
            'title' => $title,
            'content' => $content,
            'subchapter' => $subchapter,
            'hidden' => $hidden,
            'tags' => $tags,
        ]);

        
        $chapter = $DB->get_record('book_chapters', ['id' => $params['chapterid']], '*', MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $chapter->bookid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        $context = \context_module::instance($cm->id);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:updatebook', $coursecontext);
        require_capability('mod/book:edit', $context);

        $transaction = $DB->start_delegated_transaction();
        $updated = false;

        if ($params['title'] !== null) {
            $chapter->title = $params['title'];
            $updated = true;
        }
        if ($params['content'] !== null) {
            $chapter->content = $params['content'];
            $updated = true;
        }
        if ($params['subchapter'] !== null) {
            $chapter->subchapter = $params['subchapter'] ? 1 : 0;
            $updated = true;
        }
        if ($params['hidden'] !== null) {
            $chapter->hidden = $params['hidden'] ? 1 : 0;
            $updated = true;
        }

        if ($updated) {
            $chapter->timemodified = time();
            $DB->update_record('book_chapters', $chapter);

            
            $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
        }

        
        if ($params['tags'] !== null) {
            if (empty($params['tags'])) {
                
                \core_tag_tag::remove_all_item_tags('mod_book', 'book_chapters', $chapter->id);
            } else {
                $tagsarray = array_map('trim', explode(',', $params['tags']));
                $tagsarray = array_filter($tagsarray);
                if (!empty($tagsarray)) {
                    \core_tag_tag::set_item_tags('mod_book', 'book_chapters', $chapter->id, $context, $tagsarray);
                }
            }
        }

        rebuild_course_cache($course->id, true);
        $transaction->allow_commit();

        return [
            'id' => $chapter->id,
            'bookid' => $book->id,
            'pagenum' => $chapter->pagenum,
            'title' => $chapter->title,
            'subchapter' => $chapter->subchapter,
            'success' => true,
            'message' => 'Chapter updated successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Chapter ID'),
            'bookid' => new external_value(PARAM_INT, 'Book ID'),
            'pagenum' => new external_value(PARAM_INT, 'Page number'),
            'title' => new external_value(PARAM_TEXT, 'Chapter title'),
            'subchapter' => new external_value(PARAM_INT, 'Is subchapter'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
