<?php
/**
 * Edit Role & Permissions
 * File: panel/modules/users/edit_role.php
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;

// Check permission
Permission::require('users', 'manage_roles');

// Get role ID
$roleId = $_GET['id'] ?? null;

if (!$roleId) {
    header('Location: roles.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch role details
$stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->bind_param("i", $roleId);
$stmt->execute();
$result = $stmt->get_result();
$role = $result->fetch_assoc();

if (!$role) {
    FlashMessage::error('Role not found');
    header('Location: roles.php');
    exit();
}

// Fetch all permissions
$permQuery = "
    SELECT 
        id,
        permission_code,
        permission_name,
        module,
        description
    FROM permissions
    ORDER BY module, permission_code
";
$permResult = $conn->query($permQuery);
$allPermissions = $permResult->fetch_all(MYSQLI_ASSOC);

// Get current role permissions
$rolePermQuery = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
$stmt = $conn->prepare($rolePermQuery);
$stmt->bind_param("i", $roleId);
$stmt->execute();
$rolePermResult = $stmt->get_result();
$currentPermissions = array_column($rolePermResult->fetch_all(MYSQLI_ASSOC), 'permission_id');

// Group permissions by module
$permissionsByModule = [];
foreach ($allPermissions as $perm) {
    $perm['assigned'] = in_array($perm['id'], $currentPermissions);
    $permissionsByModule[$perm['module']][] = $perm;
}

// Count users with this role
$userCountQuery = "SELECT COUNT(*) as count FROM users WHERE role_id = ? AND deleted_at IS NULL";
$stmt = $conn->prepare($userCountQuery);
$stmt->bind_param("i", $roleId);
$stmt->execute();
$userCount = $stmt->get_result()->fetch_assoc()['count'];

// Page configuration
$pageTitle = 'Edit Role - ' . $role['role_name'];
$breadcrumbs = [
    'User Management' => 'index.php?action=list',
    'Roles' => 'roles.php',
    'Edit Role' => '#'
];

require_once ROOT_PATH . '/panel/includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <span class="text-muted fw-light">User Management / Roles /</span> 
                Edit: <?= escape($role['role_name']) ?>
            </h4>
            <p class="text-muted mb-0">Modify role details and permissions</p>
        </div>
        <a href="roles.php" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Roles
        </a>
    </div>

    <!-- Flash Messages -->
    <?php require_once ROOT_PATH . '/panel/includes/flash-messages.php'; ?>

    <form id="roleForm" method="POST" action="handlers/update_role_permissions.php">
        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
        <input type="hidden" name="token" value="<?= CSRFToken::generate() ?>">
        
        <div class="row">
            <div class="col-lg-4">
                <!-- Role Information Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Role Information</h5>
                        <?php if ($role['is_system_role']): ?>
                        <span class="badge bg-label-success">
                            <i class="bx bx-lock-alt me-1"></i>System Role
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Role Name -->
                        <div class="mb-3">
                            <label class="form-label" for="role_name">
                                Role Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="role_name" 
                                   name="role_name" 
                                   value="<?= escape($role['role_name']) ?>"
                                   required 
                                   maxlength="100">
                        </div>

                        <!-- Role Code (read-only for system roles) -->
                        <div class="mb-3">
                            <label class="form-label">Role Code</label>
                            <input type="text" 
                                   class="form-control bg-light" 
                                   value="<?= escape($role['role_code']) ?>"
                                   readonly>
                            <small class="text-muted">System identifier (cannot be changed)</small>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="3"><?= escape($role['description']) ?></textarea>
                        </div>

                        <?php if (!$role['is_system_role']): ?>
                        <!-- Is System Role checkbox -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_system_role" 
                                       name="is_system_role"
                                       value="1"
                                       <?= $role['is_system_role'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_system_role">
                                    Mark as System Role
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="bx bx-info-circle"></i> 
                                System roles cannot be deleted
                            </small>
                        </div>
                        <?php endif; ?>

                        <!-- Permission Summary -->
                        <div class="alert alert-info mt-4">
                            <h6 class="alert-heading">
                                <i class="bx bx-info-circle me-1"></i> Permissions
                            </h6>
                            <div id="permissionSummary" class="mb-2">
                                <strong><?= count($currentPermissions) ?></strong> permissions assigned
                            </div>
                            <small class="text-muted">
                                Total available: <?= count($allPermissions) ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Users Assigned</small>
                            <div>
                                <h4 class="mb-0"><?= $userCount ?></h4>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Created</small>
                            <div><?= formatDateTime($role['created_at']) ?></div>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted">Last Modified</small>
                            <div><?= formatDateTime($role['updated_at']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Select Card -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="selectAll()">
                            <i class="bx bx-check-double me-1"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100 mb-2" onclick="deselectAll()">
                            <i class="bx bx-x me-1"></i> Deselect All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info w-100 mb-2" onclick="selectViewOnly()">
                            <i class="bx bx-show me-1"></i> View Only
                        </button>
                        <?php if ($userCount > 0): ?>
                        <hr>
                        <a href="index.php?action=list&role_id=<?= $role['id'] ?>" 
                           class="btn btn-sm btn-outline-primary w-100">
                            <i class="bx bx-user me-1"></i> View Assigned Users
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Permissions Card -->
                <div class="card">
                    <div class="card-header sticky-top bg-white" style="z-index: 10;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Manage Permissions</h5>
                            <div>
                                <input type="text" 
                                       class="form-control form-control-sm" 
                                       id="searchPermissions" 
                                       placeholder="Search permissions..."
                                       style="min-width: 250px;">
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="max-height: 800px; overflow-y: auto;">
                        <?php foreach ($permissionsByModule as $module => $permissions): ?>
                        <div class="permission-module mb-4" data-module="<?= $module ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase fw-bold text-primary mb-0">
                                    <i class="bx bx-shield me-1"></i>
                                    <?= ucfirst(str_replace('_', ' ', $module)) ?>
                                    <span class="badge bg-label-primary ms-2"><?= count($permissions) ?></span>
                                </h6>
                                <div>
                                    <button type="button" 
                                            class="btn btn-xs btn-outline-primary" 
                                            onclick="selectModule('<?= $module ?>')">
                                        Select All
                                    </button>
                                    <button type="button" 
                                            class="btn btn-xs btn-outline-secondary" 
                                            onclick="deselectModule('<?= $module ?>')">
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                <?php foreach ($permissions as $perm): ?>
                                <div class="col-md-6 mb-2 permission-item" data-search="<?= strtolower($perm['permission_name'] . ' ' . $perm['permission_code'] . ' ' . $perm['description']) ?>">
                                    <div class="form-check">
                                        <input class="form-check-input permission-checkbox" 
                                               type="checkbox" 
                                               name="permissions[]" 
                                               value="<?= $perm['id'] ?>"
                                               id="perm_<?= $perm['id'] ?>"
                                               data-module="<?= $module ?>"
                                               data-code="<?= $perm['permission_code'] ?>"
                                               <?= $perm['assigned'] ? 'checked' : '' ?>
                                               onchange="updateSummary()">
                                        <label class="form-check-label" for="perm_<?= $perm['id'] ?>">
                                            <strong><?= escape($perm['permission_name']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= escape($perm['description'] ?: $perm['permission_code']) ?>
                                            </small>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <hr class="my-3">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer sticky-bottom bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="roles.php" class="btn btn-label-secondary">
                                    Cancel
                                </a>
                                <?php if (!$role['is_system_role'] && $userCount == 0): ?>
                                <button type="button" 
                                        class="btn btn-label-danger" 
                                        onclick="deleteRole(<?= $role['id'] ?>, '<?= escape($role['role_name']) ?>')">
                                    <i class="bx bx-trash me-1"></i> Delete Role
                                </button>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Search permissions
document.getElementById('searchPermissions').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const items = document.querySelectorAll('.permission-item');
    
    items.forEach(item => {
        const searchText = item.getAttribute('data-search');
        if (searchText.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
    
    // Hide/show modules with no visible items
    document.querySelectorAll('.permission-module').forEach(module => {
        const visibleItems = module.querySelectorAll('.permission-item:not([style*="display: none"])');
        if (visibleItems.length === 0 && searchTerm) {
            module.style.display = 'none';
        } else {
            module.style.display = '';
        }
    });
});

// Update permission summary
function updateSummary() {
    const checkboxes = document.querySelectorAll('.permission-checkbox:checked');
    const count = checkboxes.length;
    
    const summary = document.getElementById('permissionSummary');
    summary.innerHTML = `<strong>${count}</strong> permission${count !== 1 ? 's' : ''} assigned`;
}

// Select all permissions
function selectAll() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        if (cb.closest('.permission-item').style.display !== 'none') {
            cb.checked = true;
        }
    });
    updateSummary();
}

// Deselect all permissions
function deselectAll() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        if (cb.closest('.permission-item').style.display !== 'none') {
            cb.checked = false;
        }
    });
    updateSummary();
}

// Select only view permissions
function selectViewOnly() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        const code = cb.getAttribute('data-code');
        if (cb.closest('.permission-item').style.display !== 'none') {
            cb.checked = code.includes('view');
        }
    });
    updateSummary();
}

// Select all in module
function selectModule(module) {
    document.querySelectorAll(`.permission-checkbox[data-module="${module}"]`).forEach(cb => {
        if (cb.closest('.permission-item').style.display !== 'none') {
            cb.checked = true;
        }
    });
    updateSummary();
}

// Deselect all in module
function deselectModule(module) {
    document.querySelectorAll(`.permission-checkbox[data-module="${module}"]`).forEach(cb => {
        if (cb.closest('.permission-item').style.display !== 'none') {
            cb.checked = false;
        }
    });
    updateSummary();
}

// Delete role
function deleteRole(roleId, roleName) {
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
            alert(data.message);
            window.location.href = 'roles.php';
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Form submission
document.getElementById('roleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('handlers/update_role_permissions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = 'roles.php';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the role');
    });
});

// Initialize
updateSummary();
</script>

<style>
.permission-module {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.sticky-top {
    position: sticky;
    top: 0;
}

.sticky-bottom {
    position: sticky;
    bottom: 0;
}
</style>

<?php require_once ROOT_PATH . '/panel/includes/footer.php'; ?>