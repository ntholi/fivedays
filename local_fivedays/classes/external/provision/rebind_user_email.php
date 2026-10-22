<?php
namespace local_activity_utils\external\provision;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\provision_helper;

class rebind_user_email extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fromemail' => new external_value(PARAM_EMAIL, 'Current email'),
            'toemail' => new external_value(PARAM_EMAIL, 'New email'),
        ]);
    }

    public static function execute(string $fromemail, string $toemail): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'fromemail' => $fromemail,
            'toemail' => $toemail,
        ]);
        provision_helper::require_system_capability();

        $issuer = provision_helper::require_google_issuer();
        $service = provision_helper::ensure_rest_service();
        $user = provision_helper::rebind_oauth_user_email(
            $params['fromemail'],
            $params['toemail'],
            $issuer
        );

        return [
            'email' => $user->email,
            'moodleUserId' => (int) $user->id,
            'token' => provision_helper::ensure_token($service, (int) $user->id),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'email' => new external_value(PARAM_EMAIL, 'Email'),
            'moodleUserId' => new external_value(PARAM_INT, 'Moodle user id'),
            'token' => new external_value(PARAM_RAW, 'Permanent web-service token'),
        ]);
    }
}
