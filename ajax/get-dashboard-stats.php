<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Require login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Get total projects
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM projects WHERE bdm_id = ?");
        $stmt->execute([$userId]);
    }
    $totalProjects = $stmt->fetch()['count'];
    
    // Get active projects (not completed)
    if ($role === 'admin') {
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT p.id) as count 
            FROM projects p 
            JOIN project_tasks pt ON p.id = pt.project_id 
            WHERE pt.status != 'Completed'
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT p.id) as count 
            FROM projects p 
            JOIN project_tasks pt ON p.id = pt.project_id 
            WHERE p.bdm_id = ? AND pt.status != 'Completed'
        ");
        $stmt->execute([$userId]);
    }
    $activeProjects = $stmt->fetch()['count'];
    
    // Get total tasks
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_tasks");
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM project_tasks pt 
            JOIN projects p ON pt.project_id = p.id 
            WHERE p.bdm_id = ? OR pt.assigned_to = ?
        ");
        $stmt->execute([$userId, $userId]);
    }
    $totalTasks = $stmt->fetch()['count'];
    
    // Get completed tasks
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_tasks WHERE status = 'Completed'");
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM project_tasks pt 
            JOIN projects p ON pt.project_id = p.id 
            WHERE (p.bdm_id = ? OR pt.assigned_to = ?) AND pt.status = 'Completed'
        ");
        $stmt->execute([$userId, $userId]);
    }
    $completedTasks = $stmt->fetch()['count'];
    
    $stats = [
        'total_projects' => $totalProjects,
        'active_projects' => $activeProjects,
        'total_tasks' => $totalTasks,
        'completed_tasks' => $completedTasks
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>