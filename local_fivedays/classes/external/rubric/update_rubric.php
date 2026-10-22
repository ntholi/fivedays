<?php
namespace local_activity_utils\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class update_rubric extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
            'name' => new external_value(PARAM_TEXT, 'Rubric name', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_RAW, 'Rubric description', VALUE_DEFAULT, null),
            'criteria' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Criterion ID (0 for new criterion)', VALUE_DEFAULT, 0),
                    'description' => new external_value(PARAM_RAW, 'Criterion description'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0),
                    'levels' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Level ID (0 for new level)', VALUE_DEFAULT, 0),
                            'score' => new external_value(PARAM_FLOAT, 'Score for this level'),
                            'definition' => new external_value(PARAM_RAW, 'Level definition/description'),
                        ]),
                        'Rubric levels for this criterion'
                    ),
                ]),
                'Rubric criteria with levels (provide all criteria to replace)',
                VALUE_DEFAULT,
                []
            ),
            'options' => new external_single_structure([
                'sortlevelsasc' => new external_value(PARAM_INT, 'Sort levels ascending', VALUE_DEFAULT, null),
                'lockzeropoints' => new external_value(PARAM_INT, 'Lock zero points', VALUE_DEFAULT, null),
                'showdescriptionstudent' => new external_value(PARAM_INT, 'Show description to student', VALUE_DEFAULT, null),
                'showdescriptionteacher' => new external_value(PARAM_INT, 'Show description to teacher', VALUE_DEFAULT, null),
                'showscoreteacher' => new external_value(PARAM_INT, 'Show score to teacher', VALUE_DEFAULT, null),
                'showscorestudent' => new external_value(PARAM_INT, 'Show score to student', VALUE_DEFAULT, null),
                'showrangeteacher' => new external_value(PARAM_INT, 'Show range to teacher', VALUE_DEFAULT, null),
                'showrangestudent' => new external_value(PARAM_INT, 'Show range to student', VALUE_DEFAULT, null),
                'enableremarks' => new external_value(PARAM_INT, 'Enable remarks', VALUE_DEFAULT, null),
                'showremarksstudent' => new external_value(PARAM_INT, 'Show remarks to student', VALUE_DEFAULT, null),
                'alwaysshowdefinition' => new external_value(PARAM_INT, 'Always show definition', VALUE_DEFAULT, null),
            ], 'Rubric options', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(
        int $cmid,
        string $name = '',
        ?string $description = null,
        array $criteria = [],
        array $options = []
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/grade/grading/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'name' => $name,
            'description' => $description,
            'criteria' => $criteria,
            'options' => $options,
        ]);

        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/activity_utils:managerubric', $context);
        require_capability('moodle/grade:managegradingforms', $context);

        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');

        if ($gradingmanager->get_active_method() !== 'fivedays') {
            return [
                'definitionid' => 0,
                'success' => false,
                'message' => 'No FiveDays rubric is set for this assignment. Use create_rubric first.',
            ];
        }

        $controller = $gradingmanager->get_controller('fivedays');

        if (!$controller->is_form_defined()) {
            return [
                'definitionid' => 0,
                'success' => false,
                'message' => 'No FiveDays rubric definition exists. Use create_rubric first.',
            ];
        }

        $definition = $controller->get_definition();

        $rubricdata = new \stdClass();
        $rubricdata->name = !empty($params['name']) ? $params['name'] : $definition->name;
        $rubricdata->description_editor = [
            'text' => $params['description'] !== null ? $params['description'] : ($definition->description ?? ''),
            'format' => FORMAT_HTML,
        ];
        $rubricdata->status = \gradingform_controller::DEFINITION_STATUS_READY;

        if (!empty($params['criteria'])) {
            $fivedayscriteria = [];
            $sortorder = 1;
            $newcriterionindex = 1;

            foreach ($params['criteria'] as $criterion) {
                $criteriondata = [
                    'description' => $criterion['description'],
                    'descriptionformat' => FORMAT_HTML,
                    'sortorder' => array_key_exists('sortorder', $criterion)
                        ? (int) $criterion['sortorder']
                        : $sortorder,
                    'levels' => [],
                ];

                if (!empty($criterion['id'])) {
                    $criterionkey = $criterion['id'];
                } else {
                    $criterionkey = 'NEWID' . $newcriterionindex;
                    $newcriterionindex++;
                }

                $newlevelindex = 1;
                foreach ($criterion['levels'] as $level) {
                    $leveldata = [
                        'score' => $level['score'],
                        'definition' => $level['definition'],
                        'definitionformat' => FORMAT_HTML,
                    ];

                    if (!empty($level['id'])) {
                        $levelkey = $level['id'];
                    } else {
                        $levelkey = 'NEWID' . $newlevelindex;
                        $newlevelindex++;
                    }

                    $criteriondata['levels'][$levelkey] = $leveldata;
                }

                $fivedayscriteria[$criterionkey] = $criteriondata;
                $sortorder++;
            }

            $rubricdata->fivedays['criteria'] = $fivedayscriteria;
        } else {
            $existingcriteria = $DB->get_records('gradingform_fivedays_criteria', ['definitionid' => $definition->id], 'sortorder ASC');
            $fivedayscriteria = [];

            foreach ($existingcriteria as $criterion) {
                $levels = $DB->get_records('gradingform_fivedays_levels', ['criterionid' => $criterion->id], 'score ASC');
                $levelsdata = [];

                foreach ($levels as $level) {
                    $levelsdata[$level->id] = [
                        'score' => $level->score,
                        'definition' => $level->definition,
                        'definitionformat' => FORMAT_HTML,
                    ];
                }

                $fivedayscriteria[$criterion->id] = [
                    'description' => $criterion->description,
                    'descriptionformat' => FORMAT_HTML,
                    'sortorder' => $criterion->sortorder,
                    'levels' => $levelsdata,
                ];
            }

            $rubricdata->fivedays['criteria'] = $fivedayscriteria;
        }

        $existingoptions = json_decode($definition->options ?? '{}', true) ?: [];
        $newoptions = [];

        foreach ($params['options'] as $key => $value) {
            if ($value !== null) {
                $newoptions[$key] = $value;
            }
        }

        $rubricdata->fivedays['options'] = array_merge($existingoptions, $newoptions);

        // Same default as Moodle's own editor: any change to a used rubric marks its gradings for regrading.
        if ($controller->has_active_instances() && $controller->update_or_check_fivedays($rubricdata) > 0) {
            $rubricdata->fivedays['regrade'] = 1;
        }

        $controller->update_definition($rubricdata);

        return [
            'definitionid' => (int)$definition->id,
            'success' => true,
            'message' => 'FiveDays rubric updated successfully',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'definitionid' => new external_value(PARAM_INT, 'Rubric definition ID'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
