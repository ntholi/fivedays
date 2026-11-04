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

    /**
     * auditor = true needs exactly one user with the email; auditor = false removes the role from every user
     * with it, so a duplicate account can never keep site-wide read access.
     */
    public static function execute(array $users): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['users' => $users]);
        $context = provision_helper::require_system_capability();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => auditor_role::SHORTNAME], MUST_EXIST);

        $changed = 0;
        foreach ($params['users'] as $entry) {
            $matches = provision_helper::find_users_by_email($entry['email']);
            if ($entry['auditor'] && count($matches) !== 1) {
                throw new \invalid_parameter_exception(($matches ? 'More than one Moodle user has email '
                    : 'No Moodle user with email ') . $entry['email']);
            }
            foreach ($matches as $user) {
                $held = $DB->record_exists('role_assignments', [
                    'roleid' => $roleid,
                    'userid' => $user->id,
                    'contextid' => $context->id,
                    'component' => '',
                    'itemid' => 0,
                ]);
                if ($entry['auditor'] && !$held) {
                    role_assign($roleid, (int) $user->id, $context->id);
                    $changed++;
                } else if (!$entry['auditor'] && $held) {
                    role_unassign($roleid, (int) $user->id, $context->id);
                    $changed++;
                }
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
