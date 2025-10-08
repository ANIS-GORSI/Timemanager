
<?php
// File: classes/task/send_notifications.php
namespace local_timemanager\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for sending timemanager email reminders
 */
class send_notifications extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('send_notifications', 'local_timemanager');
    }

    /**
     * Execute the task
     */
    public function execute() {
        global $DB;

        mtrace('Starting timemanager email notifications...');

        // Send start date notifications
        $start_notifications = $this->send_start_date_notifications();
        
        // Send upcoming task reminders (4 weeks before due date)
        $upcoming_notifications = $this->send_upcoming_task_reminders();

        mtrace("Start date notifications sent: {$start_notifications}");
        mtrace("Upcoming task reminders sent: {$upcoming_notifications}");
        mtrace("Email notifications complete.");
    }

    /**
     * Send notifications for tasks whose start date has been reached
     */
    private function send_start_date_notifications() {
        global $DB;

        $today = strtotime('today');
        $sent = 0;

        // Get tasks where start date is today
        $sql = "SELECT t.*, u.*, c.fullname as coursename
                FROM {local_timemanager} t
                JOIN {user} u ON t.userid = u.id
                JOIN {course} c ON t.courseid = c.id
                WHERE DATE(FROM_UNIXTIME(t.startdate)) = CURDATE()
                AND u.deleted = 0 
                AND u.suspended = 0
                AND t.notified_start = 0";

        $tasks = $DB->get_records_sql($sql);

        foreach ($tasks as $task) {
            if ($this->send_start_date_email($task)) {
                // Mark as notified
                $DB->set_field('local_timemanager', 'notified_start', 1, array('id' => $task->id));
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send reminders for upcoming tasks (4 weeks before due date)
     */
    private function send_upcoming_task_reminders() {
        global $DB;

        $reminder_date = strtotime('+4 weeks');
        $sent = 0;

        // Get tasks due in 4 weeks
        $sql = "SELECT t.*, u.*, c.fullname as coursename
                FROM {local_timemanager} t
                JOIN {user} u ON t.userid = u.id
                JOIN {course} c ON t.courseid = c.id
                WHERE DATE(FROM_UNIXTIME(t.duedate)) = DATE(FROM_UNIXTIME(?))
                AND u.deleted = 0 
                AND u.suspended = 0
                AND t.notified_upcoming = 0";

        $tasks = $DB->get_records_sql($sql, array($reminder_date));

        foreach ($tasks as $task) {
            if ($this->send_upcoming_task_email($task)) {
                // Mark as notified
                $DB->set_field('local_timemanager', 'notified_upcoming', 1, array('id' => $task->id));
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send start date notification email
     */
    private function send_start_date_email($task) {
        global $CFG;

        try {
            $user = new \stdClass();
            $user->id = $task->userid;
            $user->email = $task->email;
            $user->firstname = $task->firstname;
            $user->lastname = $task->lastname;
            $user->mailformat = 1;

            $subject = get_string('startdate_subject', 'local_timemanager', array(
                'taskname' => $task->taskname,
                'coursename' => $task->coursename
            ));
            
            $messagehtml = $this->get_start_date_html($task);
            $messageplain = $this->get_start_date_plain($task);
            
            $from = \core_user::get_noreply_user();
            
            $success = email_to_user($user, $from, $subject, $messageplain, $messagehtml);
            
            if ($success) {
                mtrace("Start date notification sent to: {$user->email} for task: {$task->taskname}");
            } else {
                mtrace("Failed to send start notification to: {$user->email}");
            }
            
            return $success;
            
        } catch (\Exception $e) {
            mtrace("Error sending start notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send upcoming task reminder email
     */
    private function send_upcoming_task_email($task) {
        global $CFG;

        try {
            $user = new \stdClass();
            $user->id = $task->userid;
            $user->email = $task->email;
            $user->firstname = $task->firstname;
            $user->lastname = $task->lastname;
            $user->mailformat = 1;

            $subject = get_string('upcoming_subject', 'local_timemanager', array(
                'taskname' => $task->taskname,
                'duedate' => userdate($task->duedate, get_string('strftimedatefullshort'))
            ));
            
            $messagehtml = $this->get_upcoming_task_html($task);
            $messageplain = $this->get_upcoming_task_plain($task);
            
            $from = \core_user::get_noreply_user();
            
            $success = email_to_user($user, $from, $subject, $messageplain, $messagehtml);
            
            if ($success) {
                mtrace("Upcoming task reminder sent to: {$user->email} for task: {$task->taskname}");
            } else {
                mtrace("Failed to send upcoming reminder to: {$user->email}");
            }
            
            return $success;
            
        } catch (\Exception $e) {
            mtrace("Error sending upcoming reminder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate HTML for start date notification (Template 1)
     */
    private function get_start_date_html($task) {
        global $CFG;

        $timemanagerurl = new \moodle_url('/local/timemanager/index.php');
        $startdate = userdate($task->startdate, get_string('strftimedatefullshort'));
        $duedate = userdate($task->duedate, get_string('strftimedatefullshort'));
        $effort = $this->format_effort($task->estimatedeffort);

        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 30px 20px;
        }
        .email-body p {
            margin: 15px 0;
            color: #555555;
        }
        .task-details {
            background-color: #f8f9fa;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 20px 0;
        }
        .task-details strong {
            color: #333;
        }
        .cta-button {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Time to Start Your Task!</h1>
        </div>
        
        <div class="email-body">
            <p>Hi <strong>' . $task->firstname . '</strong>,</p>
            
            <p>You planned to begin working on <strong>"' . $task->taskname . '"</strong> for <strong>' . $task->coursename . '</strong>.</p>
            
            <div class="task-details">
                <p style="margin: 5px 0;"><strong>Start Date:</strong> ' . $startdate . '</p>
                <p style="margin: 5px 0;"><strong>Due Date:</strong> ' . $duedate . '</p>
                <p style="margin: 5px 0;"><strong>Estimated Effort:</strong> ' . $effort . '</p>
                <p style="margin: 5px 0;"><strong>Course:</strong> ' . $task->coursename . '</p>
            </div>
            
            <p>Now is a great time to review the instructions and start breaking it down into manageable steps. Consistent early progress can reduce stress closer to the deadline.</p>
            
            <div style="text-align: center;">
                <a href="' . $timemanagerurl . '" class="cta-button">
                    View Your Tasks
                </a>
            </div>
            
            <p style="text-align: center; font-size: 18px; color: #4CAF50; margin-top: 20px;">
                <strong>Keep going - you are doing great!</strong>
            </p>
        </div>
        
        <div class="email-footer">
            <p>Regards,<br>The Time Management Plugin Team</p>
            <p style="margin-top: 10px;">
                <a href="' . $CFG->wwwroot . '/local/timemanager/preferences.php" style="color: #4CAF50; text-decoration: none;">
                    Manage Email Preferences
                </a>
            </p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generate plain text for start date notification (Template 1)
     */
    private function get_start_date_plain($task) {
        global $CFG;

        $timemanagerurl = $CFG->wwwroot . '/local/timemanager/index.php';
        $startdate = userdate($task->startdate, get_string('strftimedatefullshort'));
        $duedate = userdate($task->duedate, get_string('strftimedatefullshort'));
        $effort = $this->format_effort($task->estimatedeffort);

        $plain = "
TIME TO START: '{$task->taskname}' FOR {$task->coursename}
==============================================================

Hi {$task->firstname},

You planned to begin working on '{$task->taskname}' for {$task->coursename}.

TASK DETAILS:
Start Date: {$startdate}
Due Date: {$duedate}
Estimated Effort: {$effort}
Course: {$task->coursename}

Now is a great time to review the instructions and start breaking it down into manageable steps. Consistent early progress can reduce stress closer to the deadline.

View your tasks: {$timemanagerurl}

Keep going - you are doing great!

Regards,
The Time Management Plugin Team

Manage preferences: {$CFG->wwwroot}/local/timemanager/preferences.php
";

        return $plain;
    }

    /**
     * Generate HTML for upcoming task reminder (Template 2)
     */
    private function get_upcoming_task_html($task) {
        global $CFG;

        $timemanagerurl = new \moodle_url('/local/timemanager/index.php');
        $startdate = userdate($task->startdate, get_string('strftimedatefullshort'));
        $duedate = userdate($task->duedate, get_string('strftimedatefullshort'));
        $tasktype = $this->get_task_type($task->tasktype);

        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 30px 20px;
        }
        .email-body p {
            margin: 15px 0;
            color: #555555;
        }
        .task-details {
            background-color: #f8f9fa;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
        }
        .task-details strong {
            color: #333;
        }
        .cta-button {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Upcoming Assessment Reminder</h1>
        </div>
        
        <div class="email-body">
            <p>Hi <strong>' . $task->firstname . '</strong>,</p>
            
            <p>This is a friendly reminder that your upcoming assessment for <strong>' . $task->coursename . '</strong> - <strong>"' . $task->taskname . '"</strong> - is due on <strong>' . $duedate . '</strong>.</p>
            
            <div class="task-details">
                <p style="margin: 5px 0;"><strong>Recommended Start Date:</strong> ' . $startdate . '</p>
                <p style="margin: 5px 0;"><strong>Task Type:</strong> ' . $tasktype . '</p>
                <p style="margin: 5px 0;"><strong>Course:</strong> ' . $task->coursename . '</p>
            </div>
            
            <p>Make sure to get started soon to stay on track with your studies. You can view all your upcoming tasks in the Time Management Plugin on Moodle.</p>
            
            <div style="text-align: center;">
                <a href="' . $timemanagerurl . '" class="cta-button">
                    View All Tasks
                </a>
            </div>
            
            <p><strong>Need help getting started?</strong> Use the Export to Calendar button to add this task to your personal planner.</p>
            
            <p style="text-align: center; font-size: 18px; color: #2196F3; margin-top: 20px;">
                <strong>Good luck!</strong>
            </p>
        </div>
        
        <div class="email-footer">
            <p>The Time Management Plugin Team</p>
            <p style="margin-top: 10px;">
                <a href="' . $CFG->wwwroot . '/local/timemanager/preferences.php" style="color: #2196F3; text-decoration: none;">
                    Manage Email Preferences
                </a>
            </p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generate plain text for upcoming task reminder (Template 2)
     */
    private function get_upcoming_task_plain($task) {
        global $CFG;

        $timemanagerurl = $CFG->wwwroot . '/local/timemanager/index.php';
        $startdate = userdate($task->startdate, get_string('strftimedatefullshort'));
        $duedate = userdate($task->duedate, get_string('strftimedatefullshort'));
        $tasktype = $this->get_task_type($task->tasktype);

        $plain = "
UPCOMING ASSESSMENT: {$task->taskname} DUE ON {$duedate}
=========================================================

Hi {$task->firstname},

This is a friendly reminder that your upcoming assessment for {$task->coursename} - '{$task->taskname}' - is due on {$duedate}.

TASK DETAILS:
Recommended Start Date: {$startdate}
Task Type: {$tasktype}
Course: {$task->coursename}

Make sure to get started soon to stay on track with your studies. You can view all your upcoming tasks in the Time Management Plugin on Moodle.

View all tasks: {$timemanagerurl}

Need help getting started? Use the Export to Calendar button to add this task to your personal planner.

Good luck!

The Time Management Plugin Team

Manage preferences: {$CFG->wwwroot}/local/timemanager/preferences.php
";

        return $plain;
    }

    /**
     * Format effort hours/days
     */
    private function format_effort($effort) {
        if (empty($effort)) {
            return 'Not specified';
        }
        
        if ($effort < 24) {
            return $effort . ' Hours';
        } else {
            $days = round($effort / 24, 1);
            return $days . ' Days';
        }
    }

    /**
     * Get task type label
     */
    private function get_task_type($tasktype) {
        $types = array(
            'assignment' => 'Assignment',
            'quiz' => 'Quiz',
            'forum' => 'Forum',
            'project' => 'Project',
            'exam' => 'Exam',
            'other' => 'Other'
        );
        
        return isset($types[$tasktype]) ? $types[$tasktype] : 'Assignment';
    }
}
