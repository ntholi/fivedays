<?php
defined('MOODLE_INTERNAL') || die();

/** Restores the approval block of an unapproved activity onto its copy. */
class restore_local_fivedays_plugin extends restore_local_plugin {

    protected function define_module_plugin_structure() {
        return [new restore_path_element('local_fivedays_activity_approval', $this->get_pathfor('/activity_approval'))];
    }

    public function process_local_fivedays_activity_approval($data) {
        $data = (object) $data;
        // Only a block is copied: an approved copy is a new activity luct does not manage.
        if ((int) $data->approved !== 0) {
            return;
        }
        \local_fivedays\activity_approval::set([(int) $this->task->get_moduleid()], false);
    }

    /** The activity instance exists only once the whole restore ran, so it is hidden here. */
    public function after_restore_module() {
        \local_fivedays\activity_approval::enforce((int) $this->task->get_moduleid());
    }
}
