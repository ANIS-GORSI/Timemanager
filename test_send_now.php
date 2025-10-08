<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/timemanager/test_send_now.php');
$PAGE->set_title('Test Email Notifications');

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<div class="container-fluid"><div class="row"><div class="col-md-10 offset-md-1">';
echo '<h2>📧 Test Timemanager Email Notifications</h2>';

if ($action === 'send_start') {
    // Test Template 1: Start Date Notification
    echo '<div class="alert alert-info"><strong>Testing Template 1: Start Date Notification</strong></div>';
    
    // Create mock task data
    $task = new stdClass();
    $task->id = 1;
    $task->userid = $USER->id;
    $task->email = $USER->email;
    $task->firstname = $USER->firstname;
    $task->lastname = $USER->lastname;
    $task->taskname = "Research Essay Assignment";
    $task->coursename = "Introduction to Psychology";
    $task->tasktype = "assignment";
    $task->startdate = time();
    $task->duedate = strtotime('+2 weeks');
    $task->estimatedeffort = 15;
    
    $timemanagerurl = new moodle_url('/local/timemanager/index.php');
    $startdate = userdate($task->startdate, get_string('strftimedatefullshort'));
    $duedate = userdate($task->duedate, get_string('strftimedatefullshort'));
    $effort = '15 Hours';
    
    $messagehtml = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .email-header { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: #fff; padding: 30px 20px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 24px; }
        .email-body { padding: 30px 20px; }
        .email-body p { margin: 15px 0; color: #555; }
        .task-details { background: #f8f9fa; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0; }
        .cta-button { display: inline-block; padding: 14px 30px; background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: 600; margin: 20px 0; }
        .email-footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <h1>Time to Start Your Task!</h1>
        </div>
        <div class='email-body'>
            <p>Hi <strong>{$task->firstname}</strong>,</p>
            <p>You planned to begin working on <strong>\"{$task->taskname}\"</strong> for <strong>{$task->coursename}</strong>.</p>
            <div class='task-details'>
                <p style='margin: 5px 0;'><strong>Start Date:</strong> {$startdate}</p>
                <p style='margin: 5px 0;'><strong>Due Date:</strong> {$duedate}</p>
                <p style='margin: 5px 0;'><strong>Estimated Effort:</strong> {$effort}</p>
                <p style='margin: 5px 0;'><strong>Course:</strong> {$task->coursename}</p>
            </div>
            <p>Now is a great time to review the instructions and start breaking it down into manageable steps.</p>
            <div style='text-align: center;'>
                <a href='{$timemanagerurl}' class='cta-button'>View Your Tasks</a>
            </div>
            <p style='text-align: center; font-size: 18px; color: #4CAF50; margin-top: 20px;'>
                <strong>Keep going - you are doing great!</strong>
            </p>
        </div>
        <div class='email-footer'>
            <p>Regards,<br>The Time Management Plugin Team</p>
        </div>
    </div>
</body>
</html>";

    $messageplain = "
TIME TO START: '{$task->taskname}' FOR {$task->coursename}
==============================================================

Hi {$task->firstname},

You planned to begin working on '{$task->taskname}' for {$task->coursename}.

TASK DETAILS:
Start Date: {$startdate}
Due Date: {$duedate}
Estimated Effort: {$effort}
Course: {$task->coursename}

Keep going - you are doing great!

Regards,
The Time Management Plugin Team
";

    $subject = "Time to Start: \"{$task->taskname}\" for {$task->coursename}";
    $from = core_user::get_noreply_user();
    
    $success = email_to_user($USER, $from, $subject, $messageplain, $messagehtml);
    
    if ($success) {
        echo '<div class="alert alert-success">';
        echo '<h4>✅ Start Date Notification Sent!</h4>';
        echo '<p>Template 1 email sent to: <strong>' . $USER->email . '</strong></p>';
        echo '<p>Check your inbox!</p>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger"><h4>❌ Failed to send email</h4></div>';
    }
    
} elseif ($action === 'send_upcoming') {
    // Test Template 2: Upcoming Task Reminder
    echo '<div class="alert alert-info"><strong>Testing Template 2: Upcoming Task Reminder</strong></div>';
    
    $task = new stdClass();
    $task->id = 2;
    $task->userid = $USER->id;
    $task->email = $USER->email;
    $task->firstname = $USER->firstname;
    $task->lastname = $USER->lastname;
    $task->taskname = "Final Project Presentation";
    $task->coursename = "Business Management 101";
    $task->tasktype = "project";
    $task->startdate = time();
    $task->duedate = strtotime('+4 weeks');
    $task->estimatedeffort = 40;
    
    $timemanagerurl = new moodle_url('/local/timemanager/index.php');
    $startdate = userdate($task->startdate, get_string('strftimedatefullshort'));
    $duedate = userdate($task->duedate, get_string('strftimedatefullshort'));
    $tasktype = 'Project';
    
    $messagehtml = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .email-header { background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: #fff; padding: 30px 20px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 24px; }
        .email-body { padding: 30px 20px; }
        .email-body p { margin: 15px 0; color: #555; }
        .task-details { background: #f8f9fa; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
        .cta-button { display: inline-block; padding: 14px 30px; background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: #fff !important; text-decoration: none; border-radius: 5px; font-weight: 600; margin: 20px 0; }
        .email-footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <h1>Upcoming Assessment Reminder</h1>
        </div>
        <div class='email-body'>
            <p>Hi <strong>{$task->firstname}</strong>,</p>
            <p>This is a friendly reminder that your upcoming assessment for <strong>{$task->coursename}</strong> - <strong>\"{$task->taskname}\"</strong> - is due on <strong>{$duedate}</strong>.</p>
            <div class='task-details'>
                <p style='margin: 5px 0;'><strong>Recommended Start Date:</strong> {$startdate}</p>
                <p style='margin: 5px 0;'><strong>Task Type:</strong> {$tasktype}</p>
                <p style='margin: 5px 0;'><strong>Course:</strong> {$task->coursename}</p>
            </div>
            <p>Make sure to get started soon to stay on track with your studies.</p>
            <div style='text-align: center;'>
                <a href='{$timemanagerurl}' class='cta-button'>View All Tasks</a>
            </div>
            <p><strong>Need help getting started?</strong> Use the Export to Calendar button to add this task to your personal planner.</p>
            <p style='text-align: center; font-size: 18px; color: #2196F3; margin-top: 20px;'>
                <strong>Good luck!</strong>
            </p>
        </div>
        <div class='email-footer'>
            <p>The Time Management Plugin Team</p>
        </div>
    </div>
</body>
</html>";

    $messageplain = "
UPCOMING ASSESSMENT: {$task->taskname} DUE ON {$duedate}
=========================================================

Hi {$task->firstname},

This is a friendly reminder that your upcoming assessment for {$task->coursename} - '{$task->taskname}' - is due on {$duedate}.

TASK DETAILS:
Recommended Start Date: {$startdate}
Task Type: {$tasktype}
Course: {$task->coursename}

Good luck!

The Time Management Plugin Team
";

    $subject = "Upcoming Assessment: {$task->taskname} Due on {$duedate}";
    $from = core_user::get_noreply_user();
    
    $success = email_to_user($USER, $from, $subject, $messageplain, $messagehtml);
    
    if ($success) {
        echo '<div class="alert alert-success">';
        echo '<h4>✅ Upcoming Task Reminder Sent!</h4>';
        echo '<p>Template 2 email sent to: <strong>' . $USER->email . '</strong></p>';
        echo '<p>Check your inbox!</p>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger"><h4>❌ Failed to send email</h4></div>';
    }
    
} else {
    // Show test options
    echo '<div class="alert alert-info">';
    echo '<p>This page lets you test both email notification templates.</p>';
    echo '<p>Emails will be sent to: <strong>' . $USER->email . '</strong></p>';
    echo '</div>';
    
    echo '<div class="row">';
    
    // Template 1 Card
    echo '<div class="col-md-6">';
    echo '<div class="card mb-3">';
    echo '<div class="card-header" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white;">';
    echo '<h4 style="margin: 0;">🚀 Template 1: Start Date</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<h5>Start Date Reached Notification</h5>';
    echo '<p class="text-muted">Sent when a task\'s planned start date arrives.</p>';
    echo '<a href="?action=send_start" class="btn btn-success btn-block">📤 Send Test Email (Template 1)</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Template 2 Card
    echo '<div class="col-md-6">';
    echo '<div class="card mb-3">';
    echo '<div class="card-header" style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white;">';
    echo '<h4 style="margin: 0;">⏰ Template 2: Upcoming Reminder</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<h5>Upcoming Task Reminder</h5>';
    echo '<p class="text-muted">Sent 4 weeks before the due date as an early reminder.</p>';
    echo '<a href="?action=send_upcoming" class="btn btn-primary btn-block">📤 Send Test Email (Template 2)</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
}

echo '<div class="mt-3">';
echo '<a href="' . $CFG->wwwroot . '/local/timemanager/" class="btn btn-secondary">← Back to Timemanager</a>';
echo '</div>';

echo '</div></div></div>';

echo $OUTPUT->footer();