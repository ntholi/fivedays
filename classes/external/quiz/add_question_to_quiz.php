<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class add_question_to_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
            'questionbankentryid' => new external_value(PARAM_INT, 'Question bank entry ID to add'),
            'page' => new external_value(PARAM_INT, 'Page number (0 = add to last page, or specify page number)', VALUE_DEFAULT, 0),
            'maxmark' => new external_value(PARAM_FLOAT, 'Maximum mark for this question (null = use question default)', VALUE_DEFAULT, null),
            'requireprevious' => new external_value(PARAM_INT, 'Require previous question to be answered first (1=yes, 0=no)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $quizid,
        int $questionbankentryid,
        int $page = 0,
        ?float $maxmark = null,
        int $requireprevious = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/engine/bank.php');
        require_once($CFG->dirroot . '/lib/questionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'questionbankentryid' => $questionbankentryid,
            'page' => $page,
            'maxmark' => $maxmark,
            'requireprevious' => $requireprevious,
        ]);

        
        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], '*', MUST_EXIST);

        
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);

        
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:managequizquestions', $context);
        require_capability('mod/quiz:manage', $context);
        \mod_quiz\quiz_settings::create($quiz->id)->get_structure()->check_can_be_edited();

        
        $qbe = $DB->get_record('question_bank_entries', ['id' => $params['questionbankentryid']]);
        if (!$qbe) {
            return [
                'success' => false,
                'message' => 'Question bank entry not found',
                'slotid' => 0,
                'slot' => 0,
            ];
        }

        
        $sql = "SELECT qv.questionid, qv.version, q.qtype, q.name, q.defaultmark
                  FROM {question_versions} qv
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qv.questionbankentryid = ?
                   AND qv.status = 'ready'
              ORDER BY qv.version DESC
                 LIMIT 1";
        $questionversion = $DB->get_record_sql($sql, [$params['questionbankentryid']]);

        if (!$questionversion) {
            return [
                'success' => false,
                'message' => 'No ready version of the question found',
                'slotid' => 0,
                'slot' => 0,
            ];
        }
        question_require_capability_on($questionversion->questionid, 'use');

        
        $existingref = $DB->get_record_sql(
            "SELECT qr.id
               FROM {question_references} qr
               JOIN {quiz_slots} qs ON qs.id = qr.itemid
              WHERE qr.component = 'mod_quiz'
                AND qr.questionarea = 'slot'
                AND qr.questionbankentryid = ?
                AND qs.quizid = ?",
            [$params['questionbankentryid'], $params['quizid']]
        );

        if ($existingref) {
            return [
                'success' => false,
                'message' => 'This question is already in the quiz',
                'slotid' => 0,
                'slot' => 0,
            ];
        }

        
        if ($questionversion->qtype === 'random') {
            return [
                'success' => false,
                'message' => 'Random questions cannot be added using this function',
                'slotid' => 0,
                'slot' => 0,
            ];
        }

        
        $maxslot = $DB->get_field_sql(
            'SELECT MAX(slot) FROM {quiz_slots} WHERE quizid = ?',
            [$params['quizid']]
        );
        $maxslot = $maxslot ? (int)$maxslot : 0;

        
        if ($params['page'] <= 0) {
            
            $lastpage = $DB->get_field_sql(
                'SELECT MAX(page) FROM {quiz_slots} WHERE quizid = ?',
                [$params['quizid']]
            );
            $page = $lastpage ? (int)$lastpage : 1;
        } else {
            $page = $params['page'];
        }

        
        $mark = $params['maxmark'] !== null ? $params['maxmark'] : (float)$questionversion->defaultmark;

        
        $newslot = $maxslot + 1;

        
        $slot = new \stdClass();
        $slot->quizid = $params['quizid'];
        $slot->slot = $newslot;
        $slot->page = $page;
        $slot->requireprevious = $params['requireprevious'] ? 1 : 0;
        $slot->maxmark = $mark;

        $slotid = $DB->insert_record('quiz_slots', $slot);

        
        $reference = new \stdClass();
        $reference->usingcontextid = $context->id;
        $reference->component = 'mod_quiz';
        $reference->questionarea = 'slot';
        $reference->itemid = $slotid;
        $reference->questionbankentryid = $params['questionbankentryid'];
        $reference->version = null; 

        $DB->insert_record('question_references', $reference);

        
        $sumgrades = $DB->get_field_sql(
            'SELECT SUM(maxmark) FROM {quiz_slots} WHERE quizid = ?',
            [$params['quizid']]
        );
        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $params['quizid']]);
        $DB->set_field('quiz', 'timemodified', time(), ['id' => $params['quizid']]);

        
        $event = \mod_quiz\event\slot_created::create([
            'context' => $context,
            'objectid' => $slotid,
            'other' => [
                'quizid' => $params['quizid'],
                'slotnumber' => $newslot,
                'page' => $page,
                'questionbankentryid' => (int)$params['questionbankentryid'],
                'version' => null,
            ],
        ]);
        $event->trigger();

        return [
            'success' => true,
            'message' => 'Question "' . $questionversion->name . '" added to quiz at slot ' . $newslot,
            'slotid' => $slotid,
            'slot' => $newslot,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
            'slotid' => new external_value(PARAM_INT, 'Created slot ID'),
            'slot' => new external_value(PARAM_INT, 'Slot number'),
        ]);
    }
}
