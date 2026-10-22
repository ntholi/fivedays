<?php
namespace local_activity_utils\external\provision;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_activity_utils\provision_helper;

class ensure_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
            'categoryname' => new external_value(PARAM_TEXT, 'Category name (school code)'),
            'idnumber' => new external_value(PARAM_TEXT, 'Registry termId:semesterModuleId'),
        ]);
    }

    public static function execute(
        string $fullname,
        string $shortname,
        string $categoryname,
        string $idnumber
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'fullname' => $fullname,
            'shortname' => $shortname,
            'categoryname' => $categoryname,
            'idnumber' => $idnumber,
        ]);
        provision_helper::require_system_capability();

        return provision_helper::ensure_course(
            $params['fullname'],
            $params['shortname'],
            $params['categoryname'],
            $params['idnumber']
        );
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseId' => new external_value(PARAM_INT, 'Moodle course id'),
            'created' => new external_value(PARAM_INT, '1 if created, 0 if reused'),
            'categoryId' => new external_value(PARAM_INT, 'Moodle category id'),
        ]);
    }
}
