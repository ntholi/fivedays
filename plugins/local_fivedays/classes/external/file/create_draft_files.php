<?php
namespace local_fivedays\external\file;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Saves base64 files into the calling user's own draft area and returns its item id,
 * for core functions that only take a draft id (e.g. mod_forum_add_discussion attachments)
 * when the caller's web service cannot use webservice/upload.php.
 */
class create_draft_files extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'content' => new external_value(PARAM_RAW, 'File content (base64)'),
                ])
            ),
        ]);
    }

    public static function execute(array $files): array {
        global $CFG, $USER;

        require_once($CFG->libdir . '/filelib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'files' => $files,
        ]);

        $usercontext = \context_user::instance($USER->id);
        self::validate_context($usercontext);

        $draftitemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        foreach ($params['files'] as $file) {
            $filename = clean_param($file['filename'], PARAM_FILE);
            if ($filename === '') {
                continue;
            }
            $content = base64_decode($file['content'], true);
            if ($content === false) {
                throw new \invalid_parameter_exception('File content must be base64: ' . $filename);
            }
            $fs->create_file_from_string([
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $filename,
                'userid' => $USER->id,
            ], $content);
        }

        return ['draftitemid' => $draftitemid];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'draftitemid' => new external_value(PARAM_INT, 'Draft item id'),
        ]);
    }
}
