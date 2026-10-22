<?php
namespace local_activity_utils\external\file;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class create_file extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'File resource name'),
            'intro' => new external_value(PARAM_RAW, 'File resource introduction/description', VALUE_DEFAULT, ''),
            'filename' => new external_value(PARAM_TEXT, 'File name', VALUE_DEFAULT, ''),
            'filecontent' => new external_value(PARAM_RAW, 'File content (base64 encoded)', VALUE_DEFAULT, ''),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
            'draftitemid' => new external_value(PARAM_INT, 'Draft item id from webservice/upload.php', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $intro = '',
        string $filename = '',
        string $filecontent = '',
        int $section = 0,
        int $visible = 1,
        int $draftitemid = 0
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/resource/lib.php');
        require_once($CFG->dirroot . '/mod/resource/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'filename' => $filename,
            'filecontent' => $filecontent,
            'section' => $section,
            'visible' => $visible,
            'draftitemid' => $draftitemid,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createfile', $context);
        require_capability('mod/resource:addinstance', $context);

        $hasdraft = !empty($params['draftitemid']);
        $filename = clean_param($params['filename'], PARAM_FILE);
        $hascontent = $params['filecontent'] !== '' && $filename !== '';

        if (!$hasdraft && !$hascontent) {
            if (empty($filename)) {
                throw new \moodle_exception('invalidfilename', 'local_activity_utils');
            }
            throw new \invalid_parameter_exception('File content or draft item id is required');
        }

        $transaction = $DB->start_delegated_transaction();

        $moduleinfo = helper::create_module($course, 'resource', $params['section'], $params['name'], $params['visible'], [
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'tobemigrated' => 0,
            'legacyfiles' => 0,
            'legacyfileslast' => null,
            'display' => 0,
            'printintro' => 0,
            'filterfiles' => 0,
            'revision' => 1,
            'files' => $hasdraft ? $params['draftitemid'] : 0,
        ]);
        $resourceid = $moduleinfo->instance;
        $cmid = $moduleinfo->coursemodule;

        if (!$hasdraft) {
            $fs = get_file_storage();
            $modulecontext = \context_module::instance($cmid);

            $content = base64_decode($params['filecontent'], true);
            if ($content === false) {
                $content = $params['filecontent'];
            }

            $filerecord = [
                'contextid' => $modulecontext->id,
                'component' => 'mod_resource',
                'filearea' => 'content',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
                'userid' => $USER->id,
                'timecreated' => time(),
                'timemodified' => time(),
            ];

            $fs->create_file_from_string($filerecord, $content);
        } else {
            $filename = self::stored_content_filename($cmid);
        }

        rebuild_course_cache($params['courseid'], true);
        $transaction->allow_commit();

        return [
            'id' => $resourceid,
            'coursemoduleid' => $cmid,
            'name' => $params['name'],
            'filename' => $filename,
            'success' => true,
            'message' => 'File resource created successfully'
        ];
    }

    private static function stored_content_filename(int $cmid): string {
        $fs = get_file_storage();
        $modulecontext = \context_module::instance($cmid);
        $files = $fs->get_area_files($modulecontext->id, 'mod_resource', 'content', 0, 'sortorder', false);
        foreach ($files as $file) {
            return $file->get_filename();
        }
        return '';
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Resource ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Resource name'),
            'filename' => new external_value(PARAM_TEXT, 'File name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
