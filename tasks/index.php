<?php
$pageTitle = 'My Tasks';
require_once '../includes/header.php';

$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get user's tasks
$tasks = getUserTasks($pdo, $_SESSION['user_id'], $status ?: null, $search ?: null);

// Get task counts for filters
$taskCounts = getTaskStatusCounts($pdo, $_SESSION['user_id']);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0" style="color: white;">My Tasks</h1>
    </div>
    
    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label style="color:white;" for="status" class="form-label">Filter by Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>
                            Pending (<?php echo $taskCounts['Pending']; ?>)
                        </option>
                        <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>
                            In Progress (<?php echo $taskCounts['In Progress']; ?>)
                        </option>
                        <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>
                            Completed (<?php echo $taskCounts['Completed']; ?>)
                        </option>
                        <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>
                            Rejected (<?php echo $taskCounts['Rejected']; ?>)
                        </option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label style="color:white;" for="search" class="form-label">Search</label>
                    <input style="color:white;" type="text" class="form-control" id="search" name="search" 
                           placeholder="Search tasks, projects, or hotels..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tasks List -->
    <div style="background-color:#111827;box-shadow:none;" class="card shadow">
        <div class="card-body">
            <?php if (empty($tasks)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <h5 style="color:white;" class="text-muted">No tasks found</h5>
                    <p style="color:white;" class="text-muted">
                        <?php if ($status || $search): ?>
                            Try adjusting your filters to see more tasks.
                        <?php else: ?>
                            You haven't been assigned any tasks yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($status || $search): ?>
                        <a href="/tasks/" class="btn btn-primary">Clear Filters</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        // Calculate due date status
                        $dueDateClass = '';
                        $dueDateIcon = 'fas fa-calendar';
                        $dueDateText = '';
                        
                        if ($task['due_date']) {
                            $dueDate = new DateTime($task['due_date']);
                            $today = new DateTime();
                            
                            // Set both dates to midnight for accurate comparison
                            $dueDate->setTime(23, 59, 59);
                            $today->setTime(0, 0, 0);
                            
                            $daysDiff = $today->diff($dueDate)->days;
                            $isOverdue = $dueDate < $today;
                            $isDueSoon = !$isOverdue && $daysDiff <= 3;
                            
                            if ($isOverdue && $task['status'] !== 'Completed') {
                                $dueDateClass = 'text-danger';
                                $dueDateIcon = 'fas fa-exclamation-triangle';
                                $dueDateText = 'Overdue: ' . date('M j, Y', strtotime($task['due_date']));
                            } elseif ($isDueSoon && $task['status'] !== 'Completed') {
                                $dueDateClass = 'text-warning';
                                $dueDateIcon = 'fas fa-clock';
                                $dueDateText = 'Due: ' . date('M j, Y', strtotime($task['due_date']));
                            } else {
                                $dueDateClass = 'text-muted';
                                $dueDateText = 'Due: ' . date('M j, Y', strtotime($task['due_date']));
                            }
                        }
                        ?>
                        
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100" style="border-radius: 8px; border: 1px solid #2d3748; box-shadow: none;">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 style="color:white;" class="card-title mb-0"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                        <div class="d-flex flex-column align-items-end">
                                            <span class="badge <?php echo getStatusBadgeClass($task['status']); ?> mb-1"><?php echo $task['status']; ?></span>
                                            <?php if ($task['due_date']): ?>
                                                <?php
                                                $dueDate = new DateTime($task['due_date']);
                                                $today = new DateTime();
                                                
                                                // Set both dates to midnight for accurate comparison
                                                $dueDate->setTime(23, 59, 59);
                                                $today->setTime(0, 0, 0);
                                                
                                                $isOverdue = $dueDate < $today;
                                                $isDueSoon = !$isOverdue && $today->diff($dueDate)->days <= 3;
                                                
                                                if ($isOverdue && $task['status'] !== 'Completed'): ?>
                                                    <small class="badge bg-danger">Overdue</small>
                                                <?php elseif ($isDueSoon && $task['status'] !== 'Completed'): ?>
                                                    <small class="badge bg-warning text-light">Due Soon</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <p style="color: white;" class="card-text small mb-2">
                                        <i style="color: white;" class="fas fa-hotel me-1"></i><?php echo htmlspecialchars($task['hotel_name']); ?>
                                    </p>
                                    
                                    <?php if ($task['due_date']): ?>
                                        <p class="card-text small <?php echo $dueDateClass; ?> mb-2" style="<?php echo $dueDateClass === 'text-muted' ? 'color:#d1d5db;' : ''; ?>">
                                            <i class="<?php echo $dueDateIcon; ?> me-1"></i><?php echo $dueDateText; ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="card-text small text-muted mb-2" style="color:#d1d5db;">
                                            <i class="fas fa-calendar me-1"></i>No due date set
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['task_description']): ?>
                                        <p style="color: white;" class="card-text small">
                                            <?php echo htmlspecialchars(substr($task['task_description'], 0, 100)); ?><?php echo strlen($task['task_description']) > 100 ? '...' : ''; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small style="color: #9ca3af;">
                                            Created <?php echo date('M j, Y', strtotime($task['created_at'])); ?>
                                        </small>
                                        <a href="/tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary">
                                            View <i class="fas fa-arrow-right ms-1"></i>
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

<?php require_once '../includes/footer.php'; ?>
