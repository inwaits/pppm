<?php
require_once '../includes/header.php';

$projectId = $_GET['id'] ?? 0;

if (!$projectId || !canAccessProject($pdo, $_SESSION['user_id'], $projectId)) {
    header('Location: /projects/');
    exit;
}

// Get project details
$stmt = $pdo->prepare("
    SELECT p.*, u.name as bdm_name, u.email as bdm_email
    FROM projects p
    LEFT JOIN users u ON p.bdm_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: /projects/');
    exit;
}

$pageTitle = $project['name'];

// Get project tasks
$tasks = getProjectTasks($pdo, $projectId, $_SESSION['user_id']);

$tasksforall = getProjectTasks($pdo, $projectId);

// Get project stats
$taskCounts = getTaskStatusCounts($pdo, null, $projectId);

// Get recent activity for this project
$stmt = $pdo->prepare("
    SELECT pal.*, u.name as user_name
    FROM project_activity_logs pal
    JOIN users u ON pal.user_id = u.id
    WHERE pal.project_id = ?
    ORDER BY pal.created_at DESC
    LIMIT 10
");
$stmt->execute([$projectId]);
$recentActivities = $stmt->fetchAll();
?>

<style>
    /* ===== Dark Theme Accordion Styles ===== */

/* Accordion container */
.accordion-item {
    background-color: #1e1e2f; /* Dark surface */
    border: 1px solid #2c2c3a;
    border-radius: 8px;
    margin-bottom: 8px;
    overflow: hidden;
}

/* Accordion button (header) */
.accordion-button {
    background-color: #1e1e2f;
    color: #e4e6eb; /* Light text */
    font-weight: 500;
    padding: 1rem;
    border: none;
    outline: none;
    transition: background-color 0.2s ease;
}

/* On hover */
.accordion-button:hover {
    background-color: #29293d;
}

/* On expand */
.accordion-button:not(.collapsed) {
    background-color: #252539;
    color: #ffffff;
    box-shadow: none;
}

/* Remove default Bootstrap arrow icon and make custom */
.accordion-button::after {
    background-image: none;
    content: '\25BC'; /* Down arrow */
    font-size: 0.8rem;
    color: #aaa;
    transform: rotate(0deg);
    transition: transform 0.2s ease;
}

/* Rotate arrow when expanded */
.accordion-button:not(.collapsed)::after {
    transform: rotate(180deg);
    color: #fff;
}

/* Accordion body */
.accordion-body {
    background-color: #202030;
    color: #dcdde1;
    padding: 1rem 1.25rem;
}

/* Badge styles */
.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.6em;
    border-radius: 6px;
}

/* Info alert box in dark mode */
.alert-info {
    background-color: rgba(0, 173, 255, 0.15);
    color: #87cefa;
    border: 1px solid rgba(0, 173, 255, 0.3);
}

/* Buttons inside accordion */
.accordion-body .btn-primary {
    background-color: #4f8cff;
    border-color: #4f8cff;
    color: #fff;
}

.accordion-body .btn-primary:hover {
    background-color: #3d73d9;
    border-color: #3d73d9;
}

