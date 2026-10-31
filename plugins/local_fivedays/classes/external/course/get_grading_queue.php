<?php
namespace local_fivedays\external\course;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\grading_queue;

class get_grading_queue extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']]);
        if (!$course) {
            throw new \moodle_exception('coursenotfound', 'local_fivedays');
        }
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/fivedays:viewgradingqueue', $context);

        $modinfo = get_fast_modinfo($course);
        $items = [];

        foreach ($modinfo->get_instances_of('assign') as $cm) {
            $item = self::assignment_item($course, $cm);
            if ($item) {
                $items[] = $item;
            }
        }
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            $item = self::quiz_item($cm);
            if ($item) {
                $items[] = $item;
            }
        }

        return [
            'success' => true,
            'items' => $items,
        ];
    }

    private static function assignment_item(\stdClass $course, \cm_info $cm): ?array {
        if (!has_capability('mod/assign:viewgrades', $cm->context)) {
            return null;
        }

        $assign = new \assign($cm->context, $cm->get_course_module_record(), $course);
        $queue = grading_queue::assignment($assign);
        return $queue['waiting'] ? self::item('assign', $cm, $queue) : null;
    }

    private static function quiz_item(\cm_info $cm): ?array {
        if (!has_capability('mod/quiz:viewreports', $cm->context)) {
            return null;
        }

        $queue = grading_queue::quiz($cm);
        return $queue['waiting'] ? self::item('quiz', $cm, $queue) : null;
    }

    private static function item(string $type, \cm_info $cm, array $queue): array {
        return [
            'type' => $type,
            'instanceid' => (int)$cm->instance,
            'cmid' => (int)$cm->id,
            'name' => $cm->name,
            'waiting' => $queue['waiting'],
            'oldest' => $queue['oldest'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHA, 'assign or quiz'),
                    'instanceid' => new external_value(PARAM_INT, 'Assignment or quiz instance ID'),
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                    'name' => new external_value(PARAM_TEXT, 'Activity name'),
                    'waiting' => new external_value(PARAM_INT, 'Submissions or attempts waiting to be graded'),
                    'oldest' => new external_value(PARAM_INT, 'Time the oldest waiting item arrived, 0 when unknown'),
                ])
            ),
        ]);
    }
}
