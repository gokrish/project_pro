<?php
/**
 * Edit User Form
 * File: panel/modules/users/edit.php
 */

// Load common bootstrap
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;

// Check permission
Permission::require('users', 'edit');

// Get user ID from query parameter
$userId = $_GET['id'] ?? null;

if (!$userId) {
    header('Location: index.php?action=list');
    exit();
}

// Fetch user data
$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT u.*, r.role_code, r.role_name 
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.id = ? AND u.deleted_at IS NULL
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    FlashMessage::error('User not found');
    header('Location: index.php?action=list');
    exit();
}

// Fetch all roles for dropdown
$rolesResult = $conn->query("SELECT id, role_code, role_name FROM roles ORDER BY id");
$roles = $rolesResult->fetch_all(MYSQLI_ASSOC);

// Page configuration
$pageTitle = 'Edit User';
$breadcrumbs = [
    'Team' => 'index.php?action=list',
    'Edit User' => '#'
];

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <span class="text-muted fw-light">User Management /</span> Edit User
            </h4>
            <p class="text-muted mb-0">Update user information and permissions</p>
        </div>
        <div>
            <a href="index.php?action=list" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back to Users
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php require_once ROOT_PATH . '/panel/includes/flash-messages.php'; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- User Information Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">User Information</h5>
                    <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                <div class="card-body">
                    <form id="userForm" method="POST" action="handlers/user_save_handler.php">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="token" value="<?= CSRFToken::generate() ?>">

                        <!-- User Code (Read-only) -->
                        <div class="mb-3">
                            <label class="form-label">User Code</label>
                            <input type="text" 
                                   class="form-control bg-light" 
                                   value="<?= escape($user['user_code']) ?>" 
                                   readonly>
                            <small class="text-muted">System generated identifier</small>
                        </div>

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label class="form-label" for="name">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name" 
                                   name="name" 
                                   value="<?= escape($user['name']) ?>"
                                   required 
                                   maxlength="100">
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label" for="email">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= escape($user['email']) ?>"
                                   required 
                                   maxlength="255">
                            <small class="text-muted">Used for login and notifications</small>
                        </div>

                        <!-- Role/Level -->
                        <div class="mb-3">
                            <label class="form-label" for="level">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="level" name="level" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= escape($role['role_code']) ?>" 
                                        <?= $user['level'] === $role['role_code'] ? 'selected' : '' ?>>
                                    <?= escape($role['role_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Phone -->
                        <div class="mb-3">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= escape($user['phone'] ?? '') ?>"
                                   maxlength="20"
                                   placeholder="+32 2 XXX XX XX">
                        </div>

                        <!-- Department -->
                        <div class="mb-3">
                            <label class="form-label" for="department">Department</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="department" 
                                   name="department" 
                                   value="<?= escape($user['department'] ?? '') ?>"
                                   maxlength="100"
                                   placeholder="e.g., Recruitment">
                        </div>

                        <!-- Position -->
                        <div class="mb-3">
                            <label class="form-label" for="position">Position</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="position" 
                                   name="position" 
                                   value="<?= escape($user['position'] ?? '') ?>"
                                   maxlength="100"
                                   placeholder="e.g., Senior Recruiter">
                        </div>

                        <hr class="my-4">

                        <!-- Change Password Section -->
                        <h6 class="mb-3">Change Password (Optional)</h6>
                        <p class="text-muted small">Leave blank to keep current password</p>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label class="form-label" for="password">New Password</label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       minlength="8"
                                       placeholder="Enter new password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bx bx-show"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">Confirm New Password</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password_confirmation" 
                                   name="password_confirmation"
                                   placeholder="Confirm new password">
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php?action=list" class="btn btn-label-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- User Stats Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Account Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Created</small>
                        <div><?= formatDateTime($user['created_at']) ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Last Login</small>
                        <div><?= $user['last_login'] ? formatDateTime($user['last_login']) : 'Never' ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Password Last Changed</small>
                        <div><?= $user['password_changed_at'] ? formatDateTime($user['password_changed_at']) : 'Never' ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Failed Login Attempts</small>
                        <div>
                            <span class="badge bg-<?= $user['failed_login_attempts'] > 0 ? 'warning' : 'success' ?>">
                                <?= $user['failed_login_attempts'] ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($user['locked_until']): ?>
                    <div class="mb-0">
                        <small class="text-muted">Account Locked Until</small>
                        <div class="text-danger"><?= formatDateTime($user['locked_until']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <?php if (Permission::can('users', 'toggle_status')): ?>
                    <button type="button" 
                            class="btn btn-<?= $user['is_active'] ? 'warning' : 'success' ?> w-100 mb-2"
                            onclick="toggleUserStatus(<?= $user['id'] ?>)">
                        <i class="bx bx-power-off me-1"></i>
                        <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?> Account
                    </button>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('users', 'reset_password')): ?>
                    <button type="button" 
                            class="btn btn-info w-100 mb-2"
                            onclick="sendPasswordReset(<?= $user['id'] ?>)">
                        <i class="bx bx-envelope me-1"></i>
                        Send Password Reset
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($user['failed_login_attempts'] > 0 || $user['locked_until']): ?>
                    <button type="button" 
                            class="btn btn-warning w-100 mb-2"
                            onclick="unlockAccount(<?= $user['id'] ?>)">
                        <i class="bx bx-lock-open me-1"></i>
                        Unlock Account
                    </button>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('users', 'delete')): ?>
                    <button type="button" 
                            class="btn btn-danger w-100"
                            onclick="deleteUser(<?= $user['id'] ?>, '<?= escape($user['name']) ?>')">
                        <i class="bx bx-trash me-1"></i>
                        Delete User
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('bx-show');
        icon.classList.add('bx-hide');
    } else {
        password.type = 'password';
        icon.classList.remove('bx-hide');
        icon.classList.add('bx-show');
    }
});

// Password confirmation validation
document.getElementById('password_confirmation').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmation = this.value;
    
    if (password && confirmation && password !== confirmation) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Form submission
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('handlers/user_save_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = 'index.php?action=list';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the user');
    });
});

// Toggle user status
function toggleUserStatus(userId) {
    if (!confirm('Are you sure you want to toggle this user\'s status?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('user_id', userId);
    
    fetch('handlers/toggle_status.php', {
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
    });
}

// Send password reset
function sendPasswordReset(userId) {
    if (!confirm('Send password reset email to this user?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('user_id', userId);
    
    fetch('handlers/reset_password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
    });
}

// Unlock account
function unlockAccount(userId) {
    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('action', 'unlock');
    
    fetch('handlers/toggle_status.php', {
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
    });
}

// Delete user
function deleteUser(userId, userName) {
    if (!confirm(`Are you sure you want to delete ${userName}? This action cannot be undone.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('user_id', userId);
    
    fetch('handlers/delete_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = 'index.php?action=list';
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once ROOT_PATH . '/panel/includes/footer.php'; ?>