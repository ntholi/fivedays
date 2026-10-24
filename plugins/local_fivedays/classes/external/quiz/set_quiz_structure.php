<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;

class set_quiz_structure extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'sectionid' => new external_value(PARAM_INT, 'quiz_sections ID'),
                    'slotids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'quiz_slots ID'),
                        'Slots of this section in the order wanted'
                    ),
                ]),
                'Every section in the order wanted, each with its slots'
            ),
        ]);
    }

    /**
     * Declarative reorder. Every existing section id and every existing slot id must appear
     * exactly once and no section may be empty. The first listed section must be the one that
     * currently starts at slot 1: that section is Moodle's first section (the one that cannot be
     * removed and may have a blank heading), so it cannot be moved down or replaced. Everything
     * else may move freely, including questions between sections. Slots are renumbered 1..N in
     * the given order, page is set to the slot number, every section's firstslot is set from its
     * new first slot, and all of it commits together.
     */
    public static function execute(int $quizid, array $sections): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'sections' => $sections,
        ]);
        $quizid = $params['quizid'];

        $quizobj = helper::find_quiz_settings($quizid);
        if (!$quizobj) {
            return self::failure('Quiz not found');
        }
        $context = $quizobj->get_context();
        self::validate_context($context);
        helper::require_quiz_structure_caps($context);

        $lock = helper::quiz_structure_lock_message($quizobj->get_structure());
        if ($lock) {
            return self::failure($lock);
        }

        $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
        $existing = helper::quiz_sections_in_order($quizid);
        if (!$slots || !$existing) {
            return self::failure('The quiz has no questions');
        }

        $plan = self::plan($params['sections'], $slots, $existing);
        if (is_string($plan)) {
            return self::failure($plan);
        }
        [$newslotnumber, $newfirstslot] = $plan;

        $transaction = $DB->start_delegated_transaction();

        $slotmap = [];
        foreach ($slots as $slot) {
            $slotmap[(int)$slot->slot] = $newslotnumber[$slot->id];
        }
        update_field_with_unique_index('quiz_slots', 'slot', $slotmap, ['quizid' => $quizid]);

        foreach ($slots as $slot) {
            // A page is one question here, so a moved question never shares a page with a section start.
            if ((int)$slot->page !== $newslotnumber[$slot->id]) {
                $DB->set_field('quiz_slots', 'page', $newslotnumber[$slot->id], ['id' => $slot->id]);
            }
        }
        // Moodle does not allow a question that depends on the previous one to be first.
        $DB->set_field('quiz_slots', 'requireprevious', 0, ['quizid' => $quizid, 'slot' => 1]);

        $firstslotmap = [];
        foreach ($existing as $section) {
            $firstslotmap[(int)$section->firstslot] = $newfirstslot[$section->id];
        }
        update_field_with_unique_index('quiz_sections', 'firstslot', $firstslotmap, ['quizid' => $quizid]);

        $DB->set_field('quiz', 'timemodified', time(), ['id' => $quizid]);
        quiz_delete_previews($quizobj->get_quiz());

        foreach ($slots as $slot) {
            $new = $newslotnumber[$slot->id];
            if ((int)$slot->slot === $new && (int)$slot->page === $new) {
                continue;
            }
            \mod_quiz\event\slot_moved::create([
                'context' => $context,
                'objectid' => $slot->id,
                'other' => [
                    'quizid' => $quizid,
                    'previousslotnumber' => (int)$slot->slot,
                    'afterslotnumber' => $new - 1,
                    'page' => $new,
                ],
            ])->trigger();
        }

        $transaction->allow_commit();

        return [
            'success' => true,
            'message' => 'Quiz structure saved: ' . count($slots) . ' question(s) in ' . count($existing) . ' section(s)',
            'slotcount' => count($slots),
            'sectioncount' => count($existing),
        ];
    }

    /**
     * Checks the wanted structure and works out the new numbers.
     *
     * @return string|array The reason it is invalid, or [new slot number by slot id, new firstslot by section id].
     */
    private static function plan(array $wanted, array $slots, array $existing): string|array {
        if (count($wanted) !== count($existing)) {
            return 'Expected ' . count($existing) . ' section(s) but got ' . count($wanted);
        }
        $firstexisting = reset($existing);
        if ((int)$firstexisting->firstslot !== 1) {
            return 'The quiz has no section starting at slot 1; repair it in Moodle first';
        }
        if ((int)reset($wanted)['sectionid'] !== (int)$firstexisting->id) {
            return 'The first section must stay first (section ' . $firstexisting->id . ')';
        }

        $seensections = [];
        $seenslots = [];
        $newslotnumber = [];
        $newfirstslot = [];
        $next = 0;
        foreach ($wanted as $item) {
            $sectionid = (int)$item['sectionid'];
            if (!isset($existing[$sectionid])) {
                return 'Section ' . $sectionid . ' does not belong to this quiz';
            }
            if (isset($seensections[$sectionid])) {
                return 'Section ' . $sectionid . ' is listed twice';
            }
            $seensections[$sectionid] = true;
            if (!$item['slotids']) {
                return 'Section ' . $sectionid . ' has no questions';
            }
            $newfirstslot[$sectionid] = $next + 1;
            foreach ($item['slotids'] as $slotid) {
                $slotid = (int)$slotid;
                if (!isset($slots[$slotid])) {
                    return 'Slot ' . $slotid . ' does not belong to this quiz';
                }
                if (isset($seenslots[$slotid])) {
                    return 'Slot ' . $slotid . ' is listed twice';
                }
                $seenslots[$slotid] = true;
                $newslotnumber[$slotid] = ++$next;
            }
        }
        if ($next !== count($slots)) {
            $missing = array_diff(array_keys($slots), array_keys($seenslots));
            return 'Every slot must be listed once; missing: ' . implode(', ', $missing);
        }
        return [$newslotnumber, $newfirstslot];
    }

    private static function failure(string $message): array {
        return ['success' => false, 'message' => $message, 'slotcount' => 0, 'sectioncount' => 0];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
            'slotcount' => new external_value(PARAM_INT, 'Number of slots saved (0 on failure)'),
            'sectioncount' => new external_value(PARAM_INT, 'Number of sections saved (0 on failure)'),
        ]);
    }
}
