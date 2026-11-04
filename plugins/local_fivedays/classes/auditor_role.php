<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only site role luct gives registry, quality assurance and admin staff so they can open any course
 * without being enrolled.
 *
 * The overseer's capabilities plus moodle/course:view, assigned in the system context only. No archetype and
 * allow rows only, for the same reason as overseer_role: a prohibit would take rights away from an auditor who
 * also teaches a class.
 */
class auditor_role {

    public const SHORTNAME = 'fivedays_auditor';

    public const NAME = 'FiveDays Auditor';

    public const DESCRIPTION = 'Given by luct to registry, quality assurance and admin staff. View only, every course.';

    public const CAPABILITIES = [...overseer_role::CAPABILITIES, 'moodle/course:view'];

    /**
     * Creates or repairs the role: system context only, exactly the installed CAPABILITIES, assignable by fivedays_ws.
     *
     * @return string[] what changed, empty when the role was already right
     */
    public static function sync(): array {
        return role_sync::sync(self::SHORTNAME, self::NAME, self::DESCRIPTION, CONTEXT_SYSTEM, self::CAPABILITIES);
    }
}
