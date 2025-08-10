<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

function logActivity($pdo, $projectId, $userId, $action, $description) {
    $stmt = $pdo->prepare("
        INSERT INTO project_activity_logs (project_id, user_id, action, description) 
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$projectId, $userId, $action, $description]);
}

function getProjectStats($pdo) {
    $stats = [];
    
    // Total projects
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
    $stats['total_projects'] = $stmt->fetch()['count'];
    
    // Active projects
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status = 'Active'");
    $stats['active_projects'] = $stmt->fetch()['count'];
    
    // Total tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_tasks");
    $stats['total_tasks'] = $stmt->fetch()['count'];
    
    // Completed tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_tasks WHERE status = 'Completed'");
    $stats['completed_tasks'] = $stmt->fetch()['count'];
    
    // Pending tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_tasks WHERE status = 'Pending'");
    $stats['pending_tasks'] = $stmt->fetch()['count'];
    
    // In progress tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_tasks WHERE status = 'In Progress'");
    $stats['in_progress_tasks'] = $stmt->fetch()['count'];
    
    return $stats;
}

function getTaskStatusCounts($pdo, $userId = null, $projectId = null) {
    $sql = "SELECT status, COUNT(*) as count FROM project_tasks WHERE 1=1";
    $params = [];
    
    if ($userId && !isAdmin()) {
        $sql .= " AND assigned_to = ?";
        $params[] = $userId;
    }
    
    if ($projectId) {
        $sql .= " AND project_id = ?";
        $params[] = $projectId;
    }
    
    $sql .= " GROUP BY status";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $counts = [
        'Pending' => 0,
        'In Progress' => 0,
        'Completed' => 0,
        'Rejected' => 0
    ];
    
    while ($row = $stmt->fetch()) {
        $counts[$row['status']] = $row['count'];
    }
    
    return $counts;
}

function getUserTasks($pdo, $userId, $status = null, $search = null) {
    $sql = "
        SELECT pt.*, p.name as project_name, p.hotel_name, pdt.name as task_name, pdt.description as task_description,
               u.name as assigned_user_name
        FROM project_tasks pt
        JOIN projects p ON pt.project_id = p.id
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
        LEFT JOIN users u ON pt.assigned_to = u.id
        WHERE pt.assigned_to = ?
    ";
    
    $params = [$userId];
    
    if ($status) {
        $sql .= " AND pt.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $sql .= " AND (pdt.name LIKE ? OR p.name LIKE ? OR p.hotel_name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY pt.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function getProjectTasks($pdo, $projectId, $userId = null) {
    $sql = "
        SELECT pt.*, pdt.name as task_name, pdt.description as task_description,
               u.name as assigned_user_name, u.email as assigned_user_email
        FROM project_tasks pt
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
        LEFT JOIN users u ON pt.assigned_to = u.id
        WHERE pt.project_id = ?
    ";
    
    $params = [$projectId];
    
    // If not admin and not BDM, only show assigned tasks
    if ($userId && !isAdmin() && !isBDMForProject($pdo, $userId, $projectId)) {
        $sql .= " AND pt.assigned_to = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY pdt.order_position ASC, pt.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function getTaskSubtasks($pdo, $taskId) {
    $stmt = $pdo->prepare("
        SELECT s.*, ps.name as predefined_name, ps.description as predefined_description
        FROM subtasks s
        LEFT JOIN predefined_subtasks ps ON s.predefined_subtask_id = ps.id
        WHERE s.project_task_id = ?
        ORDER BY s.order_position ASC, s.created_at ASC
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

function getTaskComments($pdo, $taskId) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.project_task_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

function getTaskAttachments($pdo, $taskId) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as uploaded_by_name
        FROM attachments a
        JOIN users u ON a.user_id = u.id
        WHERE a.project_task_id = ?
        ORDER BY a.uploaded_at DESC
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending':
            return 'bg-warning';
        case 'In Progress':
            return 'bg-info';
        case 'Completed':
            return 'bg-success';
        case 'Rejected':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

function sanitizeFileName($filename) {
    // Remove any path traversal attempts
    $filename = basename($filename);
    // Remove any non-alphanumeric characters except dots, dashes, and underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

function isAllowedFileType($filename) {
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions);
}

// --- Additional function you wanted to add below ---

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function updateMainTaskStatusBasedOnSubtasks($pdo, $taskId) {
    try {
        // Get all subtasks for this task
        $stmt = $pdo->prepare("SELECT status FROM subtasks WHERE project_task_id = ?");
        $stmt->execute([$taskId]);
        $subtasks = $stmt->fetchAll();
        
        if (empty($subtasks)) {
            return; // No subtasks, no update needed
        }
        
        $subtaskStatuses = array_column($subtasks, 'status');
        $totalSubtasks = count($subtaskStatuses);
        
        // Count statuses
        $completedCount = count(array_filter($subtaskStatuses, function($status) {
            return $status === 'Completed';
        }));
        
        $inProgressCount = count(array_filter($subtaskStatuses, function($status) {
            return $status === 'In Progress';
        }));
        
        $newStatus = null;
        
        // Determine new status
        if ($completedCount === $totalSubtasks) {
            $newStatus = 'Completed';
        } elseif ($inProgressCount > 0 || $completedCount > 0) {
            $newStatus = 'In Progress';
        } else {
            $newStatus = 'Pending';
        }
        
        // Additional safety check
        if ($newStatus === 'Completed' && $completedCount < $totalSubtasks) {
            $newStatus = 'In Progress';
        }
        
        // Get current task status
        $stmt = $pdo->prepare("SELECT status FROM project_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $currentTask = $stmt->fetch();
        
        // Only update if status actually changed
        if ($currentTask && $currentTask['status'] !== $newStatus) {
            $completedAt = ($newStatus === 'Completed') ? date('Y-m-d H:i:s') : null;
            
            $stmt = $pdo->prepare("
                UPDATE project_tasks 
                SET status = ?, completed_at = ? 
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $completedAt, $taskId]);
            
            // Log activity
            $stmt = $pdo->prepare("
                SELECT pt.project_id, pdt.name as task_name 
                FROM project_tasks pt 
                JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id 
                WHERE pt.id = ?
            ");
            $stmt->execute([$taskId]);
            $taskInfo = $stmt->fetch();
            
            if ($taskInfo) {
                $userId = $_SESSION['user_id'] ?? null;
                logActivity(
                    $pdo,
                    $taskInfo['project_id'],
                    $userId,
                    'task_auto_updated', 
                    "Task '{$taskInfo['task_name']}' status automatically updated to: $newStatus (based on subtask completion)"
                );
            }
        }
        
    } catch (Exception $e) {
        error_log("Error updating main task status: " . $e->getMessage());
    }
}

?>
