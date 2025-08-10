<?php

function getEmailSettings($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_settings WHERE is_enabled = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error getting email settings: " . $e->getMessage());
        return false;
    }
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isHTMLContent($content) {
    return preg_match('/<[^<]+>/', $content) !== 0;
}

// Global variable to store PHPMailer path once found
$GLOBALS['phpmailer_path'] = null;

// Check if PHPMailer is available and working
function isPHPMailerReady() {
    // Return cached result if already found
    if ($GLOBALS['phpmailer_path'] !== null) {
        return true;
    }
    
    // Get the project root directory (go up from config/ to project root)
    $project_root = dirname(__DIR__);
    
    // Define possible paths with absolute priority
    $possible_paths = [
        // Most likely location based on your structure
        '/home/pphotelc/projects.pphotel.co.uk/libs/phpmailer/src/',
        // Relative paths from config directory
        $project_root . '/libs/phpmailer/src/',
        __DIR__ . '/../libs/phpmailer/src/',
        // Other possible locations
        dirname(__DIR__) . '/libs/phpmailer/src/',
        $_SERVER['DOCUMENT_ROOT'] . '/libs/phpmailer/src/',
        // Composer locations (just in case)
        $project_root . '/vendor/phpmailer/phpmailer/src/',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
    ];
    
    error_log("=== PHPMailer Path Detection Debug ===");
    error_log("Project root detected as: " . $project_root);
    error_log("Current __DIR__: " . __DIR__);
    
    foreach ($possible_paths as $path) {
        error_log("Checking PHPMailer path: " . $path);
        
        // Normalize path
        $path = rtrim($path, '/') . '/';
        
        if (is_dir($path)) {
            error_log("Directory exists: " . $path);
            
            // Check for required files
            $required_files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
            $all_files_exist = true;
            
            foreach ($required_files as $file) {
                if (!file_exists($path . $file)) {
                    error_log("Missing file: " . $path . $file);
                    $all_files_exist = false;
                    break;
                }
            }
            
            if ($all_files_exist) {
                error_log("All PHPMailer files found at: " . $path);
                
                // Try to include the files safely
                try {
                    if (!class_exists('PHPMailer\PHPMailer\Exception')) {
                        require_once $path . 'Exception.php';
                        error_log("Loaded Exception.php");
                    }
                    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                        require_once $path . 'PHPMailer.php';
                        error_log("Loaded PHPMailer.php");
                    }
                    if (!class_exists('PHPMailer\PHPMailer\SMTP')) {
                        require_once $path . 'SMTP.php';
                        error_log("Loaded SMTP.php");
                    }
                    
                    // Cache the successful path
                    $GLOBALS['phpmailer_path'] = $path;
                    error_log("PHPMailer loaded successfully from: " . $path);
                    return true;
                    
                } catch (Exception $e) {
                    error_log("Failed to load PHPMailer from: " . $path . " - " . $e->getMessage());
                    continue;
                }
            } else {
                error_log("Some PHPMailer files missing in: " . $path);
            }
        } else {
            error_log("Directory does not exist: " . $path);
        }
    }
    
    error_log("PHPMailer not found in any of the checked paths");
    return false;
}

