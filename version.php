<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_activity_utils';
$plugin->version = 2026101302;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v5.4';
$plugin->dependencies = [
    'gradingform_fivedays' => 2026101200,
];
