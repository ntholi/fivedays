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

    return true;
}
