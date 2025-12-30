<?php
/**
 * Users List - User Management
 * View and manage all system users
 * 
 * @version 2.0 FINAL
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

// Check permission
Permission::require('users', 'view');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Filters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$where = ["1=1"];
$params = [];
$types = "";

if ($roleFilter) {
    $where[] = "u.role_id = ?";
    $params[] = $roleFilter;
    $types .= "i";
}

if ($statusFilter) {
    if ($statusFilter === 'active') {
        $where[] = "u.is_active = 1";
    } else {
        $where[] = "u.is_active = 0";
    }
}

if ($searchQuery) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.user_code LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

$whereClause = implode(" AND ", $where);

$query = "
    SELECT 
        u.*,
        r.role_name,
        r.role_code,
        (SELECT COUNT(*) FROM candidates WHERE assigned_to = u.user_code AND deleted_at IS NULL) as candidate_count,
        (SELECT COUNT(*) FROM jobs WHERE assigned_to = u.user_code AND deleted_at IS NULL) as job_count
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE {$whereClause}
    ORDER BY u.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get roles for filter
$rolesQuery = "SELECT id, role_name FROM roles ORDER BY role_name";
$roles = $conn->query($rolesQuery);

// Page config
$pageTitle = 'User Management';
$breadcrumbs = [
    ['title' => 'Users', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-user text-primary me-2"></i>
                User Management
            </h4>
            <p class="text-muted mb-0">Manage system users, roles, and permissions</p>
        </div>
        
        <div class="d-flex gap-2">
            <?php if (Permission::can('users', 'manage_permissions')): ?>
            <a href="permissions.php" class="btn btn-outline-primary">
                <i class="bx bx-key me-1"></i> Manage Permissions
            </a>
            <?php endif; ?>
            
            <?php if (Permission::can('users', 'create')): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Add New User
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                
                <!-- Search -->
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, or user code..." 
                           value="<?= escape($searchQuery) ?>">
                </div>
                
                <!-- Role Filter -->
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <?php $roles->data_seek(0); while ($role = $roles->fetch_assoc()): ?>
                            <option value="<?= $role['id'] ?>" <?= $roleFilter == $role['id'] ? 'selected' : '' ?>>
                                <?= escape($role['role_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <!-- Actions -->
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bx bx-search"></i>
                        </button>
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="bx bx-x"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            
            <?php if ($users->num_rows === 0): ?>
                
                <div class="text-center py-5">
                    <i class="bx bx-user display-1 text-muted"></i>
                    <h5 class="mt-3">No Users Found</h5>
                    <p class="text-muted">
                        <?php if ($searchQuery || $roleFilter || $statusFilter): ?>
                            No users match your filters. <a href="list.php">Clear filters</a>
                        <?php else: ?>
                            Start by adding your first user.
                        <?php endif; ?>
                    </p>
                    <?php if (Permission::can('users', 'create')): ?>
                    <a href="create.php" class="btn btn-primary mt-2">
                        <i class="bx bx-plus me-1"></i> Add First User
                    </a>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Assigned Items</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($userRow = $users->fetch_assoc()): ?>
                        <tr>
                            
                            <!-- User Info -->
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm bg-label-primary me-3">
                                        <span class="avatar-initial rounded-circle">
                                            <?= strtoupper(substr($userRow['name'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <strong><?= escape($userRow['name']) ?></strong>
                                        <?php if ($userRow['user_code'] === Auth::userCode()): ?>
                                            <span class="badge badge-sm bg-info ms-1">You</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?= escape($userRow['email']) ?></small>
                                        <br>
                                        <small class="text-muted"><?= escape($userRow['user_code']) ?></small>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Role -->
                            <td>
                                <span class="badge bg-label-primary">
                                    <?= escape($userRow['role_name'] ?: $userRow['level']) ?>
                                </span>
                            </td>
                            
                            <!-- Status -->
                            <td>
                                <?php if ($userRow['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                                
                                <?php if ($userRow['locked_until'] && strtotime($userRow['locked_until']) > time()): ?>
                                    <br><span class="badge bg-danger mt-1">Locked</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Assigned Items -->
                            <td>
                                <div class="small">
                                    <div><i class="bx bx-briefcase me-1"></i><?= $userRow['job_count'] ?> Jobs</div>
                                    <div><i class="bx bx-user me-1"></i><?= $userRow['candidate_count'] ?> Candidates</div>
                                </div>
                            </td>
                            
                            <!-- Last Login -->
                            <td>
                                <?php if ($userRow['last_login']): ?>
                                    <small><?= timeAgo($userRow['last_login']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Never</small>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Created -->
                            <td>
                                <small><?= formatDate($userRow['created_at']) ?></small>
                            </td>
                            
                            <!-- Actions -->
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (Permission::can('users', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" href="edit.php?user_code=<?= $userRow['user_code'] ?>">
                                                <i class="bx bx-edit me-2"></i> Edit User
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if (Permission::can('users', 'manage_permissions')): ?>
                                        <li>
                                            <a class="dropdown-item" href="permissions.php?user_code=<?= $userRow['user_code'] ?>">
                                                <i class="bx bx-key me-2"></i> Manage Permissions
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if (Permission::can('users', 'edit') && $userRow['user_code'] !== Auth::userCode()): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        
                                        <?php if ($userRow['is_active']): ?>
                                        <li>
                                            <a class="dropdown-item text-warning" href="#" 
                                               onclick="toggleUserStatus('<?= $userRow['user_code'] ?>', 0); return false;">
                                                <i class="bx bx-block me-2"></i> Deactivate
                                            </a>
                                        </li>
                                        <?php else: ?>
                                        <li>
                                            <a class="dropdown-item text-success" href="#" 
                                               onclick="toggleUserStatus('<?= $userRow['user_code'] ?>', 1); return false;">
                                                <i class="bx bx-check-circle me-2"></i> Activate
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <li>
                                            <a class="dropdown-item text-info" href="#" 
                                               onclick="resetPassword('<?= $userRow['user_code'] ?>'); return false;">
                                                <i class="bx bx-reset me-2"></i> Reset Password
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if (Permission::can('users', 'delete') && $userRow['user_code'] !== Auth::userCode()): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" 
                                               onclick="deleteUser('<?= $userRow['user_code'] ?>'); return false;">
                                                <i class="bx bx-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleUserStatus(userCode, status) {
    const action = status ? 'activate' : 'deactivate';
    if (!confirm(`Are you sure you want to ${action} this user?`)) {
        return;
    }
    
    fetch('handlers/toggle_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_code=${userCode}&is_active=${status}&csrf_token=<?= CSRFToken::generate() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function resetPassword(userCode) {
    if (!confirm('Reset password to default? User will need to change it on next login.')) {
        return;
    }
    
    fetch('handlers/reset_password.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_code=${userCode}&csrf_token=<?= CSRFToken::generate() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password reset successfully! New password: ' + data.new_password);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteUser(userCode) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    fetch('handlers/delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_code=${userCode}&csrf_token=<?= CSRFToken::generate() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>