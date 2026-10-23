<?php
namespace local_fivedays;

/**
 * Moodle team submission for assignments whose groups are owned by the app.
 * The app's groups are copied into one grouping per assignment; every other
 * submitter gets a one-person group so Moodle never falls back to its shared
 * "Default group".
 */
class assignment_teams {

    public static function grouping_idnumber(int $assignmentid): string {
        return 'fivedays-assign-' . $assignmentid;
    }

    public static function group_idnumber_prefix(int $assignmentid): string {
        return 'fivedays-a' . $assignmentid . '-';
    }

    public static function has_submissions(int $assignmentid): bool {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        return $DB->record_exists_select(
            'assign_submission',
            'assignment = :assignment AND status <> :status',
            ['assignment' => $assignmentid, 'status' => ASSIGN_SUBMISSION_STATUS_NEW]
        );
    }

    /**
     * Groups Moodle uses for this assignment's team submissions, with their members.
     * A grouping id of 0 means Moodle uses every group in the course.
     */
    public static function list_teams(\stdClass $instance, int $courseid): array {
        global $DB;

        if (empty($instance->teamsubmission)) {
            return [];
        }

        $groups = groups_get_all_groups($courseid, 0, (int)$instance->teamsubmissiongroupingid, 'g.id, g.name');
        if (!$groups) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'g');
        $members = $DB->get_recordset_select('groups_members', "groupid $insql", $params, 'userid ASC', 'id, groupid, userid');
        $userids = [];
        foreach ($members as $member) {
            $userids[(int)$member->groupid][] = (int)$member->userid;
        }
        $members->close();

