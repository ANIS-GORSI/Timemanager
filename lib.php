<?php
// local/timemanager/lib.php

defined('MOODLE_INTERNAL') || die();

/**
 * Save user assignment planning preferences to database
 */
function local_timemanager_save_plan($userid, $assignmentid, $courseid, $startdate, $completiondate, $remind_before_start, $remind_before_due) {
    global $DB;
    
    $now = time();
    
    // Check if plan exists
    $existing = $DB->get_record('local_timemanager_plans', [
        'userid' => $userid,
        'assignmentid' => $assignmentid
    ]);
    
    $plandata = [
        'userid' => $userid,
        'assignmentid' => $assignmentid,
        'courseid' => $courseid,
        'startdate' => $startdate,
        'completiondate' => $completiondate,
        'remind_before_start' => $remind_before_start,
        'remind_before_due' => $remind_before_due,
        'timemodified' => $now
    ];
    
    if ($existing) {
        $plandata['id'] = $existing->id;
        $planid = $DB->update_record('local_timemanager_plans', $plandata);
        $planid = $existing->id;
    } else {
        $plandata['timecreated'] = $now;
        $planid = $DB->insert_record('local_timemanager_plans', $plandata);
    }
    
    // Clear existing notifications for this plan
    $DB->delete_records('local_timemanager_notifications', ['planid' => $planid]);
    
    // Schedule new notifications
    local_timemanager_schedule_notifications($planid, $userid, $assignmentid, $startdate, $completiondate, $remind_before_start, $remind_before_due);
    
    return $planid;
}

/**
 * Schedule email notifications based on user preferences
 */
function local_timemanager_schedule_notifications($planid, $userid, $assignmentid, $startdate, $completiondate, $remind_before_start, $remind_before_due) {
    global $DB;
    
    $notifications = [];
    $now = time();
    
    // Schedule start date reminder
    if ($startdate && $remind_before_start > 0) {
        $notification_time = $startdate - ($remind_before_start * DAYSECS);
        if ($notification_time > $now) {
            $notifications[] = [
                'planid' => $planid,
                'userid' => $userid,
                'assignmentid' => $assignmentid,
                'notification_type' => 'start_reminder',
                'scheduled_time' => $notification_time,
                'sent' => 0,
                'timecreated' => $now
            ];
        }
    }
    
    // Schedule due date reminder
    if ($completiondate && $remind_before_due > 0) {
        $notification_time = $completiondate - ($remind_before_due * DAYSECS);
        if ($notification_time > $now) {
            $notifications[] = [
                'planid' => $planid,
                'userid' => $userid,
                'assignmentid' => $assignmentid,
                'notification_type' => 'due_reminder',
                'scheduled_time' => $notification_time,
                'sent' => 0,
                'timecreated' => $now
            ];
        }
    }
    
    // Insert notifications
    foreach ($notifications as $notification) {
        $DB->insert_record('local_timemanager_notifications', $notification);
    }
}

/**
 * Get user's plan for an assignment
 */
function local_timemanager_get_plan($userid, $assignmentid) {
    global $DB;
    
    return $DB->get_record('local_timemanager_plans', [
        'userid' => $userid,
        'assignmentid' => $assignmentid
    ]);
}

/**
 * Send pending email notifications
 * This function will be called by the scheduled task
 */
function local_timemanager_send_pending_notifications() {
    global $DB;
    
    $now = time();
    
    // Get all unsent notifications that are due
    $sql = "SELECT n.*, u.email, u.firstname, u.lastname, a.name as assignmentname, c.fullname as coursename 
            FROM {local_timemanager_notifications} n
            JOIN {user} u ON u.id = n.userid
            JOIN {assign} a ON a.id = n.assignmentid  
            JOIN {course} c ON c.id = a.course
            WHERE n.sent = 0 AND n.scheduled_time <= :now
            ORDER BY n.scheduled_time ASC";
    
    $notifications = $DB->get_records_sql($sql, ['now' => $now]);
    
    $sent_count = 0;
    
    foreach ($notifications as $notification) {
        $success = local_timemanager_send_notification_email($notification);
        
        if ($success) {
            // Mark as sent
            $DB->update_record('local_timemanager_notifications', [
                'id' => $notification->id,
                'sent' => 1,
                'sent_time' => $now
            ]);
            $sent_count++;
        }
    }
    
    return $sent_count;
}

