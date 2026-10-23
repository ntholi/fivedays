<?php
namespace local_fivedays\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\assignment_teams;
use local_fivedays\helper;

class get_assignment_teams extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment instance ID'),
        ]);
    }

    public static function execute(int $assignmentid): array {
        global $CFG;

        require_once($CFG->dirroot . '/group/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
        ]);

        [$instance, $cm] = helper::require_activity('assign', $params['assignmentid']);
        [$assign] = helper::get_assign((int)$instance->id);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/fivedays:viewassignment', $context);
        require_capability('mod/assign:view', $context);

        return [
            'teamsubmission' => !empty($instance->teamsubmission),
            'canchangemode' => assignment_teams::can_change_mode($assign),
            'teams' => assignment_teams::list_teams($instance, (int)$cm->course),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'teamsubmission' => new external_value(PARAM_BOOL, 'Whether team submission is on'),
            'canchangemode' => new external_value(PARAM_BOOL, 'Whether team submission can still be switched (no submissions or grades yet)'),
            'teams' => new external_multiple_structure(
                new external_single_structure([
                    'groupid' => new external_value(PARAM_INT, 'Moodle group ID'),
                    'name' => new external_value(PARAM_TEXT, 'Group name'),
                    'submitted' => new external_value(PARAM_BOOL, 'Whether the group submission moved past "new"; its members cannot change'),
                    'userids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'Member user ID')
                    ),
                ])
            ),
        ]);
    }
}
