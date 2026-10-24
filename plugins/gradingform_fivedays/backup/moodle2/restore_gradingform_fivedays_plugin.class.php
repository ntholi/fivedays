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
 * Support for restore API.
 *
 * @package    gradingform_fivedays
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores the FiveDays rubric specific data from grading.xml file.
 *
 * @package    gradingform_fivedays
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_gradingform_fivedays_plugin extends restore_gradingform_plugin {

    /**
     * Declares the FiveDays XML paths attached to the form definition element.
     *
     * @return restore_path_element[]
     */
    protected function define_definition_plugin_structure() {
        return [
            new restore_path_element('gradingform_fivedays_criterion',
                $this->get_pathfor('/fivedayscriteria/fivedayscriterion')),
            new restore_path_element('gradingform_fivedays_level',
                $this->get_pathfor('/fivedayscriteria/fivedayscriterion/fivedayslevels/fivedayslevel')),
        ];
    }

    /**
     * Declares the FiveDays XML paths attached to the form instance element.
     *
     * @return restore_path_element[]
     */
    protected function define_instance_plugin_structure() {
        return [
            new restore_path_element('gradingform_fivedays_filling',
                $this->get_pathfor('/fivedaysfillings/fivedaysfilling')),
        ];
    }

    /**
     * Processes criterion element data.
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_fivedays_criterion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->definitionid = $this->get_new_parentid('grading_definition');

        $newid = $DB->insert_record('gradingform_fivedays_criteria', $data);
        $this->set_mapping('gradingform_fivedays_criterion', $oldid, $newid);
    }

    /**
     * Processes level element data.
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_fivedays_level($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->criterionid = $this->get_new_parentid('gradingform_fivedays_criterion');

        $newid = $DB->insert_record('gradingform_fivedays_levels', $data);
        $this->set_mapping('gradingform_fivedays_level', $oldid, $newid);
    }

    /**
     * Processes filling element data.
     *
     * @param stdClass|array $data
     */
    public function process_gradingform_fivedays_filling($data) {
        global $DB;

        $data = (object)$data;
        $data->instanceid = $this->get_new_parentid('grading_instance');
        $data->criterionid = $this->get_mappingid('gradingform_fivedays_criterion', $data->criterionid);

        // A filling may hold only a custom score, so levelid is optional.
        $levelid = null;
        if (!empty($data->levelid)) {
            $levelid = $this->get_mappingid('gradingform_fivedays_level', $data->levelid) ?: null;
        }
        $data->levelid = $levelid;

        if (!empty($data->criterionid)) {
            $DB->insert_record('gradingform_fivedays_fillings', $data);
        }
    }
}
