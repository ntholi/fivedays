<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

/**
 * What waits for grading in assignments and quizzes. Shared by get_grading_queue, get_course_health and
 * get_courses_health so all report the same numbers. Each result is ['waiting' => int, 'oldest' => int], oldest
 * being the time the oldest waiting item arrived, 0 when none.
 */
class grading_queue {

    /**
     * @param \assign[] $assigns
     * @return array<int, array{waiting: int, oldest: int}> keyed by assignment ID
     */
    public static function assignments(array $assigns): array {
        $out = [];
        $branches = [];
        foreach ($assigns as $assign) {
            $id = (int)$assign->get_instance()->id;
            $waiting = (int)$assign->count_submissions_need_grading();
            $out[$id] = ['waiting' => $waiting, 'oldest' => 0];
            if ($waiting) {
                $branches[] = self::assignment_oldest_sql($assign);
            }
        }
        foreach (helper::union_all($branches) as $row) {
            $out[(int)$row->itemid]['oldest'] = (int)$row->oldest;
        }
        return $out;
    }

    private static function assignment_oldest_sql(\assign $assign): array {
        $id = (int)$assign->get_instance()->id;
        [$esql, $eparams] = get_enrolled_sql($assign->get_context(), 'mod/assign:submit', 0, true);
        $sql = "SELECT {$id} AS itemid, MIN(s.timemodified) AS oldest
                  FROM {assign_submission} s
             LEFT JOIN {assign_grades} g
                    ON g.assignment = s.assignment
                   AND g.userid = s.userid
                   AND g.attemptnumber = s.attemptnumber
                 WHERE s.assignment = {$id}
                   AND s.latest = 1
                   AND s.status = :status{$id}
                   AND s.userid IN ($esql)
                   AND (g.timemodified IS NULL OR g.timemodified < s.timemodified OR g.grade IS NULL OR g.grade < 0)";
        return [$sql, $eparams + ['status' . $id => ASSIGN_SUBMISSION_STATUS_SUBMITTED]];
    }

    /**
     * Finished attempts with a question still needing grading.
     *
     * @param \cm_info[] $cms quiz course modules
     * @return array<int, array{waiting: int, oldest: int}> keyed by course module ID
     */
    public static function quizzes(array $cms): array {
        $out = [];
        $branches = [];
        foreach ($cms as $cm) {
            $out[(int)$cm->id] = ['waiting' => 0, 'oldest' => 0];
            $branches[] = self::quiz_sql($cm);
        }
        foreach (helper::union_all($branches) as $row) {
            $waiting = (int)$row->waiting;
            $out[(int)$row->itemid] = ['waiting' => $waiting, 'oldest' => $waiting ? (int)$row->oldest : 0];
        }
        return $out;
    }

    private static function quiz_sql(\cm_info $cm): array {
        $cmid = (int)$cm->id;
        $quizid = (int)$cm->instance;
        [$esql, $eparams] = get_enrolled_sql($cm->context, 'mod/quiz:attempt', 0, true);
        $sql = "SELECT {$cmid} AS itemid, COUNT(DISTINCT quiza.id) AS waiting, MIN(quiza.timefinish) AS oldest
                  FROM {quiz_attempts} quiza
                  JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                 WHERE quiza.quiz = {$quizid}
                   AND quiza.state = :finished{$cmid}
                   AND quiza.preview = 0
                   AND quiza.userid IN ($esql)
                   AND qas.state = :needsgrading{$cmid}
                   AND qas.sequencenumber = (
                       SELECT MAX(latest.sequencenumber)
                         FROM {question_attempt_steps} latest
                        WHERE latest.questionattemptid = qa.id
                   )";
        return [$sql, $eparams + [
            'finished' . $cmid => \mod_quiz\quiz_attempt::FINISHED,
            'needsgrading' . $cmid => 'needsgrading',
        ]];
    }
}
