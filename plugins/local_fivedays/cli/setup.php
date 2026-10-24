<?php
// Site setup from the environment, run by the Docker `migrate` service after every install or upgrade.
// Safe to rerun: each item prints ok (already right), changed or FAIL, and the script exits 1 on any FAIL.
// It never touches user_preferences or any *_locked notification setting, so students' choices stay.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/message/lib.php');
require_once($CFG->dirroot . '/message/output/airnotifier/classes/manager.php');
require_once($CFG->dirroot . '/cache/stores/redis/lib.php');

use core_cache\config_writer;
use core_cache\store;
use local_fivedays\provision_helper;

$failed = false;
$report = static function (string $status, string $item, string $detail = '') use (&$failed): void {
    if ($status === 'FAIL') {
        $failed = true;
    }
    cli_writeln(sprintf('%-8s %s%s', $status, $item, $detail === '' ? '' : ": {$detail}"));
};
$env = static fn(string $name): string => (string) getenv($name);
$isprod = $env('MOODLE_ALLOW_INSTALL') !== '1';

\core\session\manager::set_user(get_admin());

// Web-service user (D21): a Manager-archetype system role, not a site admin.
$wsrole = $DB->get_record('role', ['shortname' => 'fivedays_ws']);
if (!$wsrole) {
    $roleid = create_role('FiveDays web service', 'fivedays_ws',
        'Used by registry-web for provisioning and for writing into classes the lecturer is not assigned to.', 'manager');
    reset_role_capabilities($roleid);
    set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
    $wsrole = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
    $report('changed', 'fivedays_ws role', 'created from the Manager archetype');
} else {
    $report('ok', 'fivedays_ws role');
}
$systemcontext = context_system::instance();
// webservice/rest:use has no archetypes, so the Manager defaults don't include it.
if (!$DB->record_exists('role_capabilities', ['roleid' => $wsrole->id, 'capability' => 'webservice/rest:use',
        'contextid' => $systemcontext->id, 'permission' => CAP_ALLOW])) {
    assign_capability('webservice/rest:use', CAP_ALLOW, $wsrole->id, $systemcontext->id, true);
    $report('changed', 'fivedays_ws role', 'allowed webservice/rest:use');
}

// reset_role_capabilities() copies capabilities only; without the Manager archetype's allow rows the
// role can't enrol anyone (enrol_manual_enrol_users throws wsusercannotassign).
$allowed = 0;
foreach (['assign' => 'allowassign', 'override' => 'allowoverride', 'switch' => 'allowswitch', 'view' => 'allowview']
        as $type => $column) {
    foreach (get_default_role_archetype_allows($type, 'manager') as $targetroleid) {
        if (!$DB->record_exists("role_allow_{$type}", ['roleid' => $wsrole->id, $column => $targetroleid])) {
            ("core_role_set_{$type}_allowed")($wsrole->id, $targetroleid);
            $allowed++;
        }
    }
}
if ($allowed) {
    $report('changed', 'fivedays_ws role', "added {$allowed} Manager allow-assign/override/switch/view rows");
}

