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

use core_external\external_format_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

class gradingform_fivedays_controller extends gradingform_controller {

    const DISPLAY_EDIT_FULL     = 1;
    const DISPLAY_EDIT_FROZEN   = 2;
    const DISPLAY_PREVIEW       = 3;
    const DISPLAY_PREVIEW_GRADED = 8;
    const DISPLAY_EVAL          = 4;
    const DISPLAY_EVAL_FROZEN   = 5;
    const DISPLAY_REVIEW        = 6;
    const DISPLAY_VIEW          = 7;

    public static function get_default_options(): array {
        return [
            'sortlevelsasc' => 1,
            'lockzeropoints' => 1,
            'showdescriptionteacher' => 1,
            'showdescriptionstudent' => 1,
            'showscoreteacher' => 1,
            'showscorestudent' => 1,
            'showrangeteacher' => 1,
            'showrangestudent' => 1,
            'enableremarks' => 1,
            'showremarksstudent' => 1,
            'alwaysshowdefinition' => 1,
        ];
    }

    public function get_options(): array {
        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            $decoded = json_decode($this->definition->options, true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    $options[$key] = $value;
                }
            }
        }
        return $options;
    }

    protected function load_definition(): void {
        global $DB;

        $sql = "SELECT gd.id, gd.name, gd.description, gd.descriptionformat, gd.status, 
                       gd.copiedfromid, gd.timecreated, gd.usercreated, gd.timemodified, 
                       gd.usermodified, gd.timecopied, gd.options,
                       rc.id AS rcid, rc.sortorder AS rcsortorder, rc.description AS rcdescription,
                       rc.descriptionformat AS rcdescriptionformat,
                       rl.id AS rlid, rl.score AS rlscore, rl.definition AS rldefinition,
                       rl.definitionformat AS rldefinitionformat
                  FROM {grading_definitions} gd
             LEFT JOIN {gradingform_fivedays_criteria} rc ON (rc.definitionid = gd.id)
             LEFT JOIN {gradingform_fivedays_levels} rl ON (rl.criterionid = rc.id)
                 WHERE gd.areaid = :areaid AND gd.method = :method
              ORDER BY rc.sortorder, rl.score";
        $params = ['areaid' => $this->areaid, 'method' => $this->get_method_name()];

        $rs = $DB->get_recordset_sql($sql, $params);
        $this->definition = false;
        foreach ($rs as $record) {
            if ($this->definition === false) {
                $this->definition = new stdClass();
                foreach (['id', 'name', 'description', 'descriptionformat', 'status', 'copiedfromid',
                        'timecreated', 'usercreated', 'timemodified', 'usermodified', 'timecopied', 'options'] as $fieldname) {
                    $this->definition->$fieldname = $record->$fieldname;
                }
                $this->definition->fivedays_criteria = [];
            }
            if (!empty($record->rcid) && empty($this->definition->fivedays_criteria[$record->rcid])) {
                foreach (['id', 'sortorder', 'description', 'descriptionformat'] as $fieldname) {
                    $this->definition->fivedays_criteria[$record->rcid][$fieldname] = $record->{'rc' . $fieldname};
                }
                $this->definition->fivedays_criteria[$record->rcid]['levels'] = [];
            }
            if (!empty($record->rlid)) {
                foreach (['id', 'score', 'definition', 'definitionformat'] as $fieldname) {
                    $value = $record->{'rl' . $fieldname};
                    if ($fieldname == 'score') {
                        $value = (float) $value;
                    }
                    $this->definition->fivedays_criteria[$record->rcid]['levels'][$record->rlid][$fieldname] = $value;
                }
            }
        }
        $rs->close();
    }

    public function get_definition_for_editing($addemptycriterion = false): ?stdClass {
        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;
        if ($definition) {
            foreach (['id', 'name', 'description', 'descriptionformat', 'status'] as $key) {
                $properties->$key = $definition->$key;
            }
            $options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor($properties, 'description', $options, $this->get_context(),
                'grading', 'description', $definition->id);
        }
        $properties->fivedays = ['criteria' => [], 'options' => $this->get_options()];
        if (!empty($definition->fivedays_criteria)) {
            $properties->fivedays['criteria'] = $definition->fivedays_criteria;
        } else if (!$definition && $addemptycriterion) {
            $properties->fivedays['criteria'] = ['addcriterion' => 1];
        }
        return $properties;
    }

    public function get_definition_copy(gradingform_controller $target): stdClass {
        $new = parent::get_definition_copy($target);
        $old = $this->get_definition_for_editing();
        $new->description_editor = $old->description_editor;
        $new->fivedays = ['criteria' => [], 'options' => $old->fivedays['options']];
        $newcritid = 1;
        foreach ($old->fivedays['criteria'] as $oldcrit) {
            unset($oldcrit['id']);
            if (isset($oldcrit['levels'])) {
                $newlevid = 1;
                foreach ($oldcrit['levels'] as $oldlev) {
                    unset($oldlev['id']);
                    $oldcrit['levels']['NEWID' . $newlevid] = $oldlev;
                    $newlevid++;
                }
            }
            $new->fivedays['criteria']['NEWID' . $newcritid] = $oldcrit;
            $newcritid++;
        }
        return $new;
    }

    public function update_definition(stdClass $userdata, $usermodified = null): void {
        global $DB;

        $haschanges = [];
        if ($this->definition === false) {
            if (!isset($userdata->name)) {
                $userdata->name = '';
            }
            $userdata->status = gradingform_controller::DEFINITION_STATUS_DRAFT;
            $haschanges[5] = true;
        }
        parent::update_definition($userdata, $usermodified);
        $this->load_definition();
        if (isset($userdata->fivedays['options'])) {
            $newoptionsdata = $userdata->fivedays['options'];
            $newoptions = [];
            foreach (self::get_default_options() as $option => $default) {
                if (isset($newoptionsdata[$option])) {
                    $newoptions[$option] = $newoptionsdata[$option];
                } else {
                    $newoptions[$option] = $default;
                }
            }
            $this->definition->options = json_encode($newoptions);
            $DB->update_record('grading_definitions', ['id' => $this->definition->id, 'options' => $this->definition->options]);
        }

        if (empty($userdata->fivedays['criteria'])) {
            $userdata->fivedays['criteria'] = [];
        }

        $currentcriteria = [];
        if (!empty($this->definition->fivedays_criteria)) {
            foreach ($this->definition->fivedays_criteria as $criterion) {
                $currentcriteria[$criterion['id']] = [
                    'levels' => [],
                ];
                foreach ($criterion['levels'] as $level) {
                    $currentcriteria[$criterion['id']]['levels'][$level['id']] = $level;
                }
            }
        }

        foreach ($userdata->fivedays['criteria'] as $id => $criterion) {
            if (preg_match('/^NEWID(\d+)$/', $id, $matches)) {
                $criterion['definitionid'] = $this->definition->id;
                $criterion['descriptionformat'] = FORMAT_MOODLE;
                $id = $DB->insert_record('gradingform_fivedays_criteria', $criterion);
                $haschanges[5] = true;
            } else {
                $record = [
                    'id' => $id,
                    'sortorder' => $criterion['sortorder'],
                    'description' => $criterion['description'],
                ];
                if (isset($currentcriteria[$id]['description']) && $criterion['description'] != $currentcriteria[$id]['description']) {
                    $haschanges[4] = true;
                }
                $DB->update_record('gradingform_fivedays_criteria', $record);
            }
            if (!empty($criterion['levels'])) {
                foreach ($criterion['levels'] as $levelid => $level) {
                    if (preg_match('/^NEWID(\d+)$/', $levelid, $matches)) {
                        $level['criterionid'] = $id;
                        $level['definitionformat'] = FORMAT_MOODLE;
                        $levelid = $DB->insert_record('gradingform_fivedays_levels', $level);
                        $haschanges[5] = true;
                    } else {
                        $record = [
                            'id' => $levelid,
                            'score' => $level['score'],
                            'definition' => $level['definition'],
                        ];
                        if (isset($currentcriteria[$criterion['id']]['levels'][$levelid]['score']) &&
                            $level['score'] != $currentcriteria[$criterion['id']]['levels'][$levelid]['score']) {
                            $haschanges[1] = true;
                        }
                        $DB->update_record('gradingform_fivedays_levels', $record);
                    }
                    if (isset($currentcriteria[$id]['levels'][$levelid])) {
                        unset($currentcriteria[$id]['levels'][$levelid]);
                    }
                }
            }
            if (isset($currentcriteria[$id])) {
                unset($currentcriteria[$id]);
            }
        }

        foreach ($currentcriteria as $id => $criterion) {
            $DB->delete_records('gradingform_fivedays_levels', ['criterionid' => $id]);
            $DB->delete_records('gradingform_fivedays_criteria', ['id' => $id]);
            $haschanges[3] = true;
        }
        foreach ($this->get_elements_to_delete($haschanges) as $what => $hasit) {
            if ($hasit) {
                $DB->delete_records('gradingform_fivedays_fillings', ['criterionid' => $what]);
            }
        }
        $this->load_definition();
    }

    protected function get_elements_to_delete(array $haschanges): array {
        return [];
    }

    public function update_or_check_fivedays(stdClass $userdata, $checkonly = false): int {
        $dominated = [
            1 => [2, 3, 4, 5],
            2 => [3, 4, 5],
            3 => [4, 5],
            4 => [5],
            5 => [],
        ];
        $dominated[0] = array_keys($dominated);

        $currentcriteria = [];
        if (!empty($this->definition->fivedays_criteria)) {
            foreach ($this->definition->fivedays_criteria as $criterion) {
                $currentcriteria[$criterion['id']] = [
                    'description' => $criterion['description'],
                    'levels' => [],
                ];
                foreach ($criterion['levels'] as $level) {
                    $currentcriteria[$criterion['id']]['levels'][$level['id']] = [
                        'score' => $level['score'],
                        'definition' => $level['definition'],
                    ];
                }
            }
        }

        $haschanges = [];
        foreach ($userdata->fivedays['criteria'] as $id => $criterion) {
            if (preg_match('/^NEWID(\d+)$/', $id, $matches)) {
                $haschanges[5] = true;
            } else {
                if (!isset($currentcriteria[$id])) {
                    $haschanges[5] = true;
                } else {
                    if ($criterion['description'] != $currentcriteria[$id]['description']) {
                        $haschanges[4] = true;
                    }
                    if (!empty($criterion['levels'])) {
                        foreach ($criterion['levels'] as $levelid => $level) {
                            if (preg_match('/^NEWID(\d+)$/', $levelid, $matches)) {
                                $haschanges[5] = true;
                            } else {
                                if (!isset($currentcriteria[$id]['levels'][$levelid])) {
                                    $haschanges[5] = true;
                                } else {
                                    if ($level['score'] != $currentcriteria[$id]['levels'][$levelid]['score']) {
                                        $haschanges[1] = true;
                                    }
                                    if ($level['definition'] != $currentcriteria[$id]['levels'][$levelid]['definition']) {
                                        $haschanges[2] = true;
                                    }
                                    unset($currentcriteria[$id]['levels'][$levelid]);
                                }
                            }
                        }
                    }
                    foreach ($currentcriteria[$id]['levels'] as $levelid => $level) {
                        $haschanges[3] = true;
                    }
                    unset($currentcriteria[$id]);
                }
            }
        }
        foreach ($currentcriteria as $id => $criterion) {
            $haschanges[3] = true;
        }

        $changelevel = 0;
        foreach ($haschanges as $level => $hasit) {
            if ($hasit && $level > $changelevel) {
                $changelevel = $level;
            }
        }

        if (!$checkonly) {
            $this->update_definition($userdata);
        }
        return $changelevel;
    }

    public function mark_for_regrade(): void {
        global $DB;
        if ($this->has_active_instances()) {
            $conditions = ['definitionid' => $this->definition->id,
                'status' => gradingform_instance::INSTANCE_STATUS_ACTIVE];
            $DB->set_field('grading_instances', 'status', gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $conditions);
        }
    }

    protected function delete_plugin_definition(): void {
        global $DB;
        $sql = "DELETE FROM {gradingform_fivedays_fillings}
                 WHERE criterionid IN (SELECT id FROM {gradingform_fivedays_criteria} WHERE definitionid = :definitionid)";
        $DB->execute($sql, ['definitionid' => $this->definition->id]);
        $sql = "DELETE FROM {gradingform_fivedays_levels}
                 WHERE criterionid IN (SELECT id FROM {gradingform_fivedays_criteria} WHERE definitionid = :definitionid)";
        $DB->execute($sql, ['definitionid' => $this->definition->id]);
        $DB->delete_records('gradingform_fivedays_criteria', ['definitionid' => $this->definition->id]);
    }

    public static function description_form_field_options($context): array {
        return [
            'maxfiles' => -1,
            'maxbytes' => get_max_upload_file_size(),
            'context' => $context,
        ];
    }

    public function get_formatted_description(): string {
        if (!isset($this->definition->description)) {
            return '';
        }
        $context = $this->get_context();
        $options = self::description_form_field_options($context);
        $description = file_rewrite_pluginfile_urls($this->definition->description, 'pluginfile.php',
            $context->id, 'grading', 'description', $this->definition->id, $options);
        $formatoptions = [
            'noclean' => false,
            'trusted' => false,
            'filter' => true,
            'context' => $context,
        ];
        return format_text($description, $this->definition->descriptionformat, $formatoptions);
    }

    public function get_renderer(moodle_page $page): gradingform_fivedays_renderer {
        return $page->get_renderer('gradingform_' . $this->get_method_name());
    }

    public function render_preview(moodle_page $page): string {
        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }
        $criteria = $this->definition->fivedays_criteria;
        $options = $this->get_options();
        $fivedays = '';
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $showdescription = true;
        } else {
            if (empty($options['alwaysshowdefinition'])) {
                return '';
            }
            $showdescription = $options['showdescriptionstudent'];
        }
        $output = $this->get_renderer($page);
        if ($showdescription) {
            $fivedays .= $output->box($this->get_formatted_description(), 'gradingform_fivedays-description');
        }
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            if (!$options['lockzeropoints']) {
                $fivedays .= $output->display_fivedays_mapping_explained($this->get_min_max_score());
            }
            $fivedays .= $output->display_fivedays($criteria, $options, self::DISPLAY_PREVIEW, 'fivedays');
        } else {
            $fivedays .= $output->display_fivedays($criteria, $options, self::DISPLAY_PREVIEW_GRADED, 'fivedays');
        }
        return $fivedays;
    }

    public function render_grade($page, $itemid, $gradinginfo, $defaultcontent, $cangrade): string {
        return $this->get_renderer($page)->display_instances($this->get_active_instances($itemid), $defaultcontent, $cangrade);
    }

    public function get_min_max_score(): ?array {
        if (!$this->is_form_defined()) {
            return null;
        }
        $returnvalue = ['minscore' => 0, 'maxscore' => 0];
        $options = $this->get_options();
        foreach ($this->definition->fivedays_criteria as $id => $criterion) {
            $scores = [];
            foreach ($criterion['levels'] as $level) {
                $scores[] = $level['score'];
            }
            sort($scores);
            $returnvalue['minscore'] += $options['lockzeropoints'] ? $scores[0] : 0;
            $returnvalue['maxscore'] += $scores[count($scores) - 1];
        }
        return $returnvalue;
    }

    public function get_fivedays(): gradingform_fivedays_instance {
        return $this->get_or_create_instance(0, 0);
    }

    protected function prepare_instance($instance, $raterid, $itemid): gradingform_fivedays_instance {
        return new gradingform_fivedays_instance($this, $instance);
    }

    public static function get_external_definition_details(): ?array {
        $criteria = new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'criterion id'),
                'sortorder' => new external_value(PARAM_INT, 'sortorder'),
                'description' => new external_value(PARAM_RAW, 'description'),
                'descriptionformat' => new external_format_value('description'),
                'levels' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'level id'),
                        'score' => new external_value(PARAM_FLOAT, 'score'),
                        'definition' => new external_value(PARAM_RAW, 'definition'),
                        'definitionformat' => new external_format_value('definition'),
                    ]), 'levels', VALUE_OPTIONAL),
            ]), 'criteria', VALUE_OPTIONAL);

        return ['criteria' => $criteria];
    }

    public static function get_external_instance_filling_details(): ?array {
        $criteria = new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'filling id'),
                'criterionid' => new external_value(PARAM_INT, 'criterion id'),
                'levelid' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                'score' => new external_value(PARAM_FLOAT, 'custom score', VALUE_OPTIONAL),
                'remark' => new external_value(PARAM_RAW, 'remark', VALUE_OPTIONAL),
                'remarkformat' => new external_format_value('remark', VALUE_OPTIONAL),
            ]), 'filling', VALUE_OPTIONAL);
        return ['criteria' => $criteria];
    }
}

