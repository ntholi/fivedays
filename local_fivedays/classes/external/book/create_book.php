<?php
namespace local_activity_utils\external\book;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;


class create_book extends external_api {

    
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Book name/title'),
            'intro' => new external_value(PARAM_RAW, 'Book introduction/description (HTML)', VALUE_DEFAULT, ''),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
            'numbering' => new external_value(PARAM_INT, 'Chapter numbering style (0=none, 1=numbers, 2=bullets, 3=indented)', VALUE_DEFAULT, 1),
            'navstyle' => new external_value(PARAM_INT, 'Navigation style (0=none, 1=images, 2=text)', VALUE_DEFAULT, 1),
            'customtitles' => new external_value(PARAM_INT, 'Use custom titles (0=no, 1=yes)', VALUE_DEFAULT, 0),
            'chapters' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Chapter title'),
                    'content' => new external_value(PARAM_RAW, 'Chapter content (HTML)', VALUE_DEFAULT, ''),
                    'subchapter' => new external_value(PARAM_INT, 'Is subchapter (0=main chapter, 1=subchapter)', VALUE_DEFAULT, 0),
                    'hidden' => new external_value(PARAM_INT, 'Hidden (0=visible, 1=hidden)', VALUE_DEFAULT, 0),
                    'tags' => new external_value(PARAM_RAW, 'Comma-separated tags for the chapter', VALUE_DEFAULT, ''),
                ]),
                'Array of chapters to add to the book',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    
    public static function execute(
        int $courseid,
        string $name,
        string $intro = '',
        int $section = 0,
        int $visible = 1,
        int $numbering = 1,
        int $navstyle = 1,
        int $customtitles = 0,
        array $chapters = []
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/book/lib.php');
        require_once($CFG->dirroot . '/mod/book/locallib.php');

        
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'section' => $section,
            'visible' => $visible,
            'numbering' => $numbering,
            'navstyle' => $navstyle,
            'customtitles' => $customtitles,
            'chapters' => $chapters,
        ]);

        
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createbook', $context);
        require_capability('mod/book:addinstance', $context);

        
        $numbering = max(0, min(3, $params['numbering']));

        
        $navstyle = max(0, min(2, $params['navstyle']));

        $transaction = $DB->start_delegated_transaction();
        $moduleinfo = helper::create_module($course, 'book', $params['section'], $params['name'], $params['visible'], [
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'numbering' => $numbering,
            'navstyle' => $navstyle,
            'customtitles' => $params['customtitles'] ? 1 : 0,
            'revision' => 1,
        ]);

        $bookid = $moduleinfo->instance;
        $modulecontext = \context_module::instance($moduleinfo->coursemodule);

        
        $createdchapters = [];
        $pagenum = 0;

        foreach ($params['chapters'] as $chapterdata) {
            $pagenum++;

            $chapter = new \stdClass();
            $chapter->bookid = $bookid;
            $chapter->pagenum = $pagenum;
            $chapter->subchapter = !empty($chapterdata['subchapter']) ? 1 : 0;
            $chapter->title = $chapterdata['title'];
            $chapter->content = $chapterdata['content'] ?? '';
            $chapter->contentformat = FORMAT_HTML;
            $chapter->hidden = !empty($chapterdata['hidden']) ? 1 : 0;
            $chapter->timecreated = time();
            $chapter->timemodified = time();
            $chapter->importsrc = '';

            $chapterid = $DB->insert_record('book_chapters', $chapter);

            
            if (!empty($chapterdata['tags'])) {
                $tags = array_map('trim', explode(',', $chapterdata['tags']));
                $tags = array_filter($tags);
                if (!empty($tags)) {
                    \core_tag_tag::set_item_tags('mod_book', 'book_chapters', $chapterid, $modulecontext, $tags);
                }
            }

            $createdchapters[] = [
                'id' => $chapterid,
                'pagenum' => $pagenum,
                'title' => $chapter->title,
                'subchapter' => $chapter->subchapter,
            ];
        }

        
        rebuild_course_cache($params['courseid'], true);
        $transaction->allow_commit();

        return [
            'id' => $bookid,
            'coursemoduleid' => $moduleinfo->coursemodule,
            'name' => $params['name'],
            'chaptercount' => count($createdchapters),
            'chapters' => $createdchapters,
            'success' => true,
            'message' => 'Book created successfully' . (count($createdchapters) > 0 ? ' with ' . count($createdchapters) . ' chapter(s)' : ''),
        ];
    }

    
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Book ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Book name'),
            'chaptercount' => new external_value(PARAM_INT, 'Number of chapters created'),
            'chapters' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Chapter ID'),
                    'pagenum' => new external_value(PARAM_INT, 'Page number'),
                    'title' => new external_value(PARAM_TEXT, 'Chapter title'),
                    'subchapter' => new external_value(PARAM_INT, 'Is subchapter'),
                ]),
                'List of created chapters'
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
