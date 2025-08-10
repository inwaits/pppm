<?php
// Create this as debug_email.php in your project root to test

// Include your database connection - UPDATE THIS PATH
// Try one of these (uncomment the correct one):
// require_once 'includes/config.php';
require_once 'includes/database.php';  
// require_once 'config/database.php';
// require_once 'includes/connection.php';

require_once 'config/email.php';

echo "<h2>Email Configuration Debug</h2>";

// Check current directory
echo "<h3>1. Current Directory Information:</h3>";
echo "Current script directory: " . __DIR__ . "<br>";
echo "Current working directory: " . getcwd() . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// Check PHPMailer paths
echo "<h3>2. PHPMailer Path Check:</h3>";
$possible_paths = [
    __DIR__ . '/libs/phpmailer/src/',
    dirname(__DIR__) . '/libs/phpmailer/src/',
    $_SERVER['DOCUMENT_ROOT'] . '/libs/phpmailer/src/',
    '/home/pphotelc/projects.pphotel.co.uk/libs/phpmailer/src/'
];

foreach ($possible_paths as $path) {
    echo "Checking: " . $path . " - ";
    if (file_exists($path)) {
        echo "Directory EXISTS<br>";
        $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
        foreach ($files as $file) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;" . $file . ": " . (file_exists($path . $file) ? "EXISTS" : "MISSING") . "<br>";
        }
    } else {
        echo "Directory NOT FOUND<br>";
    }
}

// Check if PHPMailer can be loaded
echo "<h3>3. PHPMailer Loading Test:</h3>";
$phpmailer_ready = isPHPMailerReady();
echo "PHPMailer Ready: " . ($phpmailer_ready ? "YES" : "NO") . "<br>";

// Check email settings from database
echo "<h3>4. Database Email Settings:</h3>";
$emailSettings = getEmailSettings($pdo);
if ($emailSettings) {
    echo "Settings found:<br>";
    echo "- Enabled: " . ($emailSettings['is_enabled'] ? 'YES' : 'NO') . "<br>";
    echo "- SMTP Host: " . htmlspecialchars($emailSettings['smtp_host'] ?? 'empty') . "<br>";
    echo "- SMTP Port: " . htmlspecialchars($emailSettings['smtp_port'] ?? 'empty') . "<br>";
    echo "- SMTP Username: " . htmlspecialchars($emailSettings['smtp_username'] ?? 'empty') . "<br>";
    echo "- SMTP Password: " . (!empty($emailSettings['smtp_password']) ? 'SET' : 'EMPTY') . "<br>";
    echo "- SMTP Encryption: " . htmlspecialchars($emailSettings['smtp_encryption'] ?? 'empty') . "<br>";
    echo "- From Email: " . htmlspecialchars($emailSettings['from_email'] ?? 'empty') . "<br>";
    echo "- From Name: " . htmlspecialchars($emailSettings['from_name'] ?? 'empty') . "<br>";
} else {
    echo "No email settings found in database or disabled<br>";
}

// Test SMTP configuration check
echo "<h3>5. SMTP Configuration Test:</h3>";
if ($emailSettings) {
    $hasSMTPConfig = !empty($emailSettings['smtp_host']) && 
                     !empty($emailSettings['smtp_username']) && 
                     !empty($emailSettings['smtp_password']);
    
    echo "Has SMTP Config: " . ($hasSMTPConfig ? 'YES' : 'NO') . "<br>";
    echo "Can Use SMTP: " . (($hasSMTPConfig && $phpmailer_ready) ? 'YES' : 'NO') . "<br>";
}

// Check server configuration
echo "<h3>6. Server Configuration:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Mail function available: " . (function_exists('mail') ? 'YES' : 'NO') . "<br>";
echo "Error reporting level: " . error_reporting() . "<br>";

// Try to test email sending logic
echo "<h3>7. Email Sending Test (without actually sending):</h3>";
if ($emailSettings) {
    $test_to = "test@example.com";
    $test_subject = "Test Subject";
    $test_body = "Test Body";
    
    echo "Would send email to: " . $test_to . "<br>";
    echo "Subject: " . $test_subject . "<br>";
    
    // Simulate the decision logic
    $hasSMTPConfig = !empty($emailSettings['smtp_host']) && 
                     !empty($emailSettings['smtp_username']) && 
                     !empty($emailSettings['smtp_password']);
    
    $canUseSMTP = $hasSMTPConfig && isPHPMailerReady();
    
    if ($canUseSMTP) {
        echo "Decision: Would use SMTP<br>";
    } else {
        echo "Decision: Would use hosting mail() function<br>";
        if ($hasSMTPConfig && !isPHPMailerReady()) {
            echo "Reason: SMTP configured but PHPMailer not available<br>";
        } elseif (!$hasSMTPConfig) {
            echo "Reason: SMTP not configured<br>";
        }
    }
}

echo "<h3>8. Error Log Check:</h3>";
echo "Check your server error logs for detailed debugging information.<br>";
echo "Common log locations:<br>";
echo "- /home/pphotelc/public_html/error_log<br>";
echo "- /home/pphotelc/logs/<br>";
echo "- /var/log/apache2/error.log<br>";

?>