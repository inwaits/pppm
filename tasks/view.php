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
           u.name as assigned_user_name, u.email as assigned_user_email
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

$pageTitle = $task['task_name'];

// Get subtasks
$subtasks = getTaskSubtasks($pdo, $taskId);

// Get comments
$comments = getTaskComments($pdo, $taskId);

// Get attachments
$attachments = getTaskAttachments($pdo, $taskId);

// Check if current user can update this task
$canUpdate = isAdmin() || $task['assigned_to'] == $_SESSION['user_id'] || $task['bdm_id'] == $_SESSION['user_id'];

// Function to check if due date is overdue
function isDueOverdue($dueDate, $status) {
    if (!$dueDate || $status === 'Completed') return false;
    return strtotime($dueDate) < strtotime(date('Y-m-d'));
}

// Function to get due date badge class
function getDueDateBadgeClass($dueDate, $status) {
    if (!$dueDate) return '';
    if ($status === 'Completed') return 'badge bg-success';
    
    $today = strtotime(date('Y-m-d'));
    $due = strtotime($dueDate);
    $daysDiff = ($due - $today) / (60 * 60 * 24);
    
    if ($daysDiff < 0) return 'badge bg-danger'; // Overdue
    if ($daysDiff <= 1) return 'badge bg-warning'; // Due today or tomorrow
    if ($daysDiff <= 3) return 'badge bg-info'; // Due within 3 days
    return 'badge bg-secondary'; // Future due date
}
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo htmlspecialchars($task['task_name']); ?></h1>
            <p style="color=white;" class="mb-0">
