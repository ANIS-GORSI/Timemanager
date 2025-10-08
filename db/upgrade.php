<?php
// File: db/upgrade.php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_timemanager_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Add notification tracking fields
    if ($oldversion < 2025010100) {
        
        $table = new xmldb_table('local_timemanager');
        
        // Add notified_start field
        $field = new xmldb_field('notified_start', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'estimatedeffort');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add notified_upcoming field
        $field = new xmldb_field('notified_upcoming', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notified_start');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add tasktype field if it doesn't exist
        $field = new xmldb_field('tasktype', XMLDB_TYPE_CHAR, '50', null, null, null, 'assignment', 'taskname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add indexes for better performance
        $index = new xmldb_index('notified_start', XMLDB_INDEX_NOTUNIQUE, ['notified_start']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('notified_upcoming', XMLDB_INDEX_NOTUNIQUE, ['notified_upcoming']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_plugin_savepoint(true, 2025010100, 'local', 'timemanager');
    }

    return true;
}