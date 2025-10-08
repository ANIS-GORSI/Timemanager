<?php
// local/timemanager/export_calendar.php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$assignid = optional_param('assignid', 0, PARAM_INT);
$sesskey_param = optional_param('sesskey', '', PARAM_RAW);

// Also check for form data passed via GET/POST (for direct export)
$form_startdate = optional_param('startdate', '', PARAM_RAW_TRIMMED);
$form_completiondate = optional_param('completiondate', '', PARAM_RAW_TRIMMED);

// Verify sesskey for security
if (!confirm_sesskey($sesskey_param)) {
    throw new moodle_exception('invalidsesskey');
}

// Set headers for ICS file download
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="assignment_reminders.ics"');

global $USER, $DB;

// Start the ICS file
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//TimeManager//Moodle//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";

if ($assignid) {
    // Export specific assignment
    $assignment = $DB->get_record('assign', ['id' => $assignid], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $assignment->course], '*', MUST_EXIST);
    
    // Check if user has access to this assignment
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id, false, MUST_EXIST);
    require_course_login($course, false, $cm);
    
    // Get planning data from session
    if (!isset($_SESSION['timemanager_plan'])) {
        $_SESSION['timemanager_plan'] = [];
    }
    $plan = $_SESSION['timemanager_plan'][$assignment->id] ?? null;
    
    // Determine dates - prioritize form data, then session data
    $startdate = null;
    $finishdate = null;
    
    // Helper function to parse date string
    $parsedate = function(string $ymd): ?int {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return make_timestamp((int)$m[1], (int)$m[2], (int)$m[3], 0, 0, 0);
        }
        return null;
    };
    
    // First, try form data (if coming directly from form)
    if ($form_startdate) {
        $startdate = $parsedate($form_startdate);
    }
    if ($form_completiondate) {
        $finishdate = $parsedate($form_completiondate);
    }
    
    // Fallback to session data
    if (!$startdate || !$finishdate) {
        if ($plan) {
            $startdate = $startdate ?: ($plan['startdate'] ?? null);
            $finishdate = $finishdate ?: ($plan['completiondate'] ?? null);
        }
    }
    
    // Fallback to recommended start (28 days before due) if no user-set start date
    if (!$startdate && $assignment->duedate) {
        $startdate = $assignment->duedate - (28 * DAYSECS);
    }
    
    // Fallback to due date if no finish date set
    if (!$finishdate && $assignment->duedate) {
        $finishdate = $assignment->duedate;
    }
    
    // Create events if we have valid dates
    if ($startdate && $finishdate) {
        $events_created = 0;
        
        // Event 1: Start reminder (5-minute event at 10:30 AM on start date)
        $start_date_components = getdate($startdate);
        $start_event_start = make_timestamp($start_date_components['year'], $start_date_components['mon'], 
                                          $start_date_components['mday'], 10, 30, 0);
        $start_event_end = $start_event_start + (5 * 60); // 5 minutes later
        $uid_start = 'tm-start-' . $assignment->id . '-' . time() . '@' . $_SERVER['HTTP_HOST'];
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $uid_start . "\r\n";
        echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:" . gmdate('Ymd\THis\Z', $start_event_start) . "\r\n";
        echo "DTEND:" . gmdate('Ymd\THis\Z', $start_event_end) . "\r\n";
        echo "SUMMARY:Start: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "DESCRIPTION:Time to start working on this assignment\\n\\nCourse: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\\nDue: " . gmdate('Y-m-d H:i', $assignment->duedate) . "\r\n";
        echo "LOCATION:" . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\r\n";
        
        // Add alarm/reminder 15 minutes before the start event (10:15 AM)
        echo "BEGIN:VALARM\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:Time to start: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "TRIGGER:-PT15M\r\n";
        echo "END:VALARM\r\n";
        
        echo "END:VEVENT\r\n";
        $events_created++;
        
        // Event 1B: Evening reminder for start date (5-minute event at 5:00 PM)
        $start_evening_start = make_timestamp($start_date_components['year'], $start_date_components['mon'], 
                                            $start_date_components['mday'], 17, 0, 0);
        $start_evening_end = $start_evening_start + (5 * 60); // 5 minutes later
        $uid_start_evening = 'tm-start-evening-' . $assignment->id . '-' . time() . '@' . $_SERVER['HTTP_HOST'];
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $uid_start_evening . "\r\n";
        echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:" . gmdate('Ymd\THis\Z', $start_evening_start) . "\r\n";
        echo "DTEND:" . gmdate('Ymd\THis\Z', $start_evening_end) . "\r\n";
        echo "SUMMARY:Evening Check: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "DESCRIPTION:Evening reminder - did you start working on this assignment?\\n\\nCourse: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\\nDue: " . gmdate('Y-m-d H:i', $assignment->duedate) . "\r\n";
        echo "LOCATION:" . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\r\n";
        
        // Add alarm/reminder 15 minutes before (4:45 PM)
        echo "BEGIN:VALARM\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:Evening check: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "TRIGGER:-PT15M\r\n";
        echo "END:VALARM\r\n";
        
        echo "END:VEVENT\r\n";
        $events_created++;
        
        // Event 2: Finish reminder (5-minute event at 10:30 AM on finish date)
        $finish_date_components = getdate($finishdate);
        $finish_event_start = make_timestamp($finish_date_components['year'], $finish_date_components['mon'], 
                                           $finish_date_components['mday'], 10, 30, 0);
        $finish_event_end = $finish_event_start + (5 * 60); // 5 minutes later
        $uid_finish = 'tm-finish-' . $assignment->id . '-' . time() . '@' . $_SERVER['HTTP_HOST'];
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $uid_finish . "\r\n";
        echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:" . gmdate('Ymd\THis\Z', $finish_event_start) . "\r\n";
        echo "DTEND:" . gmdate('Ymd\THis\Z', $finish_event_end) . "\r\n";
        echo "SUMMARY:Finish: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "DESCRIPTION:Final reminder - assignment should be completed today\\n\\nCourse: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\\nDue: " . gmdate('Y-m-d H:i', $assignment->duedate) . "\r\n";
        echo "LOCATION:" . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\r\n";
        
        // Add alarm/reminder 15 minutes before (10:15 AM)
        echo "BEGIN:VALARM\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:Final reminder: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "TRIGGER:-PT15M\r\n";
        echo "END:VALARM\r\n";
        
        echo "END:VEVENT\r\n";
        $events_created++;
        
        // Event 2B: Evening reminder for finish date (5-minute event at 5:00 PM)
        $finish_evening_start = make_timestamp($finish_date_components['year'], $finish_date_components['mon'], 
                                             $finish_date_components['mday'], 17, 0, 0);
        $finish_evening_end = $finish_evening_start + (5 * 60); // 5 minutes later
        $uid_finish_evening = 'tm-finish-evening-' . $assignment->id . '-' . time() . '@' . $_SERVER['HTTP_HOST'];
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $uid_finish_evening . "\r\n";
        echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:" . gmdate('Ymd\THis\Z', $finish_evening_start) . "\r\n";
        echo "DTEND:" . gmdate('Ymd\THis\Z', $finish_evening_end) . "\r\n";
        echo "SUMMARY:Completion Check: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "DESCRIPTION:Evening check - is this assignment completed?\\n\\nCourse: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\\nDue: " . gmdate('Y-m-d H:i', $assignment->duedate) . "\r\n";
        echo "LOCATION:" . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\r\n";
        
        // Add alarm/reminder 15 minutes before (4:45 PM)
        echo "BEGIN:VALARM\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:Completion check: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
        echo "TRIGGER:-PT15M\r\n";
        echo "END:VALARM\r\n";
        
        echo "END:VEVENT\r\n";
        $events_created++;
        
        // Optional: Add a mid-point reminder if the time span is more than 7 days
        $time_span = $finishdate - $startdate;
        if ($time_span > (7 * DAYSECS)) {
            $midpoint = $startdate + ($time_span / 2);
            $mid_event_start = $midpoint;
            $mid_event_end = $midpoint + (5 * 60); // 5 minutes
            $uid_mid = 'tm-mid-' . $assignment->id . '-' . time() . '@' . $_SERVER['HTTP_HOST'];
            
            echo "BEGIN:VEVENT\r\n";
            echo "UID:" . $uid_mid . "\r\n";
            echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            echo "DTSTART:" . gmdate('Ymd\THis\Z', $mid_event_start) . "\r\n";
            echo "DTEND:" . gmdate('Ymd\THis\Z', $mid_event_end) . "\r\n";
            echo "SUMMARY:Progress Check: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
            echo "DESCRIPTION:Mid-point reminder - check your progress\\n\\nCourse: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\\nDue: " . gmdate('Y-m-d H:i', $assignment->duedate) . "\r\n";
            echo "LOCATION:" . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $course->fullname) . "\r\n";
            
            echo "BEGIN:VALARM\r\n";
            echo "ACTION:DISPLAY\r\n";
            echo "DESCRIPTION:Progress check: " . str_replace(["\r", "\n", ",", ";"], ["", "", "\\,", "\\;"], $assignment->name) . "\r\n";
            echo "TRIGGER:-PT15M\r\n";
            echo "END:VALARM\r\n";
            
            echo "END:VEVENT\r\n";
            $events_created++;
        }
    }
} else {
    // No specific assignment ID provided - this could be extended for bulk export later
    // For now, just create an empty calendar
}

echo "END:VCALENDAR\r\n";
exit;
?>