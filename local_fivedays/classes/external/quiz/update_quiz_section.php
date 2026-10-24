<?php
namespace local_fivedays\external\quiz;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_fivedays\helper;

class update_quiz_section extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'quiz_sections ID'),
            'heading' => new external_value(PARAM_TEXT, 'New heading (plain text). Empty is allowed only for the first section. Omit to keep it', VALUE_DEFAULT, null, NULL_ALLOWED),
            'shufflequestions' => new external_value(PARAM_INT, 'Shuffle the questions of this section for each attempt (1=yes, 0=no). Omit to keep it', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Allowed after students have attempted the quiz: Moodle's own heading and shuffle edits
     * (structure::set_section_heading, set_section_shuffle, edit_rest.php) never check
     * check_can_be_edited(), so this function does not either.
     */
    public static function execute(int $sectionid, ?string $heading = null, ?int $shufflequestions = null): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sectionid' => $sectionid,
            'heading' => $heading,
            'shufflequestions' => $shufflequestions,
        ]);

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

        if ($params['heading'] === null && $params['shufflequestions'] === null) {
            return self::failure('Nothing to update: pass heading or shufflequestions');
        }
        if ($params['shufflequestions'] !== null && !in_array($params['shufflequestions'], [0, 1], true)) {
            return self::failure('shufflequestions must be 0 or 1');
        }

        $newheading = null;
        if ($params['heading'] !== null) {
            $newheading = trim($params['heading']);
            $problem = helper::quiz_heading_problem($newheading, (int)$section->firstslot === 1);
            if ($problem) {
                return self::failure($problem);
            }
        }

        $structure = $quizobj->get_structure();
        $transaction = $DB->start_delegated_transaction();
        if ($newheading !== null && $newheading !== (string)$section->heading) {
            $structure->set_section_heading($section->id, $newheading);
        }
        if ($params['shufflequestions'] !== null && $params['shufflequestions'] !== (int)$section->shufflequestions) {
            $structure->set_section_shuffle($section->id, $params['shufflequestions']);
        }
        $DB->set_field('quiz', 'timemodified', time(), ['id' => $section->quizid]);
        $transaction->allow_commit();

        $updated = $DB->get_record('quiz_sections', ['id' => $section->id], '*', MUST_EXIST);
        return [
            'success' => true,
            'message' => 'Section updated',
            'heading' => (string)$updated->heading,
            'shufflequestions' => (int)$updated->shufflequestions,
        ];
    }

    private static function failure(string $message): array {
        return ['success' => false, 'message' => $message, 'heading' => '', 'shufflequestions' => 0];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
            'heading' => new external_value(PARAM_RAW, 'Heading after the update (empty on failure)'),
            'shufflequestions' => new external_value(PARAM_INT, 'Shuffle flag after the update (0 on failure)'),
        ]);
    }
}
