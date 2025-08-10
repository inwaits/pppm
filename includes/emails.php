<?php
// includes/emails.php

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

// Check if PHPMailer is available and working
function isPHPMailerReady() {
    // Check multiple possible locations for PHPMailer
    $possible_paths = [
        dirname(__DIR__) . '/libs/phpmailer/src/',
        dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/',
        __DIR__ . '/../libs/phpmailer/src/',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/'
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path . 'Exception.php') && 
            file_exists($path . 'PHPMailer.php') && 
            file_exists($path . 'SMTP.php')) {
            
            // Try to include the files safely
            try {
                require_once $path . 'Exception.php';
                require_once $path . 'PHPMailer.php';
                require_once $path . 'SMTP.php';
                return true;
            } catch (Exception $e) {
                error_log("Failed to load PHPMailer from: " . $path . " - " . $e->getMessage());
                continue;
            }
        }
    }
    
    return false;
}

function sendEmailViaSMTP($to, $subject, $body, $toName, $emailSettings) {
    // Only try SMTP if PHPMailer is ready
    if (!isPHPMailerReady()) {
        error_log("PHPMailer not available for SMTP");
        return false;
    }
    
    try {
        // Use PHPMailer classes
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $emailSettings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailSettings['smtp_username'];
        $mail->Password = $emailSettings['smtp_password'];
        
        // Set encryption based on database setting
        if ($emailSettings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($emailSettings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = $emailSettings['smtp_port'];
        $mail->Timeout = 30;
        
        // Recipients
        $mail->setFrom($emailSettings['from_email'], $emailSettings['from_name']);
        
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
        
        // Send the email
        $result = $mail->send();
        
        if ($result) {
            error_log("Email sent successfully via SMTP to: " . $to . " using " . $emailSettings['smtp_host']);
        } else {
            error_log("Email sending failed via SMTP");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("SMTP Email sending failed: " . $e->getMessage());
        return false;
    }
}

function sendEmailViaHosting($to, $subject, $body, $toName, $emailSettings) {
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
            error_log("Email sent successfully via hosting server to: " . $to);
        } else {
            error_log("Email sending failed via hosting server to: " . $to);
        }
        return $result;
    } catch (Exception $e) {
        error_log("Hosting server email failed: " . $e->getMessage());
        return false;
    }
}

function sendEmail($pdo, $to, $subject, $body, $toName = '') {
    // Input validation
    if (!validateEmail($to)) {
        error_log("Invalid email address: " . $to);
        return false;
    }
    
    $emailSettings = getEmailSettings($pdo);
    
    if (!$emailSettings) {
        error_log("Email settings not configured or disabled");
        return false;
    }
    
    // Validate from email
    if (!validateEmail($emailSettings['from_email'])) {
        error_log("Invalid from email in settings: " . $emailSettings['from_email']);
        return false;
    }
    
    // Sanitize inputs
    $subject = sanitizeInput($subject);
    $toName = sanitizeInput($toName);
    
    error_log("Attempting to send email to: " . $to . " with subject: " . $subject);
    
    // Check if SMTP settings are configured and PHPMailer is available
    $hasSMTPConfig = !empty($emailSettings['smtp_host']) && 
                     !empty($emailSettings['smtp_username']) && 
                     !empty($emailSettings['smtp_password']);
    
    $canUseSMTP = $hasSMTPConfig && isPHPMailerReady();
    
    if ($canUseSMTP) {
        error_log("Using SMTP server: " . $emailSettings['smtp_host'] . ":" . $emailSettings['smtp_port']);
        $result = sendEmailViaSMTP($to, $subject, $body, $toName, $emailSettings);
        
        // Fall back to hosting server if SMTP fails
        if (!$result) {
            error_log("SMTP failed, falling back to hosting server mail()");
            $result = sendEmailViaHosting($to, $subject, $body, $toName, $emailSettings);
        }
    } else {
        if ($hasSMTPConfig && !isPHPMailerReady()) {
            error_log("SMTP configured but PHPMailer not available, using hosting server mail()");
        } else {
            error_log("SMTP not configured, using hosting server mail()");
        }
        $result = sendEmailViaHosting($to, $subject, $body, $toName, $emailSettings);
    }
    
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
        
        // Return simple boolean for backward compatibility
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