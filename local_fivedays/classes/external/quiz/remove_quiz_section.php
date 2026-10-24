<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;

class remove_quiz_section extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'quiz_sections ID of a section other than the first'),
        ]);
    }

    /** Removes only the heading: the section's questions join the section above. */
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

        $structure = $quizobj->get_structure();
        $lock = helper::quiz_structure_lock_message($structure);
        if ($lock) {
            return self::failure($lock);
        }
        if ((int)$section->firstslot === 1) {
            return self::failure('The first section cannot be removed');
        }

        $slotnumbers = helper::quiz_section_slot_numbers((int)$section->quizid)[$section->id];

        $transaction = $DB->start_delegated_transaction();
        $structure->remove_section_heading($section->id);
        $DB->set_field('quiz', 'timemodified', time(), ['id' => $section->quizid]);
        $transaction->allow_commit();

        return [
            'success' => true,
            'message' => 'Section heading removed; its ' . count($slotnumbers) . ' question(s) joined the section above',
            'movedslots' => count($slotnumbers),
        ];
    }

    private static function failure(string $message): array {
        return ['success' => false, 'message' => $message, 'movedslots' => 0];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
            'movedslots' => new external_value(PARAM_INT, 'Number of slots that joined the section above (0 on failure)'),
        ]);
    }
}