/**
 * Send individual notification email
 */
function local_timemanager_send_notification_email($notification) {
    global $CFG;
    
    // Get the user object
    $user = core_user::get_user($notification->userid);
    if (!$user) {
        return false;
    }
    
    // Prepare email content based on notification type
    if ($notification->notification_type === 'start_reminder') {
        $subject = "Time to start: {$notification->assignmentname}";
        $message = local_timemanager_get_start_reminder_message($notification);
    } else if ($notification->notification_type === 'due_reminder') {
        $subject = "Assignment due soon: {$notification->assignmentname}";
        $message = local_timemanager_get_due_reminder_message($notification);
    } else {
        return false;
    }
    
    // Send email using Moodle's email system
    $from = core_user::get_noreply_user();
    
    return email_to_user($user, $from, $subject, $message['text'], $message['html']);
}

/**
 * Generate start reminder email content
 */
function local_timemanager_get_start_reminder_message($notification) {
    $plan = local_timemanager_get_plan($notification->userid, $notification->assignmentid);
    
    $text = "Hi {$notification->firstname},\n\n";
    $text .= "This is a friendly reminder that you planned to start working on:\n\n";
    $text .= "Assignment: {$notification->assignmentname}\n";
    $text .= "Course: {$notification->coursename}\n\n";
    
    if ($plan && $plan->startdate) {
        $text .= "Your planned start date: " . userdate($plan->startdate) . "\n";
    }
    
    $text .= "\nGood luck with your assignment!\n\n";
    $text .= "Time Manager - Moodle Plugin";
    
    // HTML version
    $html = "<p>Hi {$notification->firstname},</p>";
    $html .= "<p>This is a friendly reminder that you planned to start working on:</p>";
    $html .= "<div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 15px 0;'>";
    $html .= "<strong>Assignment:</strong> {$notification->assignmentname}<br>";
    $html .= "<strong>Course:</strong> {$notification->coursename}";
    if ($plan && $plan->startdate) {
        $html .= "<br><strong>Your planned start date:</strong> " . userdate($plan->startdate);
    }
    $html .= "</div>";
    $html .= "<p>Good luck with your assignment!</p>";
    $html .= "<p><em>Time Manager - Moodle Plugin</em></p>";
    
    return ['text' => $text, 'html' => $html];
}

/**
 * Generate due reminder email content
 */
function local_timemanager_get_due_reminder_message($notification) {
    global $DB;
    
    $assign = $DB->get_record('assign', ['id' => $notification->assignmentid]);
    $plan = local_timemanager_get_plan($notification->userid, $notification->assignmentid);
    
    $text = "Hi {$notification->firstname},\n\n";
    $text .= "Your assignment is due soon:\n\n";
    $text .= "Assignment: {$notification->assignmentname}\n";
    $text .= "Course: {$notification->coursename}\n";
    
    if ($assign && $assign->duedate) {
        $text .= "Official due date: " . userdate($assign->duedate) . "\n";
    }
    
    if ($plan && $plan->completiondate) {
        $text .= "Your target completion: " . userdate($plan->completiondate) . "\n";
    }
    
    $text .= "\nDon't forget to submit your work on time!\n\n";
    $text .= "Time Manager - Moodle Plugin";
    
    // HTML version
    $html = "<p>Hi {$notification->firstname},</p>";
    $html .= "<p>Your assignment is due soon:</p>";
    $html .= "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0;'>";
    $html .= "<strong>Assignment:</strong> {$notification->assignmentname}<br>";
    $html .= "<strong>Course:</strong> {$notification->coursename}";
    if ($assign && $assign->duedate) {
        $html .= "<br><strong>Official due date:</strong> " . userdate($assign->duedate);
    }
    if ($plan && $plan->completiondate) {
        $html .= "<br><strong>Your target completion:</strong> " . userdate($plan->completiondate);
    }
    $html .= "</div>";
    $html .= "<p><strong>Don't forget to submit your work on time!</strong></p>";
    $html .= "<p><em>Time Manager - Moodle Plugin</em></p>";
    
    return ['text' => $text, 'html' => $html];
}