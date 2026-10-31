<?php
namespace local_fivedays\external\course;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\course_health;

/**
 * get_course_health for up to course_health::MAX_BATCH courses in one call, for luct's hourly class checklist
 * refresh. Ids that are not a course, the site front page, or a hidden course the caller cannot see are left out;
 * the rest come back once each, in input order.
 */
class get_courses_health extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'Course IDs, at most ' . course_health::MAX_BATCH . ' distinct'
            ),
        ]);
    }

    public static function execute(array $courseids): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseids' => $courseids,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/fivedays:viewcoursehealth', $context);

        $ids = array_values(array_unique(array_map('intval', $params['courseids'])));
        if (count($ids) > course_health::MAX_BATCH) {
            throw new \moodle_exception('toomanycourses', 'local_fivedays', '', course_health::MAX_BATCH);
        }
        $ids = array_values(array_filter($ids, fn(int $id) => $id > 0 && $id != SITEID));
        if (!$ids) {
            return ['courses' => []];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'courseid');
        $ctxfields = \context_helper::get_preload_record_columns_sql('ctx');
        $records = $DB->get_records_sql(
            "SELECT c.*, $ctxfields
               FROM {course} c
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
              WHERE c.id $insql",
            $inparams + ['courselevel' => CONTEXT_COURSE]
        );
        $courses = [];
        foreach ($ids as $id) {
            $course = $records[$id] ?? null;
            if (!$course) {
                continue;
            }
            \context_helper::preload_from_record($course);
            if (!$course->visible
                    && !has_capability('moodle/course:viewhiddencourses', \context_course::instance($id))) {
                continue;
            }
            $courses[] = $course;
        }

        $out = [];
        foreach (course_health::read($courses) as $courseid => $health) {
            $out[] = ['courseid' => $courseid] + $health;
        }
        return ['courses' => $out];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure(
                    ['courseid' => new external_value(PARAM_INT, 'Course ID')]
                    + get_course_health::execute_returns()->keys
                )
            ),
        ]);
    }
}
