<?php

// local/timemanager/assignment.php - Enhanced University Moodle Style Version
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php'); // Include our database functions
require_login();

// Params & context.
$cmid = required_param('cmid', PARAM_INT);
$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/timemanager/assignment.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

// Load enhanced university-themed styles
$PAGE->requires->css(new moodle_url('/local/timemanager/styles.css'));

// Resolve cm -> assign and require course access.
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, false, $cm);

$PAGE->set_title(format_string($assign->name));
$PAGE->set_heading(format_string($course->fullname));

// Load user's existing plan from database
$plan = local_timemanager_get_plan($USER->id, $assign->id);

// Recommended start (28 days before due).
$recommendedstart = $assign->duedate ? ($assign->duedate - (28 * DAYSECS)) : null;

// Helpers.
$datestring = function($ts) {
    if (empty($ts)) { return ''; }
    $ud = usergetdate($ts);
    return $ud['year'].'-'.sprintf('%02d', $ud['mon']).'-'.sprintf('%02d', $ud['mday']);
};
$parsedate = function(string $ymd): ?int {
    if (preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $ymd, $m)) {
        return make_timestamp((int)$m[1], (int)$m[2], (int)$m[3], 0, 0, 0);
    }
    return null;
};

// Handle form post (save to database instead of session).
if (data_submitted() && confirm_sesskey() && has_capability('local/timemanager:manageown', $context)) {
    $startstr = optional_param('startdate', '', PARAM_RAW_TRIMMED);
    $compstr  = optional_param('completiondate', '', PARAM_RAW_TRIMMED);
    $rbs      = optional_param('remind_before_start', 0, PARAM_INT);
    $rbd      = optional_param('remind_before_due', 0, PARAM_INT);

    $startts = $startstr ? $parsedate($startstr) : null;
    $compts  = $compstr ? $parsedate($compstr) : null;

 
    // DEBUG: Show all form data
    //echo "<div style='background: #f0f8ff; border: 2px solid #0066cc; padding: 15px; margin: 10px;'>";
    //echo "<h3>DEBUG: Form Processing</h3>";
    //echo "<strong>All POST data:</strong><br>";
    //print_r($_POST);
    //echo "<hr>";
    
    //$startstr = optional_param('startdate', '', PARAM_RAW_TRIMMED);
    //$compstr  = optional_param('completiondate', '', PARAM_RAW_TRIMMED);
    //$rbs      = optional_param('remind_before_start', 0, PARAM_INT);
    //$rbd      = optional_param('remind_before_due', 0, PARAM_INT);
    
    //echo "<strong>Processed form data:</strong><br>";
    //echo "Start date string: '$startstr'<br>";
    //echo "Completion date string: '$compstr'<br>";
    //echo "Remind before start: $rbs<br>";
    //echo "Remind before due: $rbd<br>";
    //echo "<hr>";
    
    //$startts = $startstr ? $parsedate($startstr) : null;
    //$compts  = $compstr ? $parsedate($compstr) : null;
    
    //echo "<strong>Date conversion:</strong><br>";
    //echo "Start timestamp: " . ($startts ?: 'NULL') . "<br>";
    //echo "Completion timestamp: " . ($compts ?: 'NULL') . "<br>";
    
    //if ($startts) {
    //    echo "Start date readable: " . date('Y-m-d H:i:s', $startts) . "<br>";
    //}
    //if ($compts) {
    //    echo "Completion date readable: " . date('Y-m-d H:i:s', $compts) . "<br>";
   // }
    
    //echo "<strong>Context info:</strong><br>";
    //echo "User ID: {$USER->id}<br>";
    //echo "Assignment ID: {$assign->id}<br>";
    //echo "Course ID: {$course->id}<br>";
    //echo "CM ID: {$cmid}<br>";
    
    //echo "</div>";
    
     // Save to database and schedule notifications  
local_timemanager_save_plan($USER->id, $assign->id, $course->id, $startts, $compts, $rbs, $rbd);

redirect(new moodle_url('/local/timemanager/assignment.php', ['cmid' => $cmid]), 
    get_string('changessaved') . ' Email notifications have been scheduled.'); 
}



echo $OUTPUT->header();

