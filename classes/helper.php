<?php
namespace local_activity_utils;

class helper {

    /**
     * Returns [$instance, $cm] for an activity, or throws `activitynotfound` when
     * it was deleted. Moodle's own missing-record error does not say which table
     * was missing, so callers could not tell a deleted activity from other errors.
     */
    public static function require_activity(string $modname, int $instanceid, string $fields = '*'): array {
        global $DB;

        $instance = $DB->get_record($modname, ['id' => $instanceid], $fields, IGNORE_MISSING);
        $cm = $instance ? get_coursemodule_from_instance($modname, $instance->id, 0, false, IGNORE_MISSING) : false;
        if (!$instance || !$cm) {
            throw new \moodle_exception('activitynotfound', 'local_activity_utils');
        }
        return [$instance, $cm];
    }

    public static function require_question_entry(int $questionbankentryid): void {
        global $DB, $USER;

        if ($questionbankentryid <= 0) {
            return;
        }

        $entry = $DB->get_record('question_bank_entries', ['id' => $questionbankentryid], '*', MUST_EXIST);
        $category = $DB->get_record('question_categories', ['id' => $entry->questioncategoryid], '*', MUST_EXIST);
        $context = \context::instance_by_id($category->contextid);

        if ((int)$entry->ownerid !== (int)$USER->id) {
            require_capability('moodle/question:editall', $context);
        } else if (!has_capability('moodle/question:editmine', $context)) {
            require_capability('moodle/question:editall', $context);
        }
    }

    public static function save_question_version(
        int $categoryid,
        int $questionid,
        string $idnumber,
        int $questionbankentryid
    ): int {
        global $DB, $USER;

        if ($questionbankentryid <= 0) {
            $entry = new \stdClass();
            $entry->questioncategoryid = $categoryid;
            $entry->idnumber = $idnumber !== '' ? $idnumber : null;
            $entry->ownerid = $USER->id;
            $questionbankentryid = $DB->insert_record('question_bank_entries', $entry);
        }

        $latest = $DB->get_record_sql(
            "SELECT questionid, version
               FROM {question_versions}
              WHERE questionbankentryid = ?
           ORDER BY version DESC",
            [$questionbankentryid],
            IGNORE_MULTIPLE
        );

        $version = new \stdClass();
        $version->questionbankentryid = $questionbankentryid;
        $version->questionid = $questionid;
        $version->version = \core_question\versions::get_next_version((int)$questionbankentryid);
        $version->status = 'ready';
        $DB->insert_record('question_versions', $version);

        if ($latest) {
            \question_bank::notify_question_edited((int)$latest->questionid);
        }

        return (int)$questionbankentryid;
    }


    public static function get_section_by_number(int $courseid, int $sectionnum): ?\stdClass {
        global $DB;
        
        $section = $DB->get_record('course_sections', [
            'course' => $courseid,
            'section' => $sectionnum
        ]);
        
        return $section;
    }

    public static function resolve_section_id(int $courseid, int $sectionnum): ?int {
        $section = self::get_section_by_number($courseid, $sectionnum);
        return $section ? (int)$section->id : null;
    }

