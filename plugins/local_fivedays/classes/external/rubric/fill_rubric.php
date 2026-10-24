<?php
namespace local_fivedays\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class fill_rubric extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
            'userid' => new external_value(PARAM_INT, 'User ID of the student being graded'),
            'fillings' => new external_multiple_structure(
                new external_single_structure([
                    'criterionid' => new external_value(PARAM_INT, 'Criterion ID'),
                    'levelid' => new external_value(PARAM_INT, 'Level ID to select for this criterion', VALUE_DEFAULT, 0),
                    'score' => new external_value(PARAM_FLOAT, 'Custom score (optional, overrides levelid)', VALUE_DEFAULT, null),
                    'remark' => new external_value(PARAM_RAW, 'Remark/feedback for this criterion', VALUE_DEFAULT, ''),
                ]),
                'Rubric fillings (selected levels/scores and remarks for each criterion)'
            ),
            'overallremark' => new external_value(PARAM_RAW, 'Overall feedback/remark for the grading', VALUE_DEFAULT, ''),
            'alsouserids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Other student user ID'),
                'Other students who get the same fillings and grade; those not enrolled are skipped',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute(
        int $cmid,
        int $userid,
        array $fillings,
        string $overallremark = '',
        array $alsouserids = []
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/grade/grading/lib.php');
        require_once($CFG->dirroot . '/grade/grading/form/lib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->dirroot . '/lib/gradelib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'fillings' => $fillings,
            'overallremark' => $overallremark,
            'alsouserids' => $alsouserids,
        ]);

        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/fivedays:graderubric', $context);
        require_capability('mod/assign:grade', $context);

        $user = $DB->get_record('user', ['id' => $params['userid']], '*', MUST_EXIST);
        if (!is_enrolled($context, $user)) {
            return self::failure('User is not enrolled in this course');
        }

        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
        $activemethod = $gradingmanager->get_active_method();

        if ($activemethod !== 'fivedays') {
            return self::failure('Assignment does not use FiveDays rubric grading');
        }

        $controller = $gradingmanager->get_controller('fivedays');

        if (!$controller->is_form_defined()) {
            return self::failure('FiveDays rubric is not defined for this assignment');
        }

        $assignment = new \assign($context, $cm, $cm->course);

        $criteria = $controller->get_definition()->fivedays_criteria;

        $posted = [];
        foreach ($params['fillings'] as $filling) {
            $criterionid = $filling['criterionid'];
            if (!isset($criteria[$criterionid])) {
                return self::failure('Invalid criterion ID: ' . $criterionid);
            }
            $levels = $criteria[$criterionid]['levels'];
            $levelid = !empty($filling['levelid']) ? (int)$filling['levelid'] : null;
            if ($levelid !== null && !isset($levels[$levelid])) {
                return self::failure('Invalid level ID: ' . $levelid . ' for criterion: ' . $criterionid);
            }

            $score = $filling['score'] ?? null;
            if ($score === null && $levelid !== null) {
                $score = (float)$levels[$levelid]['score'];
            }
            if ($score === null) {
                return self::failure('Choose a level or score for criterion: ' . $criterionid);
            }
            $maxscore = $levels ? max(array_column($levels, 'score')) : 0;
            if ($score < 0 || $score > $maxscore) {
                return self::failure('Score for criterion ' . $criterionid . ' must be between 0 and ' . $maxscore);
            }

            $posted[$criterionid] = [
                'levelid' => $levelid,
                'score' => $score,
                'remark' => $filling['remark'] ?? '',
            ];
        }

        $also = \local_fivedays\helper::enrolled_also_userids($context, $params['userid'], $params['alsouserids']);

        $transaction = $DB->start_delegated_transaction();
        $assignment = \local_fivedays\helper::ensure_marking_workflow($assignment);
        [$instance, $saved] = self::grade_user($assignment, $controller, $posted, $params['userid'], $transaction);
        foreach ($also as $alsouserid) {
            self::grade_user($assignment, $controller, $posted, $alsouserid, $transaction);
        }
        $transaction->allow_commit();

        return [
            'instanceid' => (int)$instance->get_id(),
            'grade' => $saved['grade'],
            'releasestate' => $saved['releasestate'],
            'applieduserids' => array_merge([$params['userid']], $also),
            'success' => true,
            'message' => 'FiveDays rubric filled and grade saved successfully',
        ];
    }

    /**
     * Grades one student through their own grading instance, so each group member
     * keeps a rubric filling of their own.
     *
     * @return array [grading instance, saved grade]
     */
    private static function grade_user(
        \assign $assignment,
        \gradingform_controller $controller,
        array $posted,
        int $userid,
        \moodle_transaction $transaction
    ): array {
        global $USER;

        if (empty($assignment->get_instance()->teamsubmission)) {
            $assignment->get_user_submission($userid, true);
        }
        $grade = $assignment->get_user_grade($userid, true);

        // Instance id 0 makes a new instance for this rater, copied from the current active one, like core assign grading.
        $instance = $controller->get_or_create_instance(0, $USER->id, $grade->id);
        // Clear posted criteria first so a custom score does not keep the copied level.
        $instance->clear_attempt(['criteria' => $posted]);
        $data = ['criteria' => []];
        foreach ($instance->get_fivedays_filling(true)['criteria'] as $criterionid => $record) {
            $data['criteria'][$criterionid] = [
                'levelid' => $record['levelid'],
                'score' => $record['score'],
                'remark' => $record['remark'],
            ];
        }
        foreach ($posted as $criterionid => $filling) {
            $data['criteria'][$criterionid] = $filling;
        }

        if (!$instance->validate_grading_element($data)) {
            $transaction->rollback(new \invalid_parameter_exception('Rubric fillings do not match this rubric'));
        }

        $grademax = $assignment->get_instance()->grade;
        $controller->set_grade_range(make_grades_menu($grademax), $grademax > 0);
        $gradevalue = $instance->submit_and_get_grade($data, $grade->id);
        if ($gradevalue < 0) {
            $transaction->rollback(new \invalid_parameter_exception('This rubric has no scores to calculate a grade'));
        }

        $saved = \local_fivedays\helper::save_assignment_grade($assignment, $userid, (float)$gradevalue);
        return [$instance, $saved];
    }

    private static function failure(string $message): array {
        return [
            'instanceid' => 0,
            'grade' => 0,
            'releasestate' => 'notgraded',
            'applieduserids' => [],
            'success' => false,
            'message' => $message,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'instanceid' => new external_value(PARAM_INT, 'Grading instance ID'),
            'grade' => new external_value(PARAM_FLOAT, 'Calculated grade from rubric'),
            'releasestate' => new external_value(PARAM_ALPHA, 'notgraded, notreleased, released, or beingedited'),
            'applieduserids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'The primary user and every also-user the rubric was applied to'
            ),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
