<?php
namespace local_activity_utils\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class copy_rubric extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecmid' => new external_value(PARAM_INT, 'Source course module ID (assignment with rubric to copy)'),
            'targetcmid' => new external_value(PARAM_INT, 'Target course module ID (assignment to copy rubric to)'),
        ]);
    }

    public static function execute(int $sourcecmid, int $targetcmid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/grade/grading/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'sourcecmid' => $sourcecmid,
            'targetcmid' => $targetcmid,
        ]);

        $sourcecm = get_coursemodule_from_id('assign', $params['sourcecmid'], 0, false, MUST_EXIST);
        $sourcecontext = \context_module::instance($sourcecm->id);

        $targetcm = get_coursemodule_from_id('assign', $params['targetcmid'], 0, false, MUST_EXIST);
        $targetcontext = \context_module::instance($targetcm->id);

        self::validate_context($sourcecontext);
        require_capability('local/activity_utils:managerubric', $sourcecontext);

        self::validate_context($targetcontext);
        require_capability('local/activity_utils:managerubric', $targetcontext);
        require_capability('moodle/grade:managegradingforms', $targetcontext);

        $sourcegradingmanager = get_grading_manager($sourcecontext, 'mod_assign', 'submissions');

        if ($sourcegradingmanager->get_active_method() !== 'fivedays') {
            return [
                'definitionid' => 0,
                'success' => false,
                'message' => 'Source assignment does not have a FiveDays rubric',
            ];
        }

        $sourcecontroller = $sourcegradingmanager->get_controller('fivedays');
        if (!$sourcecontroller->is_form_defined()) {
            return [
                'definitionid' => 0,
                'success' => false,
                'message' => 'Source assignment has no FiveDays rubric defined',
            ];
        }

        $sourcedefinition = $sourcecontroller->get_definition();

        $targetgradingmanager = get_grading_manager($targetcontext, 'mod_assign', 'submissions');

        $targetmethod = $targetgradingmanager->get_active_method();
        if ($targetmethod === 'fivedays') {
            $targetcontroller = $targetgradingmanager->get_controller('fivedays');
            if ($targetcontroller->is_form_defined()) {
                return [
                    'definitionid' => 0,
                    'success' => false,
                    'message' => 'Target assignment already has a FiveDays rubric. Delete it first or use update_rubric.',
                ];
            }
        } else if (!empty($targetmethod)) {
            return [
                'definitionid' => 0,
                'success' => false,
                'message' => 'Target assignment uses a different grading method: ' . $targetmethod . '. Delete it first.',
            ];
        }

        $targetgradingmanager->set_active_method('fivedays');
        $targetcontroller = $targetgradingmanager->get_controller('fivedays');

        $sourcecriteria = $DB->get_records('gradingform_fivedays_criteria', ['definitionid' => $sourcedefinition->id], 'sortorder ASC');

        $fivedayscriteria = [];
        $criterionindex = 1;
        foreach ($sourcecriteria as $criterion) {
            $levels = $DB->get_records('gradingform_fivedays_levels', ['criterionid' => $criterion->id], 'score ASC');

            $levelsdata = [];
            $levelindex = 1;
            foreach ($levels as $level) {
                $levelsdata['NEWID' . $levelindex] = [
                    'score' => $level->score,
                    'definition' => $level->definition,
                    'definitionformat' => FORMAT_HTML,
                ];
                $levelindex++;
            }

            $fivedayscriteria['NEWID' . $criterionindex] = [
                'description' => $criterion->description,
                'descriptionformat' => FORMAT_HTML,
                'sortorder' => $criterion->sortorder,
                'levels' => $levelsdata,
            ];
            $criterionindex++;
        }

        $sourceoptions = json_decode($sourcedefinition->options ?? '{}', true) ?: [];

        $rubricdata = new \stdClass();
        $rubricdata->name = $sourcedefinition->name . ' (Copy)';
        $rubricdata->description_editor = [
            'text' => $sourcedefinition->description ?? '',
            'format' => FORMAT_HTML,
        ];
        $rubricdata->fivedays = [
            'criteria' => $fivedayscriteria,
            'options' => $sourceoptions,
        ];
        $rubricdata->status = \gradingform_controller::DEFINITION_STATUS_READY;

        $targetcontroller->update_definition($rubricdata);

        $newdefinition = $targetcontroller->get_definition();

        return [
            'definitionid' => (int)$newdefinition->id,
            'success' => true,
            'message' => 'FiveDays rubric copied successfully. Target assignment now uses FiveDays grading.',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'definitionid' => new external_value(PARAM_INT, 'New rubric definition ID'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
