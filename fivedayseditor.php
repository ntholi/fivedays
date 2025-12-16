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

require_once("HTML/QuickForm/input.php");

class MoodleQuickForm_fivedayseditor extends HTML_QuickForm_input {

    public $_helpbutton = '';
    protected $validationerrors = null;
    protected $wasvalidated = false;
    protected $nonjsbuttonpressed = false;
    protected $regradeconfirmation = false;

    public function __construct($elementName = null, $elementLabel = null, $attributes = null) {
        parent::__construct($elementName, $elementLabel, $attributes);
    }

    public function getHelpButton() {
        return $this->_helpbutton;
    }

    public function getElementTemplateType() {
        return 'default';
    }

    public function add_regrade_confirmation($changelevel) {
        $this->regradeconfirmation = $changelevel;
    }

    public function toHtml() {
        global $PAGE;
        $html = $this->_getTabs();
        $renderer = $PAGE->get_renderer('gradingform_fivedays');
        $data = $this->prepare_data(null, $this->wasvalidated);
        if (!$this->_flagFrozen) {
            $mode = gradingform_fivedays_controller::DISPLAY_EDIT_FULL;
            $module = [
                'name' => 'gradingform_fivedayseditor',
                'fullpath' => '/grade/grading/form/fivedays/js/fivedayseditor.js',
                'requires' => ['base', 'dom', 'event', 'event-touch', 'escape'],
                'strings' => [
                    ['confirmdeletecriterion', 'gradingform_fivedays'],
                    ['confirmdeletelevel', 'gradingform_fivedays'],
                    ['criterionempty', 'gradingform_fivedays'],
                    ['levelempty', 'gradingform_fivedays'],
                    ['criterionmoveup', 'gradingform_fivedays'],
                    ['criterionmovedown', 'gradingform_fivedays'],
                    ['criteriondelete', 'gradingform_fivedays'],
                    ['leveldelete', 'gradingform_fivedays'],
                    ['addlevel', 'gradingform_fivedays'],
                    ['points', 'gradingform_fivedays'],
                    ['err_mintwolevels', 'gradingform_fivedays'],
                ],
            ];
            $PAGE->requires->js_init_call('M.gradingform_fivedayseditor.init', [[
                'name' => $this->getName(),
                'criteriontemplate' => $renderer->criterion_template($mode, $data['options'], $this->getName()),
                'leveltemplate' => $renderer->level_template($mode, $data['options'], $this->getName()),
            ]], true, $module);
        } else {
            if ($this->_persistantFreeze) {
                $mode = gradingform_fivedays_controller::DISPLAY_EDIT_FROZEN;
            } else {
                $mode = gradingform_fivedays_controller::DISPLAY_PREVIEW;
            }
        }
        if ($this->regradeconfirmation) {
            if (!isset($data['regrade'])) {
                $data['regrade'] = 1;
            }
            $html .= $renderer->display_regrade_confirmation($this->getName(), $this->regradeconfirmation, $data['regrade']);
        }
        if ($this->validationerrors) {
            $html .= html_writer::div($renderer->notification($this->validationerrors));
        }
        $html .= $renderer->display_fivedays($data['criteria'], $data['options'], $mode, $this->getName());
        return $html;
    }

