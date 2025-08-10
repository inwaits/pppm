<?php
$pageTitle = 'Dashboard';
$additionalJS = ['/assets/js/dashboard.js'];
require_once 'includes/header.php';

$stats = getProjectStats($pdo);
$taskCounts = getTaskStatusCounts($pdo, isAdmin() ? null : $_SESSION['user_id']);

// Get recent activities
$activitySql = "
    SELECT pal.*, p.name as project_name, p.hotel_name, u.name as user_name
    FROM project_activity_logs pal
    JOIN projects p ON pal.project_id = p.id
    JOIN users u ON pal.user_id = u.id
";

if (!isAdmin()) {
    $activitySql .= " WHERE p.id IN (
        SELECT DISTINCT pt.project_id 
        FROM project_tasks pt 
        WHERE pt.assigned_to = ? 
        UNION 
        SELECT DISTINCT p2.id 
        FROM projects p2 
        WHERE p2.bdm_id = ?
    )";
}

$activitySql .= " ORDER BY pal.created_at DESC LIMIT 10";

if (!isAdmin()) {
    $stmt = $pdo->prepare($activitySql);
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare($activitySql);
    $stmt->execute();
}
$recentActivities = $stmt->fetchAll();

// Get my tasks (for regular users) or recent tasks (for admin)
if (!isAdmin()) {
    $myTasks = getUserTasks($pdo, $_SESSION['user_id']);
    $myTasks = array_slice($myTasks, 0, 5); // Limit to 5
} else {
    $stmt = $pdo->prepare("
        SELECT pt.*, p.name as project_name, p.hotel_name, pdt.name as task_name, u.name as assigned_user_name
        FROM project_tasks pt
        JOIN projects p ON pt.project_id = p.id
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id
        LEFT JOIN users u ON pt.assigned_to = u.id
        WHERE pt.status IN ('Pending', 'In Progress')
        ORDER BY pt.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $myTasks = $stmt->fetchAll();
}
?>

<style>
/* Dark Theme Base */
body {
    background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
    color: #e4e6ea;
    min-height: 100vh;
}

.container-fluid {
    background: transparent;
}

/* Header Styling */
.dashboard-header {
    background: rgba(255, 255, 255, 0.02);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.dashboard-header h1 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    margin: 0;
}

.welcome-text {
    color: #a8b2d1;
    font-weight: 500;
}

/* Stats Cards */
.stats-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--accent-gradient);
}

