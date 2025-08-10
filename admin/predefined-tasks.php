<?php
$pageTitle = 'Task Templates';
require_once '../includes/header.php';
requireAdmin();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_task':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = 'Task name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO predefined_tasks (name, description) VALUES (?, ?)");
                        $stmt->execute([$name, $description]);
                        $success = 'Task template added successfully.';
                    } catch (Exception $e) {
                        $error = 'Error adding task: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'add_subtask':
                $taskId = $_POST['task_id'] ?? '';
                $name = trim($_POST['subtask_name'] ?? '');
                $description = trim($_POST['subtask_description'] ?? '');
                
                if (empty($taskId) || empty($name)) {
                    $error = 'Task and subtask name are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO predefined_subtasks (predefined_task_id, name, description) VALUES (?, ?, ?)");
                        $stmt->execute([$taskId, $name, $description]);
                        $success = 'Subtask added successfully.';
                    } catch (Exception $e) {
                        $error = 'Error adding subtask: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_task':
                $taskId = $_POST['task_id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($taskId) || empty($name)) {
                    $error = 'Task ID and name are required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE predefined_tasks SET name = ?, description = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $taskId]);
                        $success = 'Task template updated successfully.';
                    } catch (Exception $e) {
                        $error = 'Error updating task: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_task':
                $taskId = $_POST['task_id'] ?? '';
                
                if (empty($taskId)) {
                    $error = 'Task ID is required.';
                } else {
                    try {
                        // Check if task is used in any projects
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM project_tasks WHERE predefined_task_id = ?");
                        $stmt->execute([$taskId]);
                        $result = $stmt->fetch();
                        
                        if ($result['count'] > 0) {
                            $error = 'Cannot delete task template that is being used in projects.';
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM predefined_tasks WHERE id = ?");
                            $stmt->execute([$taskId]);
                            $success = 'Task template deleted successfully.';
                        }
                    } catch (Exception $e) {
                        $error = 'Error deleting task: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get all predefined tasks with their subtasks
$stmt = $pdo->query("
    SELECT pt.*, 
           COUNT(ps.id) as subtask_count,
           COUNT(pjt.id) as usage_count
    FROM predefined_tasks pt
    LEFT JOIN predefined_subtasks ps ON pt.id = ps.predefined_task_id
    LEFT JOIN project_tasks pjt ON pt.id = pjt.predefined_task_id
    GROUP BY pt.id
    ORDER BY pt.order_position ASC, pt.created_at ASC
");
$tasks = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Task Templates</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            <i class="fas fa-plus me-2"></i>Add Task Template
        </button>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="card shadow">
        <div class="card-body">
            <?php if (empty($tasks)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No task templates found</h5>
                    <p class="text-muted">Create your first task template to get started.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        Add Task Template
                    </button>
                </div>
            <?php else: ?>
                <div class="accordion" id="tasksAccordion">
                    <?php foreach ($tasks as $task): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $task['id']; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $task['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                        <div>
                                            <strong><?php echo htmlspecialchars($task['name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $task['subtask_count']; ?> subtasks • Used in <?php echo $task['usage_count']; ?> projects</small>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $task['id']; ?>" class="accordion-collapse collapse" 
                                 data-bs-parent="#tasksAccordion">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                            
                                            <!-- Subtasks -->
                                            <h6>Subtasks:</h6>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT * FROM predefined_subtasks WHERE predefined_task_id = ? ORDER BY order_position ASC");
                                            $stmt->execute([$task['id']]);
                                            $subtasks = $stmt->fetchAll();
                                            ?>
                                            
                                            <?php if (empty($subtasks)): ?>
                                                <p class="text-muted">No subtasks defined.</p>
                                            <?php else: ?>
                                                <ul class="list-group mb-3">
                                                    <?php foreach ($subtasks as $subtask): ?>
                                                        <li class="list-group-item">
                                                            <strong><?php echo htmlspecialchars($subtask['name']); ?></strong>
                                                            <?php if ($subtask['description']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($subtask['description']); ?></small>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="addSubtask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['name']); ?>')">
                                                <i class="fas fa-plus me-1"></i>Add Subtask
                                            </button>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex flex-column gap-2">
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        onclick="editTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['name']); ?>', '<?php echo htmlspecialchars($task['description']); ?>')">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </button>
                                                <?php if ($task['usage_count'] == 0): ?>
                                                    <button class="btn btn-outline-danger btn-sm" 
                                                            onclick="deleteTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['name']); ?>')">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                <?php else: ?>
                                                    <small class="text-muted">Cannot delete: Used in projects</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Task Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_task">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Task Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Task Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" id="editTaskId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Task Name</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Subtask Modal -->
<div class="modal fade" id="addSubtaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Subtask</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_subtask">
                <input type="hidden" name="task_id" id="subtaskTaskId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task:</label>
                        <p class="fw-bold" id="subtaskTaskName"></p>
                    </div>
                    <div class="mb-3">
                        <label for="subtaskName" class="form-label">Subtask Name</label>
                        <input type="text" class="form-control" id="subtaskName" name="subtask_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="subtaskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="subtaskDescription" name="subtask_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subtask</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the task template "<span id="deleteTaskName"></span>"?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" id="deleteTaskId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editTask(id, name, description) {
    document.getElementById('editTaskId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editDescription').value = description;
    new bootstrap.Modal(document.getElementById('editTaskModal')).show();
}

function addSubtask(taskId, taskName) {
    document.getElementById('subtaskTaskId').value = taskId;
    document.getElementById('subtaskTaskName').textContent = taskName;
    document.getElementById('subtaskName').value = '';
    document.getElementById('subtaskDescription').value = '';
    new bootstrap.Modal(document.getElementById('addSubtaskModal')).show();
}

function deleteTask(id, name) {
    document.getElementById('deleteTaskId').value = id;
    document.getElementById('deleteTaskName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteTaskModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