// Enhanced university-style page header with proper spacing
echo html_writer::start_div('assignment-header');
echo html_writer::div(
    html_writer::link(new moodle_url('/local/timemanager/index.php'), '← Back to Time Manager', 
        ['class' => 'btn btn-secondary', 'style' => 'margin-bottom: 16px;']),
    'navigation-link'
);
echo html_writer::tag('h1', format_string($assign->name), ['class' => 'assignment-title']);
echo html_writer::tag('p', format_string($course->fullname), ['class' => 'course-subtitle']);
echo html_writer::end_div();

// Enhanced assignment description section with better styling
if (!empty($assign->intro)) {
    echo html_writer::start_div('box assignment-description');
    echo html_writer::tag('h3', 'Assignment Description');
    $intro = format_module_intro('assign', $assign, $cm->id);
    echo html_writer::div($intro, 'description-content');
    echo html_writer::end_div();
}

// Professional dates information cards with enhanced styling
echo html_writer::start_div('dates-info');

// Due date card - enhanced professional styling
echo html_writer::start_div('date-card due-date');
echo html_writer::tag('h4', 'Assignment Due Date');
if ($assign->duedate) {
    echo html_writer::tag('p', userdate($assign->duedate, '%A, %d %B %Y at %I:%M %p'));
    $daysleft = (int)floor(($assign->duedate - time()) / DAYSECS);
    if ($daysleft >= 0) {
        $daysleft_text = $daysleft === 1 ? "1 day remaining" : "$daysleft days remaining";
        $urgency_class = $daysleft <= 3 ? 'urgent-text' : ($daysleft <= 7 ? 'warning-text' : '');
    } else {
        $daysleft_text = abs($daysleft) === 1 ? "1 day overdue" : abs($daysleft) . " days overdue";
        $urgency_class = 'overdue-text';
    }
    echo html_writer::tag('small', $daysleft_text, ['class' => $urgency_class]);
} else {
    echo html_writer::tag('p', 'Not set');
    echo html_writer::tag('small', 'No due date specified');
}
echo html_writer::end_div();

// Recommended start card - enhanced professional styling
echo html_writer::start_div('date-card recommended');
echo html_writer::tag('h4', 'Recommended Start Date');
if ($recommendedstart) {
    echo html_writer::tag('p', userdate($recommendedstart, '%A, %d %B %Y'));
    $days_until_recommended = (int)floor(($recommendedstart - time()) / DAYSECS);
    if ($days_until_recommended > 0) {
        $recommended_text = "Start in $days_until_recommended days for optimal pacing";
    } else if ($days_until_recommended === 0) {
        $recommended_text = "Recommended to start today!";
    } else {
        $recommended_text = "Consider starting soon for best results";
    }
    echo html_writer::tag('small', $recommended_text);
} else {
    echo html_writer::tag('p', 'Not available');
    echo html_writer::tag('small', 'Due date required for recommendation');
}
echo html_writer::end_div();

echo html_writer::end_div(); // End dates-info grid

// Form defaults from database (fallbacks).
$startval = $plan->startdate ?? ($recommendedstart ?: null);
$compval  = $plan->completiondate ?? null;
$rbsval   = $plan->remind_before_start ?? 0;
$rbdval   = $plan->remind_before_due ?? 0;

// Show current notification status if plan exists
if ($plan) {
    echo html_writer::start_div('notification-status');
    echo html_writer::tag('h4', 'Email Notification Status');
    
    // Check for pending notifications
    $pending_notifications = $DB->get_records('local_timemanager_notifications', [
        'planid' => $plan->id,
        'sent' => 0
    ]);
    
    if (!empty($pending_notifications)) {
        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('strong', 'Active Notifications: ');
        foreach ($pending_notifications as $notif) {
            $type_text = $notif->notification_type === 'start_reminder' ? 'Start reminder' : 'Due reminder';
            echo html_writer::tag('div', $type_text . ' scheduled for ' . userdate($notif->scheduled_time));
        }
        echo html_writer::end_div();
    } else {
        echo html_writer::div('No notifications currently scheduled', 'alert alert-secondary');
    }
    echo html_writer::end_div();
}

// Enhanced reminder options with better labels
$reminder_options = [
    0 => 'None',
    1 => '1 day before',
    2 => '2 days before', 
    3 => '3 days before',
    5 => '5 days before',
    7 => '1 week before',
    14 => '2 weeks before'
];

