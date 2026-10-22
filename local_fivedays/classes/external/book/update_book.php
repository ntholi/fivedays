<?php
namespace local_activity_utils\external\book;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class update_book extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bookid' => new external_value(PARAM_INT, 'Book ID to update'),
            'name' => new external_value(PARAM_TEXT, 'Book name/title', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'Book introduction/description (HTML)', VALUE_DEFAULT, null),
            'numbering' => new external_value(PARAM_INT, 'Chapter numbering style (0=none, 1=numbers, 2=bullets, 3=indented)', VALUE_DEFAULT, null),
            'navstyle' => new external_value(PARAM_INT, 'Navigation style (0=none, 1=images, 2=text)', VALUE_DEFAULT, null),
            'customtitles' => new external_value(PARAM_INT, 'Use custom titles (0=no, 1=yes)', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $bookid,
        ?string $name = null,
        ?string $intro = null,
        ?int $numbering = null,
        ?int $navstyle = null,
        ?int $customtitles = null,
        ?int $visible = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/book/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'bookid' => $bookid,
            'name' => $name,
            'intro' => $intro,
            'numbering' => $numbering,
            'navstyle' => $navstyle,
            'customtitles' => $customtitles,
            'visible' => $visible,
        ]);

        
        $book = $DB->get_record('book', ['id' => $params['bookid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:updatebook', $context);
        require_capability('mod/book:edit', \context_module::instance($cm->id));

        
        $updated = false;

        if ($params['name'] !== null) {
            $book->name = $params['name'];
            $updated = true;
        }
        if ($params['intro'] !== null) {
            $book->intro = $params['intro'];
            $updated = true;
        }
        if ($params['numbering'] !== null) {
            $book->numbering = max(0, min(3, $params['numbering']));
            $updated = true;
        }
        if ($params['navstyle'] !== null) {
            $book->navstyle = max(0, min(2, $params['navstyle']));
            $updated = true;
        }
        if ($params['customtitles'] !== null) {
            $book->customtitles = $params['customtitles'] ? 1 : 0;
            $updated = true;
        }

        if ($updated) {
            $book->timemodified = time();
            $book->revision = $book->revision + 1;
            $DB->update_record('book', $book);
        }

        
        if ($params['visible'] !== null) {
            $cm->visible = $params['visible'];
            $cm->visibleold = $params['visible'];
            $DB->update_record('course_modules', $cm);
        }

        rebuild_course_cache($course->id, true);

        return [
            'id' => $book->id,
            'coursemoduleid' => $cm->id,
            'name' => $book->name,
            'success' => true,
            'message' => 'Book updated successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Book ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Book name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
