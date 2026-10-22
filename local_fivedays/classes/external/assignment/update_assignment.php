<?php
namespace local_activity_utils\external\assignment;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;


class update_assignment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID to update'),
            'name' => new external_value(PARAM_TEXT, 'Assignment name', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'Assignment description', VALUE_DEFAULT, null),
            'activity' => new external_value(PARAM_RAW, 'Activity instructions', VALUE_DEFAULT, null),
            'allowsubmissionsfromdate' => new external_value(PARAM_INT, 'Allow submissions from date timestamp', VALUE_DEFAULT, null),
            'duedate' => new external_value(PARAM_INT, 'Due date timestamp', VALUE_DEFAULT, null),
            'cutoffdate' => new external_value(PARAM_INT, 'Cut-off date timestamp', VALUE_DEFAULT, null),
            'idnumber' => new external_value(PARAM_RAW, 'ID number for gradebook and external system reference', VALUE_DEFAULT, null),
            'grademax' => new external_value(PARAM_INT, 'Maximum grade', VALUE_DEFAULT, null),
            'introfiles' => new external_value(PARAM_RAW, 'Replacement files as JSON array (replaces all intro attachments)', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, null),
            'introattachments' => new external_value(PARAM_INT, 'Draft item id for intro attachments (replaces the area)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $assignmentid,
        ?string $name = null,
        ?string $intro = null,
        ?string $activity = null,
        ?int $allowsubmissionsfromdate = null,
        ?int $duedate = null,
        ?int $cutoffdate = null,
        ?string $idnumber = null,
        ?int $grademax = null,
        ?string $introfiles = null,
        ?int $visible = null,
        ?int $introattachments = null
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/assign/lib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/lib/gradelib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'assignmentid' => $assignmentid,
            'name' => $name,
            'intro' => $intro,
            'activity' => $activity,
            'allowsubmissionsfromdate' => $allowsubmissionsfromdate,
            'duedate' => $duedate,
            'cutoffdate' => $cutoffdate,
            'idnumber' => $idnumber,
            'grademax' => $grademax,
            'introfiles' => $introfiles,
            'visible' => $visible,
            'introattachments' => $introattachments,
        ]);

        
        $assign = $DB->get_record('assign', ['id' => $params['assignmentid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:updateassignment', $context);
        require_capability('mod/assign:addinstance', $context);

        
        $updated = false;

        if ($params['name'] !== null) {
            $assign->name = $params['name'];
            $updated = true;
        }
        if ($params['intro'] !== null) {
            $assign->intro = $params['intro'];
            $assign->alwaysshowdescription = 1;
            $updated = true;
        }
        if ($params['activity'] !== null) {
            $assign->activity = $params['activity'];
            $updated = true;
        }
        if ($params['allowsubmissionsfromdate'] !== null) {
            $assign->allowsubmissionsfromdate = $params['allowsubmissionsfromdate'];
            $updated = true;
        }
        if ($params['duedate'] !== null) {
            $assign->duedate = $params['duedate'];
            $updated = true;
        }
        if ($params['cutoffdate'] !== null) {
            $assign->cutoffdate = $params['cutoffdate'];
            $updated = true;
        }
        if ($params['grademax'] !== null) {
            $assign->grade = $params['grademax'];
            $updated = true;
        }

        if ($updated) {
            $assign->timemodified = time();
            $DB->update_record('assign', $assign);
        }

        
        if ($params['idnumber'] !== null || $params['visible'] !== null) {
            if ($params['idnumber'] !== null) {
                $cm->idnumber = $params['idnumber'];
            }
            if ($params['visible'] !== null) {
                $cm->visible = $params['visible'];
                $cm->visibleold = $params['visible'];
            }
            $DB->update_record('course_modules', $cm);
        }

        
        if ($params['grademax'] !== null || $params['name'] !== null) {
            grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, null, [
                'itemname' => $assign->name,
                'gradetype' => GRADE_TYPE_VALUE,
                'grademax' => $assign->grade,
                'grademin' => 0
            ]);
        }

        if ($params['introattachments'] !== null) {
            $modulecontext = \context_module::instance($cm->id);
            file_save_draft_area_files(
                (int) $params['introattachments'],
                $modulecontext->id,
                'mod_assign',
                ASSIGN_INTROATTACHMENT_FILEAREA,
                0
            );
        } else if ($params['introfiles'] !== null && $params['introfiles'] !== '') {
            $files = json_decode($params['introfiles'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($files)) {
                throw new \invalid_parameter_exception('Invalid introfiles JSON payload');
            }

            $fs = get_file_storage();
            $modulecontext = \context_module::instance($cm->id);
            $fs->delete_area_files($modulecontext->id, 'mod_assign', 'introattachment', 0);

            foreach ($files as $file) {
                if (empty($file['filename']) || !isset($file['content'])) {
                    continue;
                }

                $filename = clean_param($file['filename'], PARAM_FILE);
                if (empty($filename)) {
                    continue;
                }

                $filerecord = [
                    'contextid' => $modulecontext->id,
                    'component' => 'mod_assign',
                    'filearea' => 'introattachment',
                    'itemid' => 0,
                    'filepath' => '/',
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

        rebuild_course_cache($course->id, true);

        return [
            'id' => $assign->id,
            'coursemoduleid' => $cm->id,
            'name' => $assign->name,
            'success' => true,
            'message' => 'Assignment updated successfully'
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
