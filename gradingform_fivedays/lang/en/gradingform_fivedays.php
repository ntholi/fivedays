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

$string['pluginname'] = 'FiveDays Rubric';
$string['fivedays'] = 'FiveDays Rubric';
$string['definefivedays'] = 'Define FiveDays Rubric';
$string['previewfivedays'] = 'Preview FiveDays Rubric';
$string['gradingof'] = 'Grading of {$a}';

$string['name'] = 'Name';
$string['description'] = 'Description';
$string['fivedaysstatus'] = 'FiveDays rubric status';
$string['fivedaysoptions'] = 'FiveDays rubric options';

$string['criterion'] = 'Criterion';
$string['criterionmoveup'] = 'Move up';
$string['criterionmovedown'] = 'Move down';
$string['criteriondelete'] = 'Delete criterion';
$string['criterionempty'] = 'Click to edit criterion';
$string['addcriterion'] = 'Add criterion';

$string['level'] = 'Level';
$string['leveldefinition'] = 'Level definition';
$string['levelscore'] = 'Level score';
$string['leveldelete'] = 'Delete level {$a}';
$string['levelempty'] = 'Click to edit level';
$string['addlevel'] = 'Add level';
$string['selectlevel'] = 'Select this level';

$string['score'] = 'Score';
$string['customscore'] = 'Custom score';
$string['points'] = 'points';
$string['scorepostfix'] = '{$a} points';
$string['scorerange'] = 'Range: {$a->min} - {$a->max}';

$string['remarkforcriteria'] = 'Remark for criterion';
$string['remark'] = 'Remark';

$string['savefivedays'] = 'Save FiveDays rubric and make it ready';
$string['savefivedaysdraft'] = 'Save as draft';
$string['save'] = 'Save';
$string['backtoediting'] = 'Back to editing';

$string['sortlevelsasc'] = 'Sort order for levels';
$string['sortlevelsasc0'] = 'Descending by score';
$string['sortlevelsasc1'] = 'Ascending by score';
$string['lockzeropoints'] = 'Calculate grade having a minimum of the minimum score for every criterion';
$string['showdescriptionteacher'] = 'Display rubric description during evaluation';
$string['showdescriptionstudent'] = 'Display rubric description to those being graded';
$string['showscoreteacher'] = 'Display points for each level during evaluation';
$string['showscorestudent'] = 'Display points for each level to those being graded';
$string['showrangeteacher'] = 'Display score ranges during evaluation';
$string['showrangestudent'] = 'Display score ranges to those being graded';
$string['enableremarks'] = 'Allow grader to add text remarks for each criterion';
$string['showremarksstudent'] = 'Show remarks to those being graded';
$string['alwaysshowdefinition'] = 'Allow users to preview FiveDays rubric';

$string['fivedaysnotcompleted'] = 'Please select something for each criterion or enter a custom score';
$string['needregrademessage'] = 'The FiveDays rubric definition was changed after this student had been graded. The student cannot see this FiveDays rubric until you check it and update the grade.';

$string['confirmdeletecriterion'] = 'Are you sure you want to delete this criterion?';
$string['confirmdeletelevel'] = 'Are you sure you want to delete this level?';

$string['err_nocriteria'] = 'FiveDays rubric must contain at least one criterion';
$string['err_nodefinition'] = 'Level definition cannot be empty';
$string['err_nodescription'] = 'Criterion description cannot be empty';
$string['err_scoreformat'] = 'Score must be a number';
$string['err_mintwolevels'] = 'Each criterion must have at least two levels';
$string['err_totalscore'] = 'Maximum number of points possible must be greater than zero';

$string['zerolevelsabsent'] = 'Warning: The minimum score is not zero, grade calculation may differ from expected';

$string['raborconfirmation'] = 'Minor changes detected. Do you want to keep existing grades or mark them for re-grading?';
$string['regaborconfirmation'] = 'Significant changes detected. Do you want to keep existing grades or mark them for re-grading?';
$string['restorealiancnment'] = 'Keep existing grades';
$string['markfornewgrade'] = 'Mark for re-grading';

$string['gradecalculation'] = 'Grade calculation: minimum possible score is {$a->minscore}, maximum is {$a->maxscore}';

$string['rangeexplanation'] = 'How score ranges work';
$string['rangeexplanation_help'] = 'The FiveDays Rubric allows teachers to give scores within a range around each level. The range extends from halfway to the previous level up to halfway to the next level. For example, if levels are defined as Poor=10, Fair=20, Good=40, Excellent=50, then Fair would accept any score from 15 to 30 points.';

$string['privacy:metadata:criterionid'] = 'An identifier for a specific criterion being graded.';
$string['privacy:metadata:fillingssummary'] = 'Stores information about the user\'s grade created by the FiveDays rubric.';
$string['privacy:metadata:instanceid'] = 'An identifier relating to a grade in an activity.';
$string['privacy:metadata:levelid'] = 'The level obtained in the FiveDays rubric.';
$string['privacy:metadata:remark'] = 'Remarks related to the FiveDays rubric criterion being assessed.';
$string['privacy:metadata:score'] = 'The custom score given for the criterion.';
