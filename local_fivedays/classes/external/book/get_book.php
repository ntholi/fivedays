<?php
namespace local_activity_utils\external\book;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;


class get_book extends external_api {

    
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bookid' => new external_value(PARAM_INT, 'Book instance ID'),
        ]);
    }

    
    public static function execute(int $bookid): array {
        global $DB;

        
        $params = self::validate_parameters(self::execute_parameters(), [
            'bookid' => $bookid,
        ]);

        
        $book = $DB->get_record('book', ['id' => $params['bookid']], '*', MUST_EXIST);

        
        $cm = get_coursemodule_from_instance('book', $book->id, 0, false, MUST_EXIST);

        
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:readbook', \context_course::instance($cm->course));
        require_capability('mod/book:read', $context);

        
        $canedit = has_capability('mod/book:edit', $context);

        
        $course = $DB->get_record('course', ['id' => $cm->course], 'id, fullname, shortname', MUST_EXIST);

        
        $sql = "SELECT bc.id, bc.bookid, bc.pagenum, bc.subchapter, bc.title,
                       bc.content, bc.contentformat, bc.hidden,
                       bc.timecreated, bc.timemodified, bc.importsrc
                  FROM {book_chapters} bc
                 WHERE bc.bookid = ?";

        $sqlparams = [$book->id];

        
        if (!$canedit) {
            $sql .= " AND bc.hidden = 0";
        }

        $sql .= " ORDER BY bc.pagenum ASC";

        $chapters = $DB->get_records_sql($sql, $sqlparams);

        
        $chaptersarray = [];
        foreach ($chapters as $chapter) {
            
            $tags = \core_tag_tag::get_item_tags_array('mod_book', 'book_chapters', $chapter->id);

            $chaptersarray[] = [
                'id' => (int)$chapter->id,
                'pagenum' => (int)$chapter->pagenum,
                'subchapter' => (int)$chapter->subchapter,
                'title' => $chapter->title,
                'content' => $chapter->content,
                'contentformat' => (int)$chapter->contentformat,
                'hidden' => (int)$chapter->hidden,
                'timecreated' => (int)$chapter->timecreated,
                'timemodified' => (int)$chapter->timemodified,
                'importsrc' => $chapter->importsrc ?? '',
                'tags' => $tags,
            ];
        }

        return [
            'id' => $book->id,
            'coursemoduleid' => $cm->id,
            'courseid' => $cm->course,
            'coursename' => $course->fullname,
            'name' => $book->name,
            'intro' => $book->intro,
            'introformat' => $book->introformat,
            'numbering' => $book->numbering,
            'navstyle' => $book->navstyle,
            'customtitles' => $book->customtitles,
            'revision' => $book->revision,
            'timecreated' => $book->timecreated,
            'timemodified' => $book->timemodified,
            'chapters' => $chaptersarray,
            'success' => true,
            'message' => 'Book retrieved successfully with ' . count($chaptersarray) . ' chapter(s)',
        ];
    }

    
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Book ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
            'name' => new external_value(PARAM_TEXT, 'Book name'),
            'intro' => new external_value(PARAM_RAW, 'Book introduction/description'),
            'introformat' => new external_value(PARAM_INT, 'Intro format'),
            'numbering' => new external_value(PARAM_INT, 'Chapter numbering style (0=none, 1=numbers, 2=bullets, 3=indented)'),
            'navstyle' => new external_value(PARAM_INT, 'Navigation style (0=none, 1=images, 2=text)'),
            'customtitles' => new external_value(PARAM_INT, 'Use custom titles (0=no, 1=yes)'),
            'revision' => new external_value(PARAM_INT, 'Book revision number'),
            'timecreated' => new external_value(PARAM_INT, 'Time created (Unix timestamp)'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified (Unix timestamp)'),
            'chapters' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Chapter ID'),
                    'pagenum' => new external_value(PARAM_INT, 'Page number (position in book)'),
                    'subchapter' => new external_value(PARAM_INT, 'Is subchapter (0=main chapter, 1=subchapter)'),
                    'title' => new external_value(PARAM_TEXT, 'Chapter title'),
                    'content' => new external_value(PARAM_RAW, 'Chapter content HTML'),
                    'contentformat' => new external_value(PARAM_INT, 'Content format'),
                    'hidden' => new external_value(PARAM_INT, 'Is hidden (0=visible, 1=hidden)'),
                    'timecreated' => new external_value(PARAM_INT, 'Time created (Unix timestamp)'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified (Unix timestamp)'),
                    'importsrc' => new external_value(PARAM_TEXT, 'Import source'),
                    'tags' => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Tag name'),
                        'Chapter tags',
                        VALUE_OPTIONAL
                    ),
                ]),
                'Array of all chapters with full content and hierarchy'
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
