<?php
namespace local_fivedays;

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
            throw new \moodle_exception('activitynotfound', 'local_fivedays');
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

    public static function invalid_question(string $message): \moodle_exception {
        return new \moodle_exception('invalidquestiondata', 'local_fivedays', '', $message);
    }

    /**
     * Decodes a JSON array parameter, throwing `invalidquestiondata` when it is not a list.
     */
    public static function decode_question_json(string $json, string $what): array {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !array_is_list($decoded)) {
            throw self::invalid_question("{$what} must be a JSON array.");
        }
        return $decoded;
    }

    /**
     * Validates the choices JSON and the `[[n]]` placeholders of a gapselect or ddwtos
     * question and returns the choices as [['answer' => string, 'group' => int, 'infinite' => bool], ...]
     * in the given order. `[[n]]` is the 1-based index of a choice in that order.
     * Mirrors the checks of Moodle's own edit forms for these types.
     */
    public static function parse_gap_choices(string $json, string $questiontext, bool $draggable): array {
        $maxgroup = $draggable ? 8 : 20;
        $rows = self::decode_question_json($json, 'choices');

        $choices = [];
        foreach ($rows as $index => $row) {
            $number = $index + 1;
            if (!is_array($row)) {
                throw self::invalid_question("Choice {$number} must be an object with answer and group.");
            }

            $answer = trim((string)($row['answer'] ?? ''));
            if ($answer === '') {
                throw self::invalid_question("Choice {$number} is empty.");
            }

            $group = $row['group'] ?? 1;
            if (!is_int($group) && !(is_string($group) && ctype_digit($group))) {
                throw self::invalid_question("The group of choice {$number} must be a whole number.");
            }
            $group = (int)$group;
            if ($group < 1 || $group > $maxgroup) {
                throw self::invalid_question("The group of choice {$number} must be between 1 and {$maxgroup}.");
            }

            $choices[] = [
                'answer' => $answer,
                'group' => $group,
                'infinite' => $draggable && !empty($row['infinite']),
            ];
        }

        preg_match_all('/\[\[([^\]]*?)\]\]/', $questiontext, $matches);
        if (empty($matches[1])) {
            throw self::invalid_question('The question text needs at least one gap, written as [[1]].');
        }

        $uses = [];
        foreach ($matches[1] as $slot) {
            if (!ctype_digit($slot) || (int)$slot < 1 || (int)$slot > count($choices)) {
                throw self::invalid_question("The gap [[{$slot}]] does not match any of the " . count($choices) . ' choices.');
            }
            $uses[(int)$slot] = ($uses[(int)$slot] ?? 0) + 1;
        }

        if ($draggable) {
            foreach ($uses as $slot => $count) {
                if ($count > 1 && !$choices[$slot - 1]['infinite']) {
                    throw self::invalid_question("Choice {$slot} is used in more than one gap, so it must be set to unlimited.");
                }
            }
        }

        return $choices;
    }

    /**
     * Writes what qtype_gapselect_base::save_question_options() writes for a gapselect or
     * ddwtos question: one `question_answers` row per choice (the group, and for ddwtos the
     * serialised group and "unlimited" flag, live in its `feedback` column) and the
     * `question_gapselect` / `question_ddwtos` options row. $choices come from parse_gap_choices().
     */
    public static function insert_gap_question_options(
        string $qtype,
        int $questionid,
        array $choices,
        int $shuffleanswers,
        string $correctfeedback,
        string $partiallycorrectfeedback,
        string $incorrectfeedback
    ): void {
        global $DB;

        foreach ($choices as $choice) {
            if ($qtype === 'ddwtos') {
                $feedback = new \stdClass();
                $feedback->draggroup = $choice['group'];
                $feedback->infinite = $choice['infinite'];
                $feedback = serialize($feedback);
            } else {
                $feedback = (string)$choice['group'];
            }

            $answer = new \stdClass();
            $answer->question = $questionid;
            $answer->answer = $choice['answer'];
            $answer->answerformat = FORMAT_HTML;
            $answer->fraction = 0;
            $answer->feedback = $feedback;
            $answer->feedbackformat = 0;
            $DB->insert_record('question_answers', $answer);
        }

        $options = new \stdClass();
        $options->questionid = $questionid;
        $options->shuffleanswers = $shuffleanswers ? 1 : 0;
        $options->correctfeedback = $correctfeedback;
        $options->correctfeedbackformat = FORMAT_HTML;
        $options->partiallycorrectfeedback = $partiallycorrectfeedback;
        $options->partiallycorrectfeedbackformat = FORMAT_HTML;
        $options->incorrectfeedback = $incorrectfeedback;
        $options->incorrectfeedbackformat = FORMAT_HTML;
        $options->shownumcorrect = 0;
        $DB->insert_record('question_' . $qtype, $options);
    }

    /**
     * Inserts a row in `question` for a question that is saved by its own create function
     * and returns its id. Callers add the version with save_question_version() and the
     * qtype options themselves.
     */
    public static function insert_question(
        string $qtype,
        int $categoryid,
        string $name,
        string $questiontext,
        float $defaultmark,
        string $generalfeedback,
        float $penalty = 0.3333333,
        int $length = 1
    ): int {
        global $DB, $USER;

        $question = new \stdClass();
        $question->category = $categoryid;
        $question->parent = 0;
        $question->name = $name;
        $question->questiontext = $questiontext;
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = $generalfeedback;
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = $defaultmark;
        $question->penalty = $penalty;
        $question->qtype = $qtype;
        $question->length = $length;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        return (int)$DB->insert_record('question', $question);
    }

    /**
     * Returns the qtype of the question a quiz slot currently shows, or null when unknown.
     */
    public static function get_slot_qtype(int $slotid): ?string {
        global $DB;

        $qtype = $DB->get_field_sql(
            "SELECT q.qtype
               FROM {question_references} qr
               JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
               JOIN {question} q ON q.id = qv.questionid
              WHERE qr.component = 'mod_quiz'
                AND qr.questionarea = 'slot'
                AND qr.itemid = ?
                AND qv.status = 'ready'
                AND qv.version = (
                    SELECT MAX(qv2.version)
                      FROM {question_versions} qv2
                     WHERE qv2.questionbankentryid = qr.questionbankentryid
                       AND qv2.status = 'ready'
                )",
            [$slotid],
            IGNORE_MULTIPLE
        );

        return $qtype === false ? null : $qtype;
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
            throw new \moodle_exception('sectionnotfound', 'local_fivedays', '', $sectionnum);
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

    /**
     * Also-users enrolled in the assignment's course, without the primary user or duplicates.
     * Users who are not enrolled are skipped silently: the app may list a group
     * member who has since left the class.
     */
    public static function enrolled_also_userids(\context $context, int $primaryuserid, array $alsouserids): array {
        $out = [];
        foreach ($alsouserids as $userid) {
            $userid = (int)$userid;
            if ($userid <= 0 || $userid === $primaryuserid || isset($out[$userid])) {
                continue;
            }
            if (is_enrolled($context, $userid)) {
                $out[$userid] = $userid;
            }
        }
        return array_values($out);
    }

    public static function list_assignment_grades(\assign $assignment): array {
        global $DB;

        $instance = $assignment->get_instance();
        if (!empty($instance->teamsubmission)) {
            // Team grading creates only the group submission (userid 0), so a
            // member's latest grade cannot be matched to a submission of their own.
            $rows = $DB->get_records_sql(
                "SELECT g.userid, g.grade, f.workflowstate
                   FROM {assign_grades} g
              LEFT JOIN {assign_user_flags} f
                     ON f.assignment = g.assignment
                    AND f.userid = g.userid
                  WHERE g.assignment = :assignid
                    AND g.attemptnumber = (
                        SELECT MAX(g2.attemptnumber)
                          FROM {assign_grades} g2
                         WHERE g2.assignment = g.assignment
                           AND g2.userid = g.userid
                    )",
                ['assignid' => $instance->id]
            );
            return self::format_grade_rows($rows);
        }

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
        return self::format_grade_rows($rows);
    }

    private static function format_grade_rows(array $rows): array {
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

    /**
     * The submission a student's comment thread hangs on. In team mode members
     * share the group submission, so they share one thread.
     */
    public static function comment_submission(\assign $assignment, int $userid, bool $create): \stdClass|false|null {
        if (!empty($assignment->get_instance()->teamsubmission)) {
            return $assignment->get_group_submission($userid, 0, $create);
        }
        return $assignment->get_user_submission($userid, $create);
    }

    public static function list_assignment_comments(\assign $assignment, int $userid): array {
        global $DB;

        $submission = self::comment_submission($assignment, $userid, false);
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

    public const QUIZ_HEADING_MAXLENGTH = 1333;

    /**
     * Loads a quiz for a structure call, or null when the quiz or its course module is gone.
     * The caller still has to validate the context and the capabilities.
     */
    public static function find_quiz_settings(int $quizid): ?\mod_quiz\quiz_settings {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id', IGNORE_MISSING);
        if (!$quiz || !get_coursemodule_from_instance('quiz', $quiz->id, 0, false, IGNORE_MISSING)) {
            return null;
        }
        return \mod_quiz\quiz_settings::create($quiz->id);
    }

    public static function require_quiz_structure_caps(\context $context): void {
        require_capability('local/fivedays:managequizquestions', $context);
        require_capability('mod/quiz:manage', $context);
    }

    /**
     * Moodle refuses structure changes once a student has attempted the quiz (previews do not count).
     * Returns the reason, or null when the structure can be changed.
     */
    public static function quiz_structure_lock_message(\mod_quiz\structure $structure): ?string {
        try {
            $structure->check_can_be_edited();
        } catch (\moodle_exception $e) {
            if ($e->errorcode !== 'cannoteditafterattempts') {
                throw $e;
            }
            return 'The quiz has attempts, so questions and sections can no longer be added, moved or deleted';
        }
        return null;
    }

    /** Re-reads slots and sections from the database; a loaded structure goes stale after heading changes. */
    public static function reload_quiz_structure(\mod_quiz\quiz_settings $quizobj): \mod_quiz\structure {
        return \mod_quiz\structure::create_for_quiz($quizobj);
    }

    /** Returns null when the heading is acceptable, otherwise the reason. */
    public static function quiz_heading_problem(string $heading, bool $allowempty): ?string {
        if ($heading === '' && !$allowempty) {
            return 'A section needs a heading';
        }
        if (\core_text::strlen($heading) > self::QUIZ_HEADING_MAXLENGTH) {
            return 'The heading is longer than ' . self::QUIZ_HEADING_MAXLENGTH . ' characters';
        }
        return null;
    }

    /** Sections of a quiz keyed by id, in slot order. */
    public static function quiz_sections_in_order(int $quizid): array {
        global $DB;

        return $DB->get_records('quiz_sections', ['quizid' => $quizid], 'firstslot');
    }

    /**
     * Slot numbers of every slot in each section, keyed by section id. A section runs from its
     * firstslot to the slot before the next section's firstslot.
     */
    public static function quiz_section_slot_numbers(int $quizid): array {
        global $DB;

        $sections = array_values(self::quiz_sections_in_order($quizid));
        $maxslot = (int)$DB->get_field_sql('SELECT MAX(slot) FROM {quiz_slots} WHERE quizid = ?', [$quizid]);
        $result = [];
        foreach ($sections as $i => $section) {
            $end = isset($sections[$i + 1]) ? (int)$sections[$i + 1]->firstslot - 1 : $maxslot;
            $result[$section->id] = $end >= (int)$section->firstslot ? range((int)$section->firstslot, $end) : [];
        }
        return $result;
    }
}
