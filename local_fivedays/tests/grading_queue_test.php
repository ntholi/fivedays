<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use advanced_testcase;
use local_fivedays\external\course\get_grading_queue;

class grading_queue_test extends advanced_testcase {

    private \stdClass $course;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    private function queue(): array {
        return get_grading_queue::execute((int)$this->course->id)['items'];
    }

    private function submit_assignment(\stdClass $assignrecord, \stdClass $student, int $time): void {
        global $DB;

        $cm = get_coursemodule_from_instance('assign', $assignrecord->id, $this->course->id);
        $assign = new \assign(\context_module::instance($cm->id), $cm, $this->course);
        $submission = $assign->get_user_submission($student->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->timemodified = $time;
        $DB->update_record('assign_submission', $submission);
    }

    public function test_empty_course_has_no_items(): void {
        $this->assertSame([], $this->queue());
    }

    public function test_assignment_submission_without_grade_is_waiting(): void {
        $generator = $this->getDataGenerator();
        $assign = $generator->create_module('assign', ['course' => $this->course->id, 'name' => 'Essay', 'grade' => 100]);
        $one = $generator->create_and_enrol($this->course, 'student');
        $two = $generator->create_and_enrol($this->course, 'student');
        $this->submit_assignment($assign, $one, 1000);
        $this->submit_assignment($assign, $two, 2000);

        $items = $this->queue();

        $this->assertCount(1, $items);
        $this->assertSame('assign', $items[0]['type']);
        $this->assertSame('Essay', $items[0]['name']);
        $this->assertSame(2, $items[0]['waiting']);
        $this->assertSame(1000, $items[0]['oldest']);
    }

    public function test_graded_submission_leaves_the_queue(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $assign = $generator->create_module('assign', ['course' => $this->course->id, 'name' => 'Essay', 'grade' => 100]);
        $student = $generator->create_and_enrol($this->course, 'student');
        $this->submit_assignment($assign, $student, 1000);

        $DB->insert_record('assign_grades', (object)[
            'assignment' => $assign->id,
            'userid' => $student->id,
            'attemptnumber' => 0,
            'grade' => 80,
            'timecreated' => 2000,
            'timemodified' => 2000,
            'grader' => get_admin()->id,
        ]);

        $this->assertSame([], $this->queue());
    }

    public function test_finished_quiz_attempt_with_essay_is_waiting(): void {
        $generator = $this->getDataGenerator();
        $quiz = $generator->create_module('quiz', ['course' => $this->course->id, 'name' => 'Essay quiz']);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('essay', 'plain', ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $student = $generator->create_and_enrol($this->course, 'student');
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $time = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $time, false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $time);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($time, false, [1 => ['answer' => 'My answer', 'answerformat' => FORMAT_HTML]]);
        $attemptobj->process_finish($time, false);

        $items = $this->queue();

        $this->assertCount(1, $items);
        $this->assertSame('quiz', $items[0]['type']);
        $this->assertSame('Essay quiz', $items[0]['name']);
        $this->assertSame(1, $items[0]['waiting']);
    }
}
