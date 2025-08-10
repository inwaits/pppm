<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="/assets/logo.png" width="180px">
    </div>
    
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="/dashboard.php">
                    <i class="fas fa-chart-line me-2"></i>Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/projects/') === 0 ? 'active' : ''; ?>" href="/projects/">
                    <i class="fas fa-project-diagram me-2"></i>Projects
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/tasks/') === 0 ? 'active' : ''; ?>" href="/tasks/">
                    <i class="fas fa-tasks me-2"></i>My Tasks
                </a>
            </li>
            
            <?php if (isAdmin()): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cog me-2"></i>Administration
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="/admin/predefined-tasks.php"><i class="fas fa-list me-2"></i>Task Templates</a></li>
                    <li><a class="dropdown-item" href="/admin/users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                    <li><a class="dropdown-item" href="/admin/email-settings.php"><i class="fas fa-envelope me-2"></i>Email Settings</a></li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>
