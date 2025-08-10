<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /dashboard.php');
        exit;
    }
}

function getUserInfo($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    return getUserInfo($pdo, $_SESSION['user_id']);
}

function isBDMForProject($pdo, $userId, $projectId) {
    $stmt = $pdo->prepare("SELECT bdm_id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    return $project && $project['bdm_id'] == $userId;
}

function canAccessProject($pdo, $userId, $projectId) {
    // Admin can access all projects
    if (isAdmin()) {
        return true;
    }
    
    // BDM can access their assigned projects
    if (isBDMForProject($pdo, $userId, $projectId)) {
        return true;
    }
    
    // Regular users can access projects where they have assigned tasks
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM project_tasks pt 
        WHERE pt.project_id = ? AND pt.assigned_to = ?
    ");
    $stmt->execute([$projectId, $userId]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

function canAccessTask($pdo, $userId, $taskId) {
    // Admin can access all tasks
    if (isAdmin()) {
        return true;
    }
    
    // Get task and project info
    $stmt = $pdo->prepare("
        SELECT pt.*, p.bdm_id 
        FROM project_tasks pt 
        JOIN projects p ON pt.project_id = p.id 
        WHERE pt.id = ?
    ");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if (!$task) {
        return false;
    }
    
    // BDM can access all tasks in their projects
    if ($task['bdm_id'] == $userId) {
        return true;
    }
    
    // Regular users can only access their assigned tasks
    return $task['assigned_to'] == $userId;
}
?>