function sendEmailViaSMTP($to, $subject, $body, $toName, $emailSettings) {
    error_log("=== SMTP Email Attempt ===");
    error_log("To: " . $to);
    error_log("Subject: " . $subject);
    
    // Only try SMTP if PHPMailer is ready
    if (!isPHPMailerReady()) {
        error_log("PHPMailer not available for SMTP - ABORTING SMTP");
        return false;
    }
    
    error_log("PHPMailer is ready, proceeding with SMTP");
    
    try {
        // Use PHPMailer classes
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Enable verbose debugging for troubleshooting
        $mail->SMTPDebug = 2; // Set to 0 for production
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug Level $level: $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $emailSettings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailSettings['smtp_username'];
        $mail->Password = $emailSettings['smtp_password'];
        
        error_log("SMTP Config - Host: " . $emailSettings['smtp_host'] . 
                 ", Port: " . $emailSettings['smtp_port'] . 
                 ", Username: " . $emailSettings['smtp_username']);
        
        // Set encryption based on database setting
        if ($emailSettings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            error_log("Using SSL encryption");
        } elseif ($emailSettings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            error_log("Using TLS encryption");
        } else {
            error_log("No encryption");
        }
        
        $mail->Port = $emailSettings['smtp_port'];
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;
        
        // Additional SMTP options for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom($emailSettings['from_email'], $emailSettings['from_name']);
        error_log("From: " . $emailSettings['from_email'] . " (" . $emailSettings['from_name'] . ")");
        
        if (!empty($toName)) {
            $mail->addAddress($to, $toName);
        } else {
            $mail->addAddress($to);
        }
        
        // Content
        $isHTML = isHTMLContent($body);
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        if ($isHTML) {
            $mail->AltBody = strip_tags($body);
        }
        
        error_log("Attempting to send email via SMTP...");
        
        // Send the email
        $result = $mail->send();
        
        if ($result) {
            error_log("SUCCESS: Email sent via SMTP to: " . $to);
        } else {
            error_log("FAILED: Email sending failed via SMTP");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("SMTP Exception: " . $e->getMessage());
        error_log("SMTP Stack Trace: " . $e->getTraceAsString());
        return false;
    }
}

function sendEmailViaHosting($to, $subject, $body, $toName, $emailSettings) {
    error_log("=== Hosting Mail() Function ===");
    error_log("Sending email via hosting server mail() function to: " . $to);
    
    $isHTML = isHTMLContent($body);
    $contentType = $isHTML ? 'text/html' : 'text/plain';
    
    $headers = array(
        'From: ' . $emailSettings['from_name'] . ' <' . $emailSettings['from_email'] . '>',
        'Reply-To: ' . $emailSettings['from_email'],
        'Content-Type: ' . $contentType . '; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0'
    );
    
    $headerString = implode("\r\n", $headers);
    
    try {
        $result = mail($to, $subject, $body, $headerString);
        if ($result) {
            error_log("SUCCESS: Email sent via hosting server to: " . $to);
        } else {
            error_log("FAILED: Email sending failed via hosting server to: " . $to);
        }
        return $result;
    } catch (Exception $e) {
        error_log("Hosting server email failed: " . $e->getMessage());
        return false;
    }
}

function sendEmail($pdo, $to, $subject, $body, $toName = '') {
    error_log("=== EMAIL SEND FUNCTION START ===");
    
    // Input validation
    if (!validateEmail($to)) {
        error_log("ABORT: Invalid email address: " . $to);
        return false;
    }
    
    $emailSettings = getEmailSettings($pdo);
    
    if (!$emailSettings) {
        error_log("ABORT: Email settings not configured or disabled");
        return false;
    }
    
    // Validate from email
    if (!validateEmail($emailSettings['from_email'])) {
        error_log("ABORT: Invalid from email in settings: " . $emailSettings['from_email']);
        return false;
    }
    
    // Sanitize inputs
    $subject = sanitizeInput($subject);
    $toName = sanitizeInput($toName);
    
    error_log("Attempting to send email to: " . $to . " with subject: " . $subject);
    
    // Check if SMTP settings are configured
    $hasSMTPConfig = !empty($emailSettings['smtp_host']) && 
                     !empty($emailSettings['smtp_username']) && 
                     !empty($emailSettings['smtp_password']);
    
    error_log("SMTP Configuration check:");
    error_log("- Host: " . ($emailSettings['smtp_host'] ?? 'EMPTY'));
    error_log("- Username: " . ($emailSettings['smtp_username'] ?? 'EMPTY'));
    error_log("- Password: " . (!empty($emailSettings['smtp_password']) ? 'SET' : 'EMPTY'));
    error_log("- Has SMTP Config: " . ($hasSMTPConfig ? 'YES' : 'NO'));
    
    // Check if PHPMailer is available
    $phpmailerReady = isPHPMailerReady();
    error_log("- PHPMailer Ready: " . ($phpmailerReady ? 'YES' : 'NO'));
    
    $canUseSMTP = $hasSMTPConfig && $phpmailerReady;
    
    error_log("FINAL DECISION: Can use SMTP: " . ($canUseSMTP ? 'YES' : 'NO'));
    
    if ($canUseSMTP) {
        error_log("ROUTE: Using SMTP server: " . $emailSettings['smtp_host'] . ":" . $emailSettings['smtp_port']);
        $result = sendEmailViaSMTP($to, $subject, $body, $toName, $emailSettings);
        
        // Fall back to hosting server if SMTP fails
        if (!$result) {
            error_log("FALLBACK: SMTP failed, falling back to hosting server mail()");
            $result = sendEmailViaHosting($to, $subject, $body, $toName, $emailSettings);
        }
    } else {
        error_log("ROUTE: Using hosting server mail() function");
        if ($hasSMTPConfig && !$phpmailerReady) {
            error_log("REASON: SMTP configured but PHPMailer not available");
        } elseif (!$hasSMTPConfig) {
            error_log("REASON: SMTP not configured properly");
        }
        $result = sendEmailViaHosting($to, $subject, $body, $toName, $emailSettings);
    }
    
    error_log("=== EMAIL SEND FUNCTION END - Result: " . ($result ? 'SUCCESS' : 'FAILED') . " ===");
    return $result;
}

function sendNotificationEmail($pdo, $triggerEvent, $recipients, $variables = []) {
    try {
        // Get email template
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE trigger_event = ? AND is_enabled = 1");
        $stmt->execute([$triggerEvent]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            error_log("Email template not found or disabled for trigger: " . $triggerEvent);
            return false;
        }
        
        $subject = $template['subject'];
        $body = $template['body'];
        
        // Replace variables in subject and body
        foreach ($variables as $key => $value) {
            $sanitizedValue = sanitizeInput($value);
            $subject = str_replace('{{' . $key . '}}', $sanitizedValue, $subject);
            $body = str_replace('{{' . $key . '}}', $sanitizedValue, $body);
        }
        
        // Send to all recipients
        $successCount = 0;
        $totalCount = 0;
        
        foreach ($recipients as $recipient) {
            $totalCount++;
            
            if (!isset($recipient['email']) || !validateEmail($recipient['email'])) {
                error_log("Invalid recipient email: " . ($recipient['email'] ?? 'undefined'));
                continue;
            }
            
            $recipientName = isset($recipient['name']) ? $recipient['name'] : '';
            $result = sendEmail($pdo, $recipient['email'], $subject, $body, $recipientName);
            
            if ($result) {
                $successCount++;
            }
        }
        
        error_log("Notification email '{$triggerEvent}': {$successCount}/{$totalCount} emails sent successfully");
        
        return $successCount > 0;
        
    } catch (PDOException $e) {
        error_log("Database error in sendNotificationEmail: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error in sendNotificationEmail: " . $e->getMessage());
        return false;
    }
}

?>