.stats-card.primary::before {
    --accent-gradient: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.stats-card.success::before {
    --accent-gradient: linear-gradient(90deg, #56ab2f 0%, #a8e6cf 100%);
}

.stats-card.info::before {
    --accent-gradient: linear-gradient(90deg, #00c6ff 0%, #0072ff 100%);
}

.stats-card.warning::before {
    --accent-gradient: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
}

.stats-card:hover {
    transform: translateY(-8px);
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.stats-card .card-body {
    padding: 2rem 1.5rem;
}

.stat-label {
    color: #a8b2d1;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
}

.stat-value {
    color: #ffffff;
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.7);
}

/* Main Content Cards */
.content-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    transition: all 0.3s ease;
    height: 100%;
}

.content-card:hover {
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.card-header {
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px 20px 0 0 !important;
    padding: 1.5rem;
}

.card-header h6 {
    color: #ffffff;
    font-weight: 600;
    margin: 0;
    font-size: 1.1rem;
}

.card-body {
    padding: 1.5rem;
    background: transparent;
}

/* Chart Containers */
.chart-container {
    background: rgba(255, 255, 255, 0.02);
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
}

/* Task Items */
.task-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.task-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
    transform: translateX(4px);
}

.task-title {
    color: #ffffff;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.task-subtitle {
    color: #a8b2d1;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.task-meta {
    color: #64748b;
    font-size: 0.8rem;
}

.task-meta i {
    margin-right: 0.25rem;
    color: #94a3b8;
}

/* Activity Items */
.activity-item {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.activity-item:hover {
    background: rgba(255, 255, 255, 0.05);
}

.activity-avatar {
    background: #ffffff;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.activity-content p {
    color: #e4e6ea;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.activity-content strong {
    color: #ffffff;
    font-weight: 600;
}

.activity-meta {
    color: #94a3b8;
    font-size: 0.8rem;
}

/* Badges */
.badge {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge.bg-success, .badge.badge-success {
    background: #28a745 !important;
    color: #ffffff !important;
    border: none;
}

.badge.bg-warning, .badge.badge-warning {
    background: #ffc107 !important;
    color: #212529 !important;
    border: none;
}

.badge.bg-danger, .badge.badge-danger {
    background: #dc3545 !important;
    color: #ffffff !important;
    border: none;
}

.badge.bg-info, .badge.badge-info {
    background: #17a2b8 !important;
    color: #ffffff !important;
    border: none;
}

.badge.bg-secondary, .badge.badge-secondary {
    background: #6c757d !important;
    color: #ffffff !important;
    border: none;
}

.badge.bg-primary, .badge.badge-primary {
    background: #007bff !important;
    color: #ffffff !important;
    border: none;
}

/* Buttons */
.btn-primary {
    background: #ffffff;
    color: #1a1a2e;
    border: none;
    border-radius: 12px;
    padding: 0.5rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    background: #f8f9fa;
    color: #1a1a2e;
}

.btn-sm {
    font-size: 0.8rem;
    padding: 0.4rem 1rem;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #94a3b8;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Scrollbar Styling */
.card-body::-webkit-scrollbar {
    width: 6px;
}

.card-body::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .dashboard-header {
        text-align: center;
    }
    
    .stat-value {
        font-size: 2rem;
    }
    
    .stats-card .card-body {
        padding: 1.5rem 1rem;
    }
}

/* Animation for page load */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stats-card, .content-card {
    animation: fadeInUp 0.6s ease forwards;
}

.stats-card:nth-child(1) { animation-delay: 0.1s; }
.stats-card:nth-child(2) { animation-delay: 0.2s; }
.stats-card:nth-child(3) { animation-delay: 0.3s; }
.stats-card:nth-child(4) { animation-delay: 0.4s; }
</style>

<div class="container-fluid">
    <div class="dashboard-header d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">Dashboard</h1>
        <span class="welcome-text">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>!</span>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="stat-label">Total Projects</div>
                            <div class="stat-value"><?php echo $stats['total_projects']; ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="stat-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="stat-label">Active Projects</div>
                            <div class="stat-value"><?php echo $stats['active_projects']; ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="stat-icon">
                                <i class="fas fa-play"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="stat-label">Total Tasks</div>
                            <div class="stat-value"><?php echo $stats['total_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="stat-label">Completed Tasks</div>
                            <div class="stat-value"><?php echo $stats['completed_tasks']; ?></div>
                        </div>
                        <div class="col-auto">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart Section -->
    <div class="row mb-4">
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card content-card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Task Status Overview</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="taskStatusChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card content-card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Task Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="taskDistributionChart" width="300" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tasks and Activity Section -->
    <div class="row">
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card content-card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <?php echo isAdmin() ? 'Recent Tasks' : 'My Recent Tasks'; ?>
                    </h6>
                    <a href="/tasks/" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body" style="min-height: 400px; overflow-y: auto;">
                    <?php if (empty($myTasks)): ?>
                        <div class="empty-state d-flex align-items-center justify-content-center h-100 flex-column">
                            <i class="fas fa-tasks"></i>
                            <p class="text-muted text-center">No tasks found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($myTasks as $task): ?>
                            <div class="task-item">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="task-title"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                        <p class="task-subtitle"><?php echo htmlspecialchars($task['hotel_name']); ?></p>
                                        <?php if (isset($task['assigned_user_name'])): ?>
                                            <small class="task-meta">Assigned to: <?php echo htmlspecialchars($task['assigned_user_name']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($task['due_date']): ?>
                                            <div class="mt-1">
                                                <small class="task-meta">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo getStatusBadgeClass($task['status']); ?> mb-2"><?php echo $task['status']; ?></span>
                                        <?php if ($task['due_date']): ?>
                                            <br>
                                            <?php 
                                            $daysLeft = ceil((strtotime($task['due_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24));
                                            if ($daysLeft < 0 && $task['status'] !== 'Completed'): ?>
                                                <small class="badge bg-danger">Overdue</small>
                                            <?php elseif ($daysLeft >= 0 && $daysLeft <= 3 && $task['status'] !== 'Completed'): ?>
                                                <small class="badge bg-warning"><?php echo $daysLeft == 0 ? 'Due Today' : $daysLeft . ' days left'; ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card content-card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Recent Activity</h6>
                </div>
                <div class="card-body" style="min-height: 400px; overflow-y: auto;">
                    <?php if (empty($recentActivities)): ?>
                        <div class="empty-state d-flex align-items-center justify-content-center h-100 flex-column">
                            <i class="fas fa-clock"></i>
                            <p class="text-muted text-center">No recent activity.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item d-flex align-items-start">
                                <div class="activity-avatar flex-shrink-0">
                                    <i class="fas fa-user" style="font-size: 14px; color: #1a1a2e;"></i>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <p>
                                        <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong> 
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </p>
                                    <small class="activity-meta">
                                        <i class="fas fa-hotel"></i>
                                        <?php echo htmlspecialchars($activity['hotel_name']); ?> 
                                        <span class="mx-1">•</span> 
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Pass PHP data to JavaScript
window.dashboardData = {
    taskCounts: <?php echo json_encode($taskCounts); ?>
};
</script>

<?php require_once 'includes/footer.php'; ?>