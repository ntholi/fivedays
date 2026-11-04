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
        return role_sync::sync(self::SHORTNAME, self::NAME, self::DESCRIPTION, CONTEXT_COURSE, self::CAPABILITIES);
    }
}
