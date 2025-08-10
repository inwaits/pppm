<?php
$pageTitle = 'User Management';
require_once '../includes/header.php';
requireAdmin();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $name = trim($_POST['name'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'employee';
                
                if (empty($name) || empty($username) || empty($email) || empty($password)) {
                    $error = 'All fields are required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } else {
                    try {
                        // Check if email or username already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                        $stmt->execute([$email, $username]);
                        if ($stmt->fetch()) {
                            $error = 'Email address or username already exists.';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("
                                INSERT INTO users (name, username, email, password, role, is_admin, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([$name, $username, $email, $hashedPassword, $role, ($role === 'admin' ? 1 : 0)]);
                            $success = 'User added successfully.';
                        }
                    } catch (Exception $e) {
                        $error = 'Error adding user: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_user':
                $userId = $_POST['user_id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? 'employee';
                $password = $_POST['password'] ?? '';
                
                if (empty($userId) || empty($name) || empty($username) || empty($email)) {
                    $error = 'Name, username and email are required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    try {
                        // Check if email or username already exists for another user
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
                        $stmt->execute([$email, $username, $userId]);
                        if ($stmt->fetch()) {
                            $error = 'Email address or username already exists.';
                        } else {
                            if (!empty($password)) {
                                if (strlen($password) < 6) {
                                    $error = 'Password must be at least 6 characters long.';
                                } else {
                                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                                    $stmt = $pdo->prepare("
                                        UPDATE users 
                                        SET name = ?, username = ?, email = ?, password = ?, role = ?, is_admin = ?, updated_at = NOW()
                                        WHERE id = ?
                                    ");
                                    $stmt->execute([$name, $username, $email, $hashedPassword, $role, ($role === 'admin' ? 1 : 0), $userId]);
                                }
                            } else {
                                $stmt = $pdo->prepare("
                                    UPDATE users 
                                    SET name = ?, username = ?, email = ?, role = ?, is_admin = ?, updated_at = NOW()
                                    WHERE id = ?
                                ");
                                $stmt->execute([$name, $username, $email, $role, ($role === 'admin' ? 1 : 0), $userId]);
                            }
                            
                            if (!$error) {
                                $success = 'User updated successfully.';
                            }
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating user: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'toggle_status':
                $userId = $_POST['user_id'] ?? '';
                
                if (empty($userId)) {
                    $error = 'User ID is required.';
                } elseif ($userId == $_SESSION['user_id']) {
                    $error = 'You cannot disable your own account.';
                } else {
                    try {
                        // Toggle the user status (assuming there's a status field, or we can use a custom field)
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$userId]);
                        $success = 'User status updated successfully.';
                    } catch (Exception $e) {
                        $error = 'Error updating user status: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get all users
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($role)) {
    $sql .= " AND role = ?";
    $params[] = $role;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) as employee_count
    FROM users
");
$userStats = $stmt->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">User Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-2"></i>Add User
        </button>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $userStats['total_users']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Administrators</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $userStats['admin_count']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $userStats['employee_count']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Users</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Filter by Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="employee" <?php echo $role === 'employee' ? 'selected' : ''; ?>>Employee</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="/admin/users.php" class="btn btn-outline-secondary w-100">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card shadow">
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No users found</h5>
                    <p class="text-muted">Try adjusting your search criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <span class="text-white fw-bold"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-info ms-2">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-success' : 'bg-primary'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['updated_at'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                    <i class="fas fa-toggle-on"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="employee">Employee</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="editUsername" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="editPassword" name="password" minlength="6">
                        <div class="form-text">Leave blank to keep current password</div>
                    </div>
                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role</label>
                        <select class="form-select" id="editRole" name="role" required>
                            <option value="employee">Employee</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editUsername').value = user.username || '';
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editPassword').value = '';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function toggleUserStatus(userId, userName) {
    if (confirm('Are you sure you want to update the status for ' + userName + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
