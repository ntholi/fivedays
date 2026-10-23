<?php
namespace local_fivedays\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\assignment_teams;
use local_fivedays\helper;
use local_fivedays\provision_helper;

class sync_assignment_groups extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment instance ID'),
            'enabled' => new external_value(PARAM_BOOL, 'Turn team submission on (true) or off (false)'),
            'groups' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT, 'Stable app key for the group'),
                    'name' => new external_value(PARAM_TEXT, 'Group name'),
                    'userids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'Member user ID'),
                        'Member user IDs',
                        VALUE_DEFAULT,
                        []
                    ),
                ]),
                'Groups to sync; ignored when enabled is false',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute(int $assignmentid, bool $enabled, array $groups = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
            'enabled' => $enabled,
            'groups' => $groups,
        ]);
        provision_helper::require_system_capability();

        [$instance, $cm] = helper::require_activity('assign', $params['assignmentid']);
        $course = get_course($cm->course);
        require_capability('moodle/course:managegroups', \context_course::instance($course->id));

        return assignment_teams::sync(
            $instance,
            $cm,
            $course,
            (bool)$params['enabled'],
            $params['enabled'] ? $params['groups'] : []
        );
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'teamsubmission' => new external_value(PARAM_BOOL, 'Whether team submission is now on'),
            'groups' => new external_value(PARAM_INT, 'Groups now in the assignment grouping'),
            'skipped' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Listed user ID not enrolled in the course')
            ),
        ]);
    }
}
