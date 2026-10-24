<?php
namespace local_fivedays\external\course;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

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
        global $DB;

        $context = $cm->context;
        if (!has_capability('mod/assign:grade', $context)
            || !has_capability('local/fivedays:gradeassignment', $context)) {
            return null;
        }

        $assign = new \assign($context, $cm->get_course_module_record(), $course);
        $waiting = (int)$assign->count_submissions_need_grading();
        if ($waiting === 0) {
            return null;
        }

        [$esql, $eparams] = get_enrolled_sql($context, 'mod/assign:submit', 0, true);
        $oldest = $DB->get_field_sql(
            "SELECT MIN(s.timemodified)
               FROM {assign_submission} s
          LEFT JOIN {assign_grades} g
                 ON g.assignment = s.assignment
                AND g.userid = s.userid
                AND g.attemptnumber = s.attemptnumber
              WHERE s.assignment = :assignment
                AND s.latest = 1
                AND s.status = :status
                AND s.userid IN ($esql)
                AND (g.timemodified IS NULL OR g.timemodified < s.timemodified OR g.grade IS NULL OR g.grade < 0)",
            $eparams + ['assignment' => $cm->instance, 'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED]
        );

        return [
            'type' => 'assign',
            'instanceid' => (int)$cm->instance,
            'cmid' => (int)$cm->id,
            'name' => $cm->name,
            'waiting' => $waiting,
            'oldest' => (int)$oldest,
        ];
    }

    private static function quiz_item(\cm_info $cm): ?array {
        global $DB;

        $context = $cm->context;
        if (!has_capability('mod/quiz:grade', $context)
            || !has_capability('local/fivedays:gradequizattempts', $context)) {
            return null;
        }

        [$esql, $eparams] = get_enrolled_sql($context, 'mod/quiz:attempt', 0, true);
        $row = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT quiza.id) AS waiting, MIN(quiza.timefinish) AS oldest
               FROM {quiz_attempts} quiza
               JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
               JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
              WHERE quiza.quiz = :quiz
                AND quiza.state = :finished
                AND quiza.preview = 0
                AND quiza.userid IN ($esql)
                AND qas.state = :needsgrading
                AND qas.sequencenumber = (
                    SELECT MAX(latest.sequencenumber)
                      FROM {question_attempt_steps} latest
                     WHERE latest.questionattemptid = qa.id
                )",
            $eparams + [
                'quiz' => $cm->instance,
                'finished' => \mod_quiz\quiz_attempt::FINISHED,
                'needsgrading' => 'needsgrading',
            ]
        );
        if (!$row || (int)$row->waiting === 0) {
            return null;
        }

        return [
            'type' => 'quiz',
            'instanceid' => (int)$cm->instance,
            'cmid' => (int)$cm->id,
            'name' => $cm->name,
            'waiting' => (int)$row->waiting,
            'oldest' => (int)$row->oldest,
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
