<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy class for requesting user data.
 *
 * @package    gradingform_fivedays
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_fivedays\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy class for requesting user data.
 *
 * Context discovery, user listing and context/user deletion are driven by core_grading,
 * which calls back into this provider per grading instance.
 *
 * @package    gradingform_fivedays
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_grading\privacy\gradingform_provider_v2 {

    /**
     * Returns meta data about this system.
     *
     * @param  collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('gradingform_fivedays_fillings', [
            'instanceid' => 'privacy:metadata:instanceid',
            'criterionid' => 'privacy:metadata:criterionid',
            'levelid' => 'privacy:metadata:levelid',
            'score' => 'privacy:metadata:score',
            'remark' => 'privacy:metadata:remark',
        ], 'privacy:metadata:fillingssummary');
        return $collection;
    }

    /**
     * Export user data relating to an instance ID.
     *
     * @param  \context $context Context to use with the export writer.
     * @param  int $instanceid The instance ID to export data for.
     * @param  array $subcontext The directory to export this data to.
     */
    public static function export_gradingform_instance_data(\context $context, int $instanceid, array $subcontext) {
        global $DB;
        // Left join levels: a filling may carry only a custom score.
        $sql = "SELECT ff.id, fc.description, fl.definition, fl.score AS levelscore, ff.score, ff.remark
                  FROM {gradingform_fivedays_fillings} ff
                  JOIN {gradingform_fivedays_criteria} fc ON fc.id = ff.criterionid
             LEFT JOIN {gradingform_fivedays_levels} fl ON fl.id = ff.levelid
                 WHERE ff.instanceid = :instanceid
              ORDER BY fc.sortorder";
        $records = $DB->get_records_sql($sql, ['instanceid' => $instanceid]);
        if ($records) {
            $data = [];
            foreach ($records as $record) {
                unset($record->id);
                $data[] = $record;
            }
            $subcontext = array_merge($subcontext, [get_string('fivedays', 'gradingform_fivedays'), $instanceid]);
            \core_privacy\local\request\writer::with_context($context)->export_data($subcontext, (object) $data);
        }
    }

    /**
     * Deletes all user data related to the provided instance IDs.
     *
     * @param  array  $instanceids The instance IDs to delete information from.
     */
    public static function delete_gradingform_for_instances(array $instanceids) {
        global $DB;
        $DB->delete_records_list('gradingform_fivedays_fillings', 'instanceid', $instanceids);
    }
}
