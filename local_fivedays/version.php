<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_fivedays';
$plugin->version = 2026102201;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.1';
$plugin->dependencies = [
    'gradingform_fivedays' => 2026101200,
];
