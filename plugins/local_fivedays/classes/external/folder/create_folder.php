<?php
namespace local_fivedays\external\folder;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;

class create_folder extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Folder name'),
            'draftitemid' => new external_value(PARAM_INT, 'Draft item id from webservice/upload.php holding the files'),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
        ]);
    }

    public static function execute(int $courseid, string $name, int $draftitemid, int $section = 0, int $visible = 1): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/folder/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'draftitemid' => $draftitemid,
            'section' => $section,
            'visible' => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/fivedays:createfolder', $context);
        require_capability('mod/folder:addinstance', $context);

        if (empty($params['draftitemid']) || !helper::draft_area_has_files($params['draftitemid'])) {
            throw new \moodle_exception('emptydraftarea', 'local_fivedays');
        }

        $transaction = $DB->start_delegated_transaction();

        $moduleinfo = helper::create_module($course, 'folder', $params['section'], $params['name'], $params['visible'], [
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'revision' => 1,
            'display' => FOLDER_DISPLAY_PAGE,
            'showexpanded' => 1,
            'showdownloadfolder' => 1,
            'forcedownload' => 1,
            'files' => $params['draftitemid'],
        ]);

        rebuild_course_cache($course->id, true);
        $transaction->allow_commit();

        return [
            'id' => (int) $moduleinfo->instance,
            'coursemoduleid' => (int) $moduleinfo->coursemodule,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Folder ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }
}
