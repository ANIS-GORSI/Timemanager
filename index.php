<?php
// local/timemanager/index.php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
// If you want to enforce capability, uncomment the next line after granting caps.
// require_capability('local/timemanager:view', $context);

$PAGE->set_url(new moodle_url('/local/timemanager/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_timemanager'));
$PAGE->set_heading(get_string('pluginname', 'local_timemanager'));

// Load plugin stylesheet BEFORE header (Moodle auto-loads styles.css but this is explicit & safe).
$PAGE->requires->css(new moodle_url('/local/timemanager/styles.css'));

echo $OUTPUT->header();
echo html_writer::tag('h3', get_string('yourcoursesandassignments', 'local_timemanager'));

global $USER, $DB;

// --- Course filter (from old version) ---
$courses = enrol_get_users_courses($USER->id, true, 'id, fullname, shortname');
$selectedcourseid = optional_param('courseid', 0, PARAM_INT);

// Filter UI
$filterform = html_writer::start_tag('form', ['method' => 'get', 'id' => 'coursefilter', 'class' => 'local-tm-filter']);
$filterform .= html_writer::tag('label', get_string('course') . ':', ['for' => 'courseid']);
$options = [0 => get_string('allcourses', 'moodle')];
foreach ($courses as $c) {
    $options[$c->id] = format_string($c->fullname);
}
$filterform .= html_writer::select($options, 'courseid', $selectedcourseid, null, ['id' => 'courseid', 'onchange' => 'document.getElementById("coursefilter").submit()']);
$filterform .= html_writer::end_tag('form');
echo $filterform;

// --- Collect assignments (future-due only), grouped by course like before ---
$now = time();
$assignments_by_course = [];

foreach ($courses as $course) {
    if ($selectedcourseid && $selectedcourseid != $course->id) {
        continue;
    }

    // Get assignments in this course (future only).
    $assignments = $DB->get_records_select('assign',
        'course = :courseid AND duedate IS NOT NULL AND duedate > :now',
        ['courseid' => $course->id, 'now' => $now],
        'duedate ASC');

    if (!$assignments) {
        continue;
    }

    foreach ($assignments as $assignment) {
        // Attach a cmid for the Plan link.
        $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id, false, IGNORE_MISSING);
        if (!$cm) {
            // Fallback query if needed.
            $cm = $DB->get_record_sql("
                SELECT cm.*
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.instance = :instance AND cm.course = :course AND m.name = 'assign'
                 LIMIT 1
            ", ['instance' => $assignment->id, 'course' => $course->id]);
        }
        $assignment->cmid = $cm ? $cm->id : 0;

        $assignments_by_course[$course->fullname][] = $assignment;
    }
}

// Nothing to show?
if (empty($assignments_by_course)) {
    echo $OUTPUT->notification(get_string('noassignments', 'local_timemanager'), 'notifymessage');
    echo $OUTPUT->footer();
    exit;
}

// --- Render collapsible groups with coloured progress bars & alerts ---
foreach ($assignments_by_course as $coursename => $assignments) {
    // Collapsible header per course.
    echo html_writer::tag('button', format_string($coursename), ['class' => 'local-tm-collapsible']);

    echo html_writer::start_div('local-tm-content');
    foreach ($assignments as $a) {
        // Days left
        $daysleft = (int)floor(($a->duedate - $now) / DAYSECS);

        // 28-day window progress toward due date.
        // 0% at (due-28d), 100% at due (or beyond).
        $window = 28 * DAYSECS;
        $tilldue = max(0, $a->duedate - $now);
        $elapsed = min($window, $window - max(0, $a->duedate - $now));
        // More intuitive: proportion of time USED in the last 28 days.
        $pct = (int)round(($elapsed / $window) * 100);

        // Colour & alert message
        $fillclass = 'local-tm-fill-green';
        $alert = '';
        if ($daysleft <= 7) {
            $fillclass = 'local-tm-fill-red';
            $alert = html_writer::tag('span', get_string('soon', 'moodle'), ['class' => 'local-tm-alert local-tm-alert-red']); // "Soon" pill
            $alert = html_writer::tag('span', 'DUE SOON!', ['class' => 'local-tm-alert local-tm-alert-red']);
        } else if ($daysleft <= 14) {
            $fillclass = 'local-tm-fill-orange';
            $alert = html_writer::tag('span', 'Coming up', ['class' => 'local-tm-alert local-tm-alert-amber']);
        }

        // Card
        $card = html_writer::start_div('local-tm-card');

        // (When viewing "All Courses", keep a subtle course label on card)
        if (!$selectedcourseid) {
            $card .= html_writer::tag('div', format_string($coursename), ['class' => 'local-tm-course']);
        }

        $card .= html_writer::tag('div', format_string($a->name), ['class' => 'local-tm-title']);
        $card .= html_writer::tag('div', userdate($a->duedate), ['class' => 'local-tm-due']);

        $barfill = html_writer::div('', 'local-tm-bar-fill ' . $fillclass, ['style' => 'width:' . $pct . '%']);
        $card .= html_writer::div($barfill, 'local-tm-bar');

        $leftlabel = $daysleft >= 0
            ? get_string('daysleft', 'local_timemanager', $daysleft)
            : get_string('overdue', 'local_timemanager', abs($daysleft));
        $card .= html_writer::tag('div', s($leftlabel) . ' ' . $alert, ['class' => 'local-tm-left']);

        // Plan button -> assignment detail page (with description, date pickers, reminders, ICS export)
        $planurl = new moodle_url('/local/timemanager/assignment.php', ['cmid' => $a->cmid]);
        $card .= html_writer::link($planurl, get_string('plan', 'local_timemanager'), ['class' => 'btn btn-primary']);

        $card .= html_writer::end_div(); // card
        echo $card;
    }
    echo html_writer::end_div(); // content
}

// Tiny JS for collapsibles (safe inline after header)
$js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
  var coll = document.getElementsByClassName('local-tm-collapsible');
  for (var i = 0; i < coll.length; i++) {
    coll[i].addEventListener('click', function() {
      this.classList.toggle('active');
      var content = this.nextElementSibling;
      if (content.style.display === 'block') {
        content.style.display = 'none';
      } else {
        content.style.display = 'block';
      }
    });
  }
  // Auto-open the first course section for convenience.
  if (coll.length > 0) {
    coll[0].click();
  }
});
JS;
echo html_writer::script($js);

echo $OUTPUT->footer();
