<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * Which FiveDays activities may be shown to students. luct writes a row for each weighted activity;
 * approved = 0 keeps it hidden until a principal lecturer or faculty manager approves it there. A course
 * module without a row is not managed here and is never touched.
 */
class activity_approval {

    private const TABLE = 'local_fivedays_activity_approval';

    /**
     * @param int[] $cmids
     */
    public static function set(array $cmids, bool $approved): void {
        global $DB;

        $now = time();
        foreach (array_values(array_unique(array_map('intval', $cmids))) as $cmid) {
            $row = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
            if ($row) {
                if ((int) $row->approved !== (int) $approved) {
                    $DB->update_record(self::TABLE, (object) [
                        'id' => $row->id,
                        'approved' => (int) $approved,
                        'timemodified' => $now,
                    ]);
                }
                continue;
            }
            $DB->insert_record(self::TABLE, (object) [
                'cmid' => $cmid,
                'approved' => (int) $approved,
                'timemodified' => $now,
            ]);
        }
    }

    /** True when luct has not approved this course module, so students must not see it. */
    public static function is_blocked(int $cmid): bool {
        global $DB;

        return $DB->record_exists(self::TABLE, ['cmid' => $cmid, 'approved' => 0]);
    }

    /** Refuses to show an activity luct has not approved. */
    public static function require_showable(int $cmid): void {
        if (self::is_blocked($cmid)) {
            throw new \moodle_exception('activitynotapproved', 'local_fivedays');
        }
    }

    /**
     * Hides the course module again when it is visible but not approved. A hidden one in a hidden
     * section also loses the visibility the section would restore when it is shown again
     * (`visibleold`, see core_courseformat\local\sectionactions::transfer_visibility_to_cms()).
     */
    public static function enforce(int $cmid): bool {
        global $DB;

        if (!self::is_blocked($cmid)) {
            return false;
        }
        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, visible, visibleold');
        if (!$cm) {
            return false;
        }
        if ((int) $cm->visible === 0) {
            self::forget_restored_visibility([$cm]);
            return false;
        }
        \core_courseformat\formatactions::cm((int) $cm->course)->set_visibility($cmid, 0);
        debugging("local_fivedays: course module {$cmid} is not approved in luct; hidden again.", DEBUG_DEVELOPER);
        return true;
    }

    /**
     * Showing a hidden section gives each of its modules back its `visibleold`, without a
     * course_module_updated event. Moodle triggers course_section_updated before it does that, so
     * clearing `visibleold` of the unapproved modules here keeps them hidden.
     */
    public static function enforce_section(int $sectionid): void {
        global $DB;

        $sql = "SELECT cm.id, cm.course, cm.visible, cm.visibleold
                  FROM {course_modules} cm
                  JOIN {" . self::TABLE . "} a ON a.cmid = cm.id AND a.approved = 0
                 WHERE cm.section = :sectionid";
        $cms = $DB->get_records_sql($sql, ['sectionid' => $sectionid]);
        foreach ($cms as $cm) {
            if ((int) $cm->visible === 1) {
                self::enforce((int) $cm->id);
            }
        }
        self::forget_restored_visibility($cms);
    }

    /**
     * @param \stdClass[] $cms course modules with id, course and visibleold.
     */
    private static function forget_restored_visibility(array $cms): void {
        global $DB;

        $courses = [];
        foreach ($cms as $cm) {
            if ((int) $cm->visibleold === 1) {
                $DB->set_field('course_modules', 'visibleold', 0, ['id' => $cm->id]);
                $courses[(int) $cm->course][] = (int) $cm->id;
            }
        }
        foreach ($courses as $courseid => $cmids) {
            \course_modinfo::purge_course_modules_cache($courseid, $cmids);
        }
    }

    public static function forget(int $cmid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }
}
