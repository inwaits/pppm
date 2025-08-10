<?php
$pageTitle = 'Edit Project';
require_once '../includes/header.php';
requireAdmin();

$projectId = $_GET['id'] ?? 0;

if (!$projectId) {
    header('Location: /projects/');
    exit;
}

// Get project details
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: /projects/');
    exit;
}

$error = '';
$success = '';

// Get all users for BDM selection
$stmt = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('admin', 'employee') ORDER BY name");
$users = $stmt->fetchAll();

// Get predefined tasks
$stmt = $pdo->query("SELECT * FROM predefined_tasks ORDER BY order_position ASC");
$predefinedTasks = $stmt->fetchAll();

// Get current project tasks
$stmt = $pdo->prepare("SELECT * FROM project_tasks WHERE project_id = ?");
$stmt->execute([$projectId]);
$currentTasks = [];
foreach ($stmt->fetchAll() as $task) {
    $currentTasks[$task['predefined_task_id']] = $task;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $hotelName = trim($_POST['hotel_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $bdmId = $_POST['bdm_id'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $taskAssignments = $_POST['task_assignments'] ?? [];
    $taskDueDates = $_POST['task_due_dates'] ?? [];
    
    if (empty($name) || empty($hotelName) || empty($bdmId)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update project
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET name = ?, hotel_name = ?, description = ?, bdm_id = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $hotelName, $description, $bdmId, $status, $projectId]);
            
            // Update task assignments and due dates
            foreach ($predefinedTasks as $task) {
                $assignedTo = $taskAssignments[$task['id']] ?? null;
                $assignedTo = empty($assignedTo) ? null : $assignedTo;
                
                $dueDate = $taskDueDates[$task['id']] ?? null;
                $dueDate = empty($dueDate) ? null : $dueDate;
                
                if (isset($currentTasks[$task['id']])) {
                    // Update existing task
                    $stmt = $pdo->prepare("
                        UPDATE project_tasks 
                        SET assigned_to = ?, due_date = ? 
                        WHERE project_id = ? AND predefined_task_id = ?
                    ");
                    $stmt->execute([$assignedTo, $dueDate, $projectId, $task['id']]);
                } else {
                    // Create new task if it doesn't exist
                    $stmt = $pdo->prepare("
                        INSERT INTO project_tasks (project_id, predefined_task_id, assigned_to, due_date) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$projectId, $task['id'], $assignedTo, $dueDate]);
                    $projectTaskId = $pdo->lastInsertId();
                    
                    // Create subtasks for new task
                    $subtaskStmt = $pdo->prepare("SELECT * FROM predefined_subtasks WHERE predefined_task_id = ? ORDER BY order_position ASC");
                    $subtaskStmt->execute([$task['id']]);
                    $predefinedSubtasks = $subtaskStmt->fetchAll();
                    
                    foreach ($predefinedSubtasks as $subtask) {
                        $stmt = $pdo->prepare("
                            INSERT INTO subtasks (project_task_id, predefined_subtask_id, name, description, order_position) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$projectTaskId, $subtask['id'], $subtask['name'], $subtask['description'], $subtask['order_position']]);
                    }
                }
            }
            
            // Log activity
            logActivity($pdo, $projectId, $_SESSION['user_id'], 'project_updated', "Updated project: $name");
            
            $pdo->commit();
            $success = 'Project updated successfully!';
            
            // Refresh project data and current tasks
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();
            
            // Refresh current tasks data
            $stmt = $pdo->prepare("SELECT * FROM project_tasks WHERE project_id = ?");
            $stmt->execute([$projectId]);
            $currentTasks = [];
            foreach ($stmt->fetchAll() as $task) {
                $currentTasks[$task['predefined_task_id']] = $task;
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating project: ' . $e->getMessage();
        }
    }
}
?>


<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Project</h1>
        <div>
            <a href="/projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary me-2">
                <i class="fas fa-eye me-2"></i>View Project
            </a>
            <a href="/projects/" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Projects
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="card shadow">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Hotel Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($project['name']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hotel_name" class="form-label">Hotel Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="hotel_name" name="hotel_name" required
                                   value="<?php echo htmlspecialchars($project['hotel_name']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="bdm_id" class="form-label">Assigned BDM <span class="text-danger">*</span></label>
                            <select class="form-select" id="bdm_id" name="bdm_id" required>
                                <option value="">Select BDM</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $project['bdm_id'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Active" <?php echo $project['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Completed" <?php echo $project['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="On Hold" <?php echo $project['status'] === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="Cancelled" <?php echo $project['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($project['description']); ?></textarea>
                </div>
                
                <h5 class="mb-3">Task Assignments</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Description</th>
                                <th>Current Assignment</th>
                                <th>Current Due Date</th>
                                <th>Assign To</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($predefinedTasks as $task): ?>
                                <?php $currentAssignment = $currentTasks[$task['id']] ?? null; ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($task['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                                    <td>
                                        <?php if ($currentAssignment && $currentAssignment['assigned_to']): ?>
                                            <?php
                                            $assignedUser = getUserInfo($pdo, $currentAssignment['assigned_to']);
                                            echo $assignedUser ? htmlspecialchars($assignedUser['name']) : 'User not found';
                                            ?>
                                            <br><small class="badge <?php echo getStatusBadgeClass($currentAssignment['status']); ?>"><?php echo $currentAssignment['status']; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($currentAssignment && $currentAssignment['due_date']): ?>
                                            <span class="text-primary"><?php echo date('M j, Y', strtotime($currentAssignment['due_date'])); ?></span>
                                            <?php
                                            $dueDate = new DateTime($currentAssignment['due_date']);
                                            $today = new DateTime();
                                            if ($dueDate < $today && $currentAssignment['status'] !== 'Completed'):
                                            ?>
                                                <br><small class="badge bg-danger">Overdue</small>
                                            <?php elseif ($dueDate->diff($today)->days <= 3 && $dueDate >= $today && $currentAssignment['status'] !== 'Completed'): ?>
                                                <br><small class="badge bg-warning">Due Soon</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="form-select" name="task_assignments[<?php echo $task['id']; ?>]">
                                            <option value="">Select User</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" 
                                                    <?php echo ($currentAssignment && $currentAssignment['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="date" class="form-control" 
                                               name="task_due_dates[<?php echo $task['id']; ?>]"
                                               value="<?php echo $currentAssignment && $currentAssignment['due_date'] ? htmlspecialchars($currentAssignment['due_date']) : ''; ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="/projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>