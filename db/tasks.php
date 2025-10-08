<?php
defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'local_timemanager\task\send_notifications',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '9',
        'day' => '*',
        'dayofweek' => '1-5',
        'month' => '*',
        'disabled' => 0,
    ),
);