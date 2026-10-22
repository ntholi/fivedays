<?php
namespace local_activity_utils\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class get_rubric extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/grade/grading/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/activity_utils:managerubric', $context);

        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
        $activemethod = $gradingmanager->get_active_method();

        if (empty($activemethod)) {
            return [
                'definitionid' => 0,
                'name' => '',
                'description' => '',
                'status' => 0,
                'criteria' => [],
                'options' => self::get_default_options(),
                'maxscore' => 0,
                'gradingmethod' => 'simple',
                'success' => true,
                'message' => 'Assignment uses simple direct grading (no rubric)',
            ];
        }

        if ($activemethod !== 'fivedays') {
            return [
                'definitionid' => 0,
                'name' => '',
                'description' => '',
                'status' => 0,
                'criteria' => [],
                'options' => self::get_default_options(),
                'maxscore' => 0,
                'gradingmethod' => $activemethod,
                'success' => false,
                'message' => 'Assignment uses ' . $activemethod . ' grading, not FiveDays rubric',
            ];
        }

        $controller = $gradingmanager->get_controller('fivedays');

        if (!$controller->is_form_defined()) {
            return [
                'definitionid' => 0,
                'name' => '',
                'description' => '',
                'status' => 0,
                'criteria' => [],
                'options' => self::get_default_options(),
                'maxscore' => 0,
                'gradingmethod' => 'fivedays',
                'success' => false,
                'message' => 'FiveDays is set as grading method but no rubric is defined',
            ];
        }

        $definition = $controller->get_definition();
        $criteria = $DB->get_records('gradingform_fivedays_criteria', ['definitionid' => $definition->id], 'sortorder ASC');

        $criteriaresult = [];
        $maxscore = 0;

        foreach ($criteria as $criterion) {
            $levels = $DB->get_records('gradingform_fivedays_levels', ['criterionid' => $criterion->id], 'score ASC');

            $levelsresult = [];
            $criterionmaxscore = 0;
            foreach ($levels as $level) {
                $levelsresult[] = [
                    'id' => (int)$level->id,
                    'score' => (float)$level->score,
                    'definition' => $level->definition,
                ];
                if ($level->score > $criterionmaxscore) {
                    $criterionmaxscore = $level->score;
                }
            }

            $criteriaresult[] = [
                'id' => (int)$criterion->id,
                'description' => $criterion->description,
                'sortorder' => (int)$criterion->sortorder,
                'levels' => $levelsresult,
            ];

            $maxscore += $criterionmaxscore;
        }

        $options = json_decode($definition->options ?? '{}', true) ?: [];
        $optionsresult = array_merge(self::get_default_options(), $options);

        return [
            'definitionid' => (int)$definition->id,
            'name' => $definition->name,
            'description' => $definition->description ?? '',
            'status' => (int)$definition->status,
            'criteria' => $criteriaresult,
            'options' => $optionsresult,
            'maxscore' => (float)$maxscore,
            'gradingmethod' => 'fivedays',
            'success' => true,
            'message' => 'FiveDays rubric retrieved successfully',
        ];
    }

    private static function get_default_options(): array {
        return [
            'sortlevelsasc' => 1,
            'lockzeropoints' => 1,
            'showdescriptionstudent' => 1,
            'showdescriptionteacher' => 1,
            'showscoreteacher' => 1,
            'showscorestudent' => 1,
            'showrangeteacher' => 1,
            'showrangestudent' => 1,
            'enableremarks' => 1,
            'showremarksstudent' => 1,
            'alwaysshowdefinition' => 1,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'definitionid' => new external_value(PARAM_INT, 'Rubric definition ID'),
            'name' => new external_value(PARAM_TEXT, 'Rubric name'),
            'description' => new external_value(PARAM_RAW, 'Rubric description'),
            'status' => new external_value(PARAM_INT, 'Status (10=draft, 20=ready)'),
            'criteria' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Criterion ID'),
                    'description' => new external_value(PARAM_RAW, 'Criterion description'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                    'levels' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Level ID'),
                            'score' => new external_value(PARAM_FLOAT, 'Score'),
                            'definition' => new external_value(PARAM_RAW, 'Level definition'),
                        ])
                    ),
                ])
            ),
            'options' => new external_single_structure([
                'sortlevelsasc' => new external_value(PARAM_INT, 'Sort levels ascending'),
                'lockzeropoints' => new external_value(PARAM_INT, 'Lock zero points'),
                'showdescriptionstudent' => new external_value(PARAM_INT, 'Show description to student'),
                'showdescriptionteacher' => new external_value(PARAM_INT, 'Show description to teacher'),
                'showscoreteacher' => new external_value(PARAM_INT, 'Show score to teacher'),
                'showscorestudent' => new external_value(PARAM_INT, 'Show score to student'),
                'showrangeteacher' => new external_value(PARAM_INT, 'Show range to teacher'),
                'showrangestudent' => new external_value(PARAM_INT, 'Show range to student'),
                'enableremarks' => new external_value(PARAM_INT, 'Enable remarks'),
                'showremarksstudent' => new external_value(PARAM_INT, 'Show remarks to student'),
                'alwaysshowdefinition' => new external_value(PARAM_INT, 'Always show definition'),
            ]),
            'maxscore' => new external_value(PARAM_FLOAT, 'Maximum possible score'),
            'gradingmethod' => new external_value(PARAM_TEXT, 'Current grading method (simple, fivedays, or other)'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
