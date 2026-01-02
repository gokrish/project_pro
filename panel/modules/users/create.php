<?php
/**
 * User Management - Create New User
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, CSRFToken};

// Check permission
Permission::require('users', 'create');

// Page config
$pageTitle = 'Add New User';
$breadcrumbs = [
    ['title' => 'Administration', 'url' => '#'],
    ['title' => 'Users', 'url' => '/panel/modules/users/list.php'],
    ['title' => 'Add New', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-user-plus me-2"></i>
                Add New User
            </h4>
            <p class="text-muted mb-0">Create a new user account</p>
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
                <div class="card-header">
                    <h5 class="mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/panel/modules/users/handlers/create.php" id="createUserForm">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        
                        <!-- Name -->
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   name="name" 
                                   class="form-control" 
                                   placeholder="John Doe"
                                   required
                                   autofocus>
                        </div>
                        
                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="john@example.com"
                                   required>
                            <small class="text-muted">Will be used for login</small>
                        </div>
                        
                        <!-- Phone -->
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" 
                                   name="phone" 
                                   class="form-control" 
                                   placeholder="+32 123 456 789">
                        </div>
                        
                        <!-- Role/Level -->
                        <div class="mb-3">
                            <label class="form-label">Role/Level <span class="text-danger">*</span></label>
                            <select name="level" class="form-select" required>
                                <option value="">Select role...</option>
                                <option value="super_admin">Super Admin - Full system access</option>
                                <option value="admin">Admin - Administrative access</option>
                                <option value="manager">Manager - Team management</option>
                                <option value="senior_recruiter">Senior Recruiter - Extended access</option>
                                <option value="recruiter" selected>Recruiter - Standard access</option>
                                <option value="coordinator">Coordinator - Support tasks</option>
                            </select>
                            <small class="text-muted">
                                <i class="bx bx-info-circle"></i>
                                Determines what the user can access and do
                            </small>
                        </div>
                        
                        <!-- Password -->
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" 
                                       name="password" 
                                       id="password"
                                       class="form-control" 
                                       placeholder="Minimum 6 characters"
                                       required
                                       minlength="6">
                                <button class="btn btn-outline-secondary" 
                                        type="button"
                                        onclick="togglePassword('password')">
                                    <i class="bx bx-show" id="password-icon"></i>
                                </button>
                                <button class="btn btn-outline-primary" 
                                        type="button"
                                        onclick="generatePassword()">
                                    <i class="bx bx-refresh"></i> Generate
                                </button>
                            </div>
                            <small class="text-muted">
                                Must be at least 6 characters, include 1 number and 1 uppercase letter
                            </small>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" 
                                   name="password_confirm" 
                                   id="password_confirm"
                                   class="form-control" 
                                   placeholder="Re-enter password"
                                   required
                                   minlength="6">
                        </div>
                        
                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="is_active" 
                                       value="1"
                                       checked
                                       id="isActive">
                                <label class="form-check-label" for="isActive">
                                    Active (user can login)
                                </label>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-between">
                            <a href="/panel/modules/users/list.php" class="btn btn-outline-secondary">
                                <i class="bx bx-x"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Help Panel -->
        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="bx bx-info-circle"></i> Role Descriptions
                    </h6>
                    
                    <div class="mb-3">
                        <strong class="text-danger">Super Admin</strong>
                        <p class="small mb-0">Complete system access including user management and settings</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong class="text-warning">Admin</strong>
                        <p class="small mb-0">Administrative access, can manage users but limited settings</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong class="text-info">Manager</strong>
                        <p class="small mb-0">Team oversight, approvals, and reporting access</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong class="text-primary">Senior Recruiter</strong>
                        <p class="small mb-0">Experienced recruiter with extended permissions</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong class="text-success">Recruiter</strong>
                        <p class="small mb-0">Standard recruiter - day-to-day operations</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong class="text-secondary">Coordinator</strong>
                        <p class="small mb-0">Support role with limited access</p>
                    </div>
                </div>
            </div>
            
            <div class="card bg-light mt-3">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="bx bx-shield"></i> Password Requirements
                    </h6>
                    <ul class="small mb-0">
                        <li>Minimum 6 characters</li>
                        <li>At least 1 number</li>
                        <li>At least 1 uppercase letter</li>
                        <li>No special characters required</li>
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
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
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
 * Generate random password
 */
function generatePassword() {
    const length = 12;
    const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let password = '';
    
    // Ensure at least one uppercase and one number
    password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.charAt(Math.floor(Math.random() * 26));
    password += '0123456789'.charAt(Math.floor(Math.random() * 10));
    
    // Fill rest randomly
    for (let i = 2; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    
    // Shuffle
    password = password.split('').sort(() => Math.random() - 0.5).join('');
    
    // Set to both fields
    document.getElementById('password').value = password;
    document.getElementById('password_confirm').value = password;
    
    // Show password briefly
    document.getElementById('password').type = 'text';
    setTimeout(() => {
        document.getElementById('password').type = 'password';
    }, 3000);
    
    // Copy to clipboard
    navigator.clipboard.writeText(password).then(() => {
        alert('Password generated and copied to clipboard!\n\nPassword: ' + password);
    });
}

/**
 * Form validation
 */
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('password_confirm').value;
    
    // Check password match
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    // Check password strength
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    if (!/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one number!');
        return false;
    }
    
    if (!/[A-Z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one uppercase letter!');
        return false;
    }
    
    return true;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>