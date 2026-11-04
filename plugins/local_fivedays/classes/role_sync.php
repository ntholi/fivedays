<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps a FiveDays view-only role equal to its definition. Shared by overseer_role and auditor_role.
 */
class role_sync {

    private const LEVEL_NAMES = [CONTEXT_SYSTEM => 'system', CONTEXT_COURSE => 'course'];

    /**
     * Creates or repairs the role: name, description, no archetype, assignable at $contextlevel only,
     * exactly the installed $capabilities allowed in the system context, and assignable by the fivedays_ws
     * role when it exists.
     *
     * @param string[] $capabilities
     * @return string[] what changed, empty when the role was already right
     */
    public static function sync(
        string $shortname,
        string $name,
        string $description,
        int $contextlevel,
        array $capabilities
    ): array {
        global $DB;

        $changes = [];
        $role = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$role) {
            $roleid = create_role($name, $shortname, $description);
            $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
            $changes[] = 'created';
        }
        $roleid = (int) $role->id;

        foreach (['name' => $name, 'description' => $description, 'archetype' => ''] as $field => $value) {
            if ((string) $role->$field !== $value) {
                $DB->set_field('role', $field, $value, ['id' => $roleid]);
                $changes[] = "{$field} updated";
            }
        }

        $levels = array_map('intval', array_values(get_role_contextlevels($roleid)));
        if ($levels !== [$contextlevel]) {
            set_role_contextlevels($roleid, [$contextlevel]);
            $changes[] = 'context levels set to ' . self::LEVEL_NAMES[$contextlevel];
        }

        $systemcontext = \context_system::instance();
        $current = $DB->get_records_menu('role_capabilities',
            ['roleid' => $roleid, 'contextid' => $systemcontext->id], '', 'capability, permission');
        foreach ($current as $capability => $permission) {
            if (!in_array($capability, $capabilities, true)) {
                unassign_capability($capability, $roleid, $systemcontext->id);
                $changes[] = "removed {$capability}";
            }
        }
        foreach ($capabilities as $capability) {
            // On a fresh site the quiz report subplugins install after this plugin; setup.php adds theirs.
            if (!get_capability_info($capability)) {
                continue;
            }
            if ((int) ($current[$capability] ?? 0) !== CAP_ALLOW) {
                assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id, true);
                $changes[] = "allowed {$capability}";
            }
        }

        $wsroleid = $DB->get_field('role', 'id', ['shortname' => 'fivedays_ws']);
        if ($wsroleid && !$DB->record_exists('role_allow_assign', ['roleid' => $wsroleid, 'allowassign' => $roleid])) {
            core_role_set_assign_allowed($wsroleid, $roleid);
            $changes[] = 'assignable by fivedays_ws';
        }

        return $changes;
    }
}
