<?php
namespace local_fivedays\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_fivedays\external\provision\delete_course;
use local_fivedays\external\provision\enrol_users;
use local_fivedays\external\provision\ensure_course;
use local_fivedays\external\provision\ensure_google_login;
use local_fivedays\external\provision\provision_users;
use local_fivedays\external\provision\rebind_user_email;
use local_fivedays\external\provision\unenrol_users;

class provision_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    public function test_ensure_google_login_keeps_password_form_and_blocks_signup(): void {
        $result = ensure_google_login::execute('test-client-id', 'test-client-secret');

        $this->assertSame(1, $result['ok']);
        $this->assertStringContainsString('oauth2', get_config('core', 'auth'));
        $this->assertStringContainsString('manual', get_config('core', 'auth'));
        $this->assertEquals(1, get_config('core', 'showloginform'));
        $this->assertEquals(1, get_config('core', 'authpreventaccountcreation'));
    }

    public function test_ensure_course_reuses_idnumber_and_creates_category(): void {
        $first = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:34');
        $this->assertSame(1, $first['created']);
        $this->assertGreaterThan(0, $first['courseId']);
        $this->assertGreaterThan(0, $first['categoryId']);

        $second = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'fict', '12:34');
        $this->assertSame(0, $second['created']);
        $this->assertSame($first['courseId'], $second['courseId']);
        $this->assertSame($first['categoryId'], $second['categoryId']);
    }

    public function test_ensure_course_adopts_legacy_shortname_and_splits_collisions(): void {
        global $DB;

        $legacy = $this->getDataGenerator()->create_course([
            'fullname' => 'Programming I',
            'shortname' => '2026-01_CS101_BSCSMY1S1',
        ]);
        $this->assertSame('', (string) $legacy->idnumber);

        $adopted = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:34');
        $this->assertSame(0, $adopted['created']);
        $this->assertSame((int) $legacy->id, $adopted['courseId']);
        $this->assertSame('12:34', $DB->get_field('course', 'idnumber', ['id' => $legacy->id]));

        $other = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:99');
        $this->assertSame(1, $other['created']);
        $this->assertNotSame($adopted['courseId'], $other['courseId']);
        $this->assertSame(
            '2026-01_CS101_BSCSMY1S1_12-99',
            $DB->get_field('course', 'shortname', ['id' => $other['courseId']])
        );
    }

    public function test_provision_enrol_and_unenrol(): void {
        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $course = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:34');

        $provisioned = provision_users::execute([
            [
                'email' => 'lecturer@example.com',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
            [
                'email' => 'student@example.com',
                'firstname' => 'Alan',
                'lastname' => 'Turing',
            ],
        ]);

        $this->assertCount(2, $provisioned['users']);
        $this->assertNotEmpty($provisioned['users'][0]['token']);
        $this->assertNotEmpty($provisioned['users'][1]['token']);

        $enrolled = enrol_users::execute([
            [
                'email' => 'lecturer@example.com',
                'courseid' => $course['courseId'],
                'roleshortname' => 'editingteacher',
            ],
            [
                'email' => 'student@example.com',
                'courseid' => $course['courseId'],
                'roleshortname' => 'student',
            ],
        ]);
        $this->assertSame(2, $enrolled['enrolled']);

        $unenrolled = unenrol_users::execute([
            [
                'email' => 'student@example.com',
                'courseid' => $course['courseId'],
            ],
        ]);
        $this->assertSame(1, $unenrolled['unenrolled']);

        $noop = unenrol_users::execute([
            [
                'email' => 'missing@example.com',
                'courseid' => $course['courseId'],
            ],
        ]);
        $this->assertSame(0, $noop['unenrolled']);
    }

    public function test_rebind_keeps_user_id_and_enrolment(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $course = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:34');
        $fromemail = 'old.primary@example.com';
        $toemail = 'new.primary@example.com';
        $provisioned = provision_users::execute([
            [
                'email' => $fromemail,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
        ]);
        $fromid = (int) $provisioned['users'][0]['moodleUserId'];
        enrol_users::execute([
            [
                'email' => $fromemail,
                'courseid' => $course['courseId'],
                'roleshortname' => 'student',
            ],
        ]);

        $result = rebind_user_email::execute($fromemail, $toemail);

        $this->assertSame($fromid, $result['moodleUserId']);
        $this->assertSame($toemail, $result['email']);
        $this->assertNotEmpty($result['token']);
        $user = $DB->get_record('user', ['id' => $fromid], '*', MUST_EXIST);
        $this->assertSame($toemail, $user->email);
        $this->assertSame('new.primary.example.com', $user->username);
        $this->assertSame('oauth2', $user->auth);
        $this->assertTrue($DB->record_exists('user_enrolments', ['userid' => $fromid]));
        $this->assertTrue($this->has_linked_login($fromid, $toemail));
        $this->assertFalse($this->has_linked_login($fromid, $fromemail));
        $this->assertNull(\local_fivedays\provision_helper::find_user_by_email($fromemail));
    }

    public function test_rebind_retry_with_original_emails_succeeds(): void {
        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $fromemail = 'retry.from@example.com';
        $toemail = 'retry.to@example.com';
        $provisioned = provision_users::execute([
            [
                'email' => $fromemail,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
        ]);
        $fromid = (int) $provisioned['users'][0]['moodleUserId'];

        $first = rebind_user_email::execute($fromemail, $toemail);
        $this->assertSame($fromid, $first['moodleUserId']);

        $retry = rebind_user_email::execute($fromemail, $toemail);
        $this->assertSame($fromid, $retry['moodleUserId']);
        $this->assertSame($toemail, $retry['email']);
        $this->assertNotEmpty($retry['token']);
    }

    public function test_rebind_fails_when_both_users_are_enrolled(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $course = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:34');
        $fromemail = 'both.from@example.com';
        $toemail = 'both.to@example.com';
        $provisioned = provision_users::execute([
            [
                'email' => $fromemail,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
            [
                'email' => $toemail,
                'firstname' => 'Alan',
                'lastname' => 'Turing',
            ],
        ]);
        $fromid = (int) $provisioned['users'][0]['moodleUserId'];
        $toid = (int) $provisioned['users'][1]['moodleUserId'];
        enrol_users::execute([
            [
                'email' => $fromemail,
                'courseid' => $course['courseId'],
                'roleshortname' => 'student',
            ],
            [
                'email' => $toemail,
                'courseid' => $course['courseId'],
                'roleshortname' => 'student',
            ],
        ]);
        $frombefore = $DB->get_record('user', ['id' => $fromid], '*', MUST_EXIST);
        $tobefore = $DB->get_record('user', ['id' => $toid], '*', MUST_EXIST);

        try {
            rebind_user_email::execute($fromemail, $toemail);
            $this->fail('Expected emailhasmoodlework');
        } catch (\moodle_exception $exception) {
            $this->assertSame('emailhasmoodlework', $exception->errorcode);
        }

        $fromafter = $DB->get_record('user', ['id' => $fromid], '*', MUST_EXIST);
        $toafter = $DB->get_record('user', ['id' => $toid], '*', MUST_EXIST);
        $this->assertSame($frombefore->email, $fromafter->email);
        $this->assertSame($frombefore->username, $fromafter->username);
        $this->assertEquals(0, $fromafter->deleted);
        $this->assertSame($tobefore->email, $toafter->email);
        $this->assertSame($tobefore->username, $toafter->username);
        $this->assertEquals(0, $toafter->deleted);
        $this->assertTrue($DB->record_exists('user_enrolments', ['userid' => $fromid]));
        $this->assertTrue($DB->record_exists('user_enrolments', ['userid' => $toid]));
    }

    public function test_rebind_deletes_unenrolled_from_user_when_target_is_enrolled(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $course = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:34');
        $fromemail = 'empty.from@example.com';
        $toemail = 'enrolled.to@example.com';
        $provisioned = provision_users::execute([
            [
                'email' => $fromemail,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
            [
                'email' => $toemail,
                'firstname' => 'Alan',
                'lastname' => 'Turing',
            ],
        ]);
        $fromid = (int) $provisioned['users'][0]['moodleUserId'];
        $toid = (int) $provisioned['users'][1]['moodleUserId'];
        enrol_users::execute([
            [
                'email' => $toemail,
                'courseid' => $course['courseId'],
                'roleshortname' => 'student',
            ],
        ]);

        $result = rebind_user_email::execute($fromemail, $toemail);

        $this->assertSame($toid, $result['moodleUserId']);
        $this->assertSame($toemail, $result['email']);
        $this->assertEquals(1, $DB->get_field('user', 'deleted', ['id' => $fromid]));
        $this->assertEquals(0, $DB->get_field('user', 'deleted', ['id' => $toid]));
        $this->assertNull(\local_fivedays\provision_helper::find_user_by_email($fromemail));
    }

    public function test_rebind_deletes_unenrolled_target_and_keeps_from_user(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $course = ensure_course::execute('Programming I', '2026-01_CS101_BSCSMY1S1', 'FICT', '12:34');
        $fromemail = 'canonical.from@example.com';
        $toemail = 'stray.to@example.com';
        $provisioned = provision_users::execute([
            [
                'email' => $fromemail,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
            [
                'email' => $toemail,
                'firstname' => 'Alan',
                'lastname' => 'Turing',
            ],
        ]);
        $fromid = (int) $provisioned['users'][0]['moodleUserId'];
        $toid = (int) $provisioned['users'][1]['moodleUserId'];
        enrol_users::execute([
            [
                'email' => $fromemail,
                'courseid' => $course['courseId'],
                'roleshortname' => 'student',
            ],
        ]);

        $result = rebind_user_email::execute($fromemail, $toemail);

        $this->assertSame($fromid, $result['moodleUserId']);
        $this->assertSame($toemail, $result['email']);
        $this->assertEquals(1, $DB->get_field('user', 'deleted', ['id' => $toid]));
        $user = $DB->get_record('user', ['id' => $fromid], '*', MUST_EXIST);
        $this->assertEquals(0, $user->deleted);
        $this->assertSame($toemail, $user->email);
        $this->assertSame('stray.to.example.com', $user->username);
        $this->assertTrue($DB->record_exists('user_enrolments', ['userid' => $fromid]));
        $this->assertTrue($this->has_linked_login($fromid, $toemail));
    }

    public function test_rebind_missing_emails_throws_usernotfound(): void {
        ensure_google_login::execute('test-client-id', 'test-client-secret');

        try {
            rebind_user_email::execute('missing.from@example.com', 'missing.to@example.com');
            $this->fail('Expected usernotfound');
        } catch (\moodle_exception $exception) {
            $this->assertSame('usernotfound', $exception->errorcode);
        }
    }

    public function test_rebind_finds_mixed_case_stored_email(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $fromemail = 'mixed.case@example.com';
        $toemail = 'mixed.to@example.com';
        $provisioned = provision_users::execute([
            [
                'email' => $fromemail,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
        ]);
        $fromid = (int) $provisioned['users'][0]['moodleUserId'];
        $DB->set_field('user', 'email', 'Mixed.Case@example.com', ['id' => $fromid]);

        $result = rebind_user_email::execute($fromemail, $toemail);

        $this->assertSame($fromid, $result['moodleUserId']);
        $this->assertSame($toemail, $DB->get_field('user', 'email', ['id' => $fromid]));
    }

    public function test_rebind_throws_when_target_username_is_taken(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $fromemail = 'clash.from@example.com';
        $toemail = 'clash.to@example.com';
        $provisioned = provision_users::execute([
            [
                'email' => $fromemail,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
        ]);
        $fromid = (int) $provisioned['users'][0]['moodleUserId'];
        $frombefore = $DB->get_record('user', ['id' => $fromid], '*', MUST_EXIST);
        $this->getDataGenerator()->create_user([
            'username' => 'clash.to.example.com',
            'email' => 'clash.other@example.com',
        ]);

        try {
            rebind_user_email::execute($fromemail, $toemail);
            $this->fail('Expected invalid_parameter_exception');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertSame('invalidparameter', $exception->errorcode);
        }

        $fromafter = $DB->get_record('user', ['id' => $fromid], '*', MUST_EXIST);
        $this->assertSame($frombefore->email, $fromafter->email);
        $this->assertSame($frombefore->username, $fromafter->username);
        $this->assertEquals(0, $fromafter->deleted);
        $this->assertTrue($this->has_linked_login($fromid, $fromemail));
    }

    public function test_provision_reuses_user_when_email_case_differs(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $existing = $this->getDataGenerator()->create_user([
            'username' => 'case.user',
            'email' => 'Case.User@example.com',
        ]);

        $provisioned = provision_users::execute([
            [
                'email' => 'case.user@EXAMPLE.com',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
        ]);

        $this->assertSame((int) $existing->id, (int) $provisioned['users'][0]['moodleUserId']);
        $this->assertSame(1, $DB->count_records_select('user', 'LOWER(email) = ? AND deleted = 0', ['case.user@example.com']));
        $this->assertTrue($this->has_linked_login((int) $existing->id, 'case.user@example.com'));
    }

    public function test_provision_suffixes_username_derived_from_email_on_collision(): void {
        global $DB;

        ensure_google_login::execute('test-client-id', 'test-client-secret');
        $first = provision_users::execute([
            [
                'email' => 'a.b@c.com',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
            ],
        ]);
        $second = provision_users::execute([
            [
                'email' => 'a@b.c.com',
                'firstname' => 'Alan',
                'lastname' => 'Turing',
            ],
        ]);

        $firstid = (int) $first['users'][0]['moodleUserId'];
        $secondid = (int) $second['users'][0]['moodleUserId'];
        $this->assertNotSame($firstid, $secondid);
        $this->assertSame('a.b.c.com', $DB->get_field('user', 'username', ['id' => $firstid]));
        $this->assertSame('a.b.c.com2', $DB->get_field('user', 'username', ['id' => $secondid]));
        $this->assertSame('a@b.c.com', $DB->get_field('user', 'email', ['id' => $secondid]));
    }

    public function test_delete_course_removes_course_with_content(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $result = delete_course::execute((int) $course->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('course', ['id' => $course->id]));
    }

    public function test_delete_course_refuses_site_course(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('cannotdeletesitecourse', 'local_fivedays'));

        delete_course::execute((int) SITEID);
    }

    public function test_delete_course_refuses_missing_course(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('coursenotfound', 'local_fivedays'));

        delete_course::execute(999999);
    }

    private function has_linked_login(int $userid, string $email): bool {
        global $DB;

        return $DB->record_exists_select(
            'auth_oauth2_linked_login',
            'userid = ? AND LOWER(username) = LOWER(?)',
            [$userid, $email]
        );
    }
}
