<?php
namespace local_activity_utils\external\provision;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\provision_helper;

class ensure_google_login extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'clientid' => new external_value(PARAM_RAW, 'Google OAuth client id'),
            'clientsecret' => new external_value(PARAM_RAW, 'Google OAuth client secret'),
        ]);
    }

    public static function execute(string $clientid, string $clientsecret): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'clientid' => $clientid,
            'clientsecret' => $clientsecret,
        ]);
        provision_helper::require_system_capability();

        return [
            'ok' => provision_helper::ensure_google_login($params['clientid'], $params['clientsecret']),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_INT, '1 when Google-only login is configured'),
        ]);
    }
}