        $out = [];
        foreach ($groups as $group) {
            $out[] = [
                'groupid' => (int)$group->id,
                'name' => (string)$group->name,
                'userids' => $userids[(int)$group->id] ?? [],
            ];
        }
        return $out;
    }

    /**
     * @param array $groups [['key' => string, 'name' => string, 'userids' => int[]]]
     * @return array ['teamsubmission' => bool, 'groups' => int, 'skipped' => int[]]
     */
    public static function sync(\stdClass $instance, \stdClass $cm, \stdClass $course, bool $enabled, array $groups): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $assignmentid = (int)$instance->id;
        $isteam = !empty($instance->teamsubmission);
        if ($enabled !== $isteam && self::has_submissions($assignmentid)) {
            throw new \moodle_exception('assignmenthassubmissions', 'local_fivedays');
        }

        $transaction = $DB->start_delegated_transaction();

        if (!$enabled) {
            foreach (self::existing_groups($course->id, $assignmentid) as $group) {
                groups_delete_group($group);
            }
            $grouping = groups_get_grouping_by_idnumber($course->id, self::grouping_idnumber($assignmentid));
            if ($grouping) {
                groups_delete_grouping($grouping);
            }
            self::set_team_settings($instance, $course->id, 0, 0);
            $transaction->allow_commit();
            return ['teamsubmission' => false, 'groups' => 0, 'skipped' => []];
        }

        $groupingid = self::ensure_grouping($course->id, $assignmentid, (string)$instance->name);
        [$desired, $skipped] = self::desired_groups($instance, $cm, $course, $groups);

        $existing = self::existing_groups($course->id, $assignmentid);
        $byidnumber = [];
        foreach ($existing as $group) {
            $byidnumber[$group->idnumber] = $group;
        }

        foreach ($byidnumber as $idnumber => $group) {
            if (!isset($desired[$idnumber])) {
                groups_delete_group($group);
            }
        }

        foreach ($desired as $idnumber => $want) {
            $group = $byidnumber[$idnumber] ?? null;
            if (!$group) {
                $groupid = groups_create_group((object)[
                    'courseid' => $course->id,
                    'name' => $want['name'],
                    'idnumber' => $idnumber,
                ]);
            } else {
                $groupid = (int)$group->id;
                if ($group->name !== $want['name']) {
                    groups_update_group((object)[
                        'id' => $groupid,
                        'courseid' => $course->id,
                        'name' => $want['name'],
                    ]);
                }
            }
            groups_assign_grouping($groupingid, $groupid);
            self::set_members($groupid, $want['userids']);
        }

        self::set_team_settings($instance, $course->id, 1, $groupingid);
        $count = $DB->count_records('groupings_groups', ['groupingid' => $groupingid]);
        $transaction->allow_commit();

        return ['teamsubmission' => true, 'groups' => $count, 'skipped' => $skipped];
    }

    private static function existing_groups(int $courseid, int $assignmentid): array {
        global $DB;

        return $DB->get_records_select(
            'groups',
            'courseid = :courseid AND ' . $DB->sql_like('idnumber', ':prefix'),
            ['courseid' => $courseid, 'prefix' => self::group_idnumber_prefix($assignmentid) . '%'],
            'id ASC'
        );
    }

    private static function ensure_grouping(int $courseid, int $assignmentid, string $assignmentname): int {
        $name = self::truncate($assignmentname . ' groups');
        $grouping = groups_get_grouping_by_idnumber($courseid, self::grouping_idnumber($assignmentid));
        if (!$grouping) {
            return (int)groups_create_grouping((object)[
                'courseid' => $courseid,
                'name' => $name,
                'idnumber' => self::grouping_idnumber($assignmentid),
            ]);
        }
        if ($grouping->name !== $name) {
            groups_update_grouping((object)['id' => $grouping->id, 'courseid' => $courseid, 'name' => $name]);
        }
        return (int)$grouping->id;
    }

    /**
     * @return array [desired groups keyed by idnumber, skipped userids]
     */
    private static function desired_groups(\stdClass $instance, \stdClass $cm, \stdClass $course, array $groups): array {
        $assignmentid = (int)$instance->id;
        $prefix = self::group_idnumber_prefix($assignmentid);
        $coursecontext = \context_course::instance($course->id);

        $desired = [];
        $placed = [];
        $skipped = [];
        foreach ($groups as $group) {
            $idnumber = $prefix . $group['key'];
            if (isset($desired[$idnumber])) {
                throw new \invalid_parameter_exception('Group key ' . $group['key'] . ' is listed twice');
            }
            $members = [];
            foreach ($group['userids'] as $userid) {
                $userid = (int)$userid;
                if (isset($placed[$userid])) {
                    throw new \invalid_parameter_exception('User ' . $userid . ' is listed in more than one group');
                }
                $placed[$userid] = true;
                if (!is_enrolled($coursecontext, $userid)) {
                    $skipped[] = $userid;
                    continue;
                }
                $members[] = $userid;
            }
            $desired[$idnumber] = [
                'name' => self::truncate($group['name'] . ' · ' . $instance->name),
                'userids' => $members,
            ];
        }

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $submitters = get_enrolled_users(
            \context_module::instance($cm->id),
            'mod/assign:submit',
            0,
            'u.id, ' . $namefields
        );
        foreach ($submitters as $user) {
            if (isset($placed[(int)$user->id])) {
                continue;
            }
            $idnumber = $prefix . 'u' . $user->id;
            if (isset($desired[$idnumber])) {
                throw new \invalid_parameter_exception('Group key u' . $user->id . ' is reserved for a one-person group');
            }
            $desired[$idnumber] = [
                'name' => self::truncate(fullname($user) . ' · ' . $instance->name),
                'userids' => [(int)$user->id],
            ];
        }

        return [$desired, $skipped];
    }

    private static function set_members(int $groupid, array $userids): void {
        global $DB;

        $current = array_map('intval', $DB->get_fieldset_select('groups_members', 'userid', 'groupid = ?', [$groupid]));
        foreach (array_diff($current, $userids) as $userid) {
            groups_remove_member($groupid, $userid);
        }
        foreach (array_diff($userids, $current) as $userid) {
            groups_add_member($groupid, $userid);
        }
    }

    private static function set_team_settings(\stdClass $instance, int $courseid, int $teamsubmission, int $groupingid): void {
        global $DB;

        $wanted = [
            'teamsubmission' => $teamsubmission,
            'teamsubmissiongroupingid' => $groupingid,
            'preventsubmissionnotingroup' => 0,
            'requireallteammemberssubmit' => 0,
        ];
        $changed = false;
        foreach ($wanted as $field => $value) {
            if ((int)$instance->$field !== $value) {
                $changed = true;
            }
        }
        if (!$changed) {
            return;
        }
        $DB->update_record('assign', (object)(['id' => $instance->id, 'timemodified' => time()] + $wanted));
        rebuild_course_cache($courseid, true);
    }

    private static function truncate(string $name): string {
        return \core_text::substr($name, 0, 254);
    }
}
