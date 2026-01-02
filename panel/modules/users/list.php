<?php
/**
 * User Management - List All Users
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth};

// Check permission - Only admin/super_admin can access
Permission::require('users', 'view_all');

$db = Database::getInstance();
$conn = $db->getConnection();

$user = Auth::user();
$userLevel = $user['level'] ?? 'user';

// Get filter parameters
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$filterLevel = filter_input(INPUT_GET, 'level', FILTER_SANITIZE_STRING);
$filterStatus = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);

// Build query
$sql = "
    SELECT 
        u.id,
        u.user_code,
        u.name,
        u.email,
        u.phone,
        u.level,
        u.is_active,
        u.last_login,
        u.created_at,
        creator.name as created_by_name
    FROM users u
    LEFT JOIN users creator ON u.created_by = creator.user_code
    WHERE u.deleted_at IS NULL
";

$params = [];
$types = '';

// Apply search filter
if ($searchTerm) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

// Apply level filter
if ($filterLevel) {
    $sql .= " AND u.level = ?";
    $params[] = $filterLevel;
    $types .= 's';
}

// Apply status filter
if ($filterStatus !== null && $filterStatus !== '') {
    $sql .= " AND u.is_active = ?";
    $params[] = ($filterStatus === '1') ? 1 : 0;
    $types .= 'i';
}

$sql .= " ORDER BY u.created_at DESC";

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Page config
$pageTitle = 'User Management';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '#'],
    ['title' => 'Users', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-user me-2"></i>
                User Management
            </h4>
            <p class="text-muted mb-0">Manage system users and access levels</p>
        </div>
        <div>
            <a href="/panel/modules/users/create.php" class="btn btn-primary">
                <i class="bx bx-plus"></i> Add New User
            </a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash-messages.php'; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <!-- Search -->
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Name or email..."
                           value="<?= htmlspecialchars($searchTerm ?? '') ?>">
                </div>
                
                <!-- Level Filter -->
                <div class="col-md-3">
                    <label class="form-label">Role/Level</label>
                    <select name="level" class="form-select">
                        <option value="">All Roles</option>
                        <option value="super_admin" <?= $filterLevel === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                        <option value="admin" <?= $filterLevel === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="manager" <?= $filterLevel === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="senior_recruiter" <?= $filterLevel === 'senior_recruiter' ? 'selected' : '' ?>>Senior Recruiter</option>
                        <option value="recruiter" <?= $filterLevel === 'recruiter' ? 'selected' : '' ?>>Recruiter</option>
                        <option value="coordinator" <?= $filterLevel === 'coordinator' ? 'selected' : '' ?>>Coordinator</option>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <!-- Buttons -->
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bx bx-search"></i> Search
                    </button>
                    <?php if ($searchTerm || $filterLevel || $filterStatus !== null): ?>
                        <a href="/panel/modules/users/list.php" class="btn btn-outline-secondary">
                            <i class="bx bx-x"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                All Users
                <span class="badge bg-primary ms-2"><?= count($users) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="bx bx-user-x" style="font-size: 4rem; color: #ddd;"></i>
                    <p class="text-muted mt-3">No users found</p>
                    <a href="/panel/modules/users/create.php" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Add First User
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th class="text-center">Status</th>
                                <th>Last Login</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <!-- User Info -->
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-2">
                                            <span class="avatar-initial rounded-circle bg-label-primary">
                                                <?= strtoupper(substr($u['name'], 0, 2)) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($u['name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($u['user_code']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Email -->
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                
                                <!-- Phone -->
                                <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                                
                                <!-- Role -->
                                <td>
                                    <?php
                                    $roleBadges = [
                                        'super_admin' => ['class' => 'danger', 'label' => 'Super Admin'],
                                        'admin' => ['class' => 'warning', 'label' => 'Admin'],
                                        'manager' => ['class' => 'info', 'label' => 'Manager'],
                                        'senior_recruiter' => ['class' => 'primary', 'label' => 'Senior Recruiter'],
                                        'recruiter' => ['class' => 'success', 'label' => 'Recruiter'],
                                        'coordinator' => ['class' => 'secondary', 'label' => 'Coordinator']
                                    ];
                                    $badge = $roleBadges[$u['level']] ?? ['class' => 'secondary', 'label' => ucfirst($u['level'])];
                                    ?>
                                    <span class="badge bg-<?= $badge['class'] ?>">
                                        <?= $badge['label'] ?>
                                    </span>
                                </td>
                                
                                <!-- Status -->
                                <td class="text-center">
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Last Login -->
                                <td>
                                    <?php if ($u['last_login']): ?>
                                        <?= date('M d, Y H:i', strtotime($u['last_login'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Actions -->
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Edit -->
                                        <a href="/panel/modules/users/edit.php?id=<?= $u['id'] ?>" 
                                           class="btn btn-outline-primary"
                                           title="Edit User">
                                            <i class="bx bx-edit"></i>
                                        </a>
                                        
                                        <!-- Toggle Status -->
                                        <?php if (Permission::can('users', 'toggle_status')): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"
                                                    onclick="toggleUserStatus(<?= $u['id'] ?>, <?= $u['is_active'] ? 0 : 1 ?>)"
                                                    title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="bx bx-<?= $u['is_active'] ? 'x-circle' : 'check-circle' ?>"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- Reset Password -->
                                        <?php if (Permission::can('users', 'reset_password')): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-info"
                                                    onclick="resetUserPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')"
                                                    title="Reset Password">
                                                <i class="bx bx-key"></i>
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

<!-- JavaScript -->
<script>
/**
 * Toggle user active/inactive status
 */
function toggleUserStatus(userId, newStatus) {
    const action = newStatus === 1 ? 'activate' : 'deactivate';
    
    if (!confirm(`Are you sure you want to ${action} this user?`)) {
        return;
    }
    
    // Show loading
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bx bx-loader bx-spin"></i>';
    
    // Send request
    fetch('/panel/modules/users/handlers/toggle_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to show updated status
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update user status'));
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

/**
 * Reset user password
 */
function resetUserPassword(userId, userName) {
    if (!confirm(`Reset password for ${userName}?\n\nA new temporary password will be generated and displayed.`)) {
        return;
    }
    
    // Show loading
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bx bx-loader bx-spin"></i>';
    
    // Send request
    fetch('/panel/modules/users/handlers/reset_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Password reset successful!\n\nNew temporary password: ${data.new_password}\n\nPlease save this and share it with the user.`);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        } else {
            alert('Error: ' + (data.message || 'Failed to reset password'));
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>