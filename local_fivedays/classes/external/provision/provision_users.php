<?php
namespace local_activity_utils\external\provision;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\provision_helper;

class provision_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'email' => new external_value(PARAM_EMAIL, 'Email'),
                    'firstname' => new external_value(PARAM_NOTAGS, 'First name'),
                    'lastname' => new external_value(PARAM_NOTAGS, 'Last name'),
                ])
            ),
        ]);
    }

    public static function execute(array $users): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'users' => $users,
        ]);
        provision_helper::require_system_capability();

        $issuer = provision_helper::require_google_issuer();
        $service = provision_helper::ensure_rest_service();
        $result = [];

        foreach ($params['users'] as $user) {
            $moodleuser = provision_helper::ensure_oauth_user(
                $user['email'],
                $user['firstname'],
                $user['lastname'],
                $issuer
            );
            $result[] = [
                'email' => $user['email'],
                'moodleUserId' => (int) $moodleuser->id,
                'token' => provision_helper::ensure_token($service, (int) $moodleuser->id),
            ];
        }

        return ['users' => $result];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'email' => new external_value(PARAM_EMAIL, 'Email'),
                    'moodleUserId' => new external_value(PARAM_INT, 'Moodle user id'),
                    'token' => new external_value(PARAM_RAW, 'Permanent web-service token'),
                ])
            ),
        ]);
    }
}