    public static function create_module(
        \stdClass $course,
        string $modulename,
        int $sectionnum,
        string $name,
        int $visible,
        array $properties = []
    ): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = $modulename;
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => $modulename], MUST_EXIST);
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $sectionnum;
        $moduleinfo->name = $name;
        $moduleinfo->visible = $visible;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->completion = 0;
        $moduleinfo->completionview = 0;
        $moduleinfo->completionexpected = 0;
        $moduleinfo->completionpassgrade = 0;
        $moduleinfo->completiongradeitemnumber = null;
        $moduleinfo->showdescription = 0;
        $moduleinfo->availability = null;
        $moduleinfo->downloadcontent = 1;

        foreach ($properties as $property => $value) {
            $moduleinfo->$property = $value;
        }

        return add_moduleinfo($moduleinfo, $course);
    }

    public static function add_module_to_section(int $courseid, int $sectionnum, int $cmid, int $coursemodule_visible): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $section = self::get_section_by_number($courseid, $sectionnum);
        if (!$section) {
            throw new \moodle_exception('sectionnotfound', 'local_activity_utils', '', $sectionnum);
        }

        course_add_cm_to_section($courseid, $cmid, $sectionnum);

        if (!empty($section->component) && $section->component === 'mod_subsection') {
            self::inherit_subsection_visibility($cmid, $section, $coursemodule_visible);
        }
    }

    private static function inherit_subsection_visibility(int $cmid, \stdClass $delegated_section, int $requested_visibility): void {
        global $DB;

        $subsection_cm = $DB->get_record('course_modules', [
            'instance' => (int)$delegated_section->itemid,
            'module' => $DB->get_field('modules', 'id', ['name' => 'subsection'])
        ]);

        if ($subsection_cm) {
            $final_visibility = $requested_visibility && $subsection_cm->visible ? 1 : 0;
            set_coursemodule_visible($cmid, $final_visibility, 1, false);
        }
    }

    public static function get_assign(int $assignmentid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assign = $DB->get_record('assign', ['id' => $assignmentid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assignment = new \assign($context, $cm, $course);

        return [$assignment, $cm, $course, $context];
    }

    public static function get_assign_for_comments(int $assignmentid): array {
        return self::get_assign($assignmentid);
    }

    public static function assignment_release_state(?float $grade, ?string $workflowstate): string {
        if ($grade === null || $grade < 0) {
            return 'notgraded';
        }
        if ($workflowstate === ASSIGN_MARKING_WORKFLOW_STATE_RELEASED) {
            return 'released';
        }
        if ($workflowstate === ASSIGN_MARKING_WORKFLOW_STATE_INMARKING) {
            return 'beingedited';
        }
        return 'notreleased';
    }

    public static function workflow_after_grade_change(?string $current): string {
        if (
            $current === ASSIGN_MARKING_WORKFLOW_STATE_RELEASED
            || $current === ASSIGN_MARKING_WORKFLOW_STATE_INMARKING
        ) {
            return ASSIGN_MARKING_WORKFLOW_STATE_INMARKING;
        }
        return ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE;
    }

    public static function is_unpublished_release_state(string $state): bool {
        return $state === 'notreleased' || $state === 'beingedited';
    }

    public static function format_assign_grade(\assign $assignment, float $grade): float {
        $item = $assignment->get_grade_item();
        $decimals = $item ? $item->get_decimals() : 2;
        return round($grade, $decimals);
    }

    public static function ensure_marking_workflow(\assign $assignment): \assign {
        global $DB;

        $instance = $assignment->get_instance();
        if ((int)$instance->markingworkflow === 1) {
            return $assignment;
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('assign', 'markingworkflow', 1, ['id' => $instance->id]);
        [$fresh] = self::get_assign((int)$instance->id);
        // Grades students could already see stay visible once the workflow is on.
        foreach (self::list_assignment_grades($fresh) as $row) {
            if ($row['releasestate'] !== 'notreleased') {
                continue;
            }
            $flags = $fresh->get_user_flags($row['userid'], true);
            if (!empty($flags->workflowstate)) {
                continue;
            }
            $flags->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
            $fresh->update_user_flags($flags);
        }
        $transaction->allow_commit();
        return $fresh;
    }

    public static function save_assignment_grade(\assign $assignment, int $userid, float $gradevalue): array {
        global $DB, $USER;

        $assignment = self::ensure_marking_workflow($assignment);
        $gradevalue = self::format_assign_grade($assignment, $gradevalue);

        $transaction = $DB->start_delegated_transaction();
        $flags = $assignment->get_user_flags($userid, true);
        $current = !empty($flags->workflowstate) ? (string)$flags->workflowstate : null;
        $next = self::workflow_after_grade_change($current);
        $flags->workflowstate = $next;
        $assignment->update_user_flags($flags);

        $grade = $assignment->get_user_grade($userid, true);
        $grade->grade = $gradevalue;
        $grade->grader = $USER->id;
        if (!$assignment->update_grade($grade)) {
            throw new \moodle_exception('errorgradechangessaveddetail', 'mod_assign');
        }
        $transaction->allow_commit();

        return [
            'grade' => $gradevalue,
            'releasestate' => self::assignment_release_state($gradevalue, $next),
        ];
    }

    public static function list_assignment_grades(\assign $assignment): array {
        global $DB;

        $instance = $assignment->get_instance();
        $rows = $DB->get_records_sql(
            "SELECT g.userid, g.grade, f.workflowstate
               FROM {assign_grades} g
               JOIN {assign_submission} s
                 ON s.assignment = g.assignment
                AND s.userid = g.userid
                AND s.latest = 1
                AND s.attemptnumber = g.attemptnumber
          LEFT JOIN {assign_user_flags} f
                 ON f.assignment = g.assignment
                AND f.userid = g.userid
              WHERE g.assignment = :assignid",
            ['assignid' => $instance->id]
        );

        $out = [];
        foreach ($rows as $row) {
            $grade = $row->grade === null || $row->grade === '' ? null : (float)$row->grade;
            $state = self::assignment_release_state(
                $grade,
                !empty($row->workflowstate) ? (string)$row->workflowstate : null
            );
            $out[] = [
                'userid' => (int)$row->userid,
                'grade' => $grade !== null && $grade >= 0 ? $grade : -1,
                'releasestate' => $state,
            ];
        }
        return $out;
    }

    public static function save_feedback_comment(\assign $assignment, int $userid, string $comment): void {
        global $DB;

        $plugin = $assignment->get_feedback_plugin_by_type('comments');
        if (!$plugin) {
            throw new \invalid_parameter_exception('Feedback comments are not available');
        }
        if (!$plugin->is_enabled()) {
            $plugin->enable();
        }

        $grade = $assignment->get_user_grade($userid, false);
        if (!$grade) {
            throw new \invalid_parameter_exception('Student has no grade');
        }

        $existing = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
        if ($existing) {
            $existing->commenttext = $comment;
            $existing->commentformat = FORMAT_HTML;
            $DB->update_record('assignfeedback_comments', $existing);
            return;
        }

        $record = new \stdClass();
        $record->commenttext = $comment;
        $record->commentformat = FORMAT_HTML;
        $record->grade = $grade->id;
        $record->assignment = $assignment->get_instance()->id;
        $DB->insert_record('assignfeedback_comments', $record);
    }

    public static function release_unpublished_grades(
        \assign $assignment,
        array $userids,
        array $commentsbyuser
    ): array {
        $assignment = self::ensure_marking_workflow($assignment);
        $wanted = [];
        foreach ($userids as $userid) {
            $wanted[(int)$userid] = true;
        }

        $toRelease = [];
        foreach (self::list_assignment_grades($assignment) as $row) {
            if (empty($wanted[$row['userid']])) {
                continue;
            }
            if (!self::is_unpublished_release_state($row['releasestate'])) {
                continue;
            }
            $toRelease[] = $row;
        }

        foreach ($toRelease as $row) {
            $comment = trim((string)($commentsbyuser[$row['userid']] ?? ''));
            if ($comment !== '') {
                self::save_feedback_comment($assignment, $row['userid'], $comment);
            }
        }

        $released = 0;
        foreach ($toRelease as $row) {
            $flags = $assignment->get_user_flags($row['userid'], true);
            $flags->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
            $assignment->update_user_flags($flags);

            $grade = $assignment->get_user_grade($row['userid'], false);
            if ($grade) {
                $assignment->update_grade($grade);
                $assignment->notify_grade_modified($grade, true);
            }
            $released++;
        }

        return ['released' => $released];
    }

    public static function enable_assignment_comments(\assign $assignment): void {
        $plugin = $assignment->get_submission_plugin_by_type('comments');
        if ($plugin && !$plugin->is_enabled()) {
            $plugin->enable();
        }
    }

    public static function assignment_comment_manager(
        \assign $assignment,
        \stdClass $cm,
        \stdClass $course,
        int $submissionid
    ): \core_comment\manager {
        $options = new \stdClass();
        $options->context = $assignment->get_context();
        $options->component = 'assignsubmission_comments';
        $options->area = 'submission_comments';
        $options->itemid = $submissionid;
        $options->course = $course;
        $options->cm = $cm;
        return new \core_comment\manager($options);
    }

    public static function format_assignment_comment(\stdClass $comment, \stdClass $user): array {
        return [
            'id' => (int)$comment->id,
            'content' => (string)$comment->content,
            'userid' => (int)$comment->userid,
            'author' => fullname($user),
            'timecreated' => (int)$comment->timecreated,
        ];
    }

    public static function list_assignment_comments(\assign $assignment, int $userid): array {
        global $DB;

        $submission = $assignment->get_user_submission($userid, false);
        if (!$submission) {
            return [];
        }

        $comments = $DB->get_records_select(
            'comments',
            'contextid = :contextid AND component = :component AND commentarea = :commentarea AND itemid = :itemid',
            [
                'contextid' => $assignment->get_context()->id,
                'component' => 'assignsubmission_comments',
                'commentarea' => 'submission_comments',
                'itemid' => $submission->id,
            ],
            'timecreated ASC, id ASC'
        );
        if (!$comments) {
            return [];
        }

        $userids = array_unique(array_map(static fn($comment) => (int)$comment->userid, $comments));
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $users = $DB->get_records_select('user', "id $insql", $inparams);

        $out = [];
        foreach ($comments as $comment) {
            $user = $users[$comment->userid] ?? null;
            if (!$user) {
                continue;
            }
            $out[] = self::format_assignment_comment($comment, $user);
        }
        return $out;
    }

    public static function require_assignment_comment(int $commentid): array {
        global $DB;

        $comment = $DB->get_record('comments', ['id' => $commentid], '*', MUST_EXIST);
        if (
            $comment->component !== 'assignsubmission_comments'
            || $comment->commentarea !== 'submission_comments'
        ) {
            throw new \moodle_exception('invalidcommentarea');
        }

        $submission = $DB->get_record('assign_submission', ['id' => $comment->itemid], '*', MUST_EXIST);
        [$assignment, $cm, $course, $context] = self::get_assign_for_comments((int)$submission->assignment);

        return [$comment, $submission, $assignment, $cm, $course, $context];
    }
}