<?php echo htmlspecialchars($task['hotel_name']); ?>
            </p>
        </div>
        <div>
            <a href="/tasks/" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Tasks
            </a>
            <a href="/projects/view.php?id=<?php echo $task['project_id']; ?>" class="btn btn-outline-primary">
                <i class="fas fa-project-diagram me-2"></i>View Project
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- Main Task Content -->
        <div class="col-lg-8">
            <!-- Task Details -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 style="color: white;"  class="m-0 font-weight-bold">Task Details</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <?php if ($task['due_date']): ?>
                            <span class="<?php echo getDueDateBadgeClass($task['due_date'], $task['status']); ?> fs-6">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                <?php if (isDueOverdue($task['due_date'], $task['status'])): ?>
                                    <i class="fas fa-exclamation-triangle ms-1" title="Overdue"></i>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <span class="badge <?php echo getStatusBadgeClass($task['status']); ?> fs-6"><?php echo $task['status']; ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p style="color: white;"><strong>Assigned to:</strong> <?php echo $task['assigned_user_name'] ? htmlspecialchars($task['assigned_user_name']) : 'Not assigned'; ?></p>
                            <p style="color: white;"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($task['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($task['due_date']): ?>
                                <p style="color: white;">
                                    <strong>Due Date:</strong> 
                                    <span class="<?php echo isDueOverdue($task['due_date'], $task['status']) ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                        <?php if (isDueOverdue($task['due_date'], $task['status'])): ?>
                                            <i class="fas fa-exclamation-triangle text-danger ms-1" title="This task is overdue"></i>
                                        <?php else: ?>
                                            <?php
                                            $daysLeft = ceil((strtotime($task['due_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24));
                                            if ($daysLeft >= 0 && $task['status'] !== 'Completed'):
                                            ?>
                                                <small style="color: grey;" class="ms-2">
                                                    (<?php echo $daysLeft == 0 ? 'Due today' : $daysLeft . ' day' . ($daysLeft > 1 ? 's' : '') . ' left'; ?>)
                                                </small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </span>
                                </p>
                            <?php else: ?>
                                <p style="color: white;"><strong>Due Date:</strong> <span>Not set</span></p>
                            <?php endif; ?>
                            <?php if ($task['completed_at']): ?>
                                <p style="color: white;"><strong>Completed:</strong> <?php echo date('M j, Y g:i A', strtotime($task['completed_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <p style="color: white;"><?php echo nl2br(htmlspecialchars($task['task_description'])); ?></p>
                    
                    <?php if ($task['notes']): ?>
                        <div class="alert alert-info">
                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($task['notes'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($canUpdate): ?>
                        <div class="mt-3">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                <i class="fas fa-edit me-2"></i>Update Status
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Subtasks -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Subtasks</h6>
                    <?php if (isAdmin()): ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSubtaskModal">
                            <i class="fas fa-plus me-1"></i>Add Subtask
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($subtasks)): ?>
                        <p style="color: white;" class="text-center py-3">No subtasks available.</p>
                    <?php else: ?>
                        <?php foreach ($subtasks as $subtask): ?>
                            <div class="d-flex align-items-center justify-content-between p-3 border rounded mb-2">
                                <div class="flex-grow-1">
                                    <h6 style="color: white;" class="mb-1"><?php echo htmlspecialchars($subtask['name']); ?></h6>
                                    <?php if ($subtask['description']): ?>
                                        <p style="color: #c2c2c2;" class="mb-0 small"><?php echo htmlspecialchars($subtask['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge <?php echo getStatusBadgeClass($subtask['status']); ?>"><?php echo $subtask['status']; ?></span>
                                    <?php if ($canUpdate): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="updateSubtaskStatus(<?php echo $subtask['id']; ?>, 'Pending')">Mark Pending</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateSubtaskStatus(<?php echo $subtask['id']; ?>, 'In Progress')">Mark In Progress</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateSubtaskStatus(<?php echo $subtask['id']; ?>, 'Completed')">Mark Completed</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="updateSubtaskStatus(<?php echo $subtask['id']; ?>, 'Rejected')">Mark Rejected</a></li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Comments -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Comments</h6>
                </div>
                <div class="card-body">
                    <!-- Add Comment Form -->
                    <form id="commentForm" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control" id="commentText" placeholder="Add a comment..." required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Comments List -->
                    <div id="commentsList">
                        <?php if (empty($comments)): ?>
                            <p style="color: white;" class="text-center py-3">No comments yet. Be the first to comment!</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <div class="avatar bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <span class="text-white fw-bold"><?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?></span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="bg-light rounded p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['message'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Task Summary -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Task Summary</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span style="color: white;">Status:</span>
                        <span class="badge <?php echo getStatusBadgeClass($task['status']); ?>"><?php echo $task['status']; ?></span>
                    </div>
                    <?php if ($task['due_date']): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span style="color: white;">Due Date:</span>
                        <span style="color: white;" class="<?php echo isDueOverdue($task['due_date'], $task['status']) ? 'text-danger fw-bold' : ''; ?>">
                            <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                            <?php if (isDueOverdue($task['due_date'], $task['status'])): ?>
                                <i class="fas fa-exclamation-triangle text-danger ms-1"></i>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div style="color: white;" class="d-flex justify-content-between align-items-center mb-2">
                        <span>Assignee:</span>
                        <span><?php echo $task['assigned_user_name'] ? htmlspecialchars($task['assigned_user_name']) : 'Unassigned'; ?></span>
                    </div>
                    <div style="color: white;" class="d-flex justify-content-between align-items-center">
                        <span>Subtasks:</span>
                        <span><?php echo count($subtasks); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- File Attachments -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Attachments</h6>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload me-1"></i>Upload
                    </button>
                </div>
                <div class="card-body">
                    <div id="attachmentsList">
                        <?php if (empty($attachments)): ?>
                            <p class="text-muted text-center py-3">No attachments yet.</p>
                        <?php else: ?>
                            <?php foreach ($attachments as $attachment): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                    <div class="flex-grow-1">
                                        <h6 style="color: white;" class="mb-1"><?php echo htmlspecialchars($attachment['file_name']); ?></h6>
                                        <small style="color: white;">
                                            <?php echo formatFileSize($attachment['file_size']); ?> • 
                                            <?php echo htmlspecialchars($attachment['uploaded_by_name']); ?> • 
                                            <?php echo date('M j, Y', strtotime($attachment['uploaded_at'])); ?>
                                        </small>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<?php if ($canUpdate): ?>
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Task Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="updateStatusForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Rejected" <?php echo $task['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($task['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Update Due Date Modal -->
<?php if ($canUpdate): ?>
<div class="modal fade" id="updateDueDateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Due Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="updateDueDateForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date" 
                               value="<?php echo $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : ''; ?>">
                        <div class="form-text">Leave empty to remove due date</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Due Date</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Subtask Modal -->
<?php if (isAdmin()): ?>
<div class="modal fade" id="addSubtaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Subtask</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubtaskForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subtaskName" class="form-label">Subtask Name</label>
                        <input type="text" class="form-control" id="subtaskName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="subtaskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="subtaskDescription" name="description" rows="3"></textarea>
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
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file" class="form-label">Choose File</label>
                        <input type="file" class="form-control" id="file" name="file" required>
                        <div style="color: white;" class="form-text">Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, TXT, ZIP (Max 10MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const taskId = <?php echo $taskId; ?>;

// Update task status
document.getElementById('updateStatusForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('task_id', taskId);
    
    fetch('/ajax/update-task-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the task status.');
    });
});

// Update due date
document.getElementById('updateDueDateForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('task_id', taskId);
    
    fetch('/ajax/update-due-date.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the due date.');
    });
});

// Add comment
document.getElementById('commentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const commentText = document.getElementById('commentText').value.trim();
    if (!commentText) return;
    
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('message', commentText);
    
    fetch('/ajax/add-comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the comment.');
    });
});

// Upload file
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('task_id', taskId);
    
    fetch('/ajax/upload-file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while uploading the file.');
    });
});

// Add subtask
document.getElementById('addSubtaskForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('task_id', taskId);
    
    fetch('/ajax/add-subtask.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the subtask.');
    });
});

// Update subtask status
function updateSubtaskStatus(subtaskId, status) {
    const formData = new FormData();
    formData.append('subtask_id', subtaskId);
    formData.append('status', status);
    
    fetch('/ajax/update-subtask-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the subtask status.');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>