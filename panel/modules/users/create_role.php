<?php
/**
 * Roles Management - List All Roles
 * File: panel/modules/users/roles.php
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Permission;

// Check permission
Permission::require('users', 'manage_roles');

// Page configuration
$pageTitle = 'Roles & Permissions';
$breadcrumbs = [
    'User Management' => 'index.php?action=list',
    'Roles & Permissions' => '#'
];

// Fetch all roles with permission counts
$db = Database::getInstance();
$conn = $db->getConnection();

$query = "
    SELECT 
        r.id,
        r.role_code,
        r.role_name,
        r.description,
        r.is_system_role,
        r.created_at,
        COUNT(DISTINCT rp.permission_id) as permission_count,
        COUNT(DISTINCT u.id) as user_count
    FROM roles r
    LEFT JOIN role_permissions rp ON r.id = rp.role_id
    LEFT JOIN users u ON r.id = u.role_id AND u.deleted_at IS NULL
    GROUP BY r.id
    ORDER BY r.id
";

$result = $conn->query($query);
$roles = $result->fetch_all(MYSQLI_ASSOC);

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <span class="text-muted fw-light">User Management /</span> Roles & Permissions
            </h4>
            <p class="text-muted mb-0">Manage user roles and their permissions</p>
        </div>
        <div>
            <a href="index.php?action=list" class="btn btn-secondary me-2">
                <i class="bx bx-arrow-back me-1"></i> Back to Users
            </a>
            <a href="create_role.php" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Create New Role
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php require_once ROOT_PATH . '/panel/includes/flash-messages.php'; ?>

    <!-- Roles Overview -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-shield-quarter fs-4"></i>
                            </span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Total Roles</small>
                            <h4 class="mb-0"><?= count($roles) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-lock-alt fs-4"></i>
                            </span>
                        </div>
                        <div>
                            <small class="text-muted d-block">System Roles</small>
                            <h4 class="mb-0"><?= count(array_filter($roles, fn($r) => $r['is_system_role'])) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-key fs-4"></i>
                            </span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Total Permissions</small>
                            <?php
                            $totalPerms = $conn->query("SELECT COUNT(*) as count FROM permissions")->fetch_assoc()['count'];
                            ?>
                            <h4 class="mb-0"><?= $totalPerms ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-user fs-4"></i>
                            </span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Active Users</small>
                            <?php
                            $activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND deleted_at IS NULL")->fetch_assoc()['count'];
                            ?>
                            <h4 class="mb-0"><?= $activeUsers ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roles Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">All Roles</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th class="text-center">Permissions</th>
                        <th class="text-center">Users</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm me-3">
                                    <span class="avatar-initial rounded-circle bg-label-<?= $role['is_system_role'] ? 'primary' : 'secondary' ?>">
                                        <?= strtoupper(substr($role['role_name'], 0, 1)) ?>
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?= escape($role['role_name']) ?></h6>
                                    <small class="text-muted"><?= escape($role['role_code']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-muted">
                                <?= escape($role['description'] ?: 'No description') ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-label-info">
                                <?= $role['permission_count'] ?> permissions
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-label-primary">
                                <?= $role['user_count'] ?> users
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($role['is_system_role']): ?>
                                <span class="badge bg-label-success">
                                    <i class="bx bx-lock-alt me-1"></i>System
                                </span>
                            <?php else: ?>
                                <span class="badge bg-label-secondary">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" 
                                        data-bs-toggle="dropdown">
                                    <i class="bx bx-dots-vertical-rounded"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="edit_role.php?id=<?= $role['id'] ?>">
                                        <i class="bx bx-edit me-2"></i> Edit Permissions
                                    </a>
                                    <a class="dropdown-item" href="index.php?action=list&role_id=<?= $role['id'] ?>">
                                        <i class="bx bx-user me-2"></i> View Users
                                    </a>
                                    <?php if (!$role['is_system_role']): ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger" 
                                       href="javascript:void(0);"
                                       onclick="deleteRole(<?= $role['id'] ?>, '<?= escape($role['role_name']) ?>', <?= $role['user_count'] ?>)">
                                        <i class="bx bx-trash me-2"></i> Delete Role
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Permission Breakdown by Module -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Permission Distribution by Module</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get permission counts by module
                    $moduleQuery = "
                        SELECT 
                            module,
                            COUNT(*) as permission_count
                        FROM permissions
                        GROUP BY module
                        ORDER BY permission_count DESC
                    ";
                    $moduleResult = $conn->query($moduleQuery);
                    $modules = $moduleResult->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <div class="row">
                        <?php foreach ($modules as $module): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-label-primary rounded p-2 me-3">
                                    <i class="bx bx-shield fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-capitalize"><?= str_replace('_', ' ', $module) ?></h6>
                                    <small class="text-muted"><?= $module['permission_count'] ?> permissions</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Card -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="text-white mb-3">
                        <i class="bx bx-info-circle me-2"></i>About Roles & Permissions
                    </h5>
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-white">System Roles</h6>
                            <p class="mb-0 small opacity-75">
                                System roles (marked with lock icon) are protected and cannot be deleted. 
                                You can modify their permissions but not remove the role itself.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-white">Custom Roles</h6>
                            <p class="mb-0 small opacity-75">
                                Create custom roles for specific team needs. Custom roles can be deleted 
                                if no users are assigned to them.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-white">Permission Inheritance</h6>
                            <p class="mb-0 small opacity-75">
                                Users inherit all permissions from their assigned role. Individual user 
                                permissions can override role permissions if needed.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteRole(roleId, roleName, userCount) {
    if (userCount > 0) {
        alert(`Cannot delete role "${roleName}" because ${userCount} user(s) are assigned to it.\n\nPlease reassign these users to another role first.`);
        return;
    }
    
    if (!confirm(`Are you sure you want to delete the role "${roleName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('role_id', roleId);
    formData.append('token', '<?= CSRFToken::generate() ?>');
    
    fetch('handlers/delete_role.php', {
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
        alert('An error occurred while deleting the role');
    });
}
</script>

<?php require_once ROOT_PATH . '/panel/includes/footer.php'; ?>