<?php
namespace local_fivedays\external\course;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\provision_helper;

class get_activities_status extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module ID')
            ),
        ]);
    }

    public static function execute(array $cmids): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmids' => $cmids]);
        provision_helper::require_system_capability();

        $cmids = array_values(array_unique(array_map('intval', $params['cmids'])));
        $found = [];
        if ($cmids) {
            [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
            $found = $DB->get_records_sql(
                "SELECT cm.id, cm.course, cm.instance, cm.added, m.name AS modname
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module
                  WHERE cm.id $insql AND cm.deletioninprogress = 0",
                $inparams
            );
        }

        $times = self::content_times($found);

        $activities = [];
        foreach ($params['cmids'] as $cmid) {
            $cm = $found[(int) $cmid] ?? null;
            $activities[] = $cm ? [
                'cmid' => (int) $cm->id,
                'exists' => true,
                'courseid' => (int) $cm->course,
                'modname' => $cm->modname,
                'timemodified' => max((int) $cm->added, $times[(int) $cm->id] ?? 0),
            ] : [
                'cmid' => (int) $cmid,
                'exists' => false,
                'courseid' => 0,
                'modname' => '',
                'timemodified' => 0,
            ];
        }

        return ['activities' => $activities];
    }

    /**
     * Newest edit per cm: the instance's timemodified, book chapters, and files in the module context.
     *
     * @param \stdClass[] $cms keyed by cm id
     * @return int[] keyed by cm id
     */
    private static function content_times(array $cms): array {
        global $DB;

        $times = [];
        $bump = function (int $cmid, $time) use (&$times): void {
            $times[$cmid] = max($times[$cmid] ?? 0, (int) $time);
        };

        $bymod = [];
        foreach ($cms as $cm) {
            $bymod[$cm->modname][(int) $cm->instance] = (int) $cm->id;
        }

        foreach ($bymod as $modname => $instances) {
            if (!array_key_exists('timemodified', $DB->get_columns($modname))) {
                continue;
            }
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED);
            $rows = $DB->get_records_select_menu($modname, "id $insql", $inparams, '', 'id, timemodified');
            foreach ($rows as $instanceid => $time) {
                $bump($instances[$instanceid], $time);
            }
            if ($modname === 'book') {
                $rows = $DB->get_records_sql_menu(
                    "SELECT bookid, MAX(timemodified) FROM {book_chapters} WHERE bookid $insql GROUP BY bookid",
                    $inparams
                );
                foreach ($rows as $bookid => $time) {
                    $bump($instances[$bookid], $time);
                }
            }
        }

        if ($cms) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($cms), SQL_PARAMS_NAMED);
            $rows = $DB->get_records_sql_menu(
                "SELECT ctx.instanceid, MAX(f.timemodified)
                   FROM {context} ctx
                   JOIN {files} f ON f.contextid = ctx.id AND f.filename <> '.'
                  WHERE ctx.contextlevel = :level AND ctx.instanceid $insql
               GROUP BY ctx.instanceid",
                $inparams + ['level' => CONTEXT_MODULE]
            );
            foreach ($rows as $cmid => $time) {
                $bump((int) $cmid, $time);
            }
        }

        return $times;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Requested course module ID'),
                    'exists' => new external_value(PARAM_BOOL, 'False when the activity is missing or being deleted'),
                    'courseid' => new external_value(PARAM_INT, 'Course ID, 0 when missing'),
                    'modname' => new external_value(PARAM_PLUGIN, 'Module name (page, resource, url, book, folder), empty when missing'),
                    'timemodified' => new external_value(PARAM_INT, 'Newest edit as Unix seconds, 0 when missing'),
                ])
            ),
        ]);
    }
}
