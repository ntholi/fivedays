<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;

class delete_quiz_section extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'quiz_sections ID of the section to delete together with its questions'),
        ]);
    }

    /**
     * Deletes the section and every question in it in one transaction. Moodle's remove_slot()
     * refuses to take the last slot of a section while the quiz has other sections, so the
     * heading goes first and the slots are removed from the highest down:
     *
     * - A later section: its heading is removed (its slots join the section above), then its
     *   slots are deleted from the highest down.
     * - The first section: the next section's heading is removed (its slots join section 1),
     *   section 1's original slots are deleted from the highest down, then section 1 takes over
     *   the heading and shuffle setting of the section that was next.
     *
     * A quiz with only one section is refused.
     */
    public static function execute(int $sectionid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['sectionid' => $sectionid]);

        $section = $DB->get_record('quiz_sections', ['id' => $params['sectionid']]);
        if (!$section) {
            return self::failure('Section not found');
        }
        $quizobj = helper::find_quiz_settings((int)$section->quizid);
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

        $quizid = (int)$section->quizid;
        $sections = array_values(helper::quiz_sections_in_order($quizid));
        if (count($sections) < 2) {
            return self::failure('This is the only section; a quiz needs at least one section');
        }
        $slotnumbers = helper::quiz_section_slot_numbers($quizid)[$section->id];
        if (!$slotnumbers) {
            return self::failure('The section has no questions');
        }

        $isfirst = (int)$section->firstslot === 1;
        $next = $isfirst ? $sections[1] : null;

        $transaction = $DB->start_delegated_transaction();

        $structure = $quizobj->get_structure();
        $structure->remove_section_heading($isfirst ? $next->id : $section->id);
        // The heading change makes the loaded structure stale, and remove_slot() relies on it.
        $structure = helper::reload_quiz_structure($quizobj);

        foreach (array_reverse($slotnumbers) as $slotnumber) {
            $structure->remove_slot($slotnumber);
        }

        if ($isfirst) {
            $structure->set_section_heading($section->id, (string)$next->heading);
            $structure->set_section_shuffle($section->id, (int)$next->shufflequestions);
        }

        $DB->set_field('quiz', 'timemodified', time(), ['id' => $quizid]);
        quiz_delete_previews($quizobj->get_quiz());
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        $transaction->allow_commit();

        return [
            'success' => true,
            'message' => 'Section deleted with ' . count($slotnumbers) . ' question(s)',
            'deletedslots' => count($slotnumbers),
        ];
    }

    private static function failure(string $message): array {
        return ['success' => false, 'message' => $message, 'deletedslots' => 0];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
            'deletedslots' => new external_value(PARAM_INT, 'Number of slots (questions) deleted (0 on failure)'),
        ]);
    }
}
