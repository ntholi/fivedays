<?php
namespace local_activity_utils\external\section;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class create_subsection extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'parentsection' => new external_value(PARAM_INT, 'Parent section number'),
            'name' => new external_value(PARAM_TEXT, 'Subsection name'),
            'summary' => new external_value(PARAM_RAW, 'Subsection summary/description', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visibility (1=visible, 0=hidden)', VALUE_DEFAULT, 1),
        ]);
    }

    public static function execute(
        int $courseid,
        int $parentsection,
        string $name,
        string $summary = '',
        int $visible = 1
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'parentsection' => $parentsection,
            'name' => $name,
            'summary' => $summary,
            'visible' => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        self::validate_context($context);
        require_capability('local/activity_utils:createsubsection', $context);
        require_capability('moodle/course:update', $context);

        $subsectionmodule = $DB->get_record('modules', ['name' => 'subsection']);
        if (!$subsectionmodule) {
            throw new \moodle_exception('subsectionmodulenotfound', 'local_activity_utils');
        }

        $DB->get_record('course_sections', [
            'course' => $params['courseid'],
            'section' => $params['parentsection']
        ], 'id', MUST_EXIST);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'subsection';
        $moduleinfo->module = $subsectionmodule->id;
        $moduleinfo->course = $params['courseid'];
        $moduleinfo->section = $params['parentsection'];
        $moduleinfo->name = $params['name'];
        $moduleinfo->visible = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->completion = 0;
        $moduleinfo->completionview = 0;
        $moduleinfo->completionexpected = 0;
        $moduleinfo->completionpassgrade = 0;
        $moduleinfo->completiongradeitemnumber = null;
        $moduleinfo->showdescription = 0;
        $moduleinfo->availability = null;
        $moduleinfo->downloadcontent = 1;

        $transaction = $DB->start_delegated_transaction();
        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        $sectiondata = $DB->get_record('course_sections', [
            'course' => $params['courseid'],
            'component' => 'mod_subsection',
            'itemid' => $moduleinfo->instance,
        ], '*', MUST_EXIST);

        $sectiondata->summary = $params['summary'];
        $sectiondata->summaryformat = FORMAT_HTML;
        $sectiondata->timemodified = time();
        $DB->update_record('course_sections', $sectiondata);
        $transaction->allow_commit();

        return [
            'id' => $sectiondata->id,
            'sectionnum' => $sectiondata->section,
            'coursemoduleid' => $moduleinfo->coursemodule,
            'parentsection' => $params['parentsection'],
            'name' => $params['name'],
            'success' => true,
            'message' => 'Subsection created successfully'
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Section ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Course module ID'),
            'parentsection' => new external_value(PARAM_INT, 'Parent section number'),
            'name' => new external_value(PARAM_TEXT, 'Subsection name'),
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
