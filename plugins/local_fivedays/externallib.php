<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/provision_helper.php');
require_once(__DIR__ . '/classes/external/provision/provision_users.php');
require_once(__DIR__ . '/classes/external/provision/rebind_user_email.php');
require_once(__DIR__ . '/classes/external/provision/ensure_course.php');
require_once(__DIR__ . '/classes/external/provision/enrol_users.php');
require_once(__DIR__ . '/classes/external/provision/unenrol_users.php');
require_once(__DIR__ . '/classes/external/provision/ensure_google_login.php');
