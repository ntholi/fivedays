<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_fivedays_install() {
    // Moodle installs this plugin's capabilities only after install.php; the overseer role needs them now.
    update_capabilities('local_fivedays');
    \local_fivedays\overseer_role::sync();

    return true;
}
