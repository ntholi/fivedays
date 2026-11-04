<?php
namespace local_fivedays;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

/**
 * Files attached to a quiz question. They live in the question's own `question/questiontext`
 * file area (itemid = question id) and the question text ends with a marker block that
 * references them, so Moodle shows them to students natively. Every save of a question is a new
 * question row, so the files are written again for each version.
 */
class question_files {
    public const MAX_FILES = 5;
    public const MAX_BYTES = 20 * 1024 * 1024;

    private const BLOCK_OPEN = '<div class="fivedays-qfiles" data-fivedays-qfiles="1">';
    private const BLOCK_PATTERN = '~<div class="fivedays-qfiles" data-fivedays-qfiles="1">((?:<p>.*?</p>)*)</div>\s*$~s';

    /** The optional JSON input of the create_*_question functions. */
    public static function parameter(): external_value {
        return new external_value(
            PARAM_RAW,
            'JSON array of up to ' . self::MAX_FILES . ' files shown with the question: '
                . '[{"filename":"a.png","draftitemid":123}] or {"filename":"a.png","content":"<base64>"} or '
                . '{"filename":"a.png","fromquestionid":456}. Send the full list on every save.',
            VALUE_DEFAULT,
            '[]'
        );
    }

    public static function files_structure(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'filename' => new external_value(PARAM_FILE, 'File name'),
                'filesize' => new external_value(PARAM_INT, 'Size in bytes'),
                'mimetype' => new external_value(PARAM_RAW, 'MIME type'),
                'isimage' => new external_value(PARAM_INT, '1 when the file is shown as an image'),
                'fileurl' => new external_value(PARAM_RAW, 'Web service pluginfile URL (append ?token=)'),
                'questionid' => new external_value(PARAM_INT, 'Question the file belongs to'),
            ]),
            'Files shown with the question'
        );
    }

    /** True when the user may read question files in the question category context. */
    public static function can_read(\context $context): bool {
        return has_capability('local/fivedays:viewquestions', $context)
            || has_capability('local/fivedays:createquestion', $context);
    }

    /**
     * Validates the attachments JSON and resolves every source, so a bad entry fails before the
     * question row is inserted. Returns entries for save().
     *
     * @return array<int, array{filename: string, file: ?\stored_file, content: ?string}>
     */
    public static function parse(string $json): array {
        global $USER;

        if (trim($json) === '') {
            return [];
        }
        $rows = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($rows) || !array_is_list($rows)) {
            throw new \moodle_exception('attachmentinvalid', 'local_fivedays', '', 'questionfiles must be a JSON array.');
        }
        if (count($rows) > self::MAX_FILES) {
            throw new \moodle_exception('toomanyattachments', 'local_fivedays', '', self::MAX_FILES);
        }

        $fs = get_file_storage();
        $draftcontextid = \context_user::instance($USER->id)->id;
        $seen = [];
        $entries = [];
        foreach ($rows as $index => $row) {
            $number = $index + 1;
            if (!is_array($row) || !is_string($row['filename'] ?? null)) {
                throw self::invalid("Attachment {$number} needs a filename.");
            }
            $filename = clean_param($row['filename'], PARAM_FILE);
            if ($filename === '' || $filename === '.' || $filename === '..') {
                throw self::invalid("Attachment {$number} has an invalid filename.");
            }
            $key = \core_text::strtolower($filename);
            if (isset($seen[$key])) {
                throw self::invalid("The filename {$filename} is used more than once.");
            }
            $seen[$key] = true;

            $sources = array_filter(
                array_intersect_key($row, array_flip(['draftitemid', 'content', 'fromquestionid'])),
                fn($value) => $value !== null
            );
            if (count($sources) !== 1) {
                throw self::invalid("Attachment {$filename} needs exactly one of draftitemid, content or fromquestionid.");
            }

            $file = null;
            $content = null;
            if (isset($sources['draftitemid'])) {
                $itemid = self::positive_int($sources['draftitemid'], $filename);
                $file = $fs->get_file($draftcontextid, 'user', 'draft', $itemid, '/', $filename);
            } else if (isset($sources['fromquestionid'])) {
                $questionid = self::positive_int($sources['fromquestionid'], $filename);
                $file = self::source_file($questionid, $filename);
            } else {
                $content = self::decode($sources['content'], $filename);
            }

            if ($content === null && (!$file || $file->is_directory())) {
                throw new \moodle_exception('attachmentmissing', 'local_fivedays', '', $filename);
            }
            $size = $content !== null ? strlen($content) : $file->get_filesize();
            if ($size > self::MAX_BYTES) {
                throw new \moodle_exception('attachmenttoolarge', 'local_fivedays', '', $filename);
            }
            if ($size === 0) {
                throw self::invalid("The file {$filename} is empty.");
            }

            $entries[] = ['filename' => $filename, 'file' => $file, 'content' => $content];
        }

        return $entries;
    }

    /**
     * Writes the files into the new question's area and appends the marker block to its text.
     * Returns the `files` list of the question.
     */
    public static function save(array $entries, int $contextid, int $questionid, string $questiontext): array {
        global $DB, $USER;

        if (!$entries) {
            return [];
        }

        $fs = get_file_storage();
        $stored = [];
        foreach ($entries as $entry) {
            $record = [
                'contextid' => $contextid,
                'component' => 'question',
                'filearea' => 'questiontext',
                'itemid' => $questionid,
                'filepath' => '/',
                'filename' => $entry['filename'],
                'userid' => $USER->id,
            ];
            $stored[] = $entry['content'] !== null
                ? $fs->create_file_from_string($record, $entry['content'])
                : $fs->create_file_from_storedfile($record, $entry['file']);
        }

        $DB->set_field('question', 'questiontext', $questiontext . self::block($stored), ['id' => $questionid]);

        return self::list_files($contextid, $questionid);
    }

    /** Removes the marker block from the end of a question text. */
    public static function strip(string $text): string {
        return preg_replace(self::BLOCK_PATTERN, '', $text) ?? $text;
    }

    /** Files shown with one question, in the order of the marker block. */
    public static function list_files(int $contextid, int $questionid): array {
        global $DB;

        $text = (string)$DB->get_field('question', 'questiontext', ['id' => $questionid]);
        $rows = $DB->get_records_select(
            'files',
            "contextid = :contextid AND component = 'question' AND filearea = 'questiontext'
                AND itemid = :itemid AND filename <> '.'",
            ['contextid' => $contextid, 'itemid' => $questionid],
            'id ASC',
            'id, itemid, filename, filesize, mimetype'
        );

        return self::ordered($rows, $text, $contextid, $questionid);
    }

    /**
     * Files of many questions with one query. $texts maps question id to its raw question text;
     * the question category contexts are resolved in the same query.
     *
     * @return array<int, array> question id => files (every id of $texts is present)
     */
    public static function list_for_questions(array $texts): array {
        global $DB;

        $result = array_fill_keys(array_keys($texts), []);
        $withblock = array_keys(array_filter($texts, fn($text) => str_contains((string)$text, 'data-fivedays-qfiles')));
        if (!$withblock) {
            return $result;
        }

        [$insql, $params] = $DB->get_in_or_equal($withblock, SQL_PARAMS_NAMED, 'qid');
        $rows = $DB->get_records_sql(
            "SELECT f.id, f.itemid, f.filename, f.filesize, f.mimetype, f.contextid
               FROM {files} f
               JOIN {question_versions} qv ON qv.questionid = f.itemid
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid AND qc.contextid = f.contextid
              WHERE f.component = 'question' AND f.filearea = 'questiontext'
                AND f.filename <> '.' AND f.itemid {$insql}
           ORDER BY f.id ASC",
            $params
        );

        $byquestion = [];
        foreach ($rows as $row) {
            $byquestion[(int)$row->itemid][] = $row;
        }
        foreach ($byquestion as $questionid => $questionrows) {
            $contextid = (int)reset($questionrows)->contextid;
            $result[$questionid] = self::ordered($questionrows, (string)$texts[$questionid], $contextid, $questionid);
        }

        return $result;
    }

    private static function ordered(array $rows, string $text, int $contextid, int $questionid): array {
        $byname = [];
        foreach ($rows as $row) {
            $byname[$row->filename] = $row;
        }

        $files = [];
        foreach (self::block_filenames($text) as $filename) {
            if (!isset($byname[$filename])) {
                continue;
            }
            $row = $byname[$filename];
            $files[] = [
                'filename' => $filename,
                'filesize' => (int)$row->filesize,
                'mimetype' => (string)$row->mimetype,
                'isimage' => self::is_image((string)$row->mimetype) ? 1 : 0,
                'fileurl' => \moodle_url::make_webservice_pluginfile_url(
                    $contextid,
                    'local_fivedays',
                    'qfiles',
                    $questionid,
                    '/',
                    $filename
                )->out(false),
                'questionid' => $questionid,
            ];
        }

        return $files;
    }

    /** File names referenced by the marker block, in order. */
    private static function block_filenames(string $text): array {
        if (!preg_match(self::BLOCK_PATTERN, $text, $block)) {
            return [];
        }
        preg_match_all('~@@PLUGINFILE@@/([^"]+)"~', $block[1], $matches);

        return array_map('rawurldecode', $matches[1]);
    }

    /** @param \stored_file[] $stored */
    private static function block(array $stored): string {
        $html = self::BLOCK_OPEN;
        foreach ($stored as $file) {
            $name = $file->get_filename();
            $url = '@@PLUGINFILE@@/' . rawurlencode($name);
            $text = s($name);
            $html .= self::is_image($file->get_mimetype())
                ? "<p><img src=\"{$url}\" alt=\"{$text}\" class=\"img-fluid\"></p>"
                : "<p><a href=\"{$url}\">{$text}</a></p>";
        }

        return $html . '</div>';
    }

    private static function is_image(string $mimetype): bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        return file_mimetype_in_typegroup($mimetype, 'web_image');
    }

    /** The file of an existing question, after checking the caller may read that question's files. */
    private static function source_file(int $questionid, string $filename): ?\stored_file {
        global $DB;

        $contextid = $DB->get_field_sql(
            "SELECT qc.contextid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE qv.questionid = ?",
            [$questionid],
            IGNORE_MULTIPLE
        );
        if ($contextid === false) {
            throw new \moodle_exception('attachmentmissing', 'local_fivedays', '', $filename);
        }

        $context = \context::instance_by_id((int)$contextid);
        if (!self::can_read($context)) {
            require_capability('local/fivedays:viewquestions', $context);
        }

        $file = get_file_storage()->get_file((int)$contextid, 'question', 'questiontext', $questionid, '/', $filename);

        return $file ?: null;
    }

    private static function decode(mixed $value, string $filename): string {
        if (!is_string($value)) {
            throw self::invalid("The content of {$filename} must be a base64 string.");
        }
        if (strlen($value) > intdiv(self::MAX_BYTES * 4, 3) + 8) {
            throw new \moodle_exception('attachmenttoolarge', 'local_fivedays', '', $filename);
        }
        $bytes = base64_decode($value, true);
        if ($bytes === false) {
            throw self::invalid("The content of {$filename} is not valid base64.");
        }

        return $bytes;
    }

    private static function positive_int(mixed $value, string $filename): int {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw self::invalid("The source id of {$filename} must be a whole number.");
        }
        $number = (int)$value;
        if ($number < 1) {
            throw self::invalid("The source id of {$filename} must be a whole number.");
        }

        return $number;
    }

    private static function invalid(string $message): \moodle_exception {
        return new \moodle_exception('attachmentinvalid', 'local_fivedays', '', $message);
    }
}
