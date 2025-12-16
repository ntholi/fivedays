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

class gradingform_fivedays_renderer extends plugin_renderer_base {

    public function criterion_template($mode, $options, $elementname = null, $criterion = null, $levelsstr = null, $value = null) {
        if ($criterion === null || !is_array($criterion) || !array_key_exists('id', $criterion)) {
            $criterion = [
                'id' => '{CRITERION-id}',
                'description' => '{CRITERION-description}',
                'sortorder' => '{CRITERION-sortorder}',
                'class' => '{CRITERION-class}',
            ];
        } else {
            foreach (['sortorder', 'description', 'class'] as $key) {
                if (!array_key_exists($key, $criterion)) {
                    $criterion[$key] = '';
                }
            }
        }

        // Match JS structure exactly: class="criterion" without suffix for consistency
        $criteriontemplate = html_writer::start_tag('tr', [
            'class' => 'criterion',
            'id' => '{NAME}-criteria-{CRITERION-id}',
            'data-id' => $criterion['id'],
        ]);

        if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
            $criteriontemplate .= html_writer::start_tag('td', ['class' => 'controls']);
            $criteriontemplate .= html_writer::start_tag('div', ['class' => 'criterion-actions']);
            // Order matches JS: moveup, delete, movedown
            $actions = [
                'moveup' => 'fa-arrow-up',
                'delete' => 'fa-trash',
                'movedown' => 'fa-arrow-down',
            ];
            foreach ($actions as $action => $icon) {
                $criteriontemplate .= html_writer::start_tag('a', [
                    'href' => '#',
                    'class' => $action,
                    'title' => get_string('criterion' . $action, 'gradingform_fivedays'),
                ]);
                $criteriontemplate .= html_writer::tag('i', '', ['class' => 'icon fa ' . $icon]);
                $criteriontemplate .= html_writer::end_tag('a');
            }
            $criteriontemplate .= html_writer::end_tag('div');
            $criteriontemplate .= html_writer::end_tag('td');
            // Hidden input outside of td, matching JS structure
            $criteriontemplate .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => '{NAME}[criteria][{CRITERION-id}][sortorder]',
                'value' => $criterion['sortorder'],
            ]);
            $criteriontemplate .= html_writer::start_tag('td', [
                'class' => 'description',
                'id' => '{NAME}-criteria-{CRITERION-id}-description-cell',
            ]);
            // Add placeholder attribute to match JS
            $criteriontemplate .= html_writer::tag('textarea', s($criterion['description']), [
                'name' => '{NAME}[criteria][{CRITERION-id}][description]',
                'id' => '{NAME}-criteria-{CRITERION-id}-description',
                'cols' => '10',
                'rows' => '5',
                'class' => 'form-control',
                'aria-label' => get_string('criterionempty', 'gradingform_fivedays'),
                'placeholder' => get_string('criterionempty', 'gradingform_fivedays'),
            ]);
        } else {
            if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FROZEN) {
                $criteriontemplate .= html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => '{NAME}[criteria][{CRITERION-id}][sortorder]',
                    'value' => $criterion['sortorder'],
                ]);
                $criteriontemplate .= html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => '{NAME}[criteria][{CRITERION-id}][description]',
                    'value' => $criterion['description'],
                ]);
            }
            $criteriontemplate .= html_writer::start_tag('td', ['class' => 'description']);
            $criteriontemplate .= s($criterion['description']);
        }
        $criteriontemplate .= html_writer::end_tag('td');

        $criteriontemplate .= html_writer::tag('td', $levelsstr ?? '{LEVELS}', ['class' => 'levels']);

        if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
            $criteriontemplate .= html_writer::start_tag('td', ['class' => 'addlevel']);
            // Match JS structure exactly with addlevel class on the input
            $criteriontemplate .= html_writer::tag('input', '', [
                'type' => 'submit',
                'name' => '{NAME}[criteria][{CRITERION-id}][levels][addlevel]',
                'id' => '{NAME}-criteria-{CRITERION-id}-levels-addlevel',
                'value' => get_string('addlevel', 'gradingform_fivedays'),
                'class' => 'addlevel btn btn-secondary',
            ]);
            $criteriontemplate .= html_writer::end_tag('td');
        }

        $currentremark = '';
        $currentscore = '';
        if (isset($value['remark'])) {
            $currentremark = $value['remark'];
        }
        if (isset($value['score'])) {
            $currentscore = $value['score'];
        }

        if ($mode == gradingform_fivedays_controller::DISPLAY_EVAL) {
            if ($options['enableremarks']) {
                $criteriontemplate .= html_writer::start_tag('td', ['class' => 'remark']);
                $criteriontemplate .= html_writer::tag('textarea', s($currentremark), [
                    'name' => '{NAME}[criteria][{CRITERION-id}][remark]',
                    'cols' => '10',
                    'rows' => '3',
                    'class' => 'form-control',
                    'aria-label' => get_string('remarkforcriteria', 'gradingform_fivedays'),
                ]);
                $criteriontemplate .= html_writer::end_tag('td');
            }
            $criteriontemplate .= html_writer::start_tag('td', ['class' => 'customscore']);
            $criteriontemplate .= html_writer::tag('label', get_string('score', 'gradingform_fivedays'), [
                'for' => '{NAME}-criteria-{CRITERION-id}-score',
                'class' => 'form-label',
            ]);
            $criteriontemplate .= html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => '{NAME}[criteria][{CRITERION-id}][score]',
                'id' => '{NAME}-criteria-{CRITERION-id}-score',
                'value' => $currentscore,
                'class' => 'form-control score-input',
                'step' => 'any',
                'min' => '0',
                'aria-label' => get_string('customscore', 'gradingform_fivedays'),
            ]);
            $criteriontemplate .= html_writer::tag('div', '', ['class' => 'score-range-info']);
            $criteriontemplate .= html_writer::end_tag('td');
        } else if ($mode == gradingform_fivedays_controller::DISPLAY_EVAL_FROZEN) {
            if ($options['enableremarks']) {
                $criteriontemplate .= html_writer::tag('td', s($currentremark), ['class' => 'remark']);
            }
            $criteriontemplate .= html_writer::tag('td', s($currentscore), ['class' => 'customscore']);
        } else if ($mode == gradingform_fivedays_controller::DISPLAY_REVIEW || $mode == gradingform_fivedays_controller::DISPLAY_VIEW) {
            if ($options['enableremarks'] && $options['showremarksstudent']) {
                $criteriontemplate .= html_writer::tag('td', s($currentremark), ['class' => 'remark']);
            }
            if (!empty($currentscore)) {
                $criteriontemplate .= html_writer::tag('td', s($currentscore), ['class' => 'customscore']);
            }
        }

        $criteriontemplate .= html_writer::end_tag('tr');
        $criteriontemplate = str_replace('{NAME}', $elementname ?? '{NAME}', $criteriontemplate);
        $criteriontemplate = str_replace('{CRITERION-id}', $criterion['id'], $criteriontemplate);
        $criteriontemplate = str_replace('{CRITERION-description}', s($criterion['description']), $criteriontemplate);
        $criteriontemplate = str_replace('{CRITERION-sortorder}', $criterion['sortorder'], $criteriontemplate);
        $criteriontemplate = str_replace('{CRITERION-class}', $criterion['class'], $criteriontemplate);

        return $criteriontemplate;
    }

    public function level_template($mode, $options, $elementname = null, $criterionid = null, $level = null, $index = null) {
        if (!isset($level['id'])) {
            $level = [
                'id' => '{LEVEL-id}',
                'score' => '{LEVEL-score}',
                'definition' => '{LEVEL-definition}',
                'class' => '{LEVEL-class}',
                'checked' => false,
                'index' => '{LEVEL-index}',
            ];
        } else {
            foreach (['score', 'definition', 'class', 'checked', 'index'] as $key) {
                if (!array_key_exists($key, $level)) {
                    $level[$key] = '';
                }
            }
            if ($index !== null) {
                $level['index'] = $index;
            }
        }

        // Match JS structure exactly: class="level" without suffix
        $tdattributes = [
            'id' => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}',
            'class' => 'level',
            'data-id' => $level['id'],
        ];
        if (isset($level['tdwidth'])) {
            $tdattributes['style'] = 'width: ' . round($level['tdwidth']) . '%;';
        }
        $leveltemplate = html_writer::start_tag('div', $tdattributes);

        $definitionclass = 'definition d-block';
        $displayoptions = ['filter' => false, 'newlines' => true];

        if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
            $definitionclass .= ' input-group';
            $leveltemplate .= html_writer::start_tag('div', ['class' => $definitionclass]);
            // Add placeholder to match JS
            $leveltemplate .= html_writer::tag('textarea', s($level['definition']), [
                'name' => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][definition]',
                'cols' => '10',
                'rows' => '4',
                'class' => 'form-control',
                'aria-label' => get_string('levelempty', 'gradingform_fivedays'),
                'placeholder' => get_string('levelempty', 'gradingform_fivedays'),
            ]);
            $leveltemplate .= html_writer::end_tag('div');
        } else {
            if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FROZEN) {
                $leveltemplate .= html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][definition]',
                    'value' => $level['definition'],
                ]);
            }
            if ($mode == gradingform_fivedays_controller::DISPLAY_EVAL || $mode == gradingform_fivedays_controller::DISPLAY_EVAL_FROZEN) {
                $leveltemplate .= html_writer::start_tag('div', ['class' => 'level-select']);
                $checked = !empty($level['checked']);
                if ($mode == gradingform_fivedays_controller::DISPLAY_EVAL) {
                    $leveltemplate .= html_writer::empty_tag('input', [
                        'type' => 'radio',
                        'name' => '{NAME}[criteria][{CRITERION-id}][levelid]',
                        'id' => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-radio',
                        'value' => $level['id'],
                        'class' => 'level-radio',
                        'checked' => $checked ? 'checked' : null,
                        'data-score' => $level['score'],
                        'aria-label' => get_string('selectlevel', 'gradingform_fivedays'),
                    ]);
                } else {
                    if ($checked) {
                        $leveltemplate .= html_writer::tag('span', '&#10004;', ['class' => 'checked-icon text-success']);
                    }
                }
                $leveltemplate .= html_writer::end_tag('div');
            }
            $leveltemplate .= html_writer::tag('div', format_text($level['definition'], FORMAT_MOODLE, $displayoptions),
                ['class' => $definitionclass]);
        }

        $displayscore = true;
        if (!$options['showscoreteacher'] && in_array($mode, [gradingform_fivedays_controller::DISPLAY_EVAL, gradingform_fivedays_controller::DISPLAY_EVAL_FROZEN])) {
            $displayscore = false;
        }
        if (!$options['showscorestudent'] && in_array($mode, [gradingform_fivedays_controller::DISPLAY_VIEW, gradingform_fivedays_controller::DISPLAY_PREVIEW_GRADED])) {
            $displayscore = false;
        }
        if ($displayscore) {
            $score = $level['score'];
            $scoreclass = 'score d-inline';
            if (isset($level['error_score'])) {
                $scoreclass .= ' error';
            }
            if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
                $leveltemplate .= html_writer::start_tag('div', ['class' => $scoreclass]);
                $leveltemplate .= html_writer::tag('label', get_string('scorepostfix', 'gradingform_fivedays', ''), [
                    'for' => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-score',
                    'class' => 'sr-only',
                ]);
                $leveltemplate .= html_writer::empty_tag('input', [
                    'type' => 'number',
                    'name' => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][score]',
                    'id' => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-score',
                    'value' => $score,
                    'class' => 'form-control form-control-sm',
                    'step' => 'any',
                    'min' => '0',
                    'aria-label' => get_string('levelscore', 'gradingform_fivedays'),
                ]);
                $leveltemplate .= html_writer::tag('span', get_string('points', 'gradingform_fivedays'));
                $leveltemplate .= html_writer::end_tag('div');
            } else {
                if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FROZEN) {
                    $leveltemplate .= html_writer::empty_tag('input', [
                        'type' => 'hidden',
                        'name' => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][score]',
                        'value' => $score,
                    ]);
                }
                $leveltemplate .= html_writer::tag('div', get_string('scorepostfix', 'gradingform_fivedays', $score),
                    ['class' => $scoreclass]);
            }
        }

        $displayrange = false;
        if (isset($options['showrangeteacher']) && $options['showrangeteacher'] &&
            in_array($mode, [gradingform_fivedays_controller::DISPLAY_EVAL, gradingform_fivedays_controller::DISPLAY_EVAL_FROZEN])) {
            $displayrange = true;
        }
        if (isset($options['showrangestudent']) && $options['showrangestudent'] &&
            in_array($mode, [gradingform_fivedays_controller::DISPLAY_VIEW, gradingform_fivedays_controller::DISPLAY_PREVIEW_GRADED])) {
            $displayrange = true;
        }
        if ($displayrange && isset($level['rangemin']) && isset($level['rangemax'])) {
            $rangetext = get_string('scorerange', 'gradingform_fivedays', [
                'min' => number_format($level['rangemin'], 1),
                'max' => number_format($level['rangemax'], 1),
            ]);
            $leveltemplate .= html_writer::tag('div', $rangetext, ['class' => 'score-range text-muted small']);
        }

        if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
            $leveltemplate .= html_writer::start_tag('div', ['class' => 'level-actions mt-2']);
            $leveltemplate .= html_writer::start_tag('a', [
                'href' => '#',
                'class' => 'delete',
                'title' => get_string('leveldelete', 'gradingform_fivedays', $level['index']),
            ]);
            $leveltemplate .= html_writer::tag('i', '', ['class' => 'icon fa fa-trash']);
            $leveltemplate .= html_writer::end_tag('a');
            $leveltemplate .= html_writer::end_tag('div');
        }

        $leveltemplate .= html_writer::end_tag('div');

        $leveltemplate = str_replace('{NAME}', $elementname ?? '{NAME}', $leveltemplate);
        $leveltemplate = str_replace('{CRITERION-id}', $criterionid ?? '{CRITERION-id}', $leveltemplate);
        $leveltemplate = str_replace('{LEVEL-id}', $level['id'], $leveltemplate);
        $leveltemplate = str_replace('{LEVEL-score}', $level['score'], $leveltemplate);
        $leveltemplate = str_replace('{LEVEL-definition}', $level['definition'], $leveltemplate);
        $leveltemplate = str_replace('{LEVEL-class}', $level['class'], $leveltemplate);
        $leveltemplate = str_replace('{LEVEL-index}', $level['index'], $leveltemplate);

        return $leveltemplate;
    }

    protected function fivedays_template($mode, $options, $elementname, $criteriastr) {
        $classsuffix = '';
        switch ($mode) {
            case gradingform_fivedays_controller::DISPLAY_EDIT_FULL:
                $classsuffix = ' editor editable';
                break;
            case gradingform_fivedays_controller::DISPLAY_EDIT_FROZEN:
                $classsuffix = ' editor frozen';
                break;
            case gradingform_fivedays_controller::DISPLAY_PREVIEW:
            case gradingform_fivedays_controller::DISPLAY_PREVIEW_GRADED:
                $classsuffix = ' editor preview';
                break;
            case gradingform_fivedays_controller::DISPLAY_EVAL:
                $classsuffix = ' evaluate editable';
                break;
            case gradingform_fivedays_controller::DISPLAY_EVAL_FROZEN:
                $classsuffix = ' evaluate frozen';
                break;
            case gradingform_fivedays_controller::DISPLAY_REVIEW:
                $classsuffix = ' review';
                break;
            case gradingform_fivedays_controller::DISPLAY_VIEW:
                $classsuffix = ' view';
                break;
        }

        $fivedaystemplate = html_writer::start_tag('div', [
            'id' => 'fivedays-{NAME}',
            'class' => 'clearfix gradingform_fivedays' . $classsuffix,
        ]);

        $caption = html_writer::tag('caption', get_string('fivedays', 'gradingform_fivedays'), ['class' => 'visually-hidden']);
        $fivedaystable = html_writer::tag('table', $caption . $criteriastr, [
            'class' => 'criteria',
            'id' => '{NAME}-criteria',
        ]);
        $fivedaystemplate .= $fivedaystable;

        if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
            $fivedaystemplate .= html_writer::start_tag('div', ['class' => 'addcriterion mt-2']);
            $fivedaystemplate .= html_writer::tag('input', '', [
                'type' => 'submit',
                'name' => '{NAME}[criteria][addcriterion]',
                'id' => '{NAME}-criteria-addcriterion',
                'value' => get_string('addcriterion', 'gradingform_fivedays'),
                'class' => 'btn btn-secondary',
            ]);
            $fivedaystemplate .= html_writer::end_tag('div');
        }
        $fivedaystemplate .= $this->fivedays_edit_options($mode, $options, $elementname);
        $fivedaystemplate .= html_writer::end_tag('div');
        return str_replace('{NAME}', $elementname, $fivedaystemplate);
    }

    protected function fivedays_edit_options($mode, $options, $elementname) {
        if ($mode != gradingform_fivedays_controller::DISPLAY_EDIT_FULL &&
            $mode != gradingform_fivedays_controller::DISPLAY_EDIT_FROZEN &&
            $mode != gradingform_fivedays_controller::DISPLAY_PREVIEW) {
            return '';
        }

        $html = html_writer::start_tag('div', ['class' => 'options mt-3']);
        $html .= html_writer::tag('h4', get_string('fivedaysoptions', 'gradingform_fivedays'), ['class' => 'h5']);

        foreach ($options as $option => $value) {
            $optionnameid = $elementname . '-options-' . $option;
            $attrs = ['name' => $elementname . '[options][' . $option . ']', 'id' => $optionnameid];

            switch ($option) {
                case 'sortlevelsasc':
                    $html .= html_writer::tag('label', get_string($option, 'gradingform_fivedays'), ['for' => $optionnameid]);
                    $value = (int) (!!$value);
                    if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
                        $selectoptions = [
                            0 => get_string($option . '0', 'gradingform_fivedays'),
                            1 => get_string($option . '1', 'gradingform_fivedays'),
                        ];
                        $html .= html_writer::select($selectoptions, $attrs['name'], $value, false,
                            ['id' => $optionnameid, 'class' => 'form-select form-select-sm d-inline-block w-auto']);
                    } else {
                        $html .= html_writer::tag('span', get_string($option . $value, 'gradingform_fivedays'),
                            ['class' => 'value']);
                    }
                    break;

                case 'lockzeropoints':
                    $html .= html_writer::start_tag('div', ['class' => 'form-check']);
                    if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
                        $checkattrs = [
                            'type' => 'checkbox',
                            'name' => $attrs['name'],
                            'id' => $optionnameid,
                            'value' => 1,
                            'class' => 'form-check-input',
                        ];
                        if ($value) {
                            $checkattrs['checked'] = 'checked';
                        }
                        $html .= html_writer::empty_tag('input', $checkattrs);
                        $html .= html_writer::tag('label', get_string($option, 'gradingform_fivedays'),
                            ['for' => $optionnameid, 'class' => 'form-check-label']);
                    } else {
                        $html .= html_writer::tag('span', get_string($option, 'gradingform_fivedays') . ': ' .
                            ($value ? get_string('yes') : get_string('no')), ['class' => 'value']);
                    }
                    $html .= html_writer::end_tag('div');
                    break;

                default:
                    $html .= html_writer::start_tag('div', ['class' => 'form-check']);
                    if ($mode == gradingform_fivedays_controller::DISPLAY_EDIT_FULL) {
                        $checkattrs = [
                            'type' => 'checkbox',
                            'name' => $attrs['name'],
                            'id' => $optionnameid,
                            'value' => 1,
                            'class' => 'form-check-input',
                        ];
                        if ($value) {
                            $checkattrs['checked'] = 'checked';
                        }
                        $html .= html_writer::empty_tag('input', $checkattrs);
                        $html .= html_writer::tag('label', get_string($option, 'gradingform_fivedays'),
                            ['for' => $optionnameid, 'class' => 'form-check-label']);
                    } else {
                        $html .= html_writer::tag('span', get_string($option, 'gradingform_fivedays') . ': ' .
                            ($value ? get_string('yes') : get_string('no')), ['class' => 'value']);
                    }
                    $html .= html_writer::end_tag('div');
            }
        }
        $html .= html_writer::end_tag('div');
        return $html;
    }

    protected function get_css_class_suffix($cnt, $maxcnt) {
        $class = '';
        if ($cnt == 0) {
            $class .= ' first';
        }
        if ($cnt == $maxcnt) {
            $class .= ' last';
        }
        if ($cnt % 2) {
            $class .= ' odd';
        } else {
            $class .= ' even';
        }
        return $class;
    }

    protected function calculate_level_ranges($levels, $options) {
        $sortedlevels = [];
        foreach ($levels as $levelid => $level) {
            $sortedlevels[$levelid] = $level;
            $sortedlevels[$levelid]['id'] = $levelid;
        }
        
        // Use uasort to preserve keys while sorting by score.
        uasort($sortedlevels, function($a, $b) {
            $scorea = isset($a['score']) ? $a['score'] : 0;
            $scoreb = isset($b['score']) ? $b['score'] : 0;
            return $scorea <=> $scoreb;
        });

        // Convert to indexed array for range calculation, preserving level data.
        $indexedlevels = array_values($sortedlevels);
        $count = count($indexedlevels);
        
        for ($i = 0; $i < $count; $i++) {
            $currentscore = isset($indexedlevels[$i]['score']) ? $indexedlevels[$i]['score'] : 0;
            
            if ($i == 0) {
                $indexedlevels[$i]['rangemin'] = 0;
            } else {
                $prevscore = isset($indexedlevels[$i - 1]['score']) ? $indexedlevels[$i - 1]['score'] : 0;
                $indexedlevels[$i]['rangemin'] = ($prevscore + $currentscore) / 2;
            }
            
            if ($i == $count - 1) {
                $indexedlevels[$i]['rangemax'] = $currentscore;
            } else {
                $nextscore = isset($indexedlevels[$i + 1]['score']) ? $indexedlevels[$i + 1]['score'] : 0;
                $indexedlevels[$i]['rangemax'] = ($currentscore + $nextscore) / 2;
            }
        }

        // Rebuild result array keyed by level ID.
        $result = [];
        foreach ($indexedlevels as $level) {
            if (isset($level['id'])) {
                $result[$level['id']] = $level;
            }
        }
        
        return $result;
    }

    public function display_fivedays($criteria, $options, $mode, $elementname = null, $values = null, $validationerrors = null) {
        $criteriastr = '';
        $cnt = 0;
        foreach ($criteria as $id => $criterion) {
            $criterion['class'] = $this->get_css_class_suffix($cnt++, count($criteria) - 1);
            $criterion['id'] = $id;
            $levelsstr = '';
            $levelcnt = 0;
            $criterionvalue = isset($values['criteria'][$id]) ? $values['criteria'][$id] : null;

            $levelswithrages = $this->calculate_level_ranges($criterion['levels'], $options);
            $sortasc = !empty($options['sortlevelsasc']);
            $levelcount = count($criterion['levels']);
            $levelkeys = array_keys($criterion['levels']);
            if ($sortasc) {
                usort($levelkeys, function($a, $b) use ($criterion) {
                    $scorea = isset($criterion['levels'][$a]['score']) ? $criterion['levels'][$a]['score'] : 0;
                    $scoreb = isset($criterion['levels'][$b]['score']) ? $criterion['levels'][$b]['score'] : 0;
                    return $scorea <=> $scoreb;
                });
            } else {
                usort($levelkeys, function($a, $b) use ($criterion) {
                    $scorea = isset($criterion['levels'][$a]['score']) ? $criterion['levels'][$a]['score'] : 0;
                    $scoreb = isset($criterion['levels'][$b]['score']) ? $criterion['levels'][$b]['score'] : 0;
                    return $scoreb <=> $scorea;
                });
            }

            $index = 1;
            foreach ($levelkeys as $levelid) {
                // Safely get level from calculated ranges, fall back to original level data.
                $level = isset($levelswithrages[$levelid]) ? $levelswithrages[$levelid] : $criterion['levels'][$levelid];
                $level['id'] = $levelid;
                $level['class'] = $this->get_css_class_suffix($levelcnt++, $levelcount - 1);
                $level['checked'] = isset($criterionvalue['levelid']) && ((int) $criterionvalue['levelid'] === $levelid);
                if ($levelcount > 1) {
                    $level['tdwidth'] = 100 / $levelcount;
                }
                $level['index'] = $index++;
                $levelsstr .= $this->level_template($mode, $options, $elementname, $id, $level);
            }
            $levelsstr = html_writer::tag('div', $levelsstr, ['class' => 'levels-container d-flex flex-wrap']);
            $criteriastr .= $this->criterion_template($mode, $options, $elementname, $criterion, $levelsstr, $criterionvalue);
        }
        return $this->fivedays_template($mode, $options, $elementname, $criteriastr);
    }

    public function display_regrade_confirmation($elementname, $changelevel, $value) {
        $html = html_writer::start_tag('div', ['class' => 'gradingform_fivedays-regrade alert alert-warning']);
        if ($changelevel <= 2) {
            $html .= get_string('raborconfirmation', 'gradingform_fivedays');
        } else {
            $html .= get_string('regaborconfirmation', 'gradingform_fivedays');
        }
        $html .= html_writer::start_tag('div', ['class' => 'mt-2']);
        $html .= html_writer::start_tag('div', ['class' => 'form-check form-check-inline']);
        $html .= html_writer::empty_tag('input', [
            'type' => 'radio',
            'name' => $elementname . '[regrade]',
            'value' => 0,
            'class' => 'form-check-input',
            'id' => $elementname . '-regrade-0',
            'checked' => !$value ? 'checked' : null,
        ]);
        $html .= html_writer::tag('label', get_string('restorealiancnment', 'gradingform_fivedays'),
            ['for' => $elementname . '-regrade-0', 'class' => 'form-check-label']);
        $html .= html_writer::end_tag('div');
        $html .= html_writer::start_tag('div', ['class' => 'form-check form-check-inline']);
        $html .= html_writer::empty_tag('input', [
            'type' => 'radio',
            'name' => $elementname . '[regrade]',
            'value' => 1,
            'class' => 'form-check-input',
            'id' => $elementname . '-regrade-1',
            'checked' => $value ? 'checked' : null,
        ]);
        $html .= html_writer::tag('label', get_string('mark fornewgrade', 'gradingform_fivedays'),
            ['for' => $elementname . '-regrade-1', 'class' => 'form-check-label']);
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        return $html;
    }

    public function display_fivedays_mapping_explained($scores) {
        if (empty($scores) || $scores['minscore'] == 0) {
            return '';
        }
        $html = html_writer::start_tag('div', ['class' => 'alert alert-info']);
        $html .= get_string('gradecalculation', 'gradingform_fivedays', $scores);
        $html .= html_writer::end_tag('div');
        return $html;
    }

    public function display_instances($instances, $defaultcontent, $cangrade) {
        $html = '';
        $cnt = 0;
        foreach ($instances as $instance) {
            $html .= $this->display_instance($instance, $cnt++, $cangrade);
        }
        if (!$cnt) {
            $html .= $defaultcontent;
        }
        return $html;
    }

    public function display_instance(gradingform_fivedays_instance $instance, $idx, $cangrade) {
        $criteria = $instance->get_controller()->get_definition()->fivedays_criteria;
        $options = $instance->get_controller()->get_options();
        $values = $instance->get_fivedays_filling();
        if ($cangrade) {
            $mode = gradingform_fivedays_controller::DISPLAY_REVIEW;
            $showdescription = $options['showdescriptionteacher'];
        } else {
            $mode = gradingform_fivedays_controller::DISPLAY_VIEW;
            $showdescription = $options['showdescriptionstudent'];
        }
        $output = '';
        if ($showdescription) {
            $output .= $this->box($instance->get_controller()->get_formatted_description(), 'gradingform_fivedays-description');
        }
        $output .= $this->display_fivedays($criteria, $options, $mode, 'fivedays' . $idx, $values);
        return $output;
    }
}
