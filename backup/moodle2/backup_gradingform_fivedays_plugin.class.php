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
 * Support for backup API.
 *
 * @package    gradingform_fivedays
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines FiveDays rubric backup structures.
 *
 * @package    gradingform_fivedays
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_gradingform_fivedays_plugin extends backup_gradingform_plugin {

    /**
     * Declares FiveDays structures to append to the grading form definition.
     *
     * @return backup_plugin_element
     */
    protected function define_definition_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../method', 'fivedays');

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $criteria = new backup_nested_element('criteria');
        $criterion = new backup_nested_element('criterion', ['id'], [
            'sortorder', 'description', 'descriptionformat']);
        $levels = new backup_nested_element('levels');
        $level = new backup_nested_element('level', ['id'], [
            'score', 'definition', 'definitionformat']);

        $pluginwrapper->add_child($criteria);
        $criteria->add_child($criterion);
        $criterion->add_child($levels);
        $levels->add_child($level);

        $criterion->set_source_table('gradingform_fivedays_criteria',
                ['definitionid' => backup::VAR_PARENTID]);
        $level->set_source_table('gradingform_fivedays_levels',
                ['criterionid' => backup::VAR_PARENTID]);

        return $plugin;
    }

    /**
     * Declares FiveDays structures to append to the grading form instances.
     *
     * @return backup_plugin_element
     */
    protected function define_instance_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../../../method', 'fivedays');

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $fillings = new backup_nested_element('fillings');
        $filling = new backup_nested_element('filling', ['id'], [
            'criterionid', 'levelid', 'score', 'remark', 'remarkformat']);

        $pluginwrapper->add_child($fillings);
        $fillings->add_child($filling);

        // Join the criterion so orphaned fillings from another definition are not backed up.
        $filling->set_source_sql('SELECT ff.*
                FROM {gradingform_fivedays_fillings} ff
                JOIN {grading_instances} gi ON gi.id = ff.instanceid
                JOIN {gradingform_fivedays_criteria} fc ON fc.id = ff.criterionid AND gi.definitionid = fc.definitionid
                WHERE ff.instanceid = :instanceid',
                ['instanceid' => backup::VAR_PARENTID]);

        return $plugin;
    }
}
