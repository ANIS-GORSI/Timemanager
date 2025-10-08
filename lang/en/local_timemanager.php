<?php
// local/timemanager/lang/en/local_timemanager.php

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Time Manager';

// Caps (avoid [[cap]] display).
$string['timemanager:view'] = 'View Time Manager';
$string['timemanager:manageown'] = 'Manage own Time Manager planning';

// Index / list.
$string['yourcoursesandassignments'] = 'Your Courses and Assignments';
$string['noassignments'] = 'No upcoming assignments found.';
$string['daysleft'] = '{$a} days left';
$string['overdue'] = 'Overdue by {$a} days';
$string['plan'] = 'Plan';

// Detail page / labels.
$string['recommendedstart'] = 'Recommended start';
$string['na'] = 'N/A';
$string['startdate'] = 'Start date';
$string['completiondate'] = 'Planned finish date';
$string['reminderbeforestart'] = 'Email reminder (days before start)';
$string['reminderbeforedue'] = 'Email reminder (days before due)';
$string['exportics'] = 'Export ICS';

// Task strings
$string['send_notifications'] = 'Send timemanager email reminders';

// Email subject strings
$string['startdate_subject'] = 'Time to Start: "{$a->taskname}" for {$a->coursename}';
$string['upcoming_subject'] = 'Upcoming Assessment: {$a->taskname} Due on {$a->duedate}';

// Notification settings
$string['notification_settings'] = 'Notification Settings';
$string['enable_notifications'] = 'Enable email reminders';
$string['enable_notifications_desc'] = 'Receive email reminders for task start dates and upcoming deadlines';
$string['notification_preferences_saved'] = 'Notification preferences saved successfully';

// General strings
$string['timemanager'] = 'Time Manager';
$string['managetime'] = 'Manage Time';

// Task types
$string['tasktype_assignment'] = 'Assignment';
$string['tasktype_quiz'] = 'Quiz';
$string['tasktype_forum'] = 'Forum';
$string['tasktype_project'] = 'Project';
$string['tasktype_exam'] = 'Exam';
$string['tasktype_other'] = 'Other';
