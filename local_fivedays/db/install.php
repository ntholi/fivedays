<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Carries quiz attempt feedback over from local_activity_utils, which this
 * plugin replaces. Uninstalling the old plugin drops its table, so the copy
 * must happen here, before that uninstall. Role permissions carry over through
 * 'clonepermissionsfrom' in db/access.php.
 */
function xmldb_local_fivedays_install() {
    global $DB;

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('quiz_attempt_feedback')) {
        return true;
    }

    $rows = $DB->get_recordset('quiz_attempt_feedback', null, 'id');
    foreach ($rows as $row) {
        unset($row->id);
        $DB->insert_record('local_fivedays_quizfeedback', $row, false);
    }
    $rows->close();

    return true;
}
