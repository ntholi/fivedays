<?php
namespace local_fivedays\external\course;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\course_health;

/**
 * One read of everything luct's principal lecturer class checklist needs about a course (see course_health).
 * get_courses_health returns the same structure for many courses.
 */
class get_course_health extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $DB;

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

        return course_health::read([$course])[(int)$course->id];
    }

    public static function execute_returns(): external_single_structure {
        $count = fn(string $what) => new external_value(PARAM_INT, $what);
        return new external_single_structure([
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHA, 'assign or quiz'),
                    'instanceid' => $count('Assignment or quiz instance ID'),
                    'cmid' => $count('Course module ID'),
                    'name' => new external_value(PARAM_TEXT, 'Activity name'),
                    'idnumber' => new external_value(PARAM_RAW, 'Course module ID number, empty when unset'),
                    'duedate' => $count('Assignment due date or quiz close time, 0 when none'),
                    'expected' => $count('Students (student role) who can submit or attempt'),
                    'submitted' => $count('Of those, students with a submitted submission or a finished attempt'),
                    'waiting' => $count('Submissions or attempts waiting to be graded'),
                    'oldest' => $count('Time the oldest waiting item arrived, 0 when none'),
                    'released' => new external_value(PARAM_BOOL, 'Whether students can see their grades now'),
                ])
            ),
            'materials' => new external_single_structure([
                'count' => $count('Visible resources, folders, pages, URLs and books'),
                'lastadded' => $count('When the newest of them was added, 0 when none'),
            ]),
            'outline' => new external_single_structure([
                'topics' => $count('Visible sections other than section 0'),
                'covered' => $count('Of those, sections with a visible material or activity'),
            ]),
            'announcements' => new external_single_structure([
                'count' => $count('Discussions in the announcements forum'),
                'lastposted' => $count('When the newest was posted, 0 when none'),
            ]),
            'virtualclasses' => new external_single_structure([
                'held' => $count('BigBlueButton sessions held'),
            ]),
            'lecturers' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => $count('User ID'),
                    'lastaccess' => $count('Last access to the course, 0 when never'),
                ])
            ),
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => $count('User ID'),
                    'username' => new external_value(PARAM_RAW, 'Username'),
                    'lastaccess' => $count('Last access to the course, 0 when never'),
                ])
            ),
        ]);
    }
}
