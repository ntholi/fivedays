<?php
namespace local_fivedays\external\file;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns the calling user's own draft-area files as base64, so the app can copy an upload
 * to courses it writes with another token (draft areas are only readable by their owner).
 */
class get_draft_files extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'draftitemid' => new external_value(PARAM_INT, 'Draft item id from webservice/upload.php'),
        ]);
    }

    public static function execute(int $draftitemid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'draftitemid' => $draftitemid,
        ]);

        $usercontext = \context_user::instance($USER->id);
        self::validate_context($usercontext);

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $params['draftitemid'],
            'filepath, filename',
            false
        );

        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'filename' => $file->get_filename(),
                'filepath' => $file->get_filepath(),
                'mimetype' => (string) $file->get_mimetype(),
                'content' => base64_encode($file->get_content()),
            ];
        }

        return ['files' => $result];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'filepath' => new external_value(PARAM_PATH, 'File path'),
                    'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
                    'content' => new external_value(PARAM_RAW, 'File content (base64)'),
                ])
            ),
        ]);
    }
}
