<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_activity_utils';
$plugin->version = 2026101200;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v5.3';
$plugin->dependencies = [
    'gradingform_fivedays' => 2024121500,
];
