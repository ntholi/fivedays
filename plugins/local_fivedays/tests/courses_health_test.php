<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

use advanced_testcase;
use core_external\external_api;
use local_fivedays\course_health;
use local_fivedays\external\course\get_course_health;
use local_fivedays\external\course\get_courses_health;

class courses_health_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    private function batch(array $courseids): array {
        return external_api::clean_returnvalue(
            get_courses_health::execute_returns(),
            get_courses_health::execute($courseids)
        )['courses'];
    }

    private function single(int $courseid): array {
        return external_api::clean_returnvalue(
            get_course_health::execute_returns(),
            get_course_health::execute($courseid)
        );
    }

    private function submit(\stdClass $course, \stdClass $assignrecord, \stdClass $student, int $time): void {
        global $DB;

        $cm = get_coursemodule_from_instance('assign', $assignrecord->id, $course->id);
        $assign = new \assign(\context_module::instance($cm->id), $cm, $course);
        $submission = $assign->get_user_submission($student->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->timemodified = $time;
        $DB->update_record('assign_submission', $submission);
    }

    /** Three different courses: activities with work waiting, materials and announcements, and an empty one. */
    private function seed(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $busy = $generator->create_course(['numsections' => 2]);
        $essay = $generator->create_module('assign', [
            'course' => $busy->id, 'name' => 'Essay', 'grade' => 100, 'duedate' => 5000, 'idnumber' => 'CW1',
        ]);
        $held = $generator->create_module('assign', ['course' => $busy->id, 'grade' => 100, 'markingworkflow' => 1]);
        $generator->create_module('quiz', ['course' => $busy->id, 'timeclose' => time() + DAYSECS]);
        $one = $generator->create_and_enrol($busy, 'student', ['username' => 'b_one']);
        $two = $generator->create_and_enrol($busy, 'student', ['username' => 'a_two']);
        $generator->create_and_enrol($busy, 'editingteacher');
        $this->submit($busy, $essay, $one, 1000);
        $this->submit($busy, $essay, $two, 2000);
        $this->submit($busy, $held, $one, 1500);
        $DB->insert_record('assign_grades', (object)[
            'assignment' => $held->id, 'userid' => $one->id, 'attemptnumber' => 0, 'grade' => 70,
            'timecreated' => 3000, 'timemodified' => 3000, 'grader' => get_admin()->id,
        ]);
        $DB->insert_record('user_lastaccess', (object)['userid' => $two->id, 'courseid' => $busy->id, 'timeaccess' => 4000]);

        $content = $generator->create_course(['numsections' => 3]);
        $generator->create_module('page', ['course' => $content->id, 'section' => 1]);
        $teacher = $generator->create_and_enrol($content, 'editingteacher');
        $generator->create_and_enrol($content, 'student');
        $generator->create_and_enrol($content, 'student', [], 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $news = forum_get_course_forum($content->id, 'news');
        $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $content->id, 'forum' => $news->id, 'userid' => $teacher->id,
        ]);

        $empty = $generator->create_course();

        return [(int)$busy->id, (int)$content->id, (int)$empty->id];
    }

    public function test_batch_equals_each_single_call(): void {
        $ids = $this->seed();

        $courses = $this->batch($ids);

        $this->assertSame($ids, array_column($courses, 'courseid'));
        foreach ($courses as $course) {
            $courseid = $course['courseid'];
            unset($course['courseid']);
            $this->assertSame($this->single($courseid), $course);
        }
        $this->assertCount(3, $courses[0]['activities']);
        $this->assertSame(2, $courses[0]['activities'][0]['waiting']);
        $this->assertFalse($courses[0]['activities'][1]['released']);
        $this->assertSame(['count' => 1, 'lastadded' => $courses[1]['materials']['lastadded']], $courses[1]['materials']);
        $this->assertSame(1, $courses[1]['announcements']['count']);
        $this->assertCount(1, $courses[1]['students']);
    }

    public function test_missing_site_and_repeated_ids_are_left_out(): void {
        [$busy, , $empty] = $this->seed();

        $courses = $this->batch([$empty, 999999, SITEID, $busy, $empty]);

        $this->assertSame([$empty, $busy], array_column($courses, 'courseid'));
        $this->assertSame([], $this->batch([]));
    }

    public function test_hidden_course_needs_viewhiddencourses(): void {
        $generator = $this->getDataGenerator();
        $hidden = $generator->create_course(['visible' => 0]);
        $reader = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('local/fivedays:viewcoursehealth', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $reader->id, \context_system::instance()->id);

        $this->assertSame([(int)$hidden->id], array_column($this->batch([(int)$hidden->id]), 'courseid'));

        $this->setUser($reader);
        $this->assertSame([], $this->batch([(int)$hidden->id]));
    }

    public function test_more_than_the_cap_is_refused(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('toomanycourses', 'local_fivedays', course_health::MAX_BATCH));
        get_courses_health::execute(range(1, course_health::MAX_BATCH + 1));
    }

    public function test_repeated_ids_count_once_towards_the_cap(): void {
        $course = $this->getDataGenerator()->create_course();

        $ids = array_fill(0, course_health::MAX_BATCH + 1, (int)$course->id);

        $this->assertCount(1, $this->batch($ids));
    }

    public function test_course_level_teacher_cannot_read_the_batch(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        get_courses_health::execute([(int)$course->id]);
    }
}