</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo htmlspecialchars($project['hotel_name']); ?></h1>
        </div>
        <div>
            <a href="/projects/" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Projects
            </a>
            <?php if (isAdmin()): ?>
                <a href="/projects/edit.php?id=<?php echo $project['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i>Edit Project
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Project Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Project Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div style="color: white;" class="col-md-6">
                            <p><strong>BDM:</strong> <?php echo htmlspecialchars($project['bdm_name']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge <?php echo $project['status'] === 'Active' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo $project['status']; ?>
                                </span>
                            </p>
                        </div>
                        <div style="color: white;" class="col-md-6">
                            <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($project['created_at'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($project['updated_at'])); ?></p>
                        </div>
                    </div>
                    <?php if ($project['description']): ?>
                        <hr>
                        <p style="color: white;"><strong>Description:</strong></p>
                        <p style="color: white;"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Task Summary</h6>
                </div>
                <div  class="card-body">
                    <?php
                    $totalTasks = array_sum($taskCounts);
                    $completedTasks = $taskCounts['Completed'];
                    $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
                    ?>
                    <div class="text-center mb-3">
                        <h3 class="text-primary"><?php echo $progress; ?>%</h3>
                        <p style="color: white;" class="mb-0">Overall Progress</p>
                    </div>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <small style="color: white;">Pending</small>
                            <div class="h5 text-warning"><?php echo $taskCounts['Pending']; ?></div>
                        </div>
                        <div class="col-6">
                            <small style="color: white;">In Progress</small>
                            <div class="h5 text-info"><?php echo $taskCounts['In Progress']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
<div class="col-lg-12" style="margin:20px 1px;">
    <div class="card shadow" style="background-color: #2d3748; border: none;">
        <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #2d3748; border-bottom: 1px solid #4a5568;">
            <h6 class="m-0 font-weight-bold text-white">Tasks</h6>
            <span class="badge bg-primary"><?php echo count($tasksforall); ?> tasks</span>
        </div>
        <div class="card-body p-0" style="background-color: #2d3748;">
            <?php if (empty($tasksforall)): ?>
                <p class="text-muted text-center py-4">No tasks found for this project.</p>
            <?php else: ?>
                <style>
                .task-table {
                    background-color: #2a2b2d;
                    border-collapse: separate;
                    border-spacing: 0;
                    width: 100%;
                }
                .task-table thead th {
                    background-color: #2d3748;
                    color: #a0aec0;
                    font-weight: 500;
                    font-size: 0.875rem;
                    padding: 1rem;
                    border-bottom: 1px solid #4a5568;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
                .task-status-group {
                    background-color: #374151;
                    color: #e2e8f0;
                    font-weight: 600;
                    padding: 0.75rem 1rem;
                    border-bottom: 1px solid #4a5568;
                    position: relative;
                }
                .task-status-group:before {
                    content: '▼';
                    margin-right: 0.5rem;
                    font-size: 0.75rem;
                }
                .task-row {
                    background-color: #2d3748;
                    border-bottom: 1px solid #4a5568;
                    transition: background-color 0.2s;
                }
                .task-row:hover {
                    background-color: #374151;
                }
                .task-row td {
                    padding: 1rem;
                    color: #e2e8f0;
                    vertical-align: middle;
                    border: none;
                }
                .task-checkbox {
                    width: 18px;
                    height: 18px;
                    border: 2px solid #4a5568;
                    border-radius: 50%;
                    background-color: transparent;
                    margin-right: 0.75rem;
                    flex-shrink: 0;
                }
                .task-checkbox.completed {
                    background-color: #10b981;
                    border-color: #10b981;
                }
                .task-name {
                    font-weight: 500;
                    color: #f7fafc;
                }
                .assignee-avatar {
                    width: 28px;
                    height: 28px;
                    border-radius: 50%;
                    background-color: #4c51bf;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 0.75rem;
                    font-weight: 600;
                    margin-right: 0.5rem;
                }
                .status-badge {
                    padding: 0.25rem 0.75rem;
                    border-radius: 0.375rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                }
                .status-pending { background-color: #ed8936; color: #1a202c; }
                .status-in-progress { background-color: #3182ce; color: white; }
                .status-completed { background-color: #48bb78; color: white; }
                .status-on-hold { background-color: #a0aec0; color: #1a202c; }
                .due-date {
                    color: #a0aec0;
                    font-size: 0.875rem;
                }
                .due-overdue { color: #f56565; }
                .due-soon { color: #ed8936; }
                </style>

                <?php
                // Group tasks by status
                $groupedTasks = [];
                foreach ($tasksforall as $task) {
                    $status = $task['status'] ?? 'No Status';
                    $groupedTasks[$status][] = $task;
                }
                ?>

                <table class="task-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Task Name</th>
                            <th style="width: 20%;">Assigned To</th>
                            <th style="width: 20%;">Due Date</th>
                            <th style="width: 20%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupedTasks as $status => $tasks): ?>
                            <tr>
                                <td colspan="5" class="task-status-group"><?php echo htmlspecialchars($status); ?></td>
                            </tr>
                            <?php foreach ($tasks as $task): ?>
                                <tr class="task-row">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="task-checkbox <?php echo ($task['status'] === 'Completed') ? 'completed' : ''; ?>"></div>
                                            <span class="task-name"><?php echo htmlspecialchars($task['task_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($task['assigned_user_name']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="assignee-avatar">
                                                    <?php 
                                                    $nameParts = explode(' ', $task['assigned_user_name']);
                                                    $initials = '';
                                                    foreach ($nameParts as $part) {
                                                        $initials .= substr($part, 0, 1);
                                                    }
                                                    echo strtoupper(substr($initials, 0, 2));
                                                    ?>
                                                </div>
                                                <span><?php echo htmlspecialchars($task['assigned_user_name']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($task['due_date'])): ?>
                                            <?php 
                                                $dueDate = new DateTime($task['due_date']);
                                                $now = new DateTime();
                                                $isOverdue = $now > $dueDate;
                                                $interval = $now->diff($dueDate);
                                                $isDueSoon = !$isOverdue && $interval->days <= 3;
                                            ?>
                                            <span class="due-date <?php echo $isOverdue ? 'due-overdue' : ($isDueSoon ? 'due-soon' : ''); ?>">
                                                <?php echo $dueDate->format('j M'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = 'status-' . strtolower(str_replace([' ', '_'], '-', $task['status']));
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($task['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
    
    <!-- Tasks -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Tasks</h6>
                    <span class="badge bg-primary"><?php echo count($tasks); ?> tasks</span>
                </div>
                <div class="card-body">
                    <?php if (empty($tasks)): ?>
                        <p class="text-muted text-center py-4">No tasks found for this project.</p>
                    <?php else: ?>
                        <div class="accordion" id="tasksAccordion">
                            <?php foreach ($tasks as $index => $task): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?php echo $task['id']; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?php echo $task['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($task['task_name']); ?></strong>
                                                    <?php if ($task['assigned_user_name']): ?>
                                                        <br><small class="text-muted">Assigned to: <?php echo htmlspecialchars($task['assigned_user_name']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge <?php echo getStatusBadgeClass($task['status']); ?>"><?php echo $task['status']; ?></span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $task['id']; ?>" class="accordion-collapse collapse" 
                                         data-bs-parent="#tasksAccordion">
                                        <div class="accordion-body">
                                            <p><?php echo htmlspecialchars($task['task_description']); ?></p>
                                            
                                            <?php if ($task['notes']): ?>
                                                <div class="alert alert-info">
                                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($task['notes'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <a href="/tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a>
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
        
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 style="color: white;" class="m-0 font-weight-bold">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivities)): ?>
                        <p class="text-muted text-center py-3">No recent activity.</p>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="d-flex align-items-start mb-3">
                                <div class="flex-shrink-0">
                                    <i style="color: white;" class="fas fa-circle" style="font-size: 0.5rem; margin-top: 0.5rem;"></i>
                                </div>
                                <div style="color: white;" class="flex-grow-1 ms-3">
                                    <p class="mb-1"><strong><?php echo htmlspecialchars($activity['user_name']); ?></strong> <?php echo htmlspecialchars($activity['description']); ?></p>
                                    <small style="color: #707070;"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
