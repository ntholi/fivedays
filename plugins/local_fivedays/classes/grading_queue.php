<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * What waits for grading in one assignment or quiz. Shared by get_grading_queue and get_course_health so
 * both report the same numbers.
 */
class grading_queue {

    /**
     * @return array{waiting: int, oldest: int} oldest is the time the oldest waiting submission arrived, 0 when none
     */
    public static function assignment(\assign $assign): array {
        global $DB;

        $waiting = (int)$assign->count_submissions_need_grading();
        if ($waiting === 0) {
            return ['waiting' => 0, 'oldest' => 0];
        }

        [$esql, $eparams] = get_enrolled_sql($assign->get_context(), 'mod/assign:submit', 0, true);
        $oldest = $DB->get_field_sql(
            "SELECT MIN(s.timemodified)
               FROM {assign_submission} s
          LEFT JOIN {assign_grades} g
                 ON g.assignment = s.assignment
                AND g.userid = s.userid
                AND g.attemptnumber = s.attemptnumber
              WHERE s.assignment = :assignment
                AND s.latest = 1
                AND s.status = :status
                AND s.userid IN ($esql)
                AND (g.timemodified IS NULL OR g.timemodified < s.timemodified OR g.grade IS NULL OR g.grade < 0)",
            $eparams + ['assignment' => $assign->get_instance()->id, 'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED]
        );

        return ['waiting' => $waiting, 'oldest' => (int)$oldest];
    }

    /**
     * @return array{waiting: int, oldest: int} finished attempts with a question still needing grading
     */
    public static function quiz(\cm_info $cm): array {
        global $DB;

        [$esql, $eparams] = get_enrolled_sql($cm->context, 'mod/quiz:attempt', 0, true);
        $row = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT quiza.id) AS waiting, MIN(quiza.timefinish) AS oldest
               FROM {quiz_attempts} quiza
               JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
               JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
              WHERE quiza.quiz = :quiz
                AND quiza.state = :finished
                AND quiza.preview = 0
                AND quiza.userid IN ($esql)
                AND qas.state = :needsgrading
                AND qas.sequencenumber = (
                    SELECT MAX(latest.sequencenumber)
                      FROM {question_attempt_steps} latest
                     WHERE latest.questionattemptid = qa.id
                )",
            $eparams + [
                'quiz' => $cm->instance,
                'finished' => \mod_quiz\quiz_attempt::FINISHED,
                'needsgrading' => 'needsgrading',
            ]
        );
        $waiting = $row ? (int)$row->waiting : 0;

        return ['waiting' => $waiting, 'oldest' => $waiting ? (int)$row->oldest : 0];
    }
}
