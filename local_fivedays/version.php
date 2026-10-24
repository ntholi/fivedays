<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_fivedays';
$plugin->version = 2026102403;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.4.0';
$plugin->dependencies = [
    'gradingform_fivedays' => 2026101200,
];
