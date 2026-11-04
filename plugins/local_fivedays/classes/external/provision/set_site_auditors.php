<?php
namespace local_fivedays\external\provision;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\auditor_role;
use local_fivedays\provision_helper;

class set_site_auditors extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'email' => new external_value(PARAM_EMAIL, 'User email'),
                    'auditor' => new external_value(PARAM_BOOL, 'Whether the user holds fivedays_auditor site-wide'),
                ])
            ),
        ]);
    }

    public static function execute(array $users): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['users' => $users]);
        $context = provision_helper::require_system_capability();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => auditor_role::SHORTNAME], MUST_EXIST);

        $changed = 0;
        foreach ($params['users'] as $entry) {
            $user = provision_helper::find_user_by_email($entry['email']);
            if (!$user) {
                if ($entry['auditor']) {
                    throw new \invalid_parameter_exception('No Moodle user with email ' . $entry['email']);
                }
                continue;
            }
            $held = user_has_role_assignment((int) $user->id, $roleid, $context->id);
            if ($entry['auditor'] && !$held) {
                role_assign($roleid, (int) $user->id, $context->id);
                $changed++;
            } else if (!$entry['auditor'] && $held) {
                role_unassign($roleid, (int) $user->id, $context->id);
                $changed++;
            }
        }

        return ['changed' => $changed];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'changed' => new external_value(PARAM_INT, 'Number of role assignments added or removed'),
        ]);
    }
}
