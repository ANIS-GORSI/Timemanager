<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo '<html><body>';
echo '<h2>Simple Email Test</h2>';

$user = $USER;
$from = core_user::get_noreply_user();
$subject = 'Simple Test from Timemanager';
$message = 'This is a simple test email.';

echo '<p><strong>Sending to:</strong> ' . $USER->email . '</p>';
echo '<p><strong>From:</strong> ' . $from->email . '</p>';
echo '<hr>';

try {
    $result = email_to_user($user, $from, $subject, $message, $message);
    
    if ($result) {
        echo '<p style="color: green; font-size: 20px;"><strong>✅ SUCCESS!</strong> Email was sent.</p>';
        echo '<p>Check your inbox at: ' . $USER->email . '</p>';
    } else {
        echo '<p style="color: red; font-size: 20px;"><strong>❌ FAILED!</strong> Email was not sent.</p>';
        echo '<p>Check the SMTP debug output above for errors.</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;"><strong>ERROR:</strong> ' . $e->getMessage() . '</p>';
}

echo '</body></html>';