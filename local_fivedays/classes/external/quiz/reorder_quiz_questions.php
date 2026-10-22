<?php
namespace local_activity_utils\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class reorder_quiz_questions extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
            'slots' => new external_multiple_structure(
                new external_single_structure([
                    'slotid' => new external_value(PARAM_INT, 'Slot ID'),
                    'newslot' => new external_value(PARAM_INT, 'New slot number (1-based position)'),
                    'page' => new external_value(PARAM_INT, 'New page number', VALUE_DEFAULT, null),
                ]),
                'Array of slots with their new positions'
            ),
        ]);
    }

    public static function execute(int $quizid, array $slots): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'slots' => $slots,
        ]);

        
        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], '*', MUST_EXIST);

        
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);

        
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/activity_utils:managequizquestions', $context);
        require_capability('mod/quiz:manage', $context);

        
        $existingslots = $DB->get_records('quiz_slots', ['quizid' => $params['quizid']], '', 'id, slot, page');

        if (empty($existingslots)) {
            return [
                'success' => false,
                'message' => 'No slots found in quiz',
            ];
        }

        
        $existingslotids = [];
        foreach ($existingslots as $slot) {
            $existingslotids[$slot->id] = $slot;
        }

        
        $newpositions = [];
        $providedslotids = [];

        foreach ($params['slots'] as $slotdata) {
            if (!isset($existingslotids[$slotdata['slotid']])) {
                return [
                    'success' => false,
                    'message' => 'Slot ID ' . $slotdata['slotid'] . ' does not belong to this quiz',
                ];
            }

            if ($slotdata['newslot'] < 1 || $slotdata['newslot'] > count($existingslots)) {
                return [
                    'success' => false,
                    'message' => 'Invalid new slot number: ' . $slotdata['newslot'] . '. Must be between 1 and ' . count($existingslots),
                ];
            }

            if (in_array($slotdata['slotid'], $providedslotids)) {
                return [
                    'success' => false,
                    'message' => 'Duplicate slot ID: ' . $slotdata['slotid'],
                ];
            }

            if (isset($newpositions[$slotdata['newslot']])) {
                return [
                    'success' => false,
                    'message' => 'Duplicate new slot position: ' . $slotdata['newslot'],
                ];
            }

            $providedslotids[] = $slotdata['slotid'];
            $newpositions[$slotdata['newslot']] = $slotdata;
        }

        
        $transaction = $DB->start_delegated_transaction();

        try {
            
            $offset = 10000;
            foreach ($params['slots'] as $slotdata) {
                $DB->set_field('quiz_slots', 'slot', $offset + $slotdata['slotid'], ['id' => $slotdata['slotid']]);
            }

            
            foreach ($params['slots'] as $slotdata) {
                $updatedata = ['slot' => $slotdata['newslot']];

                
                if ($slotdata['page'] !== null && $slotdata['page'] > 0) {
                    $updatedata['page'] = $slotdata['page'];
                }

                $DB->set_field('quiz_slots', 'slot', $slotdata['newslot'], ['id' => $slotdata['slotid']]);

                if ($slotdata['page'] !== null && $slotdata['page'] > 0) {
                    $DB->set_field('quiz_slots', 'page', $slotdata['page'], ['id' => $slotdata['slotid']]);
                }
            }

            
            $DB->set_field('quiz', 'timemodified', time(), ['id' => $params['quizid']]);

            
            $transaction->allow_commit();

            
            foreach ($params['slots'] as $slotdata) {
                $newpage = $slotdata['page'] ?? $existingslotids[$slotdata['slotid']]->page;
                $event = \mod_quiz\event\slot_moved::create([
                    'context' => $context,
                    'objectid' => $slotdata['slotid'],
                    'other' => [
                        'quizid' => $params['quizid'],
                        'previousslot' => $existingslotids[$slotdata['slotid']]->slot,
                        'afterslot' => $slotdata['newslot'] > 1 ? $slotdata['newslot'] - 1 : 0,
                        'page' => (int)$newpage,
                    ],
                ]);
                $event->trigger();
            }

            return [
                'success' => true,
                'message' => 'Quiz questions reordered successfully. ' . count($params['slots']) . ' slot(s) updated.',
            ];

        } catch (\Exception $e) {
            $transaction->rollback($e);
            return [
                'success' => false,
                'message' => 'Failed to reorder questions: ' . $e->getMessage(),
            ];
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
