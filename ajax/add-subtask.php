<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$taskId = $_POST['task_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$taskId || !canAccessTask($pdo, $_SESSION['user_id'], $taskId)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Subtask name is required']);
    exit;
}

try {
    // Get next order position
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_position), 0) + 1 as next_position FROM subtasks WHERE project_task_id = ?");
    $stmt->execute([$taskId]);
    $result = $stmt->fetch();
    $orderPosition = $result['next_position'];
    
    // Add subtask to database
    $stmt = $pdo->prepare("
        INSERT INTO subtasks (project_task_id, name, description, order_position) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$taskId, $name, $description, $orderPosition]);
    
    // Get task and project info for activity log
    $stmt = $pdo->prepare("
        SELECT pt.project_id, pdt.name as task_name 
        FROM project_tasks pt 
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id 
        WHERE pt.id = ?
    ");
    $stmt->execute([$taskId]);
    $taskInfo = $stmt->fetch();
    
    // Log activity
    logActivity($pdo, $taskInfo['project_id'], $_SESSION['user_id'], 'subtask_added', 
               "Added subtask '$name' to task '{$taskInfo['task_name']}'");
    
    echo json_encode(['success' => true, 'message' => 'Subtask added successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
