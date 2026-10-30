<?php
namespace local_fivedays\external\folder;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;

class update_folder extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'folderid' => new external_value(PARAM_INT, 'Folder ID to update'),
            'name' => new external_value(PARAM_TEXT, 'Folder name'),
            'draftitemid' => new external_value(PARAM_INT, 'Draft item id with files to add (0=none); a same-name file replaces the existing one', VALUE_DEFAULT, 0),
            'removefiles' => new external_multiple_structure(
                new external_single_structure([
                    'filepath' => new external_value(PARAM_PATH, 'File path inside the folder, e.g. /'),
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                ]),
                'Files to delete from the folder',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute(int $folderid, string $name, int $draftitemid = 0, array $removefiles = []): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/folder/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'folderid' => $folderid,
            'name' => $name,
            'draftitemid' => $draftitemid,
            'removefiles' => $removefiles,
        ]);

        $folder = $DB->get_record('folder', ['id' => $params['folderid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('folder', $folder->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('local/fivedays:updatefolder', \context_course::instance($cm->course));
        // mod/folder:managefiles has only the editingteacher archetype, so the Manager-archetype fivedays_ws
        // role lacks it; moodle/course:manageactivities already lets a user replace the files via modedit.
        if (!has_capability('mod/folder:managefiles', $context)) {
            require_capability('moodle/course:manageactivities', $context);
        }

        $draftfiles = [];
        if (!empty($params['draftitemid'])) {
            $draftfiles = helper::get_draft_files($params['draftitemid']);
            if (!$draftfiles) {
                throw new \moodle_exception('emptydraftarea', 'local_fivedays');
            }
        }

        $fs = get_file_storage();
        $transaction = $DB->start_delegated_transaction();

        $removed = 0;
        foreach ($params['removefiles'] as $remove) {
            $file = $fs->get_file($context->id, 'mod_folder', 'content', 0, $remove['filepath'], $remove['filename']);
            if ($file && !$file->is_directory()) {
                $file->delete();
                $removed++;
            }
        }

        foreach ($draftfiles as $draftfile) {
            $existing = $fs->get_file($context->id, 'mod_folder', 'content', 0,
                $draftfile->get_filepath(), $draftfile->get_filename());
            if ($existing) {
                $existing->delete();
            }
            $fs->create_file_from_storedfile([
                'contextid' => $context->id,
                'component' => 'mod_folder',
                'filearea' => 'content',
                'itemid' => 0,
            ], $draftfile);
        }

        $folder->name = $params['name'];
        $folder->revision = $folder->revision + 1;
        $folder->timemodified = time();
        $DB->update_record('folder', $folder);

        $event = \mod_folder\event\folder_updated::create(['context' => $context, 'objectid' => $folder->id]);
        $event->add_record_snapshot('folder', $folder);
        $event->trigger();

        rebuild_course_cache($cm->course, true);
        $transaction->allow_commit();

        return [
            'id' => (int) $folder->id,
            'coursemoduleid' => (int) $cm->id,
            'success' => true,
            'message' => 'Folder updated: ' . count($draftfiles) . ' file(s) added, ' . $removed . ' removed',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Folder ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
