<?php
// php /contract/cleanup.php --courses=<id,...> [--users=<id,...>]: contract_cleanup() for courses that
// bin/cutover's upload QA created through the web service. Exit 1 on any problem.

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/cleanup-lib.php');

[$options] = cli_get_params(['courses' => '', 'users' => '']);
$ids = static fn(string $list): array => array_filter(array_map('intval', explode(',', $list)));
exit(contract_cleanup($ids($options['courses']), $ids($options['users'])) ? 1 : 0);
