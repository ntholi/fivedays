<?php
// just deploy / just rollback: proves the database runs the plugin code in the image. Runs in a one-off `app`
// container after migrate. A plugin whose version.php differs from its config_plugins version means Moodle skipped
// or half-ran its upgrade: new web-service functions, capabilities and tables are missing even though the login
// page answers 200.
// Both sides are read directly, not through core_plugin_manager or get_config(): those come from MUC (Redis), which
// keeps the pre-deploy values when migrate had nothing to upgrade and so purged no caches.
// Prints one line per FiveDays plugin; exit 1 when any present plugin's version.php and database versions differ.

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

$installed = $DB->get_records_menu('config_plugins', ['name' => 'version'], '', 'plugin, value');
$failed = false;
foreach (core_component::get_plugin_types() as $type => $unused) {
    foreach (core_component::get_plugin_list($type) as $name => $dir) {
        $component = "{$type}_{$name}";
        $plugin = new stdClass();
        $plugin->version = null;
        include($dir . '/version.php');
        $disk = (string) $plugin->version;
        $db = $installed[$component] ?? null;
        if ($disk === (string) $db) {
            if (str_ends_with($component, '_fivedays')) {
                echo "ok {$component} {$db}\n";
            }
            continue;
        }
        $failed = true;
        echo "FAIL {$component}: image has {$disk}, database has " . ($db ?? 'not installed') . "\n";
    }
}
exit($failed ? 1 : 0);
