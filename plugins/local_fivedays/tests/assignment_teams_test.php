<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/group/lib.php');

use advanced_testcase;
use local_fivedays\external\assignment\delete_assignment;
use local_fivedays\external\assignment\get_assignment_grades;
use local_fivedays\external\assignment\get_assignment_teams;
use local_fivedays\external\assignment\save_assignment_grade;
use local_fivedays\external\assignment\sync_assignment_groups;

class assignment_teams_test extends advanced_testcase {

    private \stdClass $course;
    private \stdClass $assign;
    /** @var \stdClass[] */
    private array $students = [];

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->assign = $generator->create_module('assign', ['course' => $this->course->id, 'name' => 'Essay', 'grade' => 100]);
        for ($i = 1; $i <= 4; $i++) {
            $this->students[$i] = $generator->create_and_enrol($this->course, 'student', ['firstname' => 'S' . $i, 'lastname' => 'Student']);
        }
    }

    private function id(int $n): int {
        return (int)$this->students[$n]->id;
    }

    private function teams(): array {
        $result = get_assignment_teams::execute((int)$this->assign->id);
        $teams = [];
        foreach ($result['teams'] as $team) {
            sort($team['userids']);
            $teams[$team['name']] = $team['userids'];
        }
        ksort($teams);
        return ['result' => $result, 'teams' => $teams];
    }

    public function test_teams_return_course_and_base_due_date(): void {
        global $DB;
        $DB->set_field('assign', 'duedate', 1798761600, ['id' => $this->assign->id]);

        $result = get_assignment_teams::execute((int)$this->assign->id);

        $this->assertSame((int)$this->course->id, $result['courseid']);
        $this->assertSame(1798761600, $result['duedate']);
    }

    public function test_sync_creates_grouping_groups_and_solo_groups(): void {
        global $DB;

        $outsider = $this->getDataGenerator()->create_user();
        $result = sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2), (int)$outsider->id]],
        ]);

        $this->assertTrue($result['teamsubmission']);
        $this->assertSame(3, $result['groups']);
        $this->assertSame([(int)$outsider->id], $result['skipped']);

        $instance = $DB->get_record('assign', ['id' => $this->assign->id]);
        $grouping = groups_get_grouping_by_idnumber($this->course->id, 'fivedays-assign-' . $this->assign->id);
        $this->assertEquals(1, $instance->teamsubmission);
        $this->assertEquals($grouping->id, $instance->teamsubmissiongroupingid);
        $this->assertEquals(1, $instance->preventsubmissionnotingroup);
        $this->assertEquals(0, $instance->requireallteammemberssubmit);
        $this->assertSame('Essay groups', $grouping->name);

        $teams = $this->teams()['teams'];
        $this->assertSame([
            'Group 1 · Essay' => [$this->id(1), $this->id(2)],
            'S3 Student · Essay' => [$this->id(3)],
            'S4 Student · Essay' => [$this->id(4)],
        ], $teams);
        $this->assertNotFalse(groups_get_group_by_idnumber($this->course->id, 'fivedays-a' . $this->assign->id . '-u' . $this->id(3)));
    }

    public function test_sync_skips_groups_with_nobody_enrolled_here(): void {
        $outsider = $this->getDataGenerator()->create_user();
        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
            ['key' => 'g2', 'name' => 'Group 2', 'userids' => [$this->id(3)]],
        ]);

        $result = sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
            ['key' => 'g2', 'name' => 'Group 2', 'userids' => []],
            ['key' => 'g3', 'name' => 'Group 3', 'userids' => [(int)$outsider->id]],
        ]);

        $this->assertSame(3, $result['groups']);
        $this->assertSame([(int)$outsider->id], $result['skipped']);
        $this->assertSame([
            'Group 1 · Essay' => [$this->id(1), $this->id(2)],
            'S3 Student · Essay' => [$this->id(3)],
            'S4 Student · Essay' => [$this->id(4)],
        ], $this->teams()['teams']);
    }

    public function test_resync_is_idempotent_and_moves_members(): void {
        $groups = [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
            ['key' => 'g2', 'name' => 'Group 2', 'userids' => [$this->id(3)]],
        ];
        sync_assignment_groups::execute((int)$this->assign->id, true, $groups);
        $first = $this->teams()['result']['teams'];
        sync_assignment_groups::execute((int)$this->assign->id, true, $groups);
        $this->assertEquals($first, $this->teams()['result']['teams']);

        $result = sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Team A', 'userids' => [$this->id(1)]],
            ['key' => 'g2', 'name' => 'Group 2', 'userids' => [$this->id(2), $this->id(3)]],
        ]);
        $this->assertSame(3, $result['groups']);
        $teams = $this->teams()['teams'];
        $this->assertSame([
            'Group 2 · Essay' => [$this->id(2), $this->id(3)],
            'S4 Student · Essay' => [$this->id(4)],
            'Team A · Essay' => [$this->id(1)],
        ], $teams);
    }

    public function test_sync_rejects_user_in_two_groups(): void {
        $this->expectException(\invalid_parameter_exception::class);
        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1)]],
            ['key' => 'g2', 'name' => 'Group 2', 'userids' => [$this->id(1)]],
        ]);
    }

    private function submit(int $userid, int $groupid): void {
        global $DB;

        $DB->insert_record('assign_submission', (object)[
            'assignment' => $this->assign->id,
            'userid' => $userid,
            'groupid' => $groupid,
            'status' => 'submitted',
            'latest' => 1,
            'attemptnumber' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    private function group_id(string $key): int {
        $group = groups_get_group_by_idnumber($this->course->id, 'fivedays-a' . $this->assign->id . '-' . $key);
        return (int)$group->id;
    }

    public function test_enable_is_locked_when_submissions_exist(): void {
        global $DB;

        $this->submit($this->id(1), 0);

        $this->assertFalse(get_assignment_teams::execute((int)$this->assign->id)['canchangemode']);
        $result = sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
        ]);
        $this->assertFalse($result['teamsubmission']);
        $this->assertTrue($result['locked']);
        $this->assertEquals(0, $DB->get_field('assign', 'teamsubmission', ['id' => $this->assign->id]));
        $this->assertFalse(groups_get_grouping_by_idnumber($this->course->id, 'fivedays-assign-' . $this->assign->id));
    }

    public function test_enable_is_locked_when_grades_exist(): void {
        save_assignment_grade::execute((int)$this->assign->id, $this->id(1), 50);

        $result = sync_assignment_groups::execute((int)$this->assign->id, true, []);
        $this->assertFalse($result['teamsubmission']);
        $this->assertTrue($result['locked']);
    }

    public function test_disable_keeps_team_mode_after_a_group_submitted(): void {
        global $DB;

        $groups = [['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]]];
        sync_assignment_groups::execute((int)$this->assign->id, true, $groups);
        $this->submit(0, $this->group_id('g1'));

        $result = sync_assignment_groups::execute((int)$this->assign->id, false, $groups);
        $this->assertTrue($result['teamsubmission']);
        $this->assertTrue($result['locked']);
        $this->assertEquals(1, $DB->get_field('assign', 'teamsubmission', ['id' => $this->assign->id]));
        $this->assertSame([$this->id(1), $this->id(2)], $this->teams()['teams']['Group 1 · Essay']);
    }

    public function test_submitted_groups_keep_their_members(): void {
        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
        ]);
        $this->submit(0, $this->group_id('g1'));
        $this->submit(0, $this->group_id('u' . $this->id(3)));

        $submitted = [];
        foreach (get_assignment_teams::execute((int)$this->assign->id)['teams'] as $team) {
            $submitted[$team['name']] = $team['submitted'];
        }
        ksort($submitted);
        $this->assertSame(['Group 1 · Essay' => true, 'S3 Student · Essay' => true, 'S4 Student · Essay' => false], $submitted);

        $attempts = [
            [['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2), $this->id(4)]]],
            [['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
             ['key' => 'g2', 'name' => 'Group 2', 'userids' => [$this->id(3), $this->id(4)]]],
            [],
        ];
        foreach ($attempts as $groups) {
            try {
                sync_assignment_groups::execute((int)$this->assign->id, true, $groups);
                $this->fail('Expected groupsubmitted');
            } catch (\moodle_exception $e) {
                $this->assertSame('groupsubmitted', $e->errorcode);
            }
        }

        $result = sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Team A', 'userids' => [$this->id(2), $this->id(1)]],
        ]);
        $this->assertSame(3, $result['groups']);
        $this->assertSame([$this->id(1), $this->id(2)], $this->teams()['teams']['Team A · Essay']);
    }

    public function test_new_app_group_with_the_same_members_keeps_the_submitted_group(): void {
        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
        ]);
        $groupid = $this->group_id('g1');
        $this->submit(0, $groupid);

        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g9', 'name' => 'Group 9', 'userids' => [$this->id(2), $this->id(1)]],
        ]);
        $teams = [];
        foreach (get_assignment_teams::execute((int)$this->assign->id)['teams'] as $team) {
            $teams[$team['name']] = $team['groupid'];
        }
        $this->assertSame($groupid, $teams['Group 9 · Essay']);
    }

    public function test_new_submitter_gets_a_solo_group_while_team_mode_is_kept(): void {
        $groups = [['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]]];
        sync_assignment_groups::execute((int)$this->assign->id, true, $groups);
        $this->submit(0, $this->group_id('g1'));
        $late = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Late', 'lastname' => 'Student']);

        sync_assignment_groups::execute((int)$this->assign->id, false, $groups);
        $this->assertSame([(int)$late->id], $this->teams()['teams']['Late Student · Essay']);
    }

    public function test_disable_removes_grouping_and_groups_only(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Lab A']);
        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
        ]);

        $result = sync_assignment_groups::execute((int)$this->assign->id, false, []);
        $this->assertFalse($result['teamsubmission']);
        $this->assertSame(0, $result['groups']);

        $instance = $DB->get_record('assign', ['id' => $this->assign->id]);
        $this->assertEquals(0, $instance->teamsubmission);
        $this->assertEquals(0, $instance->teamsubmissiongroupingid);
        $this->assertFalse(groups_get_grouping_by_idnumber($this->course->id, 'fivedays-assign-' . $this->assign->id));
        $this->assertSame([(int)$other->id], array_map('intval', array_keys(groups_get_all_groups($this->course->id))));
        $this->assertSame([], get_assignment_teams::execute((int)$this->assign->id)['teams']);
    }

    public function test_save_grade_with_also_users_in_team_mode(): void {
        $outsider = $this->getDataGenerator()->create_user();
        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2), $this->id(3)]],
        ]);

        $result = save_assignment_grade::execute(
            (int)$this->assign->id,
            $this->id(1),
            72.5,
            [$this->id(2), $this->id(3), $this->id(1), (int)$outsider->id]
        );
        $this->assertSame([$this->id(1), $this->id(2), $this->id(3)], $result['applieduserids']);
        $this->assertSame('notreleased', $result['releasestate']);

        $grades = [];
        foreach (get_assignment_grades::execute((int)$this->assign->id)['grades'] as $row) {
            $grades[$row['userid']] = $row['grade'];
        }
        $this->assertEquals(72.5, $grades[$this->id(1)]);
        $this->assertEquals(72.5, $grades[$this->id(2)]);
        $this->assertEquals(72.5, $grades[$this->id(3)]);
        $this->assertArrayNotHasKey($this->id(4), $grades);
    }

    public function test_delete_removes_the_assignments_own_groups_only(): void {
        global $DB;

        $other = groups_create_group((object)['courseid' => $this->course->id, 'name' => 'Tutorial A']);
        groups_add_member($other, $this->id(1));
        sync_assignment_groups::execute((int)$this->assign->id, true, [
            ['key' => 'g1', 'name' => 'Group 1', 'userids' => [$this->id(1), $this->id(2)]],
        ]);
        $prefix = 'fivedays-a' . $this->assign->id . '-%';
        $this->assertGreaterThan(0, $DB->count_records_select('groups', $DB->sql_like('idnumber', ':p'), ['p' => $prefix]));

        $result = delete_assignment::execute((int)$this->assign->cmid);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $DB->count_records_select('groups', $DB->sql_like('idnumber', ':p'), ['p' => $prefix]));
        $this->assertFalse(groups_get_grouping_by_idnumber($this->course->id, 'fivedays-assign-' . $this->assign->id));
        $this->assertTrue($DB->record_exists('groups', ['id' => $other]));
        $this->assertTrue(groups_is_member($other, $this->id(1)));
    }
}