    protected function prepare_data($value = null, $withvalidation = false) {
        if ($value === null) {
            $value = $this->getValue();
        }
        if ($this->nonjsbuttonpressed === null) {
            $this->nonjsbuttonpressed = false;
        }
        $totalscore = 0;
        $errors = [];
        $return = ['criteria' => [], 'options' => gradingform_fivedays_controller::get_default_options()];
        if (!isset($value['criteria'])) {
            $value['criteria'] = [];
            $errors['err_nocriteria'] = 1;
        }
        if (!empty($value['options'])) {
            foreach (array_keys($return['options']) as $option) {
                if (!empty($value['options'][$option])) {
                    $return['options'][$option] = $value['options'][$option];
                } else {
                    $return['options'][$option] = null;
                }
            }
        }
        if (is_array($value)) {
            foreach (array_keys($value) as $key) {
                if ($key != 'options' && $key != 'criteria') {
                    $return[$key] = $value[$key];
                }
            }
        }

        $lastaction = null;
        $lastid = null;
        $overallminscore = $overallmaxscore = 0;
        foreach ($value['criteria'] as $id => $criterion) {
            if ($id == 'addcriterion') {
                $id = $this->get_next_id(array_keys($value['criteria']));
                $criterion = [
                    'description' => '',
                    'levels' => [],
                    'sortorder' => $id,
                ];
                // Match JS: create 2 levels with scores 0 and 10
                $criterion['levels']['NEWID1'] = [
                    'definition' => '',
                    'score' => 0,
                ];
                $criterion['levels']['NEWID2'] = [
                    'definition' => '',
                    'score' => 10,
                ];
                $this->nonjsbuttonpressed = true;
            }
            $levels = [];
            $minscore = $maxscore = null;
            if (isset($criterion['levels'])) {
                foreach ($criterion['levels'] as $levelid => $level) {
                    if ($levelid == 'addlevel') {
                        $levelid = $this->get_next_id(array_keys($criterion['levels']));
                        $level = [
                            'definition' => '',
                            'score' => 0,
                        ];
                        $this->nonjsbuttonpressed = true;
                    }
                    if (!empty($level['delete'])) {
                        $this->nonjsbuttonpressed = true;
                    } else {
                        if ($withvalidation) {
                            if (!strlen(trim($level['definition']))) {
                                $errors['err_nodefinition'] = 1;
                                $level['error_definition'] = true;
                            }
                            if (!is_numeric($level['score'])) {
                                $errors['err_scoreformat'] = 1;
                                $level['error_score'] = true;
                            }
                        }
                        if ($minscore === null || $level['score'] < $minscore) {
                            $minscore = $level['score'];
                        }
                        if ($maxscore === null || $level['score'] > $maxscore) {
                            $maxscore = $level['score'];
                        }
                        $levels[$levelid] = $level;
                    }
                }
            }
            $criterion['levels'] = $levels;
            if ($withvalidation && count($levels) < 2) {
                $errors['err_mintwolevels'] = 1;
            }
            if (!empty($criterion['moveup']) || !empty($criterion['movedown'])) {
                $lastaction = !empty($criterion['moveup']) ? 'moveup' : 'movedown';
                $lastid = $id;
                $this->nonjsbuttonpressed = true;
            } else if (!empty($criterion['delete'])) {
                $this->nonjsbuttonpressed = true;
            } else {
                if ($withvalidation && !strlen(trim($criterion['description']))) {
                    $errors['err_nodescription'] = 1;
                    $criterion['error_description'] = true;
                }
                $return['criteria'][$id] = $criterion;
                $overallminscore += $minscore ?? 0;
                $overallmaxscore += $maxscore ?? 0;
            }
        }
        if ($lastaction !== null) {
            $keys = array_keys($return['criteria']);
            $idx = array_search($lastid, $keys);
            if ($lastaction === 'moveup' && $idx > 0) {
                $tmp = $keys[$idx - 1];
                $keys[$idx - 1] = $keys[$idx];
                $keys[$idx] = $tmp;
            } else if ($lastaction === 'movedown' && $idx < count($keys) - 1) {
                $tmp = $keys[$idx + 1];
                $keys[$idx + 1] = $keys[$idx];
                $keys[$idx] = $tmp;
            }
            $newcriteria = [];
            foreach ($keys as $idx => $id) {
                $return['criteria'][$id]['sortorder'] = $idx + 1;
                $newcriteria[$id] = $return['criteria'][$id];
            }
            $return['criteria'] = $newcriteria;
        } else {
            $i = 1;
            foreach (array_keys($return['criteria']) as $id) {
                $return['criteria'][$id]['sortorder'] = $i++;
            }
        }
        if ($withvalidation) {
            if (count($return['criteria']) == 0) {
                $errors['err_nocriteria'] = 1;
            } else if ($overallmaxscore <= 0) {
                $errors['err_totalscore'] = 1;
            }
            $this->validationerrors = array_keys($errors);
        }
        return $return;
    }

    protected function get_next_id($ids) {
        $maxid = 0;
        foreach ($ids as $id) {
            if (preg_match('/^NEWID(\d+)$/', $id, $matches)) {
                $maxid = max($maxid, $matches[1]);
            }
        }
        return 'NEWID' . ($maxid + 1);
    }

    public function exportValue(&$submitValues, $assoc = false) {
        $value = $this->prepare_data($this->_findValue($submitValues));
        return $this->_prepareValue($value, $assoc);
    }

    public function onQuickFormEvent($event, $arg, &$caller) {
        if ($event === 'updateValue') {
            $value = $this->_findValue($caller->_constantValues);
            if ($value === null) {
                if ($caller->isSubmitted()) {
                    $value = $this->_findValue($caller->_submitValues);
                } else {
                    $value = $this->_findValue($caller->_defaultValues);
                }
            }
            if ($value !== null) {
                $value = $this->prepare_data($value);
            }
            $this->setValue($value);
            return true;
        }
        return parent::onQuickFormEvent($event, $arg, $caller);
    }

    public function validate($data) {
        if (!$this->wasvalidated) {
            $this->wasvalidated = true;
            $data = $this->prepare_data($data, true);
        }
        return !empty($this->validationerrors) ? $this->validationerrors : false;
    }

    public function non_js_button_pressed($data) {
        if ($this->nonjsbuttonpressed === null) {
            $this->prepare_data($data);
        }
        return $this->nonjsbuttonpressed;
    }
}
