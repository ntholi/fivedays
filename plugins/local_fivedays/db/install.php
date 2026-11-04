<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_fivedays_install() {
    // Moodle installs this plugin's capabilities only after install.php; the FiveDays roles need them now.
    update_capabilities('local_fivedays');
    \local_fivedays\overseer_role::sync();
    \local_fivedays\auditor_role::sync();

    return true;
}
