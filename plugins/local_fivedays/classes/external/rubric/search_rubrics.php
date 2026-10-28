<?php
namespace local_fivedays\external\rubric;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class search_rubrics extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'targetcmid' => new external_value(PARAM_INT, 'Course module ID of the assignment the rubric will be imported into'),
            'query' => new external_value(PARAM_TEXT, 'Words matched against assignment, course name and course short name', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Maximum results (1-50)', VALUE_DEFAULT, 20),
        ]);
    }

    public static function execute(int $targetcmid, string $query = '', int $limit = 20): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'targetcmid' => $targetcmid,
            'query' => $query,
            'limit' => $limit,
        ]);

        $targetcm = get_coursemodule_from_id('assign', $params['targetcmid'], 0, false, MUST_EXIST);
        $targetcontext = \context_module::instance($targetcm->id);
        self::validate_context($targetcontext);
        // Rubrics are shared site-wide: anyone who may build a rubric here may reuse any other.
        require_capability('local/fivedays:managerubric', $targetcontext);

        $limit = max(1, min(50, $params['limit']));
        $sqlparams = [
            'component' => 'mod_assign',
            'areaname' => 'submissions',
            'activemethod' => 'fivedays',
            'method' => 'fivedays',
            'ctxlevel' => CONTEXT_MODULE,
            'modname' => 'assign',
            'targetcmid' => $targetcm->id,
            'targetcourse' => $targetcm->course,
        ];

        $termsql = '';
        $words = preg_split('/\s+/', trim($params['query']), -1, PREG_SPLIT_NO_EMPTY);
        foreach (array_slice($words, 0, 6) as $index => $word) {
            $like = '%' . $DB->sql_like_escape($word) . '%';
            $termsql .= ' AND (' . $DB->sql_like('a.name', ':a' . $index, false)
                . ' OR ' . $DB->sql_like('c.fullname', ':f' . $index, false)
                . ' OR ' . $DB->sql_like('c.shortname', ':s' . $index, false) . ')';
            $sqlparams['a' . $index] = $like;
            $sqlparams['f' . $index] = $like;
            $sqlparams['s' . $index] = $like;
        }

        $sql = "SELECT cm.id AS cmid, a.name AS assignmentname, c.id AS courseid,
                       c.fullname AS coursename, c.shortname AS courseshortname,
                       gd.timemodified,
                       (SELECT COUNT(1)
                          FROM {gradingform_fivedays_criteria} fc
                         WHERE fc.definitionid = gd.id) AS criteriacount,
                       (SELECT COALESCE(SUM(crit.score), 0)
                          FROM (SELECT MAX(fl.score) AS score
                                  FROM {gradingform_fivedays_criteria} fc2
                                  JOIN {gradingform_fivedays_levels} fl ON fl.criterionid = fc2.id
                                 WHERE fc2.definitionid = gd.id
                              GROUP BY fc2.id) crit) AS maxscore,
                       CASE WHEN c.id = :targetcourse THEN 0 ELSE 1 END AS othercourse
                  FROM {grading_definitions} gd
                  JOIN {grading_areas} ga ON ga.id = gd.areaid
                       AND ga.component = :component AND ga.areaname = :areaname
                       AND ga.activemethod = :activemethod
                  JOIN {context} ctx ON ctx.id = ga.contextid AND ctx.contextlevel = :ctxlevel
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND cm.deletioninprogress = 0
                  JOIN {modules} md ON md.id = cm.module AND md.name = :modname
                  JOIN {assign} a ON a.id = cm.instance
                  JOIN {course} c ON c.id = cm.course
                 WHERE gd.method = :method
                   AND cm.id <> :targetcmid
                   AND EXISTS (SELECT 1 FROM {gradingform_fivedays_criteria} fe WHERE fe.definitionid = gd.id)
                   $termsql
              ORDER BY othercourse ASC, gd.timemodified DESC, cm.id DESC";

        $records = $DB->get_records_sql($sql, $sqlparams, 0, $limit);

        $results = [];
        foreach ($records as $record) {
            $results[] = [
                'cmid' => (int)$record->cmid,
                'assignmentname' => $record->assignmentname,
                'courseid' => (int)$record->courseid,
                'coursename' => $record->coursename,
                'courseshortname' => $record->courseshortname,
                'criteriacount' => (int)$record->criteriacount,
                'maxscore' => (float)$record->maxscore,
                'timemodified' => (int)$record->timemodified,
            ];
        }

        return ['rubrics' => $results];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rubrics' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment that owns the rubric'),
                    'assignmentname' => new external_value(PARAM_TEXT, 'Assignment name'),
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
                    'courseshortname' => new external_value(PARAM_TEXT, 'Course short name'),
                    'criteriacount' => new external_value(PARAM_INT, 'Number of criteria'),
                    'maxscore' => new external_value(PARAM_FLOAT, 'Sum of each criterion\'s highest level score'),
                    'timemodified' => new external_value(PARAM_INT, 'Rubric last modified (unix time)'),
                ])
            ),
        ]);
    }
}
