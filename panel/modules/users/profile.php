<?php
/**
 * User Management - My Profile
 * User's own profile page
 * 
 * @version 5.0 - Production Ready
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Database, CSRFToken, Auth};

$db = Database::getInstance();
$conn = $db->getConnection();

// Get current user
$currentUser = Auth::user();
$userCode = $currentUser['user_code'];

// Fetch full user data
$stmt = $conn->prepare("
    SELECT 
        id, user_code, name, email, phone, level, is_active, 
        created_at, last_login, password_changed_at
    FROM users
    WHERE user_code = ? AND deleted_at IS NULL
");
$stmt->bind_param("s", $userCode);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

if (!$userData) {
    $_SESSION['flash_error'] = 'User profile not found';
    header('Location: /panel/dashboard.php');
    exit;
}

// Page config
$pageTitle = 'My Profile';
$breadcrumbs = [
    ['title' => 'My Account', 'url' => '#'],
    ['title' => 'Profile', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-user me-2"></i>
                My Profile
            </h4>
            <p class="text-muted mb-0">Manage your account information</p>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash-messages.php'; ?>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/panel/modules/users/handlers/update_profile.php">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        
                        <!-- Name -->
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   name="name" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($userData['name']) ?>"
                                   required>
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
                        
                        <!-- Role (Read-only) -->
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" 
                                   class="form-control" 
                                   value="<?= ucwords(str_replace('_', ' ', $userData['level'])) ?>"
                                   disabled>
                            <small class="text-muted">Contact admin to change your role</small>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/panel/modules/settings/handlers/change_password.php" id="changePasswordForm">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        
                        <!-- Current Password -->
                        <div class="mb-3">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" 
                                       name="current_password" 
                                       id="current_password"
                                       class="form-control" 
                                       required>
                                <button class="btn btn-outline-secondary" 
                                        type="button"
                                        onclick="togglePassword('current_password')">
                                    <i class="bx bx-show"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- New Password -->
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" 
                                       name="new_password" 
                                       id="new_password"
                                       class="form-control" 
                                       required
                                       minlength="6">
                                <button class="btn btn-outline-secondary" 
                                        type="button"
                                        onclick="togglePassword('new_password')">
                                    <i class="bx bx-show"></i>
                                </button>
                            </div>
                            <small class="text-muted">Min 6 characters, 1 number, 1 uppercase</small>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   class="form-control" 
                                   required
                                   minlength="6">
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning">
                                <i class="bx bx-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Account Info -->
        <div class="col-lg-4">
            <!-- Account Details -->
            <div class="card mb-3">
                <div class="card-body text-center">
                    <div class="avatar avatar-xl mb-3">
                        <span class="avatar-initial rounded-circle bg-label-primary" style="font-size: 2rem;">
                            <?= strtoupper(substr($userData['name'], 0, 2)) ?>
                        </span>
                    </div>
                    <h5 class="mb-1"><?= htmlspecialchars($userData['name']) ?></h5>
                    <p class="text-muted mb-0"><?= ucwords(str_replace('_', ' ', $userData['level'])) ?></p>
                </div>
                <hr class="my-0">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted small">User Code:</dt>
                        <dd class="col-sm-7 small"><?= htmlspecialchars($userData['user_code']) ?></dd>
                        
                        <dt class="col-sm-5 text-muted small">Email:</dt>
                        <dd class="col-sm-7 small"><?= htmlspecialchars($userData['email']) ?></dd>
                        
                        <dt class="col-sm-5 text-muted small">Phone:</dt>
                        <dd class="col-sm-7 small"><?= htmlspecialchars($userData['phone'] ?? '-') ?></dd>
                        
                        <dt class="col-sm-5 text-muted small">Account Status:</dt>
                        <dd class="col-sm-7 small">
                            <?php if ($userData['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </dd>
                        
                        <dt class="col-sm-5 text-muted small">Member Since:</dt>
                        <dd class="col-sm-7 small"><?= date('M d, Y', strtotime($userData['created_at'])) ?></dd>
                        
                        <dt class="col-sm-5 text-muted small">Last Login:</dt>
                        <dd class="col-sm-7 small">
                            <?php if ($userData['last_login']): ?>
                                <?= date('M d, Y H:i', strtotime($userData['last_login'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </dd>
                        
                        <dt class="col-sm-5 text-muted small">Password Changed:</dt>
                        <dd class="col-sm-7 small">
                            <?php if ($userData['password_changed_at']): ?>
                                <?= date('M d, Y', strtotime($userData['password_changed_at'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="bx bx-shield"></i> Security Tips
                    </h6>
                    <ul class="small mb-0">
                        <li>Change your password regularly</li>
                        <li>Use a strong, unique password</li>
                        <li>Never share your credentials</li>
                        <li>Log out on shared devices</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bx-show');
        icon.classList.add('bx-hide');
    } else {
        field.type = 'password';
        icon.classList.remove('bx-hide');
        icon.classList.add('bx-show');
    }
}

// Password validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    if (!/[0-9]/.test(newPassword)) {
        e.preventDefault();
        alert('Password must contain at least one number!');
        return false;
    }
    
    if (!/[A-Z]/.test(newPassword)) {
        e.preventDefault();
        alert('Password must contain at least one uppercase letter!');
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
