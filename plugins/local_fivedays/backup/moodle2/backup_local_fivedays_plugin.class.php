<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Backs up an activity's luct approval flag, so a duplicate, restore or import of an unapproved
 * activity is not a shown copy (see restore_local_fivedays_plugin).
 */
class backup_local_fivedays_plugin extends backup_local_plugin {

    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $approval = new backup_nested_element('activity_approval', ['id'], ['approved']);
        $plugin->add_child($wrapper);
        $wrapper->add_child($approval);
        $approval->set_source_table('local_fivedays_activity_approval', ['cmid' => backup::VAR_MODID]);
        return $plugin;
    }
}
