<?php
/**
 * Create Client Form
 * File: panel/modules/clients/create.php
 */
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
    Permission::require('clients', 'create');
}

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Generate new client code
$client_code = 'CLI-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Get recruiters for account manager
$recruitersQuery = "
    SELECT user_code, name 
    FROM users 
    WHERE level IN ('recruiter', 'manager', 'admin') 
    AND is_active = 1 
    ORDER BY name
";
$recruiters = $conn->query($recruitersQuery)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-building text-primary me-2"></i>Add New Client
            </h4>
            <p class="text-muted mb-0">Create a new client company record</p>
        </div>
        <a href="?action=list" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to List
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Client Information</h5>
                </div>
                <div class="card-body">
                    <form id="clientForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        <input type="hidden" name="client_code" value="<?= htmlspecialchars($client_code) ?>">
                        
                        <!-- Basic Information -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Client Code</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($client_code) ?>" readonly style="background:#f0f0f0;">
                                <small class="text-muted">Auto-generated</small>
                            </div>
                            
                            <div class="col-md-8">
                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="company_name" required maxlength="255" placeholder="e.g., TechCorp Solutions NV">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Industry</label>
                                <select class="form-select" name="industry">
                                    <option value="">Select Industry</option>
                                    <option value="IT & Technology">IT & Technology</option>
                                    <option value="Finance & Banking">Finance & Banking</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Manufacturing">Manufacturing</option>
                                    <option value="Retail & E-commerce">Retail & E-commerce</option>
                                    <option value="Consulting">Consulting</option>
                                    <option value="Telecommunications">Telecommunications</option>
                                    <option value="Energy & Utilities">Energy & Utilities</option>
                                    <option value="Government">Government</option>
                                    <option value="Education">Education</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Primary Contact -->
                        <h6 class="mb-3 text-primary">Primary Contact</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="client_name" required maxlength="255" placeholder="e.g., John Doe">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required maxlength="255" placeholder="contact@company.com">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" maxlength="50" placeholder="+32 2 123 4567">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Alternative Contact</label>
                                <input type="text" class="form-control" name="contact_person" maxlength="255" placeholder="Optional">
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <h6 class="mb-3 text-primary">Address</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label">Street Address</label>
                                <textarea class="form-control" name="address" rows="2" placeholder="Street, building, suite"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" maxlength="100" placeholder="e.g., Brussels">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Country</label>
                                <select class="form-select" name="country">
                                    <option value="">Select Country</option>
                                    <option value="Belgium" selected>Belgium</option>
                                    <option value="Netherlands">Netherlands</option>
                                    <option value="Luxembourg">Luxembourg</option>
                                    <option value="France">France</option>
                                    <option value="Germany">Germany</option>
                                    <option value="United Kingdom">United Kingdom</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Internal Management -->
                        <h6 class="mb-3 text-primary">Internal Management</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Account Manager <span class="text-danger">*</span></label>
                                <select class="form-select" name="account_manager" required>
                                    <option value="">Assign to...</option>
                                    <?php foreach ($recruiters as $recruiter): ?>
                                    <option value="<?= htmlspecialchars($recruiter['user_code']) ?>"
                                            <?= $recruiter['user_code'] === $user['user_code'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($recruiter['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Internal Notes</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Add any internal notes..."></textarea>
                                <small class="text-muted">For internal use only</small>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="pt-3 border-top">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bx bx-save me-1"></i> Save Client
                            </button>
                            <button type="button" class="btn btn-success me-2" id="saveAndAddJob">
                                <i class="bx bx-briefcase me-1"></i> Save & Create Job
                            </button>
                            <a href="?action=list" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Help Panel -->
        <div class="col-lg-4">
            <div class="card bg-label-info">
                <div class="card-body">
                    <h6><i class="bx bx-info-circle me-2"></i>Quick Tips</h6>
                    <ul class="mb-0 small">
                        <li class="mb-2">Client Code is auto-generated</li>
                        <li class="mb-2">Company Name is the main identifier</li>
                        <li class="mb-2">Primary contact receives notifications</li>
                        <li class="mb-2">Account Manager handles this client</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#clientForm').on('submit', function(e) {
        e.preventDefault();
        submitForm(false);
    });
    
    $('#saveAndAddJob').on('click', function() {
        submitForm(true);
    });
    
    async function submitForm(redirectToJob) {
        const formData = new FormData(document.getElementById('clientForm'));
        
        try {
            $('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
            
            const response = await fetch('handlers/create.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (redirectToJob) {
                    window.location.href = '../jobs/create.php?client_code=' + result.client_code;
                } else {
                    window.location.href = '?action=list';
                }
            } else {
                alert('Error: ' + result.message);
                $('button[type="submit"]').prop('disabled', false).html('<i class="bx bx-save me-1"></i> Save Client');
            }
        } catch (error) {
            alert('Network error. Please try again.');
            $('button[type="submit"]').prop('disabled', false).html('<i class="bx bx-save me-1"></i> Save Client');
        }
    }
});
</script>