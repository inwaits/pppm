<?php
$pageTitle = 'Email Settings';
require_once '../includes/header.php';
require_once '../config/email.php'; // Make sure this points to your updated config/email.php
requireAdmin();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_smtp':
                $smtpHost = trim($_POST['smtp_host'] ?? '');
                $smtpPort = $_POST['smtp_port'] ?? 587;
                $smtpUsername = trim($_POST['smtp_username'] ?? '');
                $smtpPassword = $_POST['smtp_password'] ?? '';
                $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';
                $fromEmail = trim($_POST['from_email'] ?? '');
                $fromName = trim($_POST['from_name'] ?? '');
                $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
                
                if (empty($smtpHost) || empty($smtpUsername) || empty($fromEmail) || empty($fromName)) {
                    $error = 'Please fill in all required fields.';
                } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid from email address.';
                } else {
                    try {
                        // Delete existing settings
                        $pdo->exec("DELETE FROM email_settings");
                        
                        // Insert new settings
                        $stmt = $pdo->prepare("
                            INSERT INTO email_settings (smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, from_email, from_name, is_enabled) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpEncryption, $fromEmail, $fromName, $isEnabled]);
                        $success = 'SMTP settings updated successfully.';
                    } catch (Exception $e) {
                        $error = 'Error updating SMTP settings: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_template':
                $templateId = $_POST['template_id'] ?? '';
                $subject = trim($_POST['subject'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $isEnabled = isset($_POST['template_enabled']) ? 1 : 0;
                
                if (empty($templateId) || empty($subject) || empty($body)) {
                    $error = 'Template ID, subject, and body are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE email_templates 
                            SET subject = ?, body = ?, is_enabled = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$subject, $body, $isEnabled, $templateId]);
                        $success = 'Email template updated successfully.';
                    } catch (Exception $e) {
                        $error = 'Error updating email template: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'test_email':
                $testEmail = trim($_POST['test_email'] ?? '');
                
                if (empty($testEmail)) {
                    $error = 'Please enter an email address for testing.';
                } elseif (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    $subject = 'Test Email from HousekeepPM';
                    $body = 'This is a test email to verify your SMTP configuration is working correctly.';
                    
                    if (sendEmail($pdo, $testEmail, $subject, $body)) {
                        $success = 'Test email sent successfully!';
                    } else {
                        $error = 'Failed to send test email. Please check your SMTP settings and error logs.';
                    }
                }
                break;
        }
    }
}

// Get current SMTP settings
$stmt = $pdo->query("SELECT * FROM email_settings ORDER BY id DESC LIMIT 1");
$smtpSettings = $stmt->fetch();

// Get email templates
$stmt = $pdo->query("SELECT * FROM email_templates ORDER BY trigger_event");
$templates = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Email Settings</h1>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- SMTP Configuration -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">SMTP Configuration</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_smtp">
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" 
                                       <?php echo ($smtpSettings && $smtpSettings['is_enabled']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_enabled">
                                    Enable Email Notifications
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="smtp_host" class="form-label">SMTP Host <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" required
                                   placeholder="smtp.gmail.com"
                                   value="<?php echo htmlspecialchars($smtpSettings['smtp_host'] ?? ''); ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_port" class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                           value="<?php echo $smtpSettings['smtp_port'] ?? 587; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_encryption" class="form-label">Encryption</label>
                                    <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo ($smtpSettings && $smtpSettings['smtp_encryption'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($smtpSettings && $smtpSettings['smtp_encryption'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo ($smtpSettings && $smtpSettings['smtp_encryption'] === 'none') ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="smtp_username" class="form-label">SMTP Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" required
                                   value="<?php echo htmlspecialchars($smtpSettings['smtp_username'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="smtp_password" class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                   placeholder="Leave blank to keep current password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="from_email" class="form-label">From Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="from_email" name="from_email" required
                                   value="<?php echo htmlspecialchars($smtpSettings['from_email'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="from_name" class="form-label">From Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="from_name" name="from_name" required
                                   placeholder="HousekeepPM System"
                                   value="<?php echo htmlspecialchars($smtpSettings['from_name'] ?? ''); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Test Email -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Test Email</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">Send a test email to verify your SMTP configuration.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="test_email">
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Test Email Address</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" required
                                   placeholder="test@example.com">
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-paper-plane me-2"></i>Send Test Email
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Email Templates -->
    <div class="card shadow">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">Email Templates</h6>
        </div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <p class="text-muted text-center py-4">No email templates found.</p>
            <?php else: ?>
                <div class="accordion" id="templatesAccordion">
                    <?php foreach ($templates as $template): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $template['id']; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $template['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                        <div>
                                            <strong><?php echo ucwords(str_replace('_', ' ', $template['trigger_event'])); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($template['subject']); ?></small>
                                        </div>
                                        <span class="badge <?php echo $template['is_enabled'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $template['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                        </span>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $template['id']; ?>" class="accordion-collapse collapse" 
                                 data-bs-parent="#templatesAccordion">
                                <div class="accordion-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_template">
                                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="template_enabled" 
                                                       id="enabled<?php echo $template['id']; ?>" 
                                                       <?php echo $template['is_enabled'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enabled<?php echo $template['id']; ?>">
                                                    Enable this email template
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Trigger Event</label>
                                            <input type="text" class="form-control" readonly 
                                                   value="<?php echo ucwords(str_replace('_', ' ', $template['trigger_event'])); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Email Subject</label>
                                            <input type="text" class="form-control" name="subject" required
                                                   value="<?php echo htmlspecialchars($template['subject']); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Email Body</label>
                                            <textarea class="form-control" name="body" rows="8" required><?php echo htmlspecialchars($template['body']); ?></textarea>
                                            <div class="form-text">
                                                Available variables: {{user_name}}, {{task_name}}, {{project_name}}, {{hotel_name}}, {{new_status}}, {{comment_user}}, {{subtask_name}}
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Update Template</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>