$wsuser = $DB->get_record('user', ['username' => 'fivedays_ws', 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
if (!$wsuser) {
    $userid = user_create_user((object) [
        'username' => 'fivedays_ws',
        'auth' => 'manual',
        'password' => random_string(40) . 'aA1!',
        'firstname' => 'FiveDays',
        'lastname' => 'Web service',
        'email' => 'fivedays_ws@' . parse_url($CFG->wwwroot, PHP_URL_HOST),
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ], true, false);
    $wsuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $report('changed', 'fivedays_ws user', 'created');
} else {
    $report('ok', 'fivedays_ws user');
}
if (!user_has_role_assignment($wsuser->id, $wsrole->id, $systemcontext->id)) {
    role_assign($wsrole->id, $wsuser->id, $systemcontext->id);
    $report('changed', 'fivedays_ws user', 'given the fivedays_ws role in the system context');
}

$service = provision_helper::rest_service();
if ($DB->record_exists('external_tokens', ['userid' => $wsuser->id, 'externalserviceid' => $service->id,
        'tokentype' => EXTERNAL_TOKEN_PERMANENT])) {
    $report('ok', 'fivedays_ws token');
} else {
    $token = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $wsuser->id, $systemcontext,
        0, '', 'registry-web');
    $report('changed', 'fivedays_ws token', "created, set it as MOODLE_TOKEN in registry-web (shown once): {$token}");
}

// Google login. Prod updates the existing issuer (matched by name), so linked logins keep working.
$clientid = $env('MOODLE_GOOGLE_CLIENT_ID');
$clientsecret = $env('MOODLE_GOOGLE_CLIENT_SECRET');
if ($clientid === '' || $clientsecret === '') {
    $report($isprod ? 'FAIL' : 'ok', 'Google login', 'no client ID/secret in the environment, left as it is');
} else {
    $issuer = provision_helper::find_google_issuer();
    $current = $issuer && $issuer->get('clientid') === $clientid && $issuer->get('clientsecret') === $clientsecret
        && (int) $issuer->get('enabled') === 1 && (int) $issuer->get('requireconfirmation') === 0;
    if ($current) {
        $report('ok', 'Google login');
    } else {
        provision_helper::ensure_google_login($clientid, $clientsecret);
        $report('changed', 'Google login', $issuer ? 'issuer updated' : 'issuer created');
    }
}

// Redis application cache. Sessions use Redis through config.php.
$storename = 'fivedays_redis';
$storeconfig = [
    'server' => $env('MOODLE_REDIS_HOST') ?: 'redis',
    'prefix' => 'muc_',
    'password' => $env('MOODLE_REDIS_PASSWORD'),
    'serializer' => Redis::SERIALIZER_PHP,
    'compressor' => cachestore_redis::COMPRESSOR_NONE,
    'connectiontimeout' => 3,
    'readtimeout' => 3,
    'encryption' => false,
    'cafile' => '',
    'clustermode' => false,
];
try {
    $writer = config_writer::instance();
    $stores = $writer->get_all_stores();
    if (!isset($stores[$storename])) {
        $writer->add_store_instance($storename, 'redis', $storeconfig);
        $report('changed', 'Redis cache store', 'added');
    } else if ($stores[$storename]['configuration'] != $storeconfig) {
        $writer->edit_store_instance($storename, 'redis', $storeconfig);
        $report('changed', 'Redis cache store', 'settings updated');
    } else {
        $report('ok', 'Redis cache store');
    }

    $firststore = [];
    foreach ($writer->get_mode_mappings() as $mapping) {
        $firststore[$mapping['mode']] ??= $mapping['store'];
    }
    if (($firststore[store::MODE_APPLICATION] ?? null) === $storename) {
        $report('ok', 'application cache mapping');
    } else {
        $writer->set_mode_mappings([
            store::MODE_APPLICATION => [$storename],
            store::MODE_SESSION => [$firststore[store::MODE_SESSION] ?? 'default_session'],
            store::MODE_REQUEST => [$firststore[store::MODE_REQUEST] ?? 'default_request'],
        ]);
        $report('changed', 'application cache mapping', "now {$storename}");
    }
} catch (Throwable $e) {
    $report('FAIL', 'Redis cache', $e->getMessage());
}

// Push notifications (D11): phone delivery on by default for every notification type.
$airnotifier = \core_message\api::get_message_processor('airnotifier');
if (!$airnotifier) {
    $report('FAIL', 'push output', 'message_airnotifier is not installed');
} else {
    if ((int) $airnotifier->enabled === 1) {
        $report('ok', 'push output enabled');
    } else {
        \core_message\api::update_processor_status($airnotifier, 1);
        $report('changed', 'push output enabled');
    }

    $added = 0;
    // Every provider row, also those of disabled plugins (get_message_providers() skips them), so
    // enabling BigBlueButton later needs no rerun.
    foreach ($DB->get_records('message_providers') as $provider) {
        $name = "message_provider_{$provider->component}_{$provider->name}_enabled";
        $outputs = array_filter(explode(',', (string) get_config('message', $name)));
        if (!in_array('airnotifier', $outputs, true)) {
            $outputs[] = 'airnotifier';
            set_config($name, implode(',', $outputs), 'message');
            $added++;
        }
    }
    $report($added ? 'changed' : 'ok', 'push on by default', $added ? "added to {$added} notification types" : '');

    if ($env('MOODLE_PUSH') === '1') {
        $manager = new message_airnotifier_manager();
        if ($CFG->wwwroot !== 'https://luct.click' || !empty($CFG->noemailever)) {
            $report('FAIL', 'push access key', 'MOODLE_PUSH=1 needs wwwroot https://luct.click and email/push not blocked');
        } else if (!empty($CFG->airnotifieraccesskey)) {
            $report('ok', 'push access key');
        } else if (!\core\hub\registration::is_registered()) {
            $report('FAIL', 'push access key',
                'Site not registered with moodle.org; register in Site administration → Registration, then rerun');
        } else if ($key = $manager->request_accesskey()) {
            set_config('airnotifieraccesskey', $key);
            $report('changed', 'push access key', 'requested and saved');
        } else {
            $report('FAIL', 'push access key', 'messages.moodle.net returned no key');
        }

        foreach ($manager->check_configuration() as $result) {
            $ok = $result->get_status() === \core\check\result::OK;
            $report($ok ? 'ok' : 'FAIL', 'push check', strip_tags($result->get_summary()) .
                ($ok ? '' : ' (' . $result->get_status() . ': ' . strip_tags($result->get_details()) . ')'));
        }
    }
}

exit($failed ? 1 : 0);
