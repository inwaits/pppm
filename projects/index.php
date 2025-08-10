<?php
$pageTitle = 'Projects';
require_once '../includes/header.php';

// Get projects based on user role
if (isAdmin()) {
    $stmt = $pdo->query("
        SELECT p.*, u.name as bdm_name, 
               COUNT(pt.id) as total_tasks,
               SUM(CASE WHEN pt.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM projects p
        LEFT JOIN users u ON p.bdm_id = u.id
        LEFT JOIN project_tasks pt ON p.id = pt.project_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $projects = $stmt->fetchAll();
} else {
    // For regular users and BDMs, show only projects they're involved in
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.*, u.name as bdm_name,
               COUNT(pt.id) as total_tasks,
               SUM(CASE WHEN pt.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM projects p
        LEFT JOIN users u ON p.bdm_id = u.id
        LEFT JOIN project_tasks pt ON p.id = pt.project_id
        WHERE p.bdm_id = ? OR pt.assigned_to = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $projects = $stmt->fetchAll();
}
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Projects</h1>
        <?php if (isAdmin()): ?>
            <a href="/projects/create.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Project
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($projects)): ?>
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                <h5 class="text-white">No projects found</h5>
                <?php if (isAdmin()): ?>
                    <p class="text-white">Get started by creating your first project.</p>
                    <a href="/projects/create.php" class="btn btn-primary">Create Project</a>
                <?php else: ?>
                    <p class="text-white">You haven't been assigned to any projects yet.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($projects as $project): ?>
                <?php 
                $progress = $project['total_tasks'] > 0 ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                ?>
                <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                    <div class="card shadow h-100">
                        <div  style="background-color:#111721;"  class="card-header d-flex justify-content-between align-items-center">
                            <h5 style="color:white;" class="mb-0 text-truncate" title="<?php echo htmlspecialchars($project['hotel_name']); ?>">
                                <?php echo htmlspecialchars($project['hotel_name']); ?>
                            </h5>
                            <span class="badge <?php echo $project['status'] === 'Active' ? 'bg-success' : ($project['status'] === 'Completed' ? 'bg-primary' : 'bg-warning'); ?>">
                                <?php echo $project['status']; ?>
                            </span>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <?php if ($project['description']): ?>
                                <p style="color: white;" class="small mb-3" title="<?php echo htmlspecialchars($project['description']); ?>">
                                    <?php echo htmlspecialchars(substr($project['description'], 0, 120)); ?><?php echo strlen($project['description']) > 120 ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <i style="color: white;" class="fas fa-calendar-alt"></i>
                                        <p style="color: white;" class="mb-0 small"><strong>Hotel start date</strong></p>
                                        <p style="color: white;" class="mb-0 text-truncate" title="<?php echo htmlspecialchars($project['name']); ?>">
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <i style="color: white;" class="fas fa-user"></i>
                                        <p style="color: white;" class="mb-0 small"><strong>BDM</strong></p>
                                        <p style="color: white;" class="mb-0 text-truncate" title="<?php echo htmlspecialchars($project['bdm_name']); ?>">
                                            <?php echo htmlspecialchars($project['bdm_name']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small style="color: white;" ><strong>Progress</strong></small>
                                    <small style="color: white;" ><?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?> tasks</small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%" 
                                         aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small style="color: white;"><?php echo $progress; ?>% complete</small>
                            </div>
                            
                            <div class="mt-auto">
                                <small style="color:#9ca3af;" class="d-block mb-3">
                                    <i style="color: #9ca3af;" class="fas fa-calendar-alt me-1"></i>
                                    Created: <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                </small>
                                
                                <div class="d-flex gap-2">
                                    <a href="/projects/view.php?id=<?php echo $project['id']; ?>" 
                                       class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <?php if (isAdmin()): ?>
                                        <a href="/projects/edit.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>