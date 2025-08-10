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

$taskId = $_POST['task_id'] ?? 0;
$status = $_POST['status'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if (!$taskId || !canAccessTask($pdo, $_SESSION['user_id'], $taskId)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Rejected'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Get current task info
    $stmt = $pdo->prepare("
        SELECT pt.*, p.bdm_id, p.name as project_name, p.hotel_name,
               pdt.name as task_name
        FROM project_tasks pt 
        JOIN projects p ON pt.project_id = p.id
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit;
    }
    
    // Check if user can update this task
    $canUpdate = isAdmin() || $task['assigned_to'] == $_SESSION['user_id'] || $task['bdm_id'] == $_SESSION['user_id'];
    if (!$canUpdate) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task']);
        exit;
    }
    
    // If trying to complete the task, check if all subtasks are completed
    if ($status === 'Completed') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_subtasks, 
                                      SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_subtasks
                               FROM subtasks WHERE project_task_id = ?");
        $stmt->execute([$taskId]);
        $subtaskStats = $stmt->fetch();
        
        if ($subtaskStats['total_subtasks'] > 0 && $subtaskStats['completed_subtasks'] < $subtaskStats['total_subtasks']) {
            echo json_encode(['success' => false, 'message' => 'Cannot complete task: All subtasks must be completed first']);
            exit;
        }
    }
    
    $completedAt = ($status === 'Completed') ? date('Y-m-d H:i:s') : null;
    
    // Update task status
    $stmt = $pdo->prepare("
        UPDATE project_tasks 
        SET status = ?, notes = ?, completed_at = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $notes, $completedAt, $taskId]);
    
    // Log activity
    $currentUser = getCurrentUser($pdo);
    logActivity($pdo, $task['project_id'], $_SESSION['user_id'], 'task_updated', 
               "Updated task '{$task['task_name']}' status to: $status");
    
    // Send notification emails
    $recipients = [];
    
    // Add BDM
    if ($task['bdm_id'] && $task['bdm_id'] != $_SESSION['user_id']) {
        $bdmUser = getUserInfo($pdo, $task['bdm_id']);
        if ($bdmUser) {
            $recipients[] = $bdmUser;
        }
    }
    
    // Add assigned user if different from current user
    if ($task['assigned_to'] && $task['assigned_to'] != $_SESSION['user_id']) {
        $assignedUser = getUserInfo($pdo, $task['assigned_to']);
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
        'task_name' => $task['task_name'],
        'project_name' => $task['project_name'],
        'hotel_name' => $task['hotel_name'],
        'new_status' => $status,
        'user_name' => $currentUser['name']
    ];
    
    sendNotificationEmail($pdo, 'task_status_updated', $recipients, $variables);
    
    echo json_encode(['success' => true, 'message' => 'Task status updated successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
