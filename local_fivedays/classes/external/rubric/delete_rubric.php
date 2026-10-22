<?php
namespace local_activity_utils\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_rubric extends external_api {

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
        require_capability('moodle/grade:managegradingforms', $context);

        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');

        if ($gradingmanager->get_active_method() !== 'fivedays') {
            return [
                'success' => false,
                'message' => 'No FiveDays rubric is set for this assignment',
            ];
        }

        $controller = $gradingmanager->get_controller('fivedays');

        if (!$controller->is_form_defined()) {
            $gradingmanager->set_active_method('');
            return [
                'success' => true,
                'message' => 'Grading method cleared, assignment now uses simple direct grading',
            ];
        }

        $definition = $controller->get_definition();
        $definitionid = $definition->id;

        $instances = $DB->count_records('grading_instances', ['definitionid' => $definitionid]);
        if ($instances > 0) {
            $instanceids = $DB->get_fieldset_select('grading_instances', 'id', 'definitionid = ?', [$definitionid]);
            foreach ($instanceids as $instanceid) {
                $DB->delete_records('gradingform_fivedays_fillings', ['instanceid' => $instanceid]);
            }
            $DB->delete_records('grading_instances', ['definitionid' => $definitionid]);
        }

        $criteriaids = $DB->get_fieldset_select('gradingform_fivedays_criteria', 'id', 'definitionid = ?', [$definitionid]);
        foreach ($criteriaids as $criteriaid) {
            $DB->delete_records('gradingform_fivedays_levels', ['criterionid' => $criteriaid]);
        }

        $DB->delete_records('gradingform_fivedays_criteria', ['definitionid' => $definitionid]);
        $DB->delete_records('grading_definitions', ['id' => $definitionid]);

        $gradingmanager->set_active_method('');

        return [
            'success' => true,
            'message' => 'FiveDays rubric deleted successfully, assignment now uses simple direct grading',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
