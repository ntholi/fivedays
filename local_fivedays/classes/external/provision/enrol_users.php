<?php
namespace local_activity_utils\external\provision;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\provision_helper;

class enrol_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'enrolments' => new external_multiple_structure(
                new external_single_structure([
                    'email' => new external_value(PARAM_EMAIL, 'User email'),
                    'courseid' => new external_value(PARAM_INT, 'Course id'),
                    'roleshortname' => new external_value(PARAM_ALPHANUMEXT, 'editingteacher or student'),
                ])
            ),
        ]);
    }

    public static function execute(array $enrolments): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'enrolments' => $enrolments,
        ]);
        provision_helper::require_system_capability();

        $enrolled = 0;
        foreach ($params['enrolments'] as $enrolment) {
            $role = $enrolment['roleshortname'];
            if ($role !== 'editingteacher' && $role !== 'student') {
                throw new \invalid_parameter_exception('roleshortname must be editingteacher or student');
            }
            provision_helper::enrol_user_by_email(
                $enrolment['email'],
                (int) $enrolment['courseid'],
                $role
            );
            $enrolled++;
        }

        return ['enrolled' => $enrolled];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enrolled' => new external_value(PARAM_INT, 'Number of enrolments applied'),
        ]);
    }
}
