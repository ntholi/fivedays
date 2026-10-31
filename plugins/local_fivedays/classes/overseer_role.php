<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only course role luct gives a principal lecturer on the classes they oversee.
 *
 * No archetype and allow rows only: a principal lecturer who also teaches the class holds editingteacher
 * too, and a prohibit here would take those rights away from them.
 */
class overseer_role {

    public const SHORTNAME = 'fivedays_overseer';

    public const NAME = 'FiveDays Overseer';

    public const DESCRIPTION = 'Given by luct to principal lecturers on the classes they oversee. View only.';

    public const CAPABILITIES = [
        'moodle/course:viewparticipants',
        'moodle/user:viewdetails',
        'moodle/site:viewuseridentity',
        'moodle/site:accessallgroups',
        'moodle/course:viewsuspendedusers',
        'moodle/course:viewhiddencourses',
        'moodle/course:viewhiddensections',
        'moodle/course:viewhiddenactivities',
        'moodle/course:ignoreavailabilityrestrictions',
        'mod/assign:view',
        'mod/assign:viewgrades',
        'mod/quiz:view',
        'mod/quiz:viewreports',
        // Moodle clones these from mod/quiz:viewreports when the quiz reports install or upgrade.
        'quiz/grading:viewstudentnames',
        'quiz/grading:viewidnumber',
        'quiz/statistics:view',
        'mod/forum:viewdiscussion',
        'mod/forum:readprivatereplies',
        'mod/forum:viewqandawithoutposting',
        'mod/book:read',
        'mod/book:viewhiddenchapters',
        'mod/page:view',
        'mod/resource:view',
        'mod/folder:view',
        'mod/url:view',
        'mod/bigbluebuttonbn:view',
        'mod/bigbluebuttonbn:join',
        'local/fivedays:readbook',
        'local/fivedays:viewassignment',
        'local/fivedays:viewassignmentcomments',
        'local/fivedays:viewrubricfilling',
        'local/fivedays:viewquiz',
        'local/fivedays:viewquizattempts',
        'local/fivedays:viewgradingqueue',
    ];

    /**
     * Creates or repairs the role: name, description, no archetype, course context only, exactly the
     * installed CAPABILITIES allowed in the system context, and assignable by the fivedays_ws role when it exists.
     *
     * @return string[] what changed, empty when the role was already right
     */
    public static function sync(): array {
        global $DB;

        $changes = [];
        $role = $DB->get_record('role', ['shortname' => self::SHORTNAME]);
        if (!$role) {
            $roleid = create_role(self::NAME, self::SHORTNAME, self::DESCRIPTION);
            $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
            $changes[] = 'created';
        }
        $roleid = (int) $role->id;

        foreach (['name' => self::NAME, 'description' => self::DESCRIPTION, 'archetype' => ''] as $field => $value) {
            if ((string) $role->$field !== $value) {
                $DB->set_field('role', $field, $value, ['id' => $roleid]);
                $changes[] = "{$field} updated";
            }
        }

        $levels = array_map('intval', array_values(get_role_contextlevels($roleid)));
        if ($levels !== [CONTEXT_COURSE]) {
            set_role_contextlevels($roleid, [CONTEXT_COURSE]);
            $changes[] = 'context levels set to course';
        }

        $systemcontext = \context_system::instance();
        $current = $DB->get_records_menu('role_capabilities',
            ['roleid' => $roleid, 'contextid' => $systemcontext->id], '', 'capability, permission');
        foreach ($current as $capability => $permission) {
            if (!in_array($capability, self::CAPABILITIES, true)) {
                unassign_capability($capability, $roleid, $systemcontext->id);
                $changes[] = "removed {$capability}";
            }
        }
        foreach (self::CAPABILITIES as $capability) {
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
