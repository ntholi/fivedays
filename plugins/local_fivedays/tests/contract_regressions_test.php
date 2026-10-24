<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use advanced_testcase;
use backup;
use backup_controller;
use restore_controller;
use local_fivedays\external\assignment\delete_assignment;
use local_fivedays\external\file\create_draft_files;
use local_fivedays\external\quiz\reorder_quiz_questions;
use local_fivedays\external\rubric\create_rubric;

// Bugs the web-service contract test found (docker/contract in this repo).
class contract_regressions_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    public function test_create_draft_files_runs_over_web_services(): void {
        $result = create_draft_files::execute([
            ['filename' => 'brief.txt', 'content' => base64_encode('Contract brief')],
        ]);

        $this->assertGreaterThan(0, $result['draftitemid']);
        $files = get_file_storage()->get_area_files(
            \context_user::instance(get_admin()->id)->id, 'user', 'draft', $result['draftitemid'], 'id', false);
        $this->assertSame(['brief.txt'], array_values(array_map(fn($file) => $file->get_filename(), $files)));
        $this->assertSame('Contract brief', reset($files)->get_content());
    }

    public function test_assignment_with_fivedays_rubric_backs_up_and_restores(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        create_rubric::execute($assign->cmid, 'Essay rubric', '', [
            ['description' => 'Argument', 'sortorder' => 1, 'levels' => [
                ['score' => 0, 'definition' => 'Missing'],
                ['score' => 10, 'definition' => 'Clear'],
            ]],
        ]);

        $backup = new backup_controller(backup::TYPE_1ACTIVITY, $assign->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, get_admin()->id);
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backup->destroy();

        $target = $this->getDataGenerator()->create_course();
        $restore = new restore_controller($backupid, $target->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
            get_admin()->id, backup::TARGET_EXISTING_ADDING);
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $this->assertSame(2, $DB->count_records('gradingform_fivedays_criteria'));
        $this->assertSame(4, $DB->count_records('gradingform_fivedays_levels'));
    }

    public function test_deleting_assignment_with_recycle_bin_does_not_fail_on_backup(): void {
        global $DB;

        set_config('coursebinenable', 1, 'tool_recyclebin');
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        delete_assignment::execute($assign->cmid);

        $this->assertFalse($DB->record_exists('course_modules', ['id' => $assign->cmid]));
        $this->assertSame(1, $DB->count_records('tool_recyclebin_course', ['courseid' => $course->id]));
    }

    public function test_reorder_quiz_questions_reports_success_and_saves_order(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        foreach (['First', 'Second'] as $name) {
            $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id, 'name' => $name]);
            quiz_add_quiz_question($question->id, $quiz);
        }
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot', 'slot, id');

        $result = reorder_quiz_questions::execute($quiz->id, [
            ['slotid' => (int) $slots[2]->id, 'newslot' => 1],
            ['slotid' => (int) $slots[1]->id, 'newslot' => 2],
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(1, (int) $DB->get_field('quiz_slots', 'slot', ['id' => $slots[2]->id]));
        $this->assertSame(2, (int) $DB->get_field('quiz_slots', 'slot', ['id' => $slots[1]->id]));
    }
}
