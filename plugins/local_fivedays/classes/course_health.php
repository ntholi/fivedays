<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

use mod_quiz\question\display_options;

/**
 * Everything luct's principal lecturer class checklist needs about courses, read for a whole batch at once with
 * set-based queries. get_course_health and get_courses_health both return read() so they always agree. All times
 * are Unix seconds, 0 = never or none.
 */
class course_health {

    /** Most courses get_courses_health reads in one call. */
    public const MAX_BATCH = 100;

    private const MATERIAL_MODULES = ['resource', 'folder', 'page', 'url', 'book'];

    /**
     * @param \stdClass[] $courses course records the caller may read
     * @return array<int, array> health keyed by course ID, in $courses order
     */
    public static function read(array $courses): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        if (!$courses) {
            return [];
        }
        $courseids = array_map(fn(\stdClass $course) => (int)$course->id, array_values($courses));
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'healthcourse');
        $modinfos = [];
        foreach ($courses as $course) {
            $modinfos[(int)$course->id] = get_fast_modinfo($course);
        }

        $activities = self::activities($courses, $modinfos, $insql, $inparams);
        $announcements = self::announcements($insql, $inparams);
        $held = self::virtual_classes_held($insql, $inparams);
        $people = self::people($courses);

        $out = [];
        foreach ($courseids as $courseid) {
            $out[$courseid] = [
                'activities' => $activities[$courseid] ?? [],
                'materials' => self::materials($modinfos[$courseid]),
                'outline' => self::outline($modinfos[$courseid]),
                'announcements' => $announcements[$courseid] ?? ['count' => 0, 'lastposted' => 0],
                'virtualclasses' => ['held' => $held[$courseid] ?? 0],
                'lecturers' => $people[$courseid]['editingteacher'] ?? [],
                'students' => $people[$courseid]['student'] ?? [],
            ];
        }
        return $out;
    }

    /** Users with an active enrolment holding $shortname in the course context; $suffix keeps param names unique. */
    private static function role_users_sql(\context_course $context, string $shortname, string $suffix): array {
        [$esql, $eparams] = get_enrolled_sql($context, '', 0, true);
        $key = 'role' . $shortname . $suffix;
        $sql = "SELECT DISTINCT ra.userid AS id
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = :{$key}
                  JOIN ($esql) en ON en.id = ra.userid
                 WHERE ra.contextid = {$context->id}";
        return [$sql, $eparams + [$key => $shortname]];
    }

    /**
     * Visible assignments and quizzes the caller can see grades or reports for, in course order.
     *
     * @return array<int, array[]> keyed by course ID
     */
    private static function activities(array $courses, array $modinfos, string $insql, array $inparams): array {
        global $DB;

        self::preload_activity_contexts($insql, $inparams);
        $assigns = $DB->get_records_select('assign', "course $insql", $inparams);
        $quizzes = $DB->get_records_select('quiz', "course $insql", $inparams, '', 'id, timeclose, reviewmarks');
        $hiddengrades = self::hidden_grade_items($insql, $inparams);

        $items = [];
        $assignobjects = [];
        $quizcms = [];
        foreach ($courses as $course) {
            foreach ($modinfos[(int)$course->id]->get_cms() as $cm) {
                if (!$cm->visible) {
                    continue;
                }
                if ($cm->modname === 'assign') {
                    $instance = $assigns[$cm->instance] ?? null;
                    if (!$instance || !has_capability('mod/assign:viewgrades', $cm->context)) {
                        continue;
                    }
                    $assign = new \assign($cm->context, $cm->get_course_module_record(), $course);
                    $assign->set_instance($instance);
                    $assignobjects[(int)$cm->id] = $assign;
                } else if ($cm->modname === 'quiz') {
                    $instance = $quizzes[$cm->instance] ?? null;
                    if (!$instance || !has_capability('mod/quiz:viewreports', $cm->context)) {
                        continue;
                    }
                    $quizcms[(int)$cm->id] = $cm;
                } else {
                    continue;
                }
                $items[] = [$cm, $instance];
            }
        }
        if (!$items) {
            return [];
        }

        $assignqueue = grading_queue::assignments($assignobjects);
        $quizqueue = grading_queue::quizzes($quizcms);
        $counts = self::submission_counts($items);
        $unreleased = self::unreleased_assignments($assignobjects);

        $out = [];
        foreach ($items as [$cm, $instance]) {
            if ($cm->modname === 'assign') {
                $queue = $assignqueue[(int)$instance->id];
                $duedate = (int)$instance->duedate;
                $released = !isset($unreleased[(int)$instance->id]);
            } else {
                $queue = $quizqueue[(int)$cm->id];
                $duedate = (int)$instance->timeclose;
                $released = self::quiz_released($instance);
            }
            $out[(int)$cm->course][] = [
                'type' => $cm->modname,
                'instanceid' => (int)$cm->instance,
                'cmid' => (int)$cm->id,
                'name' => $cm->name,
                'idnumber' => (string)$cm->idnumber,
                'duedate' => $duedate,
                'expected' => $counts[(int)$cm->id]['expected'],
                'submitted' => $counts[(int)$cm->id]['submitted'],
                'waiting' => $queue['waiting'],
                'oldest' => $queue['oldest'],
                'released' => $released && !isset($hiddengrades[$cm->modname][(int)$cm->instance]),
            ];
        }
        return $out;
    }

    /** One query for the contexts of every assignment and quiz in the batch, which the capability checks need. */
    private static function preload_activity_contexts(string $insql, array $inparams): void {
        global $DB;

        $ctxfields = \context_helper::get_preload_record_columns_sql('ctx');
        $rs = $DB->get_recordset_sql(
            "SELECT $ctxfields
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name IN ('assign', 'quiz')
               JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel
              WHERE cm.course $insql",
            $inparams + ['modulelevel' => CONTEXT_MODULE]
        );
        foreach ($rs as $row) {
            \context_helper::preload_from_record($row);
        }
        $rs->close();
    }

    /**
     * Students (student role) who can submit or attempt, and how many of them have submitted, keyed by cmid.
     * In a team assignment a student counts as submitted when their own submission or a submitted team
     * submission of a group they belong to (in the assignment's grouping) is submitted. assignment_teams gives
     * every submitter a group, so Moodle's "Default group" submission is not counted. A quiz counts a finished,
     * non-preview attempt.
     *
     * @param array<array{0: \cm_info, 1: \stdClass}> $items
     * @return array<int, array{expected: int, submitted: int}>
     */
    private static function submission_counts(array $items): array {
        $branches = [];
        foreach ($items as [$cm, $instance]) {
            $branches[] = $cm->modname === 'assign'
                ? self::assignment_counts_sql($cm, $instance)
                : self::quiz_counts_sql($cm);
        }
        $out = [];
        foreach (helper::union_all($branches) as $row) {
            $out[(int)$row->itemid] = ['expected' => (int)$row->expected, 'submitted' => (int)$row->submitted];
        }
        return $out;
    }

    private static function assignment_counts_sql(\cm_info $cm, \stdClass $instance): array {
        $cmid = (int)$cm->id;
        $assignid = (int)$instance->id;
        [$esql, $eparams] = get_enrolled_sql($cm->context, 'mod/assign:submit', 0, true);
        [$studentsql, $studentparams] = self::role_users_sql($cm->context->get_course_context(), 'student', (string)$cmid);
        $params = $eparams + $studentparams + ['submitted' . $cmid => ASSIGN_SUBMISSION_STATUS_SUBMITTED];
        $team = '';
        if (!empty($instance->teamsubmission)) {
            $grouping = '';
            if ((int)$instance->teamsubmissiongroupingid) {
                $groupingid = (int)$instance->teamsubmissiongroupingid;
                $grouping = "JOIN {groupings_groups} gg ON gg.groupid = ts.groupid AND gg.groupingid = {$groupingid}";
            }
            $team = "OR EXISTS (
                        SELECT 1
                          FROM {assign_submission} ts
                          JOIN {groups_members} gm ON gm.groupid = ts.groupid
                          $grouping
                         WHERE ts.assignment = {$assignid}
                           AND ts.userid = 0
                           AND ts.latest = 1
                           AND ts.status = :teamsubmitted{$cmid}
                           AND gm.userid = e.id
                    )";
            $params['teamsubmitted' . $cmid] = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        }

        $sql = "SELECT {$cmid} AS itemid, COUNT(1) AS expected,
                       COALESCE(SUM(CASE WHEN EXISTS (
                           SELECT 1
                             FROM {assign_submission} s
                            WHERE s.assignment = {$assignid}
                              AND s.userid = e.id
                              AND s.latest = 1
                              AND s.status = :submitted{$cmid}
                       ) $team THEN 1 ELSE 0 END), 0) AS submitted
                  FROM ($esql) e
                  JOIN ($studentsql) st ON st.id = e.id";
        return [$sql, $params];
    }

    private static function quiz_counts_sql(\cm_info $cm): array {
        $cmid = (int)$cm->id;
        $quizid = (int)$cm->instance;
        [$esql, $eparams] = get_enrolled_sql($cm->context, 'mod/quiz:attempt', 0, true);
        [$studentsql, $studentparams] = self::role_users_sql($cm->context->get_course_context(), 'student', (string)$cmid);
        $sql = "SELECT {$cmid} AS itemid, COUNT(1) AS expected,
                       COALESCE(SUM(CASE WHEN EXISTS (
                           SELECT 1
                             FROM {quiz_attempts} qa
                            WHERE qa.quiz = {$quizid}
                              AND qa.userid = e.id
                              AND qa.state = :finished{$cmid}
                              AND qa.preview = 0
                       ) THEN 1 ELSE 0 END), 0) AS submitted
                  FROM ($esql) e
                  JOIN ($studentsql) st ON st.id = e.id";
        return [$sql, $eparams + $studentparams + ['finished' . $cmid => \mod_quiz\quiz_attempt::FINISHED]];
    }

    /**
     * Assignments with marking workflow where a graded student's workflow state is not "released", as
     * [assignmentid => true]. Without marking workflow, or with nothing graded yet, nothing is held back.
     *
     * @param \assign[] $assigns
     */
    private static function unreleased_assignments(array $assigns): array {
        global $DB;

        $ids = [];
        foreach ($assigns as $assign) {
            if (!empty($assign->get_instance()->markingworkflow)) {
                $ids[] = (int)$assign->get_instance()->id;
            }
        }
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'workflowassign');
        $held = $DB->get_fieldset_sql(
            "SELECT DISTINCT g.assignment
               FROM {assign_grades} g
          LEFT JOIN {assign_user_flags} uf ON uf.assignment = g.assignment AND uf.userid = g.userid
              WHERE g.assignment $insql
                AND g.grade >= 0
                AND (uf.workflowstate IS NULL OR uf.workflowstate <> :released)",
            $params + ['released' => ASSIGN_MARKING_WORKFLOW_STATE_RELEASED]
        );
        return array_fill_keys(array_map('intval', $held), true);
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
    private static function hidden_grade_items(string $insql, array $inparams): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT id, itemmodule, iteminstance
               FROM {grade_items}
              WHERE courseid $insql
                AND itemtype = 'mod'
                AND itemmodule IN ('assign', 'quiz')
                AND itemnumber = 0
                AND (hidden = 1 OR hidden > :now)",
            $inparams + ['now' => time()]
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

    /** Discussions in each course's announcements (news) forum and when the newest one was posted, by course ID. */
    private static function announcements(string $insql, array $inparams): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT f.course, COUNT(d.id) AS discussions, MAX(p.created) AS lastposted
               FROM {forum} f
               JOIN {forum_discussions} d ON d.forum = f.id
               JOIN {forum_posts} p ON p.id = d.firstpost
              WHERE f.course $insql
                AND f.type = 'news'
           GROUP BY f.course",
            $inparams
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row->course] = ['count' => (int)$row->discussions, 'lastposted' => (int)$row->lastposted];
        }
        return $out;
    }

    /**
     * BigBlueButton sessions held per course ID: distinct (meeting, UTC day) pairs with at least one join. Moodle
     * 5.2 does not log meeting creation, so joins are the only record that a session took place; a room is reused
     * for every session, so the day separates them. Empty when the module is not installed.
     */
    private static function virtual_classes_held(string $insql, array $inparams): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('bigbluebuttonbn_logs')) {
            return [];
        }
        $rows = $DB->get_records_sql(
            "SELECT held.courseid, COUNT(1) AS held
               FROM (
                    SELECT DISTINCT l.courseid, l.meetingid, FLOOR(l.timecreated / 86400) AS day
                      FROM {bigbluebuttonbn_logs} l
                     WHERE l.courseid $insql
                       AND l.log = :join
               ) held
           GROUP BY held.courseid",
            $inparams + ['join' => \mod_bigbluebuttonbn\logger::EVENT_JOIN]
        );
        return array_map(fn(\stdClass $row) => (int)$row->held, $rows);
    }

    /**
     * Editing teachers and students with an active enrolment, with their last access to the course, by username:
     * [courseid]['editingteacher' | 'student'] => rows.
     */
    private static function people(array $courses): array {
        $branches = [];
        foreach ($courses as $course) {
            $courseid = (int)$course->id;
            $context = \context_course::instance($courseid);
            [$esql, $eparams] = get_enrolled_sql($context, '', 0, true);
            $branches[] = [
                "SELECT DISTINCT {$courseid} AS courseid, r.shortname, u.id, u.username,
                        COALESCE(ul.timeaccess, 0) AS lastaccess
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                   JOIN ($esql) en ON en.id = ra.userid
                   JOIN {user} u ON u.id = ra.userid
              LEFT JOIN {user_lastaccess} ul ON ul.userid = u.id AND ul.courseid = {$courseid}
                  WHERE ra.contextid = {$context->id}
                    AND r.shortname IN ('editingteacher', 'student')",
                $eparams,
            ];
        }

        $out = [];
        foreach (helper::union_all($branches, 'courseid, username') as $row) {
            $out[(int)$row->courseid][$row->shortname][] = $row->shortname === 'editingteacher'
                ? ['userid' => (int)$row->id, 'lastaccess' => (int)$row->lastaccess]
                : ['userid' => (int)$row->id, 'username' => $row->username, 'lastaccess' => (int)$row->lastaccess];
        }
        return $out;
    }
}
