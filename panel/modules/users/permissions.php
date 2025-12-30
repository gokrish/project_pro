<?php
/**
 * User Permissions Management
 * Manage specific permissions for individual users
 * Override role-based permissions with grants/revocations
 * 
 * @version 2.0 FINAL
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;
// Check permission
Permission::require('users', 'manage_permissions');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get user code
$userCode = $_GET['user_code'] ?? null;

// Fetch user
$userStmt = $conn->prepare("
    SELECT u.*, r.role_name, r.role_code 
    FROM users u 
    LEFT JOIN roles r ON r.id = u.role_id 
    WHERE u.user_code = ?
");
$userStmt->bind_param("s", $userCode);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    FlashMessage::error('User not found');
    redirect(BASE_URL . '/panel/modules/users/list.php');
}

// Get all available permissions grouped by module
$permissionsQuery = "
    SELECT * FROM permissions 
    ORDER BY permission_module, permission_action
";
$permissions = $conn->query($permissionsQuery);

// Group permissions by module
$permissionsByModule = [];
while ($perm = $permissions->fetch_assoc()) {
    $module = $perm['permission_module'];
    if (!isset($permissionsByModule[$module])) {
        $permissionsByModule[$module] = [];
    }
    $permissionsByModule[$module][] = $perm;
}

// Get role permissions for this user
$rolePermissions = [];
if ($user['role_id']) {
    $rolePermsQuery = "
        SELECT p.permission_code 
        FROM role_permissions rp
        JOIN permissions p ON p.id = rp.permission_id
        WHERE rp.role_id = ?
    ";
    $rolePermsStmt = $conn->prepare($rolePermsQuery);
    $rolePermsStmt->bind_param("i", $user['role_id']);
    $rolePermsStmt->execute();
    $rolePermsResult = $rolePermsStmt->get_result();
    while ($rp = $rolePermsResult->fetch_assoc()) {
        $rolePermissions[] = $rp['permission_code'];
    }
}

// Get user-specific permissions (grants and revocations)
$userPermissions = [
    'grants' => [],
    'revocations' => []
];

$userPermsQuery = "
    SELECT p.permission_code, up.is_granted 
    FROM user_permissions up
    JOIN permissions p ON p.id = up.permission_id
    WHERE up.user_code = ?
";
$userPermsStmt = $conn->prepare($userPermsQuery);
$userPermsStmt->bind_param("s", $userCode);
$userPermsStmt->execute();
$userPermsResult = $userPermsStmt->get_result();

while ($up = $userPermsResult->fetch_assoc()) {
    if ($up['is_granted']) {
        $userPermissions['grants'][] = $up['permission_code'];
    } else {
        $userPermissions['revocations'][] = $up['permission_code'];
    }
}

// Page config
$pageTitle = 'Manage Permissions: ' . $user['name'];
$breadcrumbs = [
    ['title' => 'Users', 'url' => '/panel/modules/users/list.php'],
    ['title' => $user['name'], 'url' => ''],
    ['title' => 'Permissions', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.permission-module {
    background: #fff;
    border: 1px solid #e7e7e7;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.permission-module h5 {
    font-size: 16px;
    font-weight: 600;
    color: #566a7f;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;
}

.permission-item {
    padding: 12px;
    border: 1px solid #e7e7e7;
    border-radius: 6px;
    margin-bottom: 10px;
    background: #fafafa;
}

.permission-item.has-role-perm {
    background: #e8f5e9;
    border-color: #81c784;
}

.permission-item.has-grant {
    background: #bbdefb;
    border-color: #42a5f5;
}

.permission-item.has-revocation {
    background: #ffcdd2;
    border-color: #ef5350;
}

.permission-status {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 6px;
}
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Manage User Permissions</h4>
            <p class="text-muted mb-0">
                <?= escape($user['name']) ?> 
                <span class="badge bg-label-primary ms-2"><?= escape($user['role_name'] ?: $user['level']) ?></span>
            </p>
        </div>
        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Users
        </a>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info">
        <h5 class="alert-heading">
            <i class="bx bx-info-circle me-2"></i>
            How Permissions Work
        </h5>
        <ul class="mb-0">
            <li><strong>Role Permissions:</strong> This user inherits permissions from their role (<?= escape($user['role_name'] ?: $user['level']) ?>)</li>
            <li><strong>Grant Permission:</strong> Give this user a specific permission even if their role doesn't have it</li>
            <li><strong>Revoke Permission:</strong> Remove a permission even if their role has it</li>
        </ul>
    </div>

    <!-- Legend -->
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3">Legend:</h6>
            <div class="d-flex flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="me-2" style="width: 20px; height: 20px; background: #e8f5e9; border: 1px solid #81c784; border-radius: 4px;"></div>
                    <span>From Role</span>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-2" style="width: 20px; height: 20px; background: #bbdefb; border: 1px solid #42a5f5; border-radius: 4px;"></div>
                    <span>Granted (Override)</span>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-2" style="width: 20px; height: 20px; background: #ffcdd2; border: 1px solid #ef5350; border-radius: 4px;"></div>
                    <span>Revoked (Override)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions by Module -->
    <?php foreach ($permissionsByModule as $module => $perms): ?>
    <div class="permission-module">
        <h5>
            <i class="bx bx-shield me-2"></i>
            <?= ucfirst($module) ?> Permissions
        </h5>
        
        <div class="row">
            <?php foreach ($perms as $perm): ?>
                <?php
                $hasRolePerm = in_array($perm['permission_code'], $rolePermissions);
                $hasGrant = in_array($perm['permission_code'], $userPermissions['grants']);
                $hasRevocation = in_array($perm['permission_code'], $userPermissions['revocations']);
                
                $effectivelyHas = ($hasRolePerm && !$hasRevocation) || $hasGrant;
                
                $classes = [];
                if ($hasRolePerm) $classes[] = 'has-role-perm';
                if ($hasGrant) $classes[] = 'has-grant';
                if ($hasRevocation) $classes[] = 'has-revocation';
                ?>
                
                <div class="col-md-6 mb-3">
                    <div class="permission-item <?= implode(' ', $classes) ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= escape($perm['permission_name']) ?></strong>
                                <div class="small text-muted"><?= escape($perm['description']) ?></div>
                                
                                <div class="permission-status">
                                    <?php if ($hasRolePerm): ?>
                                        <span class="badge badge-sm bg-success">Role Has</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($hasGrant): ?>
                                        <span class="badge badge-sm bg-info">Granted</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($hasRevocation): ?>
                                        <span class="badge badge-sm bg-danger">Revoked</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($effectivelyHas): ?>
                                        <span class="badge badge-sm bg-primary">
                                            <i class="bx bx-check"></i> Has Permission
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="btn-group-vertical" role="group">
                                <?php if (!$effectivelyHas): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="grantPermission('<?= $perm['permission_code'] ?>')">
                                        <i class="bx bx-plus"></i> Grant
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($effectivelyHas && !$hasRevocation): ?>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="revokePermission('<?= $perm['permission_code'] ?>')">
                                        <i class="bx bx-minus"></i> Revoke
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($hasGrant || $hasRevocation): ?>
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            onclick="resetPermission('<?= $perm['permission_code'] ?>')">
                                        <i class="bx bx-reset"></i> Reset
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
const userCode = '<?= $userCode ?>';
const csrfToken = '<?= CSRFToken::generate() ?>';

function grantPermission(permissionCode) {
    if (!confirm('Grant this permission to the user?')) return;
    
    fetch('handlers/manage_permission.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_code=${userCode}&permission_code=${permissionCode}&action=grant&csrf_token=${csrfToken}`
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

function revokePermission(permissionCode) {
    if (!confirm('Revoke this permission from the user? They will lose access even if their role has it.')) return;
    
    fetch('handlers/manage_permission.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_code=${userCode}&permission_code=${permissionCode}&action=revoke&csrf_token=${csrfToken}`
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

function resetPermission(permissionCode) {
    if (!confirm('Reset this permission to use role default?')) return;
    
    fetch('handlers/manage_permission.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_code=${userCode}&permission_code=${permissionCode}&action=reset&csrf_token=${csrfToken}`
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
