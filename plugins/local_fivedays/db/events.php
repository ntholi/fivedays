<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_fivedays\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_fivedays\observer::course_module_updated',
    ],
    [
        'eventname' => '\core\event\course_section_updated',
        'callback' => '\local_fivedays\observer::course_section_updated',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_fivedays\observer::course_module_deleted',
    ],
];
