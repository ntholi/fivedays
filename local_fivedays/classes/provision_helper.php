<?php
namespace local_fivedays;

defined('MOODLE_INTERNAL') || die();

class provision_helper {

    public static function require_system_capability(): \context_system {
        $context = \context_system::instance();
        \core_external\external_api::validate_context($context);
        require_capability('local/fivedays:provision', $context);
        return $context;
    }

    public static function find_google_issuer(): ?\core\oauth2\issuer {
        $issuers = \core\oauth2\api::get_all_issuers(true);
        foreach ($issuers as $candidate) {
            if (strtolower((string) $candidate->get('name')) === 'google') {
                return $candidate;
            }
        }
        return null;
    }

    public static function require_google_issuer(): \core\oauth2\issuer {
        $issuer = self::find_google_issuer();
        if (!$issuer) {
            throw new \moodle_exception('googleissuermissing', 'local_fivedays');
        }
        return $issuer;
    }

    public static function find_fivedays_service(): ?\stdClass {
        global $DB;

        foreach (['fivedays', 'registry_lms'] as $shortname) {
            $service = $DB->get_record('external_services', ['shortname' => $shortname]);
            if ($service) {
                return $service;
            }
        }

        return null;
    }

    public static function ensure_rest_service(): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/webservice/lib.php');

        set_config('enablewebservices', 1);
        $protocols = array_filter(explode(',', get_config('core', 'webserviceprotocols') ?: ''));
        if (!in_array('rest', $protocols, true)) {
            $protocols[] = 'rest';
            set_config('webserviceprotocols', implode(',', $protocols));
        }

        $webservicemanager = new \webservice();
        $service = self::find_fivedays_service();
        if (!$service) {
            $service = (object) [
                'name' => 'FiveDays',
                'shortname' => 'fivedays',
                'enabled' => 1,
                'restrictedusers' => 0,
                'downloadfiles' => 1,
                'uploadfiles' => 1,
            ];
            $service->id = $webservicemanager->add_external_service($service);
        } else if (
            $service->name !== 'FiveDays'
            || $service->shortname !== 'fivedays'
            || (int) $service->enabled !== 1
        ) {
            $service->name = 'FiveDays';
            $service->shortname = 'fivedays';
            $service->enabled = 1;
            $webservicemanager->update_external_service($service);
        }

        $pluginfunctions = $DB->get_fieldset_select('external_functions', 'name', "name LIKE 'local_fivedays_%'");
        $core = [
            'core_enrol_get_enrolled_users',
        ];
        foreach (array_unique(array_merge($pluginfunctions, $core)) as $functionname) {
            if (!$DB->record_exists('external_functions', ['name' => $functionname])) {
                continue;
            }
            if (!$webservicemanager->service_function_exists($functionname, $service->id)) {
                $webservicemanager->add_external_function_to_service($functionname, $service->id);
            }
        }

