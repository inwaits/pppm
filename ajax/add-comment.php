<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$taskId = $_POST['task_id'] ?? 0;
$message = trim($_POST['message'] ?? '');

if (!$taskId || !canAccessTask($pdo, $_SESSION['user_id'], $taskId)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Comment message is required']);
    exit;
}

try {
    // Add comment to database
    $stmt = $pdo->prepare("
        INSERT INTO comments (project_task_id, user_id, message) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$taskId, $_SESSION['user_id'], $message]);
    
    // Get task and project info for notifications
    $stmt = $pdo->prepare("
        SELECT pt.project_id, pt.assigned_to, p.bdm_id, p.name as project_name, p.hotel_name,
               pdt.name as task_name, u.name as comment_user
        FROM project_tasks pt 
        JOIN projects p ON pt.project_id = p.id
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
        JOIN users u ON u.id = ?
        WHERE pt.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $taskId]);
    $taskInfo = $stmt->fetch();
    
    // Log activity
    logActivity($pdo, $taskInfo['project_id'], $_SESSION['user_id'], 'comment_added', 
               "Added comment to task '{$taskInfo['task_name']}'");
    
    // Send notification emails
    $recipients = [];
    
    // Add BDM
    if ($taskInfo['bdm_id'] && $taskInfo['bdm_id'] != $_SESSION['user_id']) {
        $bdmUser = getUserInfo($pdo, $taskInfo['bdm_id']);
        if ($bdmUser) {
            $recipients[] = $bdmUser;
        }
    }
    
    // Add assigned user if different from current user
    if ($taskInfo['assigned_to'] && $taskInfo['assigned_to'] != $_SESSION['user_id']) {
        $assignedUser = getUserInfo($pdo, $taskInfo['assigned_to']);
        if ($assignedUser) {
            $recipients[] = $assignedUser;
        }
    }
    
    // Add admin users
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin'");
    $adminUsers = $stmt->fetchAll();
    foreach ($adminUsers as $admin) {
        if ($admin['id'] != $_SESSION['user_id']) {
            $recipients[] = $admin;
        }
    }
    
    $variables = [
        'task_name' => $taskInfo['task_name'],
        'project_name' => $taskInfo['project_name'],
        'hotel_name' => $taskInfo['hotel_name'],
        'comment_user' => $taskInfo['comment_user'],
        'user_name' => $taskInfo['comment_user']
    ];
    
    sendNotificationEmail($pdo, 'comment_added', $recipients, $variables);
    
    echo json_encode(['success' => true, 'message' => 'Comment added successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
