<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only site role luct gives registry, quality assurance and admin staff so they can open any course
 * without being enrolled.
 *
 * The overseer's capabilities minus SITE_WIDE_EXCLUDED plus moodle/course:view, assigned in the system context
 * only. No archetype and allow rows only, for the same reason as overseer_role: a prohibit would take rights
 * away from an auditor who also teaches a class.
 */
class auditor_role {

    public const SHORTNAME = 'fivedays_auditor';

    public const NAME = 'FiveDays Auditor';

    public const DESCRIPTION = 'Given by luct to registry, quality assurance and admin staff. View only, every course.';

    /** Fine in one course, but held in the system context they expose every user's profile and email. */
    public const SITE_WIDE_EXCLUDED = ['moodle/user:viewdetails', 'moodle/site:viewuseridentity'];

    /** @return string[] */
    public static function capabilities(): array {
        return [...array_values(array_diff(overseer_role::CAPABILITIES, self::SITE_WIDE_EXCLUDED)), 'moodle/course:view'];
    }

    /**
     * Creates or repairs the role: system context only, exactly the installed capabilities(), assignable by fivedays_ws.
     *
     * @return string[] what changed, empty when the role was already right
     */
    public static function sync(): array {
        return role_sync::sync(self::SHORTNAME, self::NAME, self::DESCRIPTION, CONTEXT_SYSTEM, self::capabilities());
    }
}
