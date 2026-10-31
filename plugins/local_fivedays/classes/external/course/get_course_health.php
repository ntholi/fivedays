<?php
namespace local_fivedays\external\course;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\grading_queue;
use mod_quiz\question\display_options;

/**
 * One read of everything luct's principal lecturer class checklist needs about a course. All times are
 * Unix seconds, 0 = never or none.
 */
class get_course_health extends external_api {

    private const MATERIAL_MODULES = ['resource', 'folder', 'page', 'url', 'book'];

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
        [$studentsql, $studentparams] = self::role_users_sql($context, 'student');
        [$lecturers, $students] = self::people($context);

        return [
            'activities' => self::activities($course, $modinfo, $studentsql, $studentparams),
            'materials' => self::materials($modinfo),
            'outline' => self::outline($modinfo),
            'announcements' => self::announcements((int)$course->id),
            'virtualclasses' => ['held' => self::virtual_classes_held((int)$course->id)],
            'lecturers' => $lecturers,
            'students' => $students,
        ];
    }

    /** Users with an active enrolment holding $shortname in the course context. */
    private static function role_users_sql(\context_course $context, string $shortname): array {
        [$esql, $eparams] = get_enrolled_sql($context, '', 0, true);
        $key = 'role' . $shortname;
        $sql = "SELECT DISTINCT ra.userid AS id
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = :{$key}
                  JOIN ($esql) en ON en.id = ra.userid
                 WHERE ra.contextid = :{$key}ctx";
        return [$sql, $eparams + [$key => $shortname, $key . 'ctx' => $context->id]];
    }

    /** Visible assignments and quizzes the caller can see grades or reports for, in course order. */
    private static function activities(\stdClass $course, \course_modinfo $modinfo, string $studentsql, array $studentparams): array {
        global $DB;

        $cms = array_filter(
            $modinfo->get_cms(),
            fn(\cm_info $cm) => $cm->visible && in_array($cm->modname, ['assign', 'quiz'], true)
        );
        if (!$cms) {
            return [];
        }

        $assigns = $DB->get_records('assign', ['course' => $course->id]);
        $quizzes = $DB->get_records('quiz', ['course' => $course->id], '', 'id, timeclose, reviewmarks');
        $hiddengrades = self::hidden_grade_items((int)$course->id);

        $out = [];
        foreach ($cms as $cm) {
            if ($cm->modname === 'assign') {
                $instance = $assigns[$cm->instance] ?? null;
                if (!$instance || !has_capability('mod/assign:viewgrades', $cm->context)) {
                    continue;
                }
                $assign = new \assign($cm->context, $cm->get_course_module_record(), $course);
                $assign->set_instance($instance);
                $queue = grading_queue::assignment($assign);
                $counts = self::assignment_counts($cm, $instance, $studentsql, $studentparams);
                $duedate = (int)$instance->duedate;
                $released = self::assignment_released($instance);
            } else {
                $instance = $quizzes[$cm->instance] ?? null;
                if (!$instance || !has_capability('mod/quiz:viewreports', $cm->context)) {
                    continue;
                }
                $queue = grading_queue::quiz($cm);
                $counts = self::quiz_counts($cm, $studentsql, $studentparams);
                $duedate = (int)$instance->timeclose;
                $released = self::quiz_released($instance);
            }

            $out[] = [
                'type' => $cm->modname,
                'instanceid' => (int)$cm->instance,
                'cmid' => (int)$cm->id,
                'name' => $cm->name,
                'idnumber' => (string)$cm->idnumber,
                'duedate' => $duedate,
                'expected' => $counts['expected'],
                'submitted' => $counts['submitted'],
                'waiting' => $queue['waiting'],
                'oldest' => $queue['oldest'],
                'released' => $released && !isset($hiddengrades[$cm->modname][(int)$cm->instance]),
            ];
        }
        return $out;
    }

    /**
     * Students (student role) who can submit, and how many of them have submitted. In a team assignment a
     * student counts as submitted when their own submission or a submitted team submission of a group they
     * belong to (in the assignment's grouping) is submitted. assignment_teams gives every submitter a group, so
     * Moodle's "Default group" submission is not counted.
     */
    private static function assignment_counts(\cm_info $cm, \stdClass $instance, string $studentsql, array $studentparams): array {
        global $DB;

        [$esql, $eparams] = get_enrolled_sql($cm->context, 'mod/assign:submit', 0, true);
        $team = '';
        $params = $eparams + $studentparams + [
            'assignment' => $instance->id,
            'submitted' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
        ];
        if (!empty($instance->teamsubmission)) {
            $grouping = '';
            if ((int)$instance->teamsubmissiongroupingid) {
                $grouping = 'JOIN {groupings_groups} gg ON gg.groupid = ts.groupid AND gg.groupingid = :grouping';
                $params['grouping'] = (int)$instance->teamsubmissiongroupingid;
            }
            $team = "OR EXISTS (
                        SELECT 1
                          FROM {assign_submission} ts
                          JOIN {groups_members} gm ON gm.groupid = ts.groupid
                          $grouping
                         WHERE ts.assignment = :teamassignment
                           AND ts.userid = 0
                           AND ts.latest = 1
                           AND ts.status = :teamsubmitted
                           AND gm.userid = e.id
                    )";
            $params['teamassignment'] = $instance->id;
            $params['teamsubmitted'] = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        }

        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS expected,
                    COALESCE(SUM(CASE WHEN EXISTS (
                        SELECT 1
                          FROM {assign_submission} s
                         WHERE s.assignment = :assignment
                           AND s.userid = e.id
                           AND s.latest = 1
                           AND s.status = :submitted
                    ) $team THEN 1 ELSE 0 END), 0) AS submitted
               FROM ($esql) e
               JOIN ($studentsql) st ON st.id = e.id",
            $params
        );
        return ['expected' => (int)$row->expected, 'submitted' => (int)$row->submitted];
    }

    /** Students (student role) who can attempt, and how many of them have a finished, non-preview attempt. */
    private static function quiz_counts(\cm_info $cm, string $studentsql, array $studentparams): array {
        global $DB;

        [$esql, $eparams] = get_enrolled_sql($cm->context, 'mod/quiz:attempt', 0, true);
        $row = $DB->get_record_sql(
            "SELECT COUNT(1) AS expected,
                    COALESCE(SUM(CASE WHEN EXISTS (
                        SELECT 1
                          FROM {quiz_attempts} qa
                         WHERE qa.quiz = :quiz
                           AND qa.userid = e.id
                           AND qa.state = :finished
                           AND qa.preview = 0
                    ) THEN 1 ELSE 0 END), 0) AS submitted
               FROM ($esql) e
               JOIN ($studentsql) st ON st.id = e.id",
            $eparams + $studentparams + [
                'quiz' => $cm->instance,
                'finished' => \mod_quiz\quiz_attempt::FINISHED,
            ]
        );
        return ['expected' => (int)$row->expected, 'submitted' => (int)$row->submitted];
    }

    /**
     * Released when the assignment has no marking workflow, or every graded student's workflow state is
     * "released". With nothing graded yet there is nothing held back, so it reads as released.
     */
    private static function assignment_released(\stdClass $instance): bool {
        global $DB;

        if (empty($instance->markingworkflow)) {
            return true;
        }
        return !$DB->record_exists_sql(
            "SELECT 1
               FROM {assign_grades} g
          LEFT JOIN {assign_user_flags} uf ON uf.assignment = g.assignment AND uf.userid = g.userid
              WHERE g.assignment = :assignment
                AND g.grade >= 0
                AND (uf.workflowstate IS NULL OR uf.workflowstate <> :released)",
            ['assignment' => $instance->id, 'released' => ASSIGN_MARKING_WORKFLOW_STATE_RELEASED]
        );
    }

    /**
     * Released when the quiz's "Marks" review option lets students see their marks right now: the "After the quiz
     * is closed" box once timeclose has passed, otherwise the "Later, while the quiz is still open" box. The
     * "Immediately after the attempt" box only lasts two minutes after each attempt, so it does not count.
     */
    private static function quiz_released(\stdClass $instance): bool {
        $closed = (int)$instance->timeclose > 0 && (int)$instance->timeclose <= time();
        $when = $closed ? display_options::AFTER_CLOSE : display_options::LATER_WHILE_OPEN;
        return ((int)$instance->reviewmarks & $when) !== 0;
    }

    /** Assignment and quiz grade items hidden from students in the gradebook now: [modname][instanceid] => true. */
    private static function hidden_grade_items(int $courseid): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT id, itemmodule, iteminstance
               FROM {grade_items}
              WHERE courseid = :courseid
                AND itemtype = 'mod'
                AND itemmodule IN ('assign', 'quiz')
                AND itemnumber = 0
                AND (hidden = 1 OR hidden > :now)",
            ['courseid' => $courseid, 'now' => time()]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row->itemmodule][(int)$row->iteminstance] = true;
        }
        return $out;
    }

    private static function materials(\course_modinfo $modinfo): array {
        $count = 0;
        $lastadded = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->visible && in_array($cm->modname, self::MATERIAL_MODULES, true)) {
                $count++;
                $lastadded = max($lastadded, (int)$cm->added);
            }
        }
        return ['count' => $count, 'lastadded' => $lastadded];
    }

    /**
     * Visible top-level sections other than section 0, and how many hold a visible material or activity
     * (labels do not count), looking inside their subsections too.
     */
    private static function outline(\course_modinfo $modinfo): array {
        $topics = 0;
        $covered = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int)$section->section === 0 || $section->is_delegated() || !$section->visible) {
                continue;
            }
            $topics++;
            if (self::section_has_content($modinfo, $section)) {
                $covered++;
            }
        }
        return ['topics' => $topics, 'covered' => $covered];
    }

    private static function section_has_content(\course_modinfo $modinfo, \section_info $section): bool {
        foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->visible || $cm->modname === 'label') {
                continue;
            }
            $delegated = $cm->get_delegated_section_info();
            if (!$delegated) {
                return true;
            }
            if ($delegated->visible && self::section_has_content($modinfo, $delegated)) {
                return true;
            }
        }
        return false;
    }

    /** Discussions in the course's announcements (news) forum and when the newest one was posted. */
    private static function announcements(int $courseid): array {
        global $DB;

        $row = $DB->get_record_sql(
            "SELECT COUNT(d.id) AS discussions, MAX(p.created) AS lastposted
               FROM {forum} f
               JOIN {forum_discussions} d ON d.forum = f.id
               JOIN {forum_posts} p ON p.id = d.firstpost
              WHERE f.course = :courseid
                AND f.type = 'news'",
            ['courseid' => $courseid]
        );
        return ['count' => (int)$row->discussions, 'lastposted' => (int)$row->lastposted];
    }

    /**
     * BigBlueButton sessions held: distinct (meeting, UTC day) pairs with at least one join. Moodle 5.2 does not log
     * meeting creation, so joins are the only record that a session took place; a room is reused for every session,
     * so the day separates them. 0 when the module is not installed.
     */
    private static function virtual_classes_held(int $courseid): int {
        global $DB;

        if (!$DB->get_manager()->table_exists('bigbluebuttonbn_logs')) {
            return 0;
        }
        return (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM (
                    SELECT DISTINCT l.meetingid, FLOOR(l.timecreated / 86400) AS day
                      FROM {bigbluebuttonbn_logs} l
                     WHERE l.courseid = :courseid
                       AND l.log = :join
               ) held",
            ['courseid' => $courseid, 'join' => \mod_bigbluebuttonbn\logger::EVENT_JOIN]
        );
    }

    /** Editing teachers and students with an active enrolment, with their last access to this course. */
    private static function people(\context_course $context): array {
        global $DB;

        [$esql, $eparams] = get_enrolled_sql($context, '', 0, true);
        $rows = $DB->get_recordset_sql(
            "SELECT DISTINCT r.shortname, u.id, u.username, COALESCE(ul.timeaccess, 0) AS lastaccess
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
               JOIN ($esql) en ON en.id = ra.userid
               JOIN {user} u ON u.id = ra.userid
          LEFT JOIN {user_lastaccess} ul ON ul.userid = u.id AND ul.courseid = :courseid
              WHERE ra.contextid = :contextid
                AND r.shortname IN ('editingteacher', 'student')
           ORDER BY u.username",
            $eparams + ['courseid' => $context->instanceid, 'contextid' => $context->id]
        );
        $lecturers = [];
        $students = [];
        foreach ($rows as $row) {
            if ($row->shortname === 'editingteacher') {
                $lecturers[] = ['userid' => (int)$row->id, 'lastaccess' => (int)$row->lastaccess];
            } else {
                $students[] = ['userid' => (int)$row->id, 'username' => $row->username, 'lastaccess' => (int)$row->lastaccess];
            }
        }
        $rows->close();
        return [$lecturers, $students];
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
