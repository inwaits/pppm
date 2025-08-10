<?php
$pageTitle = 'Create Project';
require_once '../includes/header.php';
requireAdmin();

$error = '';
$success = '';

// Get all users for BDM selection
$stmt = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('admin', 'employee') ORDER BY name");
$users = $stmt->fetchAll();

// Get predefined tasks
$stmt = $pdo->query("SELECT * FROM predefined_tasks ORDER BY order_position ASC");
$predefinedTasks = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $hotelName = trim($_POST['hotel_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $bdmId = $_POST['bdm_id'] ?? '';
    $taskAssignments = $_POST['task_assignments'] ?? [];
    $taskDueDates = $_POST['task_due_dates'] ?? [];
    
    if (empty($name) || empty($hotelName) || empty($bdmId)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Create project
            $stmt = $pdo->prepare("
                INSERT INTO projects (name, hotel_name, description, bdm_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $hotelName, $description, $bdmId]);
            $projectId = $pdo->lastInsertId();
            
            // Create project tasks
            foreach ($predefinedTasks as $task) {
                $assignedTo = $taskAssignments[$task['id']] ?? null;
                $assignedTo = empty($assignedTo) ? null : $assignedTo;
                
                $dueDate = $taskDueDates[$task['id']] ?? null;
                $dueDate = empty($dueDate) ? null : $dueDate;
                
                $stmt = $pdo->prepare("
                    INSERT INTO project_tasks (project_id, predefined_task_id, assigned_to, due_date) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$projectId, $task['id'], $assignedTo, $dueDate]);
                $projectTaskId = $pdo->lastInsertId();
                
                // Create subtasks for this task
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
            
            // Log activity
            logActivity($pdo, $projectId, $_SESSION['user_id'], 'project_created', "Created project: $name");
            
            // Send notifications
            $bdmUser = getUserInfo($pdo, $bdmId);
            $recipients = [$bdmUser];
            
            // Add assigned users to recipients
            foreach ($taskAssignments as $taskId => $userId) {
                if (!empty($userId)) {
                    $user = getUserInfo($pdo, $userId);
                    if ($user && $user['id'] != $bdmId) {
                        $recipients[] = $user;
                    }
                }
            }
            
            $variables = [
                'project_name' => $name,
                'hotel_name' => $hotelName,
                'user_name' => $bdmUser['name']
            ];
            
            sendNotificationEmail($pdo, 'project_created', $recipients, $variables);
            
            $pdo->commit();
            $success = 'Project created successfully!';
            
            // Store redirect URL for JavaScript redirect
            $redirectUrl = "/projects/view.php?id=$projectId";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error creating project: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create New Project</h1>
        <a href="/projects/" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Projects
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php if (isset($redirectUrl)): ?>
        <script>
            setTimeout(function() {
                window.location.href = '<?php echo $redirectUrl; ?>';
            }, 2000);
        </script>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="card shadow">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Hotel Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hotel_name" class="form-label">Hotel Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="hotel_name" name="hotel_name" required
                                   value="<?php echo htmlspecialchars($_POST['hotel_name'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="bdm_id" class="form-label">Assigned BDM <span class="text-danger">*</span></label>
                    <select class="form-select" id="bdm_id" name="bdm_id" required>
                        <option value="">Select BDM</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($_POST['bdm_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <h5 class="mb-3">Task Assignments</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Description</th>
                                <th>Assign To</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($predefinedTasks as $task): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($task['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                                    <td>
                                        <select class="form-select" name="task_assignments[<?php echo $task['id']; ?>]">
                                            <option value="">Select User</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" 
                                                    <?php echo ($_POST['task_assignments'][$task['id']] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="date" class="form-control" 
                                               name="task_due_dates[<?php echo $task['id']; ?>]"
                                               value="<?php echo htmlspecialchars($_POST['task_due_dates'][$task['id']] ?? ''); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="/projects/" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>