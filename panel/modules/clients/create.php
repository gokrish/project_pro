<?php
/**
 * Create Client Form
 * Simplified based on schema requirements
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('clients', 'create');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Generate client code
$client_code = 'CLI' . date('Ymd') . strtoupper(substr(uniqid(), -4));

// Get account managers
$managersSQL = "
    SELECT user_code, name 
    FROM users 
    WHERE level IN ('admin', 'manager', 'recruiter') 
    AND is_active = 1 
    ORDER BY name
";
$managers = $conn->query($managersSQL)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Add New Client';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Clients', 'url' => '/panel/modules/clients/?action=list'],
    ['title' => 'Add New', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Client Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="handlers/create.php">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="client_code" value="<?= $client_code ?>">
                    
                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">Basic Information</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Client Code</label>
                            <input type="text" class="form-control" value="<?= $client_code ?>" readonly>
                            <small class="text-muted">Auto-generated</small>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required 
                                   placeholder="e.g., TechCorp Solutions NV">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" 
                                   placeholder="Primary contact name">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="contact@company.com">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   placeholder="+32 2 123 4567">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Internal Management -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">Internal Management</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Manager</label>
                            <select name="account_manager" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['user_code'] ?>" 
                                            <?= $manager['user_code'] === $user['user_code'] ? 'selected' : '' ?>>
                                        <?= escape($manager['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="Add any internal notes about this client..."></textarea>
                            <small class="text-muted">For internal use only</small>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="pt-3 border-top">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bx bx-save"></i> Save Client
                        </button>
                        <a href="?action=list" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Help Panel -->
    <div class="col-lg-4">
        <div class="card bg-label-info">
            <div class="card-body">
                <h6><i class="bx bx-info-circle"></i> Quick Tips</h6>
                <ul class="mb-0 small">
                    <li class="mb-2">Client Code is auto-generated</li>
                    <li class="mb-2">Company Name is required</li>
                    <li class="mb-2">Contact details are optional but recommended</li>
                    <li class="mb-2">Assign an Account Manager to track this client</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>