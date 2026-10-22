<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\helper;

class create_assignment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Assignment name'),
            'intro' => new external_value(PARAM_RAW, 'Assignment description', VALUE_DEFAULT, ''),
            'activity' => new external_value(PARAM_RAW, 'Activity instructions', VALUE_DEFAULT, ''),
            'allowsubmissionsfromdate' => new external_value(PARAM_INT, 'Allow submissions from date timestamp', VALUE_DEFAULT, 0),
            'duedate' => new external_value(PARAM_INT, 'Due date timestamp', VALUE_DEFAULT, 0),
            'section' => new external_value(PARAM_INT, 'Course section number', VALUE_DEFAULT, 0),
            'idnumber' => new external_value(PARAM_RAW, 'ID number for gradebook and external system reference', VALUE_DEFAULT, ''),
            'grademax' => new external_value(PARAM_INT, 'Maximum grade (can be negative to indicate use of a scale)', VALUE_DEFAULT, 100),
            'introfiles' => new external_value(PARAM_RAW, 'Additional files as JSON array', VALUE_DEFAULT, '[]'),
            'visible' => new external_value(PARAM_INT, 'Module visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
            'cutoffdate' => new external_value(PARAM_INT, 'Cut-off date timestamp (defaults to due date)', VALUE_DEFAULT, 0),
            'introattachments' => new external_value(PARAM_INT, 'Draft item id for intro attachments', VALUE_DEFAULT, 0),
            'onlinetext' => new external_value(PARAM_INT, 'Online text submissions instead of file submissions (1=yes, 0=no)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $courseid,
        string $name,
        string $intro = '',
        string $activity = '',
        int $allowsubmissionsfromdate = 0,
        int $duedate = 0,
        int $section = 0,
        string $idnumber = '',
        int $grademax = 100,
        string $introfiles = '[]',
        int $visible = 1,
        int $cutoffdate = 0,
        int $introattachments = 0,
        int $onlinetext = 0
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/assign/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'activity' => $activity,
            'allowsubmissionsfromdate' => $allowsubmissionsfromdate,
            'duedate' => $duedate,
            'section' => $section,
            'idnumber' => $idnumber,
            'grademax' => $grademax,
            'introfiles' => $introfiles,
            'visible' => $visible,
            'cutoffdate' => $cutoffdate,
            'introattachments' => $introattachments,
            'onlinetext' => $onlinetext,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createassignment', $context);
        require_capability('mod/assign:addinstance', $context);

        $transaction = $DB->start_delegated_transaction();
        $properties = [
            'cmidnumber' => $params['idnumber'],
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'alwaysshowdescription' => 1,
            'submissiondrafts' => 0,
            'sendnotifications' => 0,
            'sendlatenotifications' => 0,
            'sendstudentnotifications' => 1,
            'duedate' => $params['duedate'],
            'cutoffdate' => ($params['cutoffdate'] !== null && $params['cutoffdate'] !== 0)
                ? $params['cutoffdate']
                : $params['duedate'],
            'gradingduedate' => 0,
            'allowsubmissionsfromdate' => $params['allowsubmissionsfromdate'],
            'grade' => $params['grademax'],
            'teamsubmission' => 0,
            'requireallteammemberssubmit' => 0,
            'teamsubmissiongroupingid' => 0,
            'blindmarking' => 0,
            'hidegrader' => 0,
            'attemptreopenmethod' => 'none',
            'maxattempts' => -1,
            'markingworkflow' => 1,
            'markingallocation' => 0,
            'requiresubmissionstatement' => 0,
            'preventsubmissionnotingroup' => 0,
            'activityeditor' => [
                'text' => $params['activity'],
                'format' => FORMAT_HTML,
            ],
            'timelimit' => 0,
            'submissionattachments' => 0,
            'completionsubmit' => 0,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 20,
            'assignsubmission_file_maxsizebytes' => $CFG->maxbytes ?? 0,
            'assignsubmission_file_filetypes' => '',
            'assignsubmission_comments_enabled' => 1,
            'assignfeedback_comments_enabled' => 1,
        ];
        if ($params['onlinetext'] === 1) {
            $properties['assignsubmission_onlinetext_enabled'] = 1;
            $properties['assignsubmission_file_enabled'] = 0;
        }
        if (!empty($params['introattachments'])) {
            $properties['introattachments'] = $params['introattachments'];
        }
        $moduleinfo = helper::create_module($course, 'assign', $params['section'], $params['name'], $params['visible'], $properties);
        $assignid = $moduleinfo->instance;
        $cmid = $moduleinfo->coursemodule;

        if (empty($params['introattachments']) && !empty($params['introfiles']) && $params['introfiles'] !== '[]') {
            $files = json_decode($params['introfiles'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($files) && !empty($files)) {
                $fs = get_file_storage();
                $modulecontext = \context_module::instance($cmid);

                foreach ($files as $file) {
                    if (!empty($file['filename']) && isset($file['content'])) {

                        $filename = clean_param($file['filename'], PARAM_FILE);
                        if (empty($filename)) {
                            continue;
                        }

                        $filepath = '/';
                        $existingfile = $fs->get_file(
                            $modulecontext->id,
                            'mod_assign',
                            'introattachment',
                            0,
                            $filepath,
                            $filename
                        );
                        if ($existingfile) {
                            $existingfile->delete();
                        }

                        $filerecord = [
                            'contextid' => $modulecontext->id,
                            'component' => 'mod_assign',
                            'filearea' => 'introattachment',
                            'itemid' => 0,
                            'filepath' => $filepath,
                            'filename' => $filename,
                            'userid' => $USER->id,
                            'timecreated' => time(),
                            'timemodified' => time(),
                        ];

                        $filecontent = base64_decode($file['content'], true);
                        if ($filecontent === false) {
                            $filecontent = $file['content'];
                        }

                        $fs->create_file_from_string($filerecord, $filecontent);
                    }
                }
            }
        }

        rebuild_course_cache($params['courseid'], true);
        $transaction->allow_commit();

        return [
            'id' => $assignid,
            'coursemoduleid' => $cmid,
            'name' => $params['name'],
            'success' => true,
            'message' => 'Assignment created successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Assignment ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Assignment name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
