<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

use advanced_testcase;
use local_fivedays\external\course\get_course_health;
use local_fivedays\overseer_role;

class course_health_test extends advanced_testcase {

    private \stdClass $course;

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 3]);
    }

    private function health(): array {
        return get_course_health::execute((int)$this->course->id);
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

    private function grade(\stdClass $assign, \stdClass $student, int $time): void {
        global $DB;

        $DB->insert_record('assign_grades', (object)[
            'assignment' => $assign->id,
            'userid' => $student->id,
            'attemptnumber' => 0,
            'grade' => 80,
            'timecreated' => $time,
            'timemodified' => $time,
            'grader' => get_admin()->id,
        ]);
    }

    public function test_empty_course(): void {
        $health = $this->health();

        $this->assertSame([], $health['activities']);
        $this->assertSame(['count' => 0, 'lastadded' => 0], $health['materials']);
        $this->assertSame(['topics' => 3, 'covered' => 0], $health['outline']);
        $this->assertSame(['count' => 0, 'lastposted' => 0], $health['announcements']);
        $this->assertSame(['held' => 0], $health['virtualclasses']);
        $this->assertSame([], $health['lecturers']);
        $this->assertSame([], $health['students']);
    }

    public function test_assignment_counts_match_the_grading_queue(): void {
        $generator = $this->getDataGenerator();
        $assign = $generator->create_module('assign', [
            'course' => $this->course->id, 'name' => 'Essay', 'grade' => 100, 'duedate' => 5000, 'idnumber' => 'CW1',
        ]);
        $one = $generator->create_and_enrol($this->course, 'student');
        $two = $generator->create_and_enrol($this->course, 'student');
        $generator->create_and_enrol($this->course, 'student');
        $generator->create_and_enrol($this->course, 'editingteacher');
        $this->submit_assignment($assign, $one, 1000);
        $this->submit_assignment($assign, $two, 2000);
        $this->grade($assign, $two, 3000);

        $activities = $this->health()['activities'];

        $this->assertCount(1, $activities);
        $this->assertSame('assign', $activities[0]['type']);
        $this->assertSame('Essay', $activities[0]['name']);
        $this->assertSame('CW1', $activities[0]['idnumber']);
        $this->assertSame(5000, $activities[0]['duedate']);
        $this->assertSame(3, $activities[0]['expected']);
        $this->assertSame(2, $activities[0]['submitted']);
        $this->assertSame(1, $activities[0]['waiting']);
        $this->assertSame(1000, $activities[0]['oldest']);
        $this->assertTrue($activities[0]['released']);
    }

    public function test_marking_workflow_not_released_until_every_graded_student_is(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $assign = $generator->create_module('assign', [
            'course' => $this->course->id, 'name' => 'Essay', 'grade' => 100, 'markingworkflow' => 1,
        ]);
        $student = $generator->create_and_enrol($this->course, 'student');
        $this->submit_assignment($assign, $student, 1000);
        $this->grade($assign, $student, 2000);

        $this->assertFalse($this->health()['activities'][0]['released']);

        $DB->insert_record('assign_user_flags', (object)[
            'assignment' => $assign->id,
            'userid' => $student->id,
            'workflowstate' => ASSIGN_MARKING_WORKFLOW_STATE_RELEASED,
        ]);
        $this->assertTrue($this->health()['activities'][0]['released']);
    }

    public function test_hidden_grade_item_is_not_released(): void {
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'grade' => 100]);
        $item = \grade_item::fetch([
            'courseid' => $this->course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'itemnumber' => 0,
        ]);
        $item->set_hidden(1);

        $this->assertFalse($this->health()['activities'][0]['released']);
    }

    public function test_hidden_activity_is_left_out(): void {
        $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'visible' => 0]);

        $this->assertSame([], $this->health()['activities']);
    }

    public function test_quiz_finished_attempt_counts_as_submitted(): void {
        $generator = $this->getDataGenerator();
        $quiz = $generator->create_module('quiz', [
            'course' => $this->course->id, 'name' => 'Check', 'timeclose' => time() + DAYSECS,
        ]);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        $student = $generator->create_and_enrol($this->course, 'student');
        $generator->create_and_enrol($this->course, 'student');
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $time = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $time, false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $time);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($time, false, [1 => ['answer' => '1']]);
        $attemptobj->process_submit($time, false);
        $attemptobj->process_grade_submission($time);

        $activities = $this->health()['activities'];

        $this->assertCount(1, $activities);
        $this->assertSame('quiz', $activities[0]['type']);
        $this->assertSame('', $activities[0]['idnumber']);
        $this->assertSame((int)$quiz->timeclose, $activities[0]['duedate']);
        $this->assertSame(2, $activities[0]['expected']);
        $this->assertSame(1, $activities[0]['submitted']);
        $this->assertSame(0, $activities[0]['waiting']);
        $this->assertTrue($activities[0]['released']);
    }

    public function test_quiz_marks_only_after_close_is_not_released_while_open(): void {
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id, 'timeclose' => time() + DAYSECS, 'marksopen' => 0, 'maxmarksopen' => 0,
        ]);

        $this->assertFalse($this->health()['activities'][0]['released']);
    }

    public function test_materials_and_outline(): void {
        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $this->course->id, 'section' => 1]);
        $generator->create_module('label', ['course' => $this->course->id, 'section' => 2]);
        $generator->create_module('url', ['course' => $this->course->id, 'section' => 3, 'visible' => 0]);

        $health = $this->health();

        $added = (int)get_coursemodule_from_instance('page', $page->id, $this->course->id)->added;
        $this->assertSame(['count' => 1, 'lastadded' => $added], $health['materials']);
        $this->assertSame(['topics' => 3, 'covered' => 1], $health['outline']);
    }

    public function test_announcements(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $news = forum_get_course_forum($this->course->id, 'news');
        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $forumgenerator = $generator->get_plugin_generator('mod_forum');
        $forumgenerator->create_discussion(['course' => $this->course->id, 'forum' => $news->id, 'userid' => $teacher->id, 'timemodified' => 1000]);
        $latest = $forumgenerator->create_discussion(['course' => $this->course->id, 'forum' => $news->id, 'userid' => $teacher->id]);

        $announcements = $this->health()['announcements'];

        $created = (int)$DB->get_field('forum_posts', 'created', ['id' => $latest->firstpost]);
        $this->assertSame(2, $announcements['count']);
        $this->assertSame($created, $announcements['lastposted']);
    }

    public function test_people_with_course_last_access(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $seen = $generator->create_and_enrol($this->course, 'student', ['username' => 'a_seen']);
        $never = $generator->create_and_enrol($this->course, 'student', ['username' => 'b_never']);
        $generator->create_and_enrol($this->course, 'teacher');
        $DB->insert_record('user_lastaccess', (object)['userid' => $seen->id, 'courseid' => $this->course->id, 'timeaccess' => 4000]);

        $health = $this->health();

        $this->assertSame([['userid' => (int)$teacher->id, 'lastaccess' => 0]], $health['lecturers']);
        $this->assertSame([
            ['userid' => (int)$seen->id, 'username' => 'a_seen', 'lastaccess' => 4000],
            ['userid' => (int)$never->id, 'username' => 'b_never', 'lastaccess' => 0],
        ], $health['students']);
    }

    public function test_virtual_classes_held_counts_meeting_days_with_joins(): void {
        global $DB;

        $bbb = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $this->course->id]);
        foreach ([1000, 2000, DAYSECS + 1000] as $time) {
            $DB->insert_record('bigbluebuttonbn_logs', (object)[
                'courseid' => $this->course->id,
                'bigbluebuttonbnid' => $bbb->id,
                'userid' => get_admin()->id,
                'timecreated' => $time,
                'meetingid' => $bbb->meetingid . '-' . $this->course->id . '-' . $bbb->id,
                'log' => \mod_bigbluebuttonbn\logger::EVENT_JOIN,
            ]);
        }

        $this->assertSame(['held' => 2], $this->health()['virtualclasses']);
    }

    public function test_overseer_can_read_health(): void {
        $overseer = $this->getDataGenerator()->create_and_enrol($this->course, overseer_role::SHORTNAME);
        $this->setUser($overseer);

        $this->assertSame(['topics' => 3, 'covered' => 0], $this->health()['outline']);
    }

    public function test_student_cannot_read_health(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        $this->health();
    }
}
