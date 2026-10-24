<?php
// Moodle config for the Docker stack. Every value comes from the environment (compose.yaml), so the
// same image runs locally, in rehearsals and on prod. Settings that must never drift are plain $CFG
// lines: Moodle shows them greyed out in the admin UI and ignores the DB value.

unset($CFG);
global $CFG;
$CFG = new stdClass();

$env = static function (string $name, ?string $default = null): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default === null) {
            throw new RuntimeException("Environment variable {$name} is not set");
        }
        return $default;
    }
    return $value;
};

// Only the one-off `transfer` container sets MOODLE_DB_TYPE=mariadb: dbtransfer reads the old MariaDB
// through this config and writes the new Postgres named on its command line.
$CFG->dbtype = $env('MOODLE_DB_TYPE', 'pgsql');
$CFG->dblibrary = 'native';
$CFG->dbhost = $env('MOODLE_DB_HOST', 'db');
$CFG->dbname = $env('MOODLE_DB_NAME', 'moodle');
$CFG->dbuser = $env('MOODLE_DB_USER', 'moodle');
$CFG->dbpass = $env('MOODLE_DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport' => (int) $env('MOODLE_DB_PORT', $CFG->dbtype === 'pgsql' ? '5432' : '3306'),
    'dbsocket' => '',
];
if ($CFG->dbtype === 'mariadb') {
    $CFG->dboptions['dbcollation'] = 'utf8mb4_unicode_ci';
}

$CFG->wwwroot = $env('MOODLE_WWWROOT');
$CFG->dataroot = '/var/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02777;

// Caddy sends paths that match no file to /r.php.
$CFG->routerconfigured = true;

$CFG->pathtophp = '/usr/local/bin/php';
$CFG->pathtogs = '/usr/bin/gs';
$CFG->preventexecpath = true;

// PHPUnit (the `test` image) runs on a vanilla site: tests set these through set_config() and use the
// default file system and sessions.
if (getenv('MOODLE_PHPUNIT') === '1') {
    $CFG->phpunit_prefix = 'phpu_';
    $CFG->phpunit_dataroot = '/var/phpunitdata';
}
if (!defined('PHPUNIT_TEST')) {
    $CFG->enablewebservices = 1;
    $CFG->enablemobilewebservice = 1;
    $CFG->webserviceprotocols = 'rest';
    $CFG->auth = 'email,oauth2';
    $CFG->maxbytes = 20971520;
    $CFG->airnotifierurl = 'https://messages.moodle.net';
    $CFG->airnotifierport = 443;
    $CFG->airnotifiermobileappname = 'com.moodle.moodlemobile';
    $CFG->airnotifierappname = 'commoodlemoodlemobile';

    $CFG->session_handler_class = '\core\session\redis';
    $CFG->session_redis_host = $env('MOODLE_REDIS_HOST', 'redis');
    $CFG->session_redis_port = 6379;
    $CFG->session_redis_auth = $env('MOODLE_REDIS_PASSWORD');
    $CFG->session_redis_prefix = 'sess_';
    $CFG->session_redis_acquire_lock_timeout = 120;
    $CFG->session_redis_lock_expire = 7200;

    // Blocks email and push (message_output_airnotifier::send_message checks it). Local and rehearsals only.
    if ($env('MOODLE_NOEMAIL', '0') === '1') {
        $CFG->noemailever = true;
    }

    // D15–D17: uploaded files in the Lightsail bucket, served through Moodle (no signed links).
    // Names and defaults: tool_objectfs classes/local/manager.php get_objectfs_config(). The defaults keep
    // files under 10 KB or younger than 7 days local only; both are 0 so every file reaches the bucket (D18).
    // "Delete local objects" stays off until the day-14 check.
    if ($env('MOODLE_BUCKET', '-') !== '-') {
        $CFG->alternative_file_system_class = '\tool_objectfs\s3_file_system';
        $CFG->forced_plugin_settings['tool_objectfs'] = [
            'enabletasks' => 1,
            'filesystem' => '\tool_objectfs\s3_file_system',
            's3_usesdkcreds' => 0,
            's3_key' => $env('MOODLE_BUCKET_KEY'),
            's3_secret' => $env('MOODLE_BUCKET_SECRET'),
            's3_bucket' => $env('MOODLE_BUCKET'),
            's3_region' => 'eu-central-1',
            'sizethreshold' => 0,
            'minimumage' => 0,
            'enablepresignedurls' => 0,
            'deletelocal' => 0,
        ];
        // `just test` only: a local S3 stand-in instead of Lightsail.
        if ($env('MOODLE_BUCKET_ENDPOINT', '-') !== '-') {
            $CFG->forced_plugin_settings['tool_objectfs']['s3_base_url'] = $env('MOODLE_BUCKET_ENDPOINT');
        }
    }
}

require_once(__DIR__ . '/lib/setup.php');
