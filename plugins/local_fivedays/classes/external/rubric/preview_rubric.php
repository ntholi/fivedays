<?php
namespace local_fivedays\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class preview_rubric extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecmid' => new external_value(PARAM_INT, 'Course module ID of the assignment that owns the rubric'),
            'targetcmid' => new external_value(PARAM_INT, 'Course module ID of the assignment the rubric will be imported into'),
        ]);
    }

    public static function execute(int $sourcecmid, int $targetcmid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/grade/grading/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'sourcecmid' => $sourcecmid,
            'targetcmid' => $targetcmid,
        ]);

        $targetcm = get_coursemodule_from_id('assign', $params['targetcmid'], 0, false, MUST_EXIST);
        $targetcontext = \context_module::instance($targetcm->id);
        self::validate_context($targetcontext);
        // Same rule as search_rubrics: the caller's right to build a rubric on the target covers reading any source.
        require_capability('local/fivedays:managerubric', $targetcontext);

        $sourcecm = get_coursemodule_from_id('assign', $params['sourcecmid'], 0, false, MUST_EXIST);
        $sourcecontext = \context_module::instance($sourcecm->id);
        $assignmentname = $DB->get_field('assign', 'name', ['id' => $sourcecm->instance], MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $sourcecm->course], 'fullname, shortname', MUST_EXIST);

        $empty = [
            'name' => '',
            'assignmentname' => $assignmentname,
            'coursename' => $course->fullname,
            'courseshortname' => $course->shortname,
            'criteria' => [],
            'maxscore' => 0,
            'success' => false,
            'message' => 'Source assignment has no FiveDays rubric',
        ];

        $gradingmanager = get_grading_manager($sourcecontext, 'mod_assign', 'submissions');
        if ($gradingmanager->get_active_method() !== 'fivedays') {
            return $empty;
        }
        $controller = $gradingmanager->get_controller('fivedays');
        if (!$controller->is_form_defined()) {
            return $empty;
        }

        $definition = $controller->get_definition();
        $criteria = $DB->get_records('gradingform_fivedays_criteria', ['definitionid' => $definition->id], 'sortorder ASC');

        $criteriaresult = [];
        $maxscore = 0;
        foreach ($criteria as $criterion) {
            $levels = $DB->get_records('gradingform_fivedays_levels', ['criterionid' => $criterion->id], 'score ASC');
            $levelsresult = [];
            $criterionmax = 0;
            foreach ($levels as $level) {
                $levelsresult[] = [
                    'score' => (float)$level->score,
                    'definition' => $level->definition,
                ];
                $criterionmax = max($criterionmax, (float)$level->score);
            }
            $criteriaresult[] = [
                'description' => $criterion->description,
                'sortorder' => (int)$criterion->sortorder,
                'levels' => $levelsresult,
            ];
            $maxscore += $criterionmax;
        }

        return [
            'name' => $definition->name,
            'assignmentname' => $assignmentname,
            'coursename' => $course->fullname,
            'courseshortname' => $course->shortname,
            'criteria' => $criteriaresult,
            'maxscore' => (float)$maxscore,
            'success' => true,
            'message' => 'FiveDays rubric retrieved successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Rubric name'),
            'assignmentname' => new external_value(PARAM_TEXT, 'Source assignment name'),
            'coursename' => new external_value(PARAM_TEXT, 'Source course full name'),
            'courseshortname' => new external_value(PARAM_TEXT, 'Source course short name'),
            'criteria' => new external_multiple_structure(
                new external_single_structure([
                    'description' => new external_value(PARAM_RAW, 'Criterion description'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                    'levels' => new external_multiple_structure(
                        new external_single_structure([
                            'score' => new external_value(PARAM_FLOAT, 'Score'),
                            'definition' => new external_value(PARAM_RAW, 'Level definition'),
                        ])
                    ),
                ])
            ),
            'maxscore' => new external_value(PARAM_FLOAT, 'Sum of each criterion\'s highest level score'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
