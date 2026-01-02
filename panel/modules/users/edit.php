<?php
/**
 * User Management - Edit User
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, CSRFToken, Auth};

// Check permission
Permission::require('users', 'edit');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get user ID from URL
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$userId) {
    $_SESSION['flash_error'] = 'Invalid user ID';
    header('Location: /panel/modules/users/list.php');
    exit;
}

// Fetch user data
$stmt = $conn->prepare("
    SELECT 
        id, user_code, name, email, phone, level, is_active, created_at, last_login
    FROM users
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

if (!$userData) {
    $_SESSION['flash_error'] = 'User not found';
    header('Location: /panel/modules/users/list.php');
    exit;
}

// Prevent editing own account (use profile page instead)
$currentUser = Auth::user();
if ($userData['user_code'] === $currentUser['user_code']) {
    $_SESSION['flash_warning'] = 'Use "My Profile" to edit your own account';
    header('Location: /panel/modules/users/profile.php');
    exit;
}

// Page config
$pageTitle = 'Edit User';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '#'],
    ['title' => 'Users', 'url' => '/panel/modules/users/list.php'],
    ['title' => 'Edit', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-edit me-2"></i>
                Edit User
            </h4>
            <p class="text-muted mb-0">Update user information</p>
        </div>
        <div>
            <a href="/panel/modules/users/list.php" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back"></i> Back to List
            </a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash-messages.php'; ?>

    <!-- Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">User Information</h5>
                    <span class="badge bg-secondary"><?= htmlspecialchars($userData['user_code']) ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" action="/panel/modules/users/handlers/update.php" id="editUserForm">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        <input type="hidden" name="user_id" value="<?= $userData['id'] ?>">
                        
                        <!-- Name -->
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   name="name" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($userData['name']) ?>"
                                   required
                                   autofocus>
                        </div>
                        
                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($userData['email']) ?>"
                                   required>
                            <small class="text-muted">Used for login</small>
                        </div>
                        
                        <!-- Phone -->
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" 
                                   name="phone" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($userData['phone'] ?? '') ?>"
                                   placeholder="+32 123 456 789">
                        </div>
                        
                        <!-- Role/Level -->
                        <div class="mb-3">
                            <label class="form-label">Role/Level <span class="text-danger">*</span></label>
                            <select name="level" class="form-select" required>
                                <option value="">Select role...</option>
                                <option value="super_admin" <?= $userData['level'] === 'super_admin' ? 'selected' : '' ?>>Super Admin - Full system access</option>
                                <option value="admin" <?= $userData['level'] === 'admin' ? 'selected' : '' ?>>Admin - Administrative access</option>
                                <option value="manager" <?= $userData['level'] === 'manager' ? 'selected' : '' ?>>Manager - Team management</option>
                                <option value="senior_recruiter" <?= $userData['level'] === 'senior_recruiter' ? 'selected' : '' ?>>Senior Recruiter - Extended access</option>
                                <option value="recruiter" <?= $userData['level'] === 'recruiter' ? 'selected' : '' ?>>Recruiter - Standard access</option>
                                <option value="coordinator" <?= $userData['level'] === 'coordinator' ? 'selected' : '' ?>>Coordinator - Support tasks</option>
                            </select>
                        </div>
                        
                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="is_active" 
                                       value="1"
                                       <?= $userData['is_active'] ? 'checked' : '' ?>
                                       id="isActive">
                                <label class="form-check-label" for="isActive">
                                    Active (user can login)
                                </label>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Password Change Section -->
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle"></i>
                            <strong>Password:</strong> To reset this user's password, use the "Reset Password" button in the user list.
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-between">
                            <a href="/panel/modules/users/list.php" class="btn btn-outline-secondary">
                                <i class="bx bx-x"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Info Panel -->
        <div class="col-lg-4">
            <!-- Account Info -->
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="bx bx-info-circle"></i> Account Info
                    </h6>
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted small">User Code:</dt>
                        <dd class="col-sm-7 small"><?= htmlspecialchars($userData['user_code']) ?></dd>
                        
                        <dt class="col-sm-5 text-muted small">Created:</dt>
                        <dd class="col-sm-7 small"><?= date('M d, Y', strtotime($userData['created_at'])) ?></dd>
                        
                        <dt class="col-sm-5 text-muted small">Last Login:</dt>
                        <dd class="col-sm-7 small">
                            <?php if ($userData['last_login']): ?>
                                <?= date('M d, Y H:i', strtotime($userData['last_login'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="bx bx-cog"></i> Quick Actions
                    </h6>
                    
                    <?php if (Permission::can('users', 'reset_password')): ?>
                    <button type="button" 
                            class="btn btn-outline-warning btn-sm w-100 mb-2"
                            onclick="resetPassword(<?= $userData['id'] ?>, '<?= htmlspecialchars($userData['name']) ?>')">
                        <i class="bx bx-key"></i> Reset Password
                    </button>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('users', 'toggle_status')): ?>
                    <button type="button" 
                            class="btn btn-outline-<?= $userData['is_active'] ? 'danger' : 'success' ?> btn-sm w-100"
                            onclick="toggleStatus(<?= $userData['id'] ?>, <?= $userData['is_active'] ? 0 : 1 ?>)">
                        <i class="bx bx-<?= $userData['is_active'] ? 'x-circle' : 'check-circle' ?>"></i> 
                        <?= $userData['is_active'] ? 'Deactivate' : 'Activate' ?> User
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
function resetPassword(userId, userName) {
    if (!confirm(`Reset password for ${userName}?\n\nA new temporary password will be generated.`)) {
        return;
    }
    
    fetch('/panel/modules/users/handlers/reset_password.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_id=${userId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Password reset successful!\n\nNew password: ${data.new_password}\n\nPlease save this and share with the user.`);
        } else {
            alert('Error: ' + (data.message || 'Failed to reset password'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function toggleStatus(userId, newStatus) {
    const action = newStatus === 1 ? 'activate' : 'deactivate';
    
    if (!confirm(`Are you sure you want to ${action} this user?`)) {
        return;
    }
    
    fetch('/panel/modules/users/handlers/toggle_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_id=${userId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update status'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>