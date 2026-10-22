<?php
namespace local_activity_utils\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class create_rubric extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
            'name' => new external_value(PARAM_TEXT, 'Rubric name'),
            'description' => new external_value(PARAM_RAW, 'Rubric description', VALUE_DEFAULT, ''),
            'criteria' => new external_multiple_structure(
                new external_single_structure([
                    'description' => new external_value(PARAM_RAW, 'Criterion description'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order', VALUE_DEFAULT, 0),
                    'levels' => new external_multiple_structure(
                        new external_single_structure([
                            'score' => new external_value(PARAM_FLOAT, 'Score for this level'),
                            'definition' => new external_value(PARAM_RAW, 'Level definition/description'),
                        ]),
                        'Rubric levels for this criterion'
                    ),
                ]),
                'Rubric criteria with levels'
            ),
            'options' => new external_single_structure([
                'sortlevelsasc' => new external_value(PARAM_INT, 'Sort levels ascending (0 or 1)', VALUE_DEFAULT, 1),
                'lockzeropoints' => new external_value(PARAM_INT, 'Lock zero points (0 or 1)', VALUE_DEFAULT, 1),
                'showdescriptionstudent' => new external_value(PARAM_INT, 'Show description to student (0 or 1)', VALUE_DEFAULT, 1),
                'showdescriptionteacher' => new external_value(PARAM_INT, 'Show description to teacher (0 or 1)', VALUE_DEFAULT, 1),
                'showscoreteacher' => new external_value(PARAM_INT, 'Show score to teacher (0 or 1)', VALUE_DEFAULT, 1),
                'showscorestudent' => new external_value(PARAM_INT, 'Show score to student (0 or 1)', VALUE_DEFAULT, 1),
                'showrangeteacher' => new external_value(PARAM_INT, 'Show range to teacher (0 or 1)', VALUE_DEFAULT, 1),
                'showrangestudent' => new external_value(PARAM_INT, 'Show range to student (0 or 1)', VALUE_DEFAULT, 1),
                'enableremarks' => new external_value(PARAM_INT, 'Enable remarks (0 or 1)', VALUE_DEFAULT, 1),
                'showremarksstudent' => new external_value(PARAM_INT, 'Show remarks to student (0 or 1)', VALUE_DEFAULT, 1),
                'alwaysshowdefinition' => new external_value(PARAM_INT, 'Always show definition (0 or 1)', VALUE_DEFAULT, 1),
            ], 'Rubric options', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(
        int $cmid,
        string $name,
        string $description,
        array $criteria,
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

        $currentmethod = $gradingmanager->get_active_method();
        if ($currentmethod === 'fivedays') {
            $controller = $gradingmanager->get_controller('fivedays');
            if ($controller->is_form_defined()) {
                return [
                    'definitionid' => 0,
                    'success' => false,
                    'message' => 'A FiveDays rubric already exists for this assignment. Use update_rubric to modify it.',
                ];
            }
        } else if (!empty($currentmethod)) {
            return [
                'definitionid' => 0,
                'success' => false,
                'message' => 'Assignment already uses a different grading method: ' . $currentmethod . '. Delete it first.',
            ];
        }

        $gradingmanager->set_active_method('fivedays');
        $controller = $gradingmanager->get_controller('fivedays');

        $fivedayscriteria = [];
        $sortorder = 1;
        $criterionindex = 1;
        foreach ($params['criteria'] as $criterion) {
            $criterionkey = 'NEWID' . $criterionindex;
            $criteriondata = [
                'description' => $criterion['description'],
                'descriptionformat' => FORMAT_HTML,
                'sortorder' => array_key_exists('sortorder', $criterion)
                    ? (int) $criterion['sortorder']
                    : $sortorder,
                'levels' => [],
            ];

            $levelindex = 1;
            foreach ($criterion['levels'] as $level) {
                $levelkey = 'NEWID' . $levelindex;
                $criteriondata['levels'][$levelkey] = [
                    'score' => $level['score'],
                    'definition' => $level['definition'],
                    'definitionformat' => FORMAT_HTML,
                ];
                $levelindex++;
            }

            $fivedayscriteria[$criterionkey] = $criteriondata;
            $sortorder++;
            $criterionindex++;
        }

        $defaultoptions = [
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
        $fivedaysoptions = array_merge($defaultoptions, $params['options']);

        $rubricdata = new \stdClass();
        $rubricdata->name = $params['name'];
        $rubricdata->description_editor = [
            'text' => $params['description'],
            'format' => FORMAT_HTML,
        ];
        $rubricdata->fivedays = [
            'criteria' => $fivedayscriteria,
            'options' => $fivedaysoptions,
        ];
        $rubricdata->status = \gradingform_controller::DEFINITION_STATUS_READY;

        $controller->update_definition($rubricdata);

        $definition = $controller->get_definition();

        return [
            'definitionid' => $definition->id,
            'success' => true,
            'message' => 'FiveDays rubric created successfully. Assignment now uses FiveDays grading.',
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
