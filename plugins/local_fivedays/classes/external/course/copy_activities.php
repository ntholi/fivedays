<?php
namespace local_fivedays\external\course;

use core_courseformat\formatactions;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\provision_helper;

class copy_activities extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Target course ID'),
            'sectionname' => new external_value(PARAM_TEXT, 'Target section name; created at the end of the course when missing'),
            'sectionsummary' => new external_value(PARAM_RAW, 'Summary for a newly created section', VALUE_DEFAULT, ''),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Source course module ID'),
                    'reusekey' => new external_value(PARAM_TEXT, 'Caller id for this material (up to 64 characters); one copy per course and key'),
                ]),
                'Activities to copy, in the order to add them'
            ),
        ]);
    }

    public static function execute(int $courseid, string $sectionname, string $sectionsummary, array $items): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionname' => $sectionname,
            'sectionsummary' => $sectionsummary,
            'items' => $items,
        ]);
        provision_helper::require_system_capability();

        $name = trim($params['sectionname']);
        if ($name === '') {
            throw new \invalid_parameter_exception('sectionname is required');
        }
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        if (!$params['items']) {
            return ['copies' => []];
        }

        $sectionid = null;
        $copies = [];
        foreach ($params['items'] as $item) {
            $sourcecmid = (int) $item['cmid'];
            try {
                $reusekey = trim($item['reusekey']);
                if ($reusekey === '' || \core_text::strlen($reusekey) > 64) {
                    throw new \moodle_exception('invalidreusekey', 'local_fivedays');
                }
                $existing = self::existing_copy((int) $course->id, $reusekey);
                if ($existing) {
                    $copies[] = ['sourcecmid' => $sourcecmid, 'cmid' => $existing, 'error' => '', 'existing' => true];
                    continue;
                }
                $sectionid ??= self::ensure_section($course, $name, $params['sectionsummary']);
                $copies[] = [
                    'sourcecmid' => $sourcecmid,
                    'cmid' => self::copy_and_record($course, $sourcecmid, $sectionid, $reusekey),
                    'error' => '',
                    'existing' => false,
                ];
            } catch (\Throwable $e) {
                $copies[] = [
                    'sourcecmid' => $sourcecmid,
                    'cmid' => 0,
                    'error' => \core_text::substr($e->getMessage(), 0, 255),
                    'existing' => false,
                ];
            }
        }

        return ['copies' => $copies];
    }

    /** The cm already copied for this key when it still exists in the course; drops a record whose cm is gone. */
    private static function existing_copy(int $courseid, string $reusekey): int {
        global $DB;

        $record = $DB->get_record('local_fivedays_reuse_copies', ['courseid' => $courseid, 'reusekey' => $reusekey]);
        if (!$record) {
            return 0;
        }
        if ($DB->record_exists('course_modules',
                ['id' => $record->cmid, 'course' => $courseid, 'deletioninprogress' => 0])) {
            return (int) $record->cmid;
        }
        $DB->delete_records('local_fivedays_reuse_copies', ['id' => $record->id]);
        return 0;
    }

    /** Copies the activity and records it under the key; a copy that cannot be recorded is deleted again. */
    private static function copy_and_record(\stdClass $course, int $sourcecmid, int $sectionid, string $reusekey): int {
        global $DB;

        $newcmid = self::copy_activity($course, $sourcecmid, $sectionid);
        try {
            $DB->insert_record('local_fivedays_reuse_copies', (object) [
                'courseid' => $course->id,
                'reusekey' => $reusekey,
                'cmid' => $newcmid,
                'timecreated' => time(),
            ]);
        } catch (\Throwable $e) {
            formatactions::cm($course->id)->delete($newcmid);
            throw $e;
        }
        return $newcmid;
    }

    /** First regular (not delegated) section with this name, else a new one at the end of the course. */
    private static function ensure_section(\stdClass $course, string $name, string $summary): int {
        global $DB;

        $sections = $DB->get_records_select('course_sections',
            'course = :course AND component IS NULL AND name = :name',
            ['course' => $course->id, 'name' => $name], 'section ASC', 'id', 0, 1);
        if ($sections) {
            return (int) reset($sections)->id;
        }

        $section = formatactions::section($course)->create();
        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id, MUST_EXIST);
        formatactions::section($course)->update($sectioninfo, [
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => FORMAT_HTML,
        ]);
        return (int) $section->id;
    }

    /** Copies one activity (settings and files, no user data) to the end of the target section. */
    private static function copy_activity(\stdClass $course, int $sourcecmid, int $sectionid): int {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        $source = $DB->get_record_sql(
            "SELECT cm.id, m.name AS modname
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.id = :id AND cm.deletioninprogress = 0",
            ['id' => $sourcecmid]
        );
        if (!$source) {
            throw new \moodle_exception('activitynotfound', 'local_fivedays', '', $sourcecmid);
        }
        if (!plugin_supports('mod', $source->modname, FEATURE_BACKUP_MOODLE2)) {
            throw new \moodle_exception('activitycopynotsupported', 'local_fivedays', '', $source->modname);
        }
        $sourcecontextid = \context_module::instance($source->id)->id;

        $bc = new \backup_controller(\backup::TYPE_1ACTIVITY, $source->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_IMPORT, $USER->id);
        $backupid = $bc->get_backupid();
        $basepath = $bc->get_plan()->get_basepath();
        $rc = null;
        $newcmid = 0;
        try {
            $bc->execute_plan();
            $bc->destroy();
            $bc = null;

            $rc = new \restore_controller($backupid, $course->id, \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT, $USER->id, \backup::TARGET_CURRENT_ADDING);
            if (!$rc->execute_precheck()) {
                $results = $rc->get_precheck_results();
                if (!empty($results['errors'])) {
                    throw new \moodle_exception('activitycopyfailed', 'local_fivedays', '', implode('; ', $results['errors']));
                }
            }
            $rc->execute_plan();
            foreach ($rc->get_plan()->get_tasks() as $task) {
                if ($task instanceof \restore_activity_task && $task->get_old_contextid() == $sourcecontextid) {
                    $newcmid = (int) $task->get_moduleid();
                    break;
                }
            }
            $rc->destroy();
            $rc = null;

            if (!$newcmid) {
                throw new \moodle_exception('activitycopyfailed', 'local_fivedays', '', 'restored activity not found');
            }
            formatactions::cm($course->id)->move_end_section($newcmid, $sectionid);
            return $newcmid;
        } catch (\Throwable $e) {
            if ($newcmid && $DB->record_exists('course_modules', ['id' => $newcmid])) {
                formatactions::cm($course->id)->delete($newcmid);
            }
            throw $e;
        } finally {
            $bc?->destroy();
            $rc?->destroy();
            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($basepath);
            }
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'copies' => new external_multiple_structure(
                new external_single_structure([
                    'sourcecmid' => new external_value(PARAM_INT, 'Source course module ID'),
                    'cmid' => new external_value(PARAM_INT, 'New course module ID, 0 on failure'),
                    'error' => new external_value(PARAM_TEXT, 'Empty on success, else why the copy failed'),
                    'existing' => new external_value(PARAM_BOOL, 'True when the course already had a copy for this reusekey'),
                ])
            ),
        ]);
    }
}