        return $service;
    }

    public static function ensure_token(\stdClass $service, int $userid): string {
        global $DB;

        $existing = $DB->get_record('external_tokens', [
            'userid' => $userid,
            'externalserviceid' => $service->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
        ]);
        if ($existing) {
            return $existing->token;
        }
        return \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            $userid,
            \context_system::instance()
        );
    }

    public static function ensure_oauth_user(
        string $email,
        string $firstname,
        string $lastname,
        \core\oauth2\issuer $issuer
    ): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');

        $email = strtolower(trim($email));
        $user = self::find_user_by_email($email);
        if (!$user) {
            $record = (object) [
                'auth' => 'oauth2',
                'username' => self::unique_username(str_replace('@', '.', $email)),
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'password' => AUTH_PASSWORD_NOT_CACHED,
            ];
            $id = user_create_user($record, false, false);
            $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
        } else if ($user->auth !== 'oauth2') {
            $user->auth = 'oauth2';
            user_update_user($user, false, false);
        }

        try {
            \auth_oauth2\api::link_login([
                'username' => $email,
                'email' => $email,
            ], $issuer, $user->id, true);
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'alreadylinked') {
                throw $exception;
            }
        }

        return $user;
    }

    private static function unique_username(string $base): string {
        global $CFG, $DB;

        $username = $base;
        $suffix = 1;
        while ($DB->record_exists('user', [
            'username' => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ])) {
            $suffix++;
            $username = $base . $suffix;
        }
        return $username;
    }

    public static function ensure_category(string $categoryname): \core_course_category {
        global $DB;

        $record = $DB->get_record_sql(
            'SELECT * FROM {course_categories} WHERE LOWER(name) = LOWER(?)',
            [$categoryname],
            IGNORE_MULTIPLE
        );
        if ($record) {
            return \core_course_category::get((int) $record->id, MUST_EXIST, true);
        }

        return \core_course_category::create([
            'name' => $categoryname,
            'parent' => 0,
            'visible' => 1,
        ]);
    }

    public static function ensure_course(
        string $fullname,
        string $shortname,
        string $categoryname,
        string $idnumber
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $fullname = trim($fullname);
        $shortname = trim($shortname);
        $categoryname = trim($categoryname);
        $idnumber = trim($idnumber);

        if ($fullname === '' || $shortname === '' || $categoryname === '') {
            throw new \invalid_parameter_exception('Course fullname, shortname, and categoryname are required');
        }
        if (!preg_match('/^\d+:\d+$/', $idnumber)) {
            throw new \invalid_parameter_exception('idnumber must be termId:semesterModuleId');
        }

        $category = self::ensure_category($categoryname);
        $existing = self::find_reusable_course($shortname, $idnumber);
        if ($existing) {
            self::adopt_course_idnumber($existing, $idnumber);
            return self::course_ensure_result($existing, 0, $category);
        }

        $createshortname = $shortname;
        $clash = $DB->get_record('course', ['shortname' => $createshortname]);
        if ($clash && (string) $clash->idnumber !== '' && (string) $clash->idnumber !== $idnumber) {
            $createshortname = self::disambiguated_shortname($shortname, $idnumber);
        }

        try {
            $course = self::create_ensured_course($fullname, $createshortname, $idnumber, (int) $category->id);
            return self::course_ensure_result($course, 1, $category);
        } catch (\moodle_exception $exception) {
            if (!in_array($exception->errorcode, ['shortnametaken', 'courseidnumbertaken'], true)) {
                throw $exception;
            }

            $existing = self::find_reusable_course($createshortname, $idnumber)
                ?: self::find_reusable_course($shortname, $idnumber);
            if ($existing) {
                self::adopt_course_idnumber($existing, $idnumber);
                return self::course_ensure_result($existing, 0, $category);
            }

            if ($exception->errorcode === 'shortnametaken' && $createshortname === $shortname) {
                $createshortname = self::disambiguated_shortname($shortname, $idnumber);
                try {
                    $course = self::create_ensured_course(
                        $fullname,
                        $createshortname,
                        $idnumber,
                        (int) $category->id
                    );
                    return self::course_ensure_result($course, 1, $category);
                } catch (\moodle_exception $retry) {
                    if (!in_array($retry->errorcode, ['shortnametaken', 'courseidnumbertaken'], true)) {
                        throw $retry;
                    }
                    $existing = self::find_reusable_course($createshortname, $idnumber);
                    if ($existing) {
                        self::adopt_course_idnumber($existing, $idnumber);
                        return self::course_ensure_result($existing, 0, $category);
                    }
                    throw $retry;
                }
            }

            throw $exception;
        }
    }

    private static function create_ensured_course(
        string $fullname,
        string $shortname,
        string $idnumber,
        int $categoryid
    ): \stdClass {
        return create_course((object) [
            'fullname' => $fullname,
            'shortname' => $shortname,
            'idnumber' => $idnumber,
            'category' => $categoryid,
            'visible' => 1,
        ]);
    }

    private static function find_reusable_course(string $shortname, string $idnumber): ?\stdClass {
        global $DB;

        $byidnumber = $DB->get_record('course', ['idnumber' => $idnumber]);
        if ($byidnumber) {
            return $byidnumber;
        }

        $byshortname = $DB->get_record('course', ['shortname' => $shortname]);
        if (!$byshortname) {
            return null;
        }

        $existingidnumber = (string) $byshortname->idnumber;
        if ($existingidnumber === '' || $existingidnumber === $idnumber) {
            return $byshortname;
        }

        return null;
    }

    private static function adopt_course_idnumber(\stdClass $course, string $idnumber): void {
        global $DB;

        if ((string) $course->idnumber === $idnumber) {
            return;
        }
        if ((string) $course->idnumber !== '') {
            return;
        }

        $taken = $DB->get_record('course', ['idnumber' => $idnumber]);
        if ($taken && (int) $taken->id !== (int) $course->id) {
            return;
        }

        $DB->set_field('course', 'idnumber', $idnumber, ['id' => $course->id]);
        $course->idnumber = $idnumber;
    }

    private static function disambiguated_shortname(string $shortname, string $idnumber): string {
        return $shortname . '_' . str_replace(':', '-', $idnumber);
    }

    private static function course_ensure_result(
        \stdClass $course,
        int $created,
        \core_course_category $category
    ): array {
        return [
            'courseId' => (int) $course->id,
            'created' => $created,
            'categoryId' => (int) $category->id,
        ];
    }

    public static function find_user_by_email(string $email): ?\stdClass {
        global $DB;

        $email = strtolower(trim($email));
        $user = $DB->get_record_sql(
            'SELECT * FROM {user} WHERE LOWER(email) = ? AND deleted = 0',
            [$email],
            IGNORE_MULTIPLE
        );
        return $user ?: null;
    }

    public static function has_enrolments(int $userid): bool {
        global $DB;

        return $DB->record_exists('user_enrolments', ['userid' => $userid]);
    }

    public static function rebind_oauth_user_email(
        string $fromemail,
        string $toemail,
        \core\oauth2\issuer $issuer
    ): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');

        $fromemail = strtolower(trim($fromemail));
        $toemail = strtolower(trim($toemail));
        $from = self::find_user_by_email($fromemail);
        $to = self::find_user_by_email($toemail);

        if ($fromemail === $toemail) {
            $user = $from ?: $to;
            if (!$user) {
                throw new \moodle_exception('usernotfound', 'local_fivedays', '', $fromemail);
            }
            self::link_oauth_login($user, $fromemail, $issuer);
            return $user;
        }

        if ($from && $to && (int) $from->id === (int) $to->id) {
            self::link_oauth_login($to, $toemail, $issuer);
            return $to;
        }

        if ($from && $to && (int) $from->id !== (int) $to->id) {
            $fromenrolled = self::has_enrolments((int) $from->id);
            $toenrolled = self::has_enrolments((int) $to->id);
            if ($fromenrolled && $toenrolled) {
                throw new \moodle_exception('emailhasmoodlework', 'local_fivedays');
            }
            if ($toenrolled && !$fromenrolled) {
                self::unlink_oauth_login((int) $from->id, $fromemail);
                delete_user($from);
                self::link_oauth_login($to, $toemail, $issuer);
                return $to;
            }
            self::unlink_oauth_login((int) $to->id, $toemail);
            delete_user($to);
            $to = null;
        }

        if (!$from && $to) {
            self::link_oauth_login($to, $toemail, $issuer);
            return $to;
        }

        if ($from && !$to) {
            $username = strtolower(str_replace('@', '.', $toemail));
            $clash = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
            if ($clash && (int) $clash->id !== (int) $from->id) {
                throw new \invalid_parameter_exception('Username is already in use');
            }

            self::unlink_oauth_login((int) $from->id, $fromemail);
            $from->email = $toemail;
            $from->username = $username;
            user_update_user($from, false, false);
            self::link_oauth_login($from, $toemail, $issuer);
            return $from;
        }

        throw new \moodle_exception('usernotfound', 'local_fivedays', '', $fromemail);
    }

    private static function unlink_oauth_login(int $userid, string $email): void {
        global $DB;

        $DB->delete_records_select(
            'auth_oauth2_linked_login',
            'userid = ? AND LOWER(username) = LOWER(?)',
            [$userid, $email]
        );
    }

    private static function link_oauth_login(
        \stdClass $user,
        string $email,
        \core\oauth2\issuer $issuer
    ): void {
        try {
            \auth_oauth2\api::link_login([
                'username' => $email,
                'email' => $email,
            ], $issuer, $user->id, true);
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'alreadylinked') {
                throw $exception;
            }
        }
    }

    public static function ensure_manual_instance(\stdClass $course): \stdClass {
        global $DB;

        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        if (!$instance) {
            $plugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        }
        return $instance;
    }

    public static function enrol_user_by_email(string $email, int $courseid, string $roleshortname): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $user = self::find_user_by_email($email);
        if (!$user) {
            throw new \moodle_exception('usernotfound', 'local_fivedays', '', $email);
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname], MUST_EXIST);
        $plugin = enrol_get_plugin('manual');
        $instance = self::ensure_manual_instance($course);

        try {
            $plugin->enrol_user($instance, (int) $user->id, (int) $roleid);
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'Message was not sent.') {
                throw $exception;
            }
        }

        return true;
    }

    public static function unenrol_user_by_email(string $email, int $courseid): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $user = self::find_user_by_email($email);
        if (!$user) {
            return false;
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return false;
        }

        $context = \context_course::instance($course->id);
        if (!is_enrolled($context, $user)) {
            return false;
        }

        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        if (!$instance) {
            return false;
        }

        $plugin->unenrol_user($instance, (int) $user->id);
        return true;
    }

    public static function ensure_google_login(string $clientid, string $clientsecret): int {
        $auths = array_values(array_filter(explode(',', get_config('core', 'auth') ?: 'manual')));
        if (!in_array('manual', $auths, true)) {
            array_unshift($auths, 'manual');
        }
        if (!in_array('oauth2', $auths, true)) {
            $auths[] = 'oauth2';
        }
        set_config('auth', implode(',', $auths));
        set_config('showloginform', 1);
        set_config('authpreventaccountcreation', 1);

        $issuer = self::find_google_issuer();
        if (!$issuer) {
            $issuer = \core\oauth2\api::create_standard_issuer('google');
        }
        $issuer->set('clientid', $clientid);
        $issuer->set('clientsecret', $clientsecret);
        $issuer->set('enabled', 1);
        $issuer->set('showonloginpage', \core\oauth2\issuer::EVERYWHERE);
        $issuer->set('requireconfirmation', 0);
        $issuer->update();

        return 1;
    }
}