$url = new moodle_url('/local/timemanager/assignment.php', ['cmid' => $cmid]);

// Enhanced professional planning form
echo html_writer::start_div('planning-section');
echo html_writer::tag('h3', 'Plan Your Schedule');

echo html_writer::start_tag('form', [
    'method' => 'post', 
    'action' => $url, 
    'id' => 'planningform', 
    'class' => 'enhanced-form',
    'novalidate' => 'novalidate'
]);

// Date inputs section with improved layout
echo html_writer::start_div('form-section');
echo html_writer::start_div('form-grid');

echo html_writer::start_div('input-group');
echo html_writer::tag('label', 'Start Date ', [
    'for' => 'startdate',
    'class' => 'form-label'
]);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'startdate',
    'id' => 'startdate',
    'value' => $datestring($startval),
    'class' => 'form-control',
    'aria-describedby' => 'startdate-help'
]);
echo html_writer::tag('small', 'Choose when you want to start working on this assignment', [
    'id' => 'startdate-help',
    'class' => 'form-text'
]);
echo html_writer::end_div();

echo html_writer::start_div('input-group');
echo html_writer::tag('label', 'Target Completion Date ', [
    'for' => 'completiondate',
    'class' => 'form-label'
]);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'completiondate',
    'id' => 'completiondate', 
    'value' => $datestring($compval),
    'class' => 'form-control',
    'aria-describedby' => 'completion-help'
]);
echo html_writer::tag('small', 'Set your personal completion target date', [
    'id' => 'completion-help',
    'class' => 'form-text'
]);
echo html_writer::end_div();

echo html_writer::end_div(); // End form grid
echo html_writer::end_div(); // End form section

// Enhanced email reminder options in organized section
echo html_writer::start_div('reminder-section');
echo html_writer::tag('h4', 'Email Reminders');
echo html_writer::tag('p', 'Get email notifications to stay on track with your assignment schedule', ['class' => 'reminder-description']);

echo html_writer::start_div('reminder-grid');

echo html_writer::start_div('input-group');
echo html_writer::tag('label', 'Email Reminder Before Start ', [
    'for' => 'remind_before_start',
    'class' => 'form-label'
]);
echo html_writer::select($reminder_options, 'remind_before_start', $rbsval, false, [
    'id' => 'remind_before_start',
    'class' => 'form-control',
    'aria-describedby' => 'remind-start-help'
]);
echo html_writer::tag('small', 'Get notified before your planned start date', [
    'id' => 'remind-start-help',
    'class' => 'form-text'
]);
echo html_writer::end_div();

echo html_writer::start_div('input-group');
echo html_writer::tag('label', 'Email Reminder Before Due Date ', [
    'for' => 'remind_before_due',
    'class' => 'form-label'
]);
echo html_writer::select($reminder_options, 'remind_before_due', $rbdval, false, [
    'id' => 'remind_before_due',
    'class' => 'form-control',
    'aria-describedby' => 'remind-due-help'
]);
echo html_writer::tag('small', 'Get notified before the assignment is due', [
    'id' => 'remind-due-help',
    'class' => 'form-text'
]);
echo html_writer::end_div();

echo html_writer::end_div(); // End reminder grid
echo html_writer::end_div(); // End reminder section

