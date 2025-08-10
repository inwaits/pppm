<?php
require_once '../includes/header.php';

$taskId = $_GET['id'] ?? 0;

if (!$taskId || !canAccessTask($pdo, $_SESSION['user_id'], $taskId)) {
    header('Location: /tasks/');
    exit;
}

// Get task details
$stmt = $pdo->prepare("
    SELECT pt.*, p.name as project_name, p.hotel_name, p.bdm_id,
           pdt.name as task_name, pdt.description as task_description,
           u.name as assigned_user_name
    FROM project_tasks pt
    JOIN projects p ON pt.project_id = p.id
    JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
    LEFT JOIN users u ON pt.assigned_to = u.id
    WHERE pt.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: /tasks/');
    exit;
}

$pageTitle = 'Update ' . $task['task_name'];
$error = '';
$success = '';

// Check if current user can update this task
$canUpdate = isAdmin() || $task['assigned_to'] == $_SESSION['user_id'] || $task['bdm_id'] == $_SESSION['user_id'];

if (!$canUpdate) {
    header('Location: /tasks/view.php?id=' . $taskId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($status)) {
        $error = 'Please select a status.';
    } else {
        try {
            $completedAt = ($status === 'Completed') ? date('Y-m-d H:i:s') : null;
            
            $stmt = $pdo->prepare("
                UPDATE project_tasks 
                SET status = ?, notes = ?, completed_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $completedAt, $taskId]);
            
            // Log activity
            logActivity($pdo, $task['project_id'], $_SESSION['user_id'], 'task_updated', 
                       "Updated task '{$task['task_name']}' status to: $status");
            
            // Send notification emails
            $recipients = [];
            
            // Add BDM
            if ($task['bdm_id']) {
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
            
            $success = 'Task updated successfully!';
            
            // Refresh task data
            $stmt = $pdo->prepare("
                SELECT pt.*, p.name as project_name, p.hotel_name, p.bdm_id,
                       pdt.name as task_name, pdt.description as task_description,
                       u.name as assigned_user_name
                FROM project_tasks pt
                JOIN projects p ON pt.project_id = p.id
                JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
                LEFT JOIN users u ON pt.assigned_to = u.id
                WHERE pt.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Error updating task: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Update Task</h1>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($task['task_name']); ?></p>
        </div>
        <div>
            <a href="/tasks/view.php?id=<?php echo $taskId; ?>" class="btn btn-outline-primary me-2">
                <i class="fas fa-eye me-2"></i>View Details
            </a>
            <a href="/tasks/" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Tasks
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Task Information</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Project:</strong> <?php echo htmlspecialchars($task['project_name']); ?></p>
                            <p><strong>Hotel:</strong> <?php echo htmlspecialchars($task['hotel_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Assigned to:</strong> <?php echo htmlspecialchars($task['assigned_user_name']); ?></p>
                            <p><strong>Current Status:</strong> 
                                <span class="badge <?php echo getStatusBadgeClass($task['status']); ?>"><?php echo $task['status']; ?></span>
                            </p>
                        </div>
                    </div>
                    
                    <p><strong>Description:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($task['task_description'])); ?></p>
                    
                    <?php if ($task['notes']): ?>
                        <p><strong>Current Notes:</strong></p>
                        <div class="alert alert-info">
                            <?php echo nl2br(htmlspecialchars($task['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Update Status</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Rejected" <?php echo $task['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" 
                                      placeholder="Add any notes about this update..."><?php echo htmlspecialchars($task['notes']); ?></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
