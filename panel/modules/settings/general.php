<?php
/**
 * Settings - General Settings
 * SIMPLIFIED: Password Change ONLY
 * 
 * @version 5.0 - Production Ready
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{CSRFToken, Auth};

// Get current user
$currentUser = Auth::user();

// Page config
$pageTitle = 'General Settings';
$breadcrumbs = [
    ['title' => 'Settings', 'url' => '#'],
    ['title' => 'General', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-cog me-2"></i>
                General Settings
            </h4>
            <p class="text-muted mb-0">Manage your account settings</p>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/flash-messages.php'; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-key"></i> Change Password
                    </h5>
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
                                       placeholder="Enter your current password"
                                       required>
                                <button class="btn btn-outline-secondary" 
                                        type="button"
                                        onclick="togglePasswordVisibility('current_password')">
                                    <i class="bx bx-show" id="current_password_icon"></i>
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
                                       placeholder="Enter new password"
                                       required
                                       minlength="6">
                                <button class="btn btn-outline-secondary" 
                                        type="button"
                                        onclick="togglePasswordVisibility('new_password')">
                                    <i class="bx bx-show" id="new_password_icon"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <i class="bx bx-info-circle"></i>
                                Must be at least 6 characters, contain 1 number and 1 uppercase letter
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password"
                                       class="form-control" 
                                       placeholder="Re-enter new password"
                                       required
                                       minlength="6">
                                <button class="btn btn-outline-secondary" 
                                        type="button"
                                        onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="bx bx-show" id="confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Help Panel -->
        <div class="col-lg-4">
            <!-- Password Requirements -->
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="bx bx-shield"></i> Password Requirements
                    </h6>
                    <ul class="mb-0 small">
                        <li>Minimum 6 characters long</li>
                        <li>At least 1 number (0-9)</li>
                        <li>At least 1 uppercase letter (A-Z)</li>
                        <li>No special characters required</li>
                    </ul>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="bx bx-bulb"></i> Security Tips
                    </h6>
                    <ul class="mb-0 small">
                        <li>Change your password regularly</li>
                        <li>Use a unique password (don't reuse)</li>
                        <li>Never share your password</li>
                        <li>Use a password manager</li>
                        <li>Log out on shared computers</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
/**
 * Toggle password visibility
 */
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
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

/**
 * Form validation
 */
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Check if new password is different from current
    if (currentPassword === newPassword) {
        e.preventDefault();
        alert('New password must be different from current password!');
        return false;
    }
    
    // Check password match
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    // Check password length
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    // Check for number
    if (!/[0-9]/.test(newPassword)) {
        e.preventDefault();
        alert('Password must contain at least one number!');
        return false;
    }
    
    // Check for uppercase
    if (!/[A-Z]/.test(newPassword)) {
        e.preventDefault();
        alert('Password must contain at least one uppercase letter!');
        return false;
    }
    
    return true;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
