<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serves the files attached to a quiz question (filearea `qfiles`; args = [questionid, filename]).
 * They are stored in the question's own `question/questiontext` area, so this only checks that the
 * question belongs to the category of $context and that the caller may read questions there.
 *
 * @return bool false when nothing is served; a served file ends the request
 */
function local_fivedays_pluginfile($course, $cm, $context, string $filearea, array $args, bool $forcedownload, array $options = []) {
    global $DB;

    if ($filearea !== 'qfiles' || count($args) !== 2 || !ctype_digit((string)$args[0])) {
        return false;
    }
    if (!\local_fivedays\question_files::can_read($context)) {
        return false;
    }

    $questionid = (int)$args[0];
    $belongs = $DB->record_exists_sql(
        "SELECT 1
           FROM {question_versions} qv
           JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
           JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
          WHERE qv.questionid = ? AND qc.contextid = ?",
        [$questionid, $context->id]
    );
    if (!$belongs) {
        return false;
    }

    $file = get_file_storage()->get_file($context->id, 'question', 'questiontext', $questionid, '/', $args[1]);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