class gradingform_fivedays_instance extends gradingform_instance {

    protected $fivedays;

    public function cancel(): void {
        global $DB;
        parent::cancel();
        $DB->delete_records('gradingform_fivedays_fillings', ['instanceid' => $this->get_id()]);
    }

    public function copy($raterid, $itemid): int {
        global $DB;
        $instanceid = parent::copy($raterid, $itemid);
        $currentgrade = $this->get_fivedays_filling();
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            $record['instanceid'] = $instanceid;
            $DB->insert_record('gradingform_fivedays_fillings', $record);
        }
        return $instanceid;
    }

    public function is_empty_form($elementvalue): bool {
        if (!isset($elementvalue['criteria'])) {
            return true;
        }
        foreach ($elementvalue['criteria'] as $criterionid => $record) {
            if (!empty($record['levelid']) || !empty($record['remark']) || !empty($record['score'])) {
                return false;
            }
        }
        return true;
    }

    public function validate_grading_element($elementvalue): bool {
        if (!isset($elementvalue['criteria']) || !is_array($elementvalue['criteria']) || count($elementvalue['criteria']) == 0) {
            return false;
        }
        foreach ($elementvalue['criteria'] as $criterionid => $rec) {
            if (empty($rec['levelid']) && empty($rec['score'])) {
                return false;
            }
        }
        return true;
    }

    public function get_fivedays_filling($force = false): array {
        global $DB;
        if ($this->fivedays === null || $force) {
            $records = $DB->get_records('gradingform_fivedays_fillings', ['instanceid' => $this->get_id()]);
            $this->fivedays = ['criteria' => []];
            foreach ($records as $record) {
                $this->fivedays['criteria'][$record->criterionid] = (array) $record;
            }
        }
        return $this->fivedays;
    }

    public function update($data): void {
        global $DB;
        $currentgrade = $this->get_fivedays_filling();
        parent::update($data);
        foreach ($data['criteria'] as $criterionid => $record) {
            $record['remarkformat'] = FORMAT_HTML;
            if (!array_key_exists($criterionid, $currentgrade['criteria'])) {
                $newrecord = [
                    'instanceid' => $this->get_id(),
                    'criterionid' => $criterionid,
                    'levelid' => isset($record['levelid']) ? $record['levelid'] : null,
                    'score' => isset($record['score']) ? $record['score'] : null,
                    'remarkformat' => $record['remarkformat'],
                ];
                if (isset($record['remark'])) {
                    $newrecord['remark'] = $record['remark'];
                }
                $DB->insert_record('gradingform_fivedays_fillings', $newrecord);
            } else {
                $newrecord = ['id' => $currentgrade['criteria'][$criterionid]['id']];
                foreach (['levelid', 'score', 'remark', 'remarkformat'] as $key) {
                    if (isset($record[$key]) && (string) $currentgrade['criteria'][$criterionid][$key] !== (string) $record[$key]) {
                        $newrecord[$key] = $record[$key];
                    }
                }
                if (count($newrecord) > 1) {
                    $DB->update_record('gradingform_fivedays_fillings', $newrecord);
                }
            }
        }
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            if (!array_key_exists($criterionid, $data['criteria'])) {
                $DB->delete_records('gradingform_fivedays_fillings', ['id' => $record['id']]);
            }
        }
        $this->get_fivedays_filling(true);
    }

    public function clear_attempt($data): void {
        global $DB;
        foreach ($data['criteria'] as $criterionid => $record) {
            $DB->delete_records('gradingform_fivedays_fillings', [
                'criterionid' => $criterionid,
                'instanceid' => $this->get_id(),
            ]);
        }
    }

    public function get_grade(): float {
        $grade = $this->get_fivedays_filling();

        if (!($scores = $this->get_controller()->get_min_max_score()) || $scores['maxscore'] <= $scores['minscore']) {
            return -1;
        }

        $graderange = array_keys($this->get_controller()->get_grade_range());
        if (empty($graderange)) {
            return -1;
        }
        sort($graderange);
        $mingrade = $graderange[0];
        $maxgrade = $graderange[count($graderange) - 1];

        $curscore = 0;
        foreach ($grade['criteria'] as $id => $record) {
            if (!empty($record['score'])) {
                $curscore += (float) $record['score'];
            } elseif (!empty($record['levelid'])) {
                $curscore += $this->get_controller()->get_definition()->fivedays_criteria[$id]['levels'][$record['levelid']]['score'];
            }
        }

        $allowdecimals = $this->get_controller()->get_allow_grade_decimals();
        $options = $this->get_controller()->get_options();

        if ($options['lockzeropoints']) {
            $grade = max($mingrade, $curscore / $scores['maxscore'] * $maxgrade);
            return $allowdecimals ? $grade : round($grade, 0);
        } else {
            $gradeoffset = ($curscore - $scores['minscore']) / ($scores['maxscore'] - $scores['minscore']) * ($maxgrade - $mingrade);
            return ($allowdecimals ? $gradeoffset : round($gradeoffset, 0)) + $mingrade;
        }
    }

    public function render_grading_element($page, $gradingformelement): string {
        global $USER;
        if (!$gradingformelement->_flagFrozen) {
            $module = ['name' => 'gradingform_fivedays', 'fullpath' => '/grade/grading/form/fivedays/js/fivedays.js'];
            $page->requires->js_init_call('M.gradingform_fivedays.init', [['name' => $gradingformelement->getName()]], true, $module);
            $mode = gradingform_fivedays_controller::DISPLAY_EVAL;
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $mode = gradingform_fivedays_controller::DISPLAY_EVAL_FROZEN;
            } else {
                $mode = gradingform_fivedays_controller::DISPLAY_REVIEW;
            }
        }
        $criteria = $this->get_controller()->get_definition()->fivedays_criteria;
        $options = $this->get_controller()->get_options();
        $value = $gradingformelement->getValue();
        $html = '';
        if ($value === null) {
            $value = $this->get_fivedays_filling();
        } else if (!$this->validate_grading_element($value)) {
            $html .= html_writer::tag('div', get_string('fivedaysnotcompleted', 'gradingform_fivedays'),
                ['class' => 'gradingform_fivedays-error']);
        }
        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::div(get_string('needregrademessage', 'gradingform_fivedays'),
                'gradingform_fivedays-regrade', ['role' => 'alert']);
        }
        $haschanges = false;
        if ($currentinstance) {
            $curfilling = $currentinstance->get_fivedays_filling();
            foreach ($curfilling['criteria'] as $criterionid => $curvalues) {
                $curvalues['score'] = isset($curvalues['score']) ? $curvalues['score'] : null;
                $newvalues = isset($value['criteria'][$criterionid]) ? $value['criteria'][$criterionid] : ['levelid' => '', 'score' => ''];
                if ((string) $curvalues['levelid'] !== (string) $newvalues['levelid'] ||
                    (string) $curvalues['score'] !== (string) ($newvalues['score'] ?? '')) {
                    $haschanges = true;
                    break;
                }
            }
        }
        if ($currentinstance && !$haschanges) {
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $gradingformelement->getName() . '[itemid]',
                'value' => $currentinstance->get_data('itemid'),
            ]);
        }
        if ($options['showdescriptionteacher']) {
            $html .= html_writer::tag('div', $this->get_controller()->get_formatted_description(),
                ['class' => 'gradingform_fivedays-description']);
        }
        $html .= $this->get_controller()->get_renderer($page)->display_fivedays($criteria, $options, $mode,
            $gradingformelement->getName(), $value, $this->validationerrors ?? null);
        return $html;
    }
}
