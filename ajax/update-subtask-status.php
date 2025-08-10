<?php
require_once '../config/database.php';
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

$subtaskId = $_POST['subtask_id'] ?? 0;
$status = $_POST['status'] ?? '';

$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Rejected'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Get subtask and related info
    $stmt = $pdo->prepare("
        SELECT s.*, pt.project_id, pt.assigned_to, p.bdm_id, p.name as project_name, p.hotel_name,
               pdt.name as task_name
        FROM subtasks s
        JOIN project_tasks pt ON s.project_task_id = pt.id
        JOIN projects p ON pt.project_id = p.id
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
        WHERE s.id = ?
    ");
    $stmt->execute([$subtaskId]);
    $subtask = $stmt->fetch();
    
    if (!$subtask) {
        echo json_encode(['success' => false, 'message' => 'Subtask not found']);
        exit;
    }
    
    // Check if user can update this subtask
    $canUpdate = isAdmin() || $subtask['assigned_to'] == $_SESSION['user_id'] || $subtask['bdm_id'] == $_SESSION['user_id'];
    if (!$canUpdate) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this subtask']);
        exit;
    }
    
    $completedAt = ($status === 'Completed') ? date('Y-m-d H:i:s') : null;
    
    // Update subtask status
    $stmt = $pdo->prepare("
        UPDATE subtasks 
        SET status = ?, completed_at = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $completedAt, $subtaskId]);
    
    // Update main task status based on subtasks
    updateMainTaskStatusBasedOnSubtasks($pdo, $subtask['project_task_id']);
    
    // Log activity
    $currentUser = getCurrentUser($pdo);
    logActivity($pdo, $subtask['project_id'], $_SESSION['user_id'], 'subtask_updated', 
               "Updated subtask '{$subtask['name']}' status to: $status");
    
    // Send notification emails
    $recipients = [];
    
    // Add BDM
    if ($subtask['bdm_id'] && $subtask['bdm_id'] != $_SESSION['user_id']) {
        $bdmUser = getUserInfo($pdo, $subtask['bdm_id']);
        if ($bdmUser) {
            $recipients[] = $bdmUser;
        }
    }
    
    // Add assigned user if different from current user
    if ($subtask['assigned_to'] && $subtask['assigned_to'] != $_SESSION['user_id']) {
        $assignedUser = getUserInfo($pdo, $subtask['assigned_to']);
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
        'subtask_name' => $subtask['name'],
        'task_name' => $subtask['task_name'],
        'project_name' => $subtask['project_name'],
        'hotel_name' => $subtask['hotel_name'],
        'new_status' => $status,
        'user_name' => $currentUser['name']
    ];
    
    sendNotificationEmail($pdo, 'subtask_updated', $recipients, $variables);
    
    echo json_encode(['success' => true, 'message' => 'Subtask status updated successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
