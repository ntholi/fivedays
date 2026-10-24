<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;
use mod_quiz\repaginate;

class add_quiz_section extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
            'slot' => new external_value(PARAM_INT, 'Slot number (2 or more) of the question that starts the new section'),
            'heading' => new external_value(PARAM_TEXT, 'Section heading (plain text, required)'),
        ]);
    }

    public static function execute(int $quizid, int $slot, string $heading): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'slot' => $slot,
            'heading' => $heading,
        ]);

        $quizobj = helper::find_quiz_settings($params['quizid']);
        if (!$quizobj) {
            return self::failure('Quiz not found');
        }
        $context = $quizobj->get_context();
        self::validate_context($context);
        helper::require_quiz_structure_caps($context);

        $structure = $quizobj->get_structure();
        $lock = helper::quiz_structure_lock_message($structure);
        if ($lock) {
            return self::failure($lock);
        }

        $heading = trim($params['heading']);
        $problem = helper::quiz_heading_problem($heading, false);
        if ($problem) {
            return self::failure($problem);
        }

        if ($params['slot'] <= 1) {
            return self::failure('The first section already exists, so a new one cannot start at slot ' . $params['slot']);
        }
        $slotrecord = $DB->get_record('quiz_slots', ['quizid' => $params['quizid'], 'slot' => $params['slot']]);
        if (!$slotrecord) {
            return self::failure('There is no question at slot ' . $params['slot']);
        }
        if ($DB->record_exists('quiz_sections', ['quizid' => $params['quizid'], 'firstslot' => $params['slot']])) {
            return self::failure('A section already starts at slot ' . $params['slot']);
        }

        $transaction = $DB->start_delegated_transaction();

        // Moodle anchors a heading to a page, so the slot must be the first one on its page.
        if (!$structure->is_first_slot_on_page($params['slot'])) {
            $structure->update_page_break($slotrecord->id, repaginate::UNLINK);
        }
        $page = (int)$DB->get_field('quiz_slots', 'page', ['id' => $slotrecord->id], MUST_EXIST);
        $firstonpage = (int)$DB->get_field_select('quiz_slots', 'MIN(slot)', 'quizid = ? AND page = ?', [$params['quizid'], $page]);
        if ($firstonpage !== $params['slot']) {
            throw new \coding_exception('Slot ' . $params['slot'] . ' is not the first on page ' . $page . ' after splitting');
        }

        $sectionid = helper::reload_quiz_structure($quizobj)->add_section_heading($page, $heading);
        $DB->set_field('quiz', 'timemodified', time(), ['id' => $params['quizid']]);
        quiz_delete_previews($quizobj->get_quiz());

        $transaction->allow_commit();

        return [
            'success' => true,
            'message' => 'Section "' . $heading . '" added at slot ' . $params['slot'],
            'sectionid' => (int)$sectionid,
            'firstslot' => $params['slot'],
        ];
    }

    private static function failure(string $message): array {
        return ['success' => false, 'message' => $message, 'sectionid' => 0, 'firstslot' => 0];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
            'sectionid' => new external_value(PARAM_INT, 'Created quiz_sections ID (0 on failure)'),
            'firstslot' => new external_value(PARAM_INT, 'Slot number the section starts at (0 on failure)'),
        ]);
    }
}
