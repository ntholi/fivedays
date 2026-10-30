<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_fivedays_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026102404) {
        // Hand the FiveDays service (created at runtime by provision_helper, component NULL) over to
        // db/services.php. This runs before external_update_descriptions(), which matches component
        // rows by name and would otherwise create a second row and fail on the shortname. Every
        // per-user token stays on the same row.
        $service = $DB->get_record('external_services', ['shortname' => 'fivedays']);
        if ($service) {
            if ($service->name !== 'FiveDays') {
                $found = $DB->get_records_menu('external_services', null, 'id', 'id, name');
                throw new coding_exception('local_fivedays: the fivedays service is named "' . $service->name .
                    '", expected "FiveDays". Services found: ' . json_encode($found));
            }
            if ($service->component !== 'local_fivedays') {
                if (!empty($service->component)) {
                    throw new coding_exception('local_fivedays: the fivedays service belongs to "' .
                        $service->component . '", not to this plugin.');
                }
                $DB->set_field('external_services', 'component', 'local_fivedays', ['id' => $service->id]);
            }
        }
        upgrade_plugin_savepoint(true, 2026102404, 'local', 'fivedays');
    }

    if ($oldversion < 2026103004) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_fivedays_reuse_copies');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reusekey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('courseid_reusekey', XMLDB_INDEX_UNIQUE, ['courseid', 'reusekey']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026103004, 'local', 'fivedays');
    }

    return true;
}