// Enhanced form submission with validation feedback
echo html_writer::start_div('form-actions');
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'submit', 
    'class' => 'btn btn-primary', 
    'value' => 'Save My Plan',
    'id' => 'save-plan-btn'
]);
// Add a reset option
echo html_writer::empty_tag('input', [
    'type' => 'button',
    'class' => 'btn btn-secondary',
    'value' => 'Reset Form',
    'id' => 'reset-form-btn'
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div(); // End planning section

// Enhanced export section with better information
$exporturl_dynamic = new moodle_url('/local/timemanager/export_calendar.php', [
    'assignid' => $assign->id,
    'sesskey' => sesskey()
]);

echo html_writer::start_div('export-section');
echo html_writer::tag('h3', 'Export to Calendar');
echo html_writer::tag('p', 'Download your personalized schedule with smart reminders for your calendar application');

echo html_writer::link($exporturl_dynamic, 'Download ICS File', [
    'class' => 'btn btn-primary',
    'id' => 'export-ics-btn',
    'title' => 'Export calendar with your current settings'
]);

echo html_writer::start_div('export-features');
echo html_writer::tag('ul', 
    html_writer::tag('li', 'Compatible with Google Calendar, Outlook, and Apple Calendar') .
    html_writer::tag('li', 'Includes smart reminders at optimal times') .
    html_writer::tag('li', 'Updates automatically with your chosen dates') .
    html_writer::tag('li', 'Syncs across all your devices'),
    ['style' => 'margin: 0; padding-left: 20px; list-style: none;']
);

echo html_writer::tag('h4', 'How to Import:', ['style' => 'margin-top: 20px; margin-bottom: 10px;']);
echo html_writer::tag('ol', 
    html_writer::tag('li', 'Download the ICS file to your device') .
    html_writer::tag('li', 'Open your calendar app (Outlook/Apple Calendar/Google Calendar)') .
    html_writer::tag('li', 'Look for "Import" or "Add Calendar" option in the menu') .
    html_writer::tag('li', 'Select the downloaded ICS file') .
    html_writer::tag('li', 'Your events will appear in your calendar automatically'),
    ['style' => 'margin: 0; padding-left: 20px; list-style: none;']
);
echo html_writer::end_div();

echo html_writer::end_div(); // End export section

// Enhanced JavaScript with better UX and validation
$js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    var exportBtn = document.getElementById('export-ics-btn');
    var startDateField = document.getElementById('startdate');
    var completionDateField = document.getElementById('completiondate');
    var resetBtn = document.getElementById('reset-form-btn');
    var saveBtn = document.getElementById('save-plan-btn');
    var baseUrl = '{$exporturl_dynamic->out(false)}';
    
    // Initialize date constraints
    var today = new Date().toISOString().split('T')[0];
    startDateField.setAttribute('min', today);
    completionDateField.setAttribute('min', today);
    
    // Set max date to due date if available
    var dueDate = {$assign->duedate} ? new Date({$assign->duedate} * 1000) : null;
    if (dueDate) {
        var dueDateStr = dueDate.toISOString().split('T')[0];
        completionDateField.setAttribute('max', dueDateStr);
    }
    
    function updateExportLink() {
        var url = new URL(baseUrl);
        
        if (startDateField.value) {
            url.searchParams.set('startdate', startDateField.value);
        }
        if (completionDateField.value) {
            url.searchParams.set('completiondate', completionDateField.value);
        }
        
        exportBtn.href = url.toString();
        
        // Update button text based on whether dates are set
        var hasCustomDates = startDateField.value || completionDateField.value;
        exportBtn.textContent = hasCustomDates ? 'Download Personalized ICS File' : 'Download ICS File';
    }
    
    function showFieldError(field, message) {
        field.style.borderColor = '#dc3545';
        var existingError = field.parentNode.querySelector('.field-error');
        if (existingError) existingError.remove();
        
        var errorDiv = document.createElement('small');
        errorDiv.className = 'field-error';
        errorDiv.style.color = '#dc3545';
        errorDiv.style.display = 'block';
        errorDiv.style.marginTop = '4px';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }
    
    function clearFieldError(field) {
        field.style.borderColor = '';
        var existingError = field.parentNode.querySelector('.field-error');
        if (existingError) existingError.remove();
    }
    
    function validateDates() {
        var isValid = true;
        clearFieldError(startDateField);
        clearFieldError(completionDateField);
        
        var startDate = startDateField.value ? new Date(startDateField.value) : null;
        var endDate = completionDateField.value ? new Date(completionDateField.value) : null;
        var now = new Date();
        now.setHours(0, 0, 0, 0);
        
        // Check if start date is in the past
        if (startDate && startDate < now) {
            showFieldError(startDateField, 'Start date cannot be in the past');
            isValid = false;
        }
        
        // Check if completion date is before start date
        if (startDate && endDate && startDate >= endDate) {
            showFieldError(completionDateField, 'Completion date must be after start date');
            isValid = false;
        }
        
        // Check if completion date is after due date
        if (endDate && dueDate && endDate > dueDate) {
            showFieldError(completionDateField, 'Completion date should not be after the due date');
            isValid = false;
        }
        
        return isValid;
    }
    
    function handleDateChange() {
        validateDates();
        updateExportLink();
        
        // Visual feedback
        exportBtn.style.opacity = '0.8';
        setTimeout(function() {
            exportBtn.style.opacity = '1';
        }, 150);
    }
    
    // Event listeners
    startDateField.addEventListener('change', handleDateChange);
    completionDateField.addEventListener('change', handleDateChange);
    
    // Reset form functionality
    resetBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to reset all fields? This will clear your current planning data.')) {
            startDateField.value = '';
            completionDateField.value = '';
            document.getElementById('remind_before_start').selectedIndex = 0;
            document.getElementById('remind_before_due').selectedIndex = 0;
            clearFieldError(startDateField);
            clearFieldError(completionDateField);
            updateExportLink();
        }
    });
    
    // Enhanced form validation
    document.getElementById('planningform').addEventListener('submit', function(e) {
        if (!validateDates()) {
            e.preventDefault();
            saveBtn.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(function() {
                saveBtn.style.animation = '';
            }, 500);
            return false;
        }
        
        // Show loading state
        saveBtn.value = 'Saving...';
        saveBtn.disabled = true;
        
        // Additional validation: warn if timeline is very tight
        var startDate = startDateField.value ? new Date(startDateField.value) : null;
        if (startDate && dueDate) {
            var timeDiff = dueDate.getTime() - startDate.getTime();
            var daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
            
            if (daysDiff < 7 && daysDiff > 0) {
                e.preventDefault();
                saveBtn.value = 'Save My Plan';
                saveBtn.disabled = false;
                
                var proceed = confirm(
                    'You are planning to start this assignment with less than a week before the due date. ' +
                    'This may not provide enough time for quality work. Do you want to continue?'
                );
                if (proceed) {
                    this.submit();
                }
                return false;
            }
        }
    });
    
    // Auto-suggest completion date when start date is selected
    startDateField.addEventListener('change', function() {
        if (!completionDateField.value && this.value && dueDate) {
            var startDate = new Date(this.value);
            
            // Suggest completion 2-3 days before due date, or halfway if that's earlier
            var timeDiff = dueDate.getTime() - startDate.getTime();
            var availableDays = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
            
            var bufferDays = Math.min(3, Math.floor(availableDays * 0.1)); // 10% buffer or 3 days max
            var suggestedCompletion = new Date(dueDate.getTime() - (bufferDays * 24 * 60 * 60 * 1000));
            
            // But not before start date + 1 day
            var minCompletion = new Date(startDate.getTime() + (24 * 60 * 60 * 1000));
            if (suggestedCompletion < minCompletion) {
                suggestedCompletion = minCompletion;
            }
            
            if (suggestedCompletion <= dueDate) {
                var year = suggestedCompletion.getFullYear();
                var month = String(suggestedCompletion.getMonth() + 1).padStart(2, '0');
                var day = String(suggestedCompletion.getDate()).padStart(2, '0');
                completionDateField.value = year + '-' + month + '-' + day;
                
                // Show helpful message
                var helpText = completionDateField.parentNode.querySelector('.form-text');
                var originalText = helpText.textContent;
                helpText.textContent = 'Auto-suggested based on your start date';
                helpText.style.color = '#28a745';
                setTimeout(function() {
                    helpText.textContent = originalText;
                    helpText.style.color = '';
                }, 3000);
                
                updateExportLink();
            }
        }
    });
    
    // Initialize
    updateExportLink();
    validateDates();
});

// Add CSS for shake animation
var style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    .field-error {
        font-weight: 500;
        font-size: 0.8125rem;
    }
    .export-features ul {
        list-style: none;
        padding-left: 0;
    }
    .export-features li::before {
        content: "✓";
        color: #28a745;
        font-weight: bold;
        margin-right: 8px;
    }
    .urgent-text { color: #dc3545 !important; font-weight: 600; }
    .warning-text { color: #fd7e14 !important; font-weight: 600; }
    .overdue-text { color: #dc3545 !important; font-weight: 700; }
    .notification-status {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .reminder-description {
        color: #6c757d;
        margin-bottom: 15px;
    }
`;
document.head.appendChild(style);
JS;

echo html_writer::script($js);

echo $OUTPUT->footer();
?>