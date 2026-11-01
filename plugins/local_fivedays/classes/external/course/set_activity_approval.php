<?php
namespace local_fivedays\external\course;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\activity_approval;
use local_fivedays\provision_helper;

class set_activity_approval extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module ID')
            ),
            'approved' => new external_value(PARAM_BOOL, 'Whether students may see these activities'),
        ]);
    }

    public static function execute(array $cmids, bool $approved): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmids' => $cmids,
            'approved' => $approved,
        ]);
        provision_helper::require_system_capability();

        $cmids = array_values(array_unique(array_map('intval', $params['cmids'])));
        $existing = [];
        if ($cmids) {
            [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
            $existing = $DB->get_fieldset_select('course_modules', 'id', "id $insql AND deletioninprogress = 0", $inparams);
        }
        $existing = array_map('intval', $existing);
        activity_approval::set($existing, (bool) $params['approved']);
        if (!$params['approved']) {
            foreach ($existing as $cmid) {
                activity_approval::enforce($cmid);
            }
        }

        return [
            'success' => true,
            'updated' => $existing,
            'missing' => array_values(array_diff($cmids, $existing)),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Always true'),
            'updated' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module ID recorded')
            ),
            'missing' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Requested course module ID that does not exist')
            ),
        ]);
    }
}
