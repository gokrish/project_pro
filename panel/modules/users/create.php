<?php
/**
 * Create User Form
 * File: panel/modules/users/create.php
 * Included by index.php
 */

// Load common bootstrap
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\CSRFToken;


// Include header
require_once ROOT_PATH . '/panel/includes/header.php';

?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <span class="text-muted fw-light">User Management /</span> Add New User
            </h4>
            <p class="text-muted mb-0">Create a new system user account</p>
        </div>
        <a href="?action=list&ss_id=<?php echo $token; ?>" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Users
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <div id="formError" class="alert alert-danger d-none mb-3"></div>
                    <div id="formSuccess" class="alert alert-success d-none mb-3"></div>

                    <form id="userForm" method="POST" action="handlers/user_save_handler.php">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="token" value="<?php echo CSRFToken::generate(); ?>">

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label class="form-label" for="name">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name" 
                                   name="name" 
                                   required 
                                   maxlength="100"
                                   placeholder="e.g., John Doe">
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
                                   required 
                                   maxlength="255"
                                   placeholder="user@example.com">
                            <small class="text-muted">This will be used for login</small>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label class="form-label" for="password">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required 
                                       minlength="8">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bx bx-show"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label class="form-label" for="confirm_password">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required 
                                   minlength="8">
                        </div>

                        <!-- Role -->
                        <div class="mb-3">
                            <label class="form-label" for="level">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="level" name="level" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="user">Recruiter</option>
                            </select>
                            <small class="text-muted">
                                <strong>Admin:</strong> Full system access<br>
                                <strong>Recruiter:</strong> Can manage candidates and applications
                            </small>
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label" for="status">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <!-- Phone (Optional) -->
                        <div class="mb-3">
                            <label class="form-label" for="phone">
                                Phone Number
                            </label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   maxlength="50"
                                   placeholder="+32 123 456 789">
                        </div>

                        <!-- Notes (Optional) -->
                        <div class="mb-3">
                            <label class="form-label" for="notes">
                                Notes
                            </label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="3"
                                      placeholder="Any additional notes about this user"></textarea>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="pt-3">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bx bx-save me-1"></i> Create User
                            </button>
                            <a href="?action=list&ss_id=<?php echo $token; ?>" class="btn btn-label-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Help Panel -->
        <div class="col-lg-4">
            <div class="card bg-label-info mb-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bx bx-info-circle me-1"></i> User Roles
                    </h6>
                    <p class="mb-2"><strong>Administrator:</strong></p>
                    <ul class="small mb-3">
                        <li>Full system access</li>
                        <li>Manage users</li>
                        <li>System settings</li>
                        <li>Approve jobs</li>
                    </ul>
                    <p class="mb-2"><strong>Recruiter:</strong></p>
                    <ul class="small mb-0">
                        <li>Manage candidates</li>
                        <li>Process applications</li>
                        <li>Screen CVs</li>
                        <li>Track placements</li>
                    </ul>
                </div>
            </div>

            <div class="card border-warning">
                <div class="card-body">
                    <h6 class="card-title text-warning">
                        <i class="bx bx-lock-alt me-1"></i> Password Policy
                    </h6>
                    <ul class="small mb-0">
                        <li>Minimum 8 characters</li>
                        <li>User can change later</li>
                        <li>Passwords are encrypted</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Toggle password visibility
    $('#togglePassword').on('click', function() {
        const passwordField = $('#password');
        const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', type);
        $(this).find('i').toggleClass('bx-show bx-hide');
    });

    // Form submission
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate passwords match
        const password = $('#password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (password !== confirmPassword) {
            $('#formError').text('Passwords do not match').removeClass('d-none');
            return;
        }
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: 'handlers/user_save_handler.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                $('#formError, #formSuccess').addClass('d-none');
                $('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Creating...');
            },
            success: function(response) {
                if (response.success) {
                    $('#formSuccess').text(response.message).removeClass('d-none');
                    
                    // Redirect to list after 1.5 seconds
                    setTimeout(function() {
                        window.location.href = '?action=list&ss_id=<?php echo $token; ?>';
                    }, 1500);
                } else {
                    $('#formError').text(response.message).removeClass('d-none');
                    $('button[type="submit"]').prop('disabled', false).html('<i class="bx bx-save me-1"></i> Create User');
                }
            },
            error: function(xhr, status, error) {
                $('#formError').text('Network error: ' + error).removeClass('d-none');
                $('button[type="submit"]').prop('disabled', false).html('<i class="bx bx-save me-1"></i> Create User');
            }
        });
    });
});
</script>