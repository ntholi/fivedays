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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once(__DIR__ . '/fivedayseditor.php');
MoodleQuickForm::registerElementType('fivedayseditor', $CFG->dirroot . '/grade/grading/form/fivedays/fivedayseditor.php',
    'MoodleQuickForm_fivedayseditor');

class gradingform_fivedays_editfivedays extends moodleform {

    public function definition() {
        $form = $this->_form;

        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);

        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_LOCALURL);

        $form->addElement('text', 'name', get_string('name', 'gradingform_fivedays'), ['size' => 52, 'aria-required' => 'true']);
        $form->addRule('name', get_string('required'), 'required', null, 'client');
        $form->setType('name', PARAM_TEXT);

        $options = gradingform_fivedays_controller::description_form_field_options($this->_customdata['context']);
        $form->addElement('editor', 'description_editor', get_string('description', 'gradingform_fivedays'), null, $options);
        $form->setType('description_editor', PARAM_RAW);

        $choices = [];
        $choices[gradingform_controller::DEFINITION_STATUS_DRAFT] = html_writer::tag('span',
            get_string('statusdraft', 'core_grading'), ['class' => 'status draft']);
        $choices[gradingform_controller::DEFINITION_STATUS_READY] = html_writer::tag('span',
            get_string('statusready', 'core_grading'), ['class' => 'status ready']);
        $form->addElement('select', 'status', get_string('fivedaysstatus', 'gradingform_fivedays'), $choices)->freeze();

        $form->addElement('fivedayseditor', 'fivedays', get_string('fivedays', 'gradingform_fivedays'));
        $form->setType('fivedays', PARAM_RAW);

        $buttonarray = [];
        $buttonarray[] = &$form->createElement('submit', 'savefivedays', get_string('savefivedays', 'gradingform_fivedays'));
        if ($this->_customdata['allowdraft']) {
            $buttonarray[] = &$form->createElement('submit', 'savefivedaysdraft',
                get_string('savefivedaysdraft', 'gradingform_fivedays'));
        }
        $editbutton = &$form->createElement('submit', 'editfivedays', ' ');
        $editbutton->freeze();
        $buttonarray[] = &$editbutton;
        $buttonarray[] = &$form->createElement('cancel');
        $form->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $form->closeHeaderBefore('buttonar');
    }

    public function definition_after_data() {
        $form = $this->_form;
        $el = $form->getElement('status');
        if (!$el->_flagFrozen && $form->isSubmitted()) {
            $data = $form->getSubmitValues();
            if (isset($data['savefivedays'])) {
                $el->setValue(gradingform_controller::DEFINITION_STATUS_READY);
            } else if (isset($data['savefivedaysdraft'])) {
                $el->setValue(gradingform_controller::DEFINITION_STATUS_DRAFT);
            }
        }
        if ($form->isSubmitted() && $form->isNoSubmit()) {
            if ($this->need_confirm_regrading($this->_customdata['controller'])) {
                $this->findButton('savefivedays')->setValue(get_string('save', 'gradingform_fivedays'));
            }
        }
    }

    public function validation($data, $files) {
        $err = parent::validation($data, $files);
        $form = $this->_form;
        $fivedaysel = $form->getElement('fivedays');
        if ($fivedaysel->non_js_button_pressed($data['fivedays'])) {
            $err['fivedaysdummy'] = 1;
        } else if (isset($data['editfivedays'])) {
            $err['fivedaysdummy'] = 1;
        } else if (isset($data['savefivedays']) && $data['savefivedays']) {
            if ($fivedaysel->validate($data['fivedays']) !== false) {
                $err['fivedaysdummy'] = 1;
            }
        }
        return $err;
    }

    public function get_data() {
        $data = parent::get_data();
        if (!empty($data->savefivedays)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_READY;
        } else if (!empty($data->savefivedaysdraft)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_DRAFT;
        }
        return $data;
    }

    public function need_confirm_regrading($controller) {
        $dominated = [
            1 => [2, 3, 4, 5],
            2 => [3, 4, 5],
            3 => [4, 5],
            4 => [5],
            5 => [],
        ];
        $dominated[0] = array_keys($dominated);
        $data = $this->get_submitted_data();
        if (empty($data->savefivedays) && empty($data->savefivedaysdraft)) {
            return false;
        }
        if (!$controller->has_active_instances()) {
            return false;
        }
        $changelevel = $controller->update_or_check_fivedays($data);
        if ($changelevel == 0) {
            return false;
        }

        $form = $this->_form;
        foreach (['fivedays', 'name'] as $fieldname) {
            $el = &$form->getElement($fieldname);
            $el->freeze();
            $el->setPersistantFreeze(true);
            if ($fieldname == 'fivedays') {
                $el->add_regrade_confirmation($changelevel);
            }
        }

        $this->findButton('savefivedays')->setValue(get_string('continue'));
        $this->findButton('editfivedays')->setValue(get_string('backtoediting', 'gradingform_fivedays'));
        $this->findButton('editfivedays')->unfreeze();

        return true;
    }

    protected function findButton($name) {
        $form = $this->_form;
        $buttonar = $form->getElement('buttonar');
        $elements = $buttonar->getElements();
        foreach ($elements as $el) {
            if ($el->getName() == $name) {
                return $el;
            }
        }
        return null;
    }
}
