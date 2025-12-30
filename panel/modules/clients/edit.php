<?php
/**
 * Edit Client Form
 * File: panel/modules/clients/edit.php
 */
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;


if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
    Permission::require('clients', 'edit');
}

$db = Database::getInstance();
$conn = $db->getConnection();
$user = Auth::user();

// Get client
$clientCode = input('code', '');
$clientId = (int)input('id', 0);

if (empty($clientCode) && !$clientId) {
    throw new Exception('Client code or ID is required');
}

$sql = "SELECT * FROM clients WHERE " . ($clientCode ? "client_code = ?" : "client_id = ?");
$stmt = $conn->prepare($sql);
if ($clientCode) {
    $stmt->bind_param('s', $clientCode);
} else {
    $stmt->bind_param('i', $clientId);
}
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    throw new Exception('Client not found');
}

// Get recruiters
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-edit text-primary me-2"></i>Edit Client
            </h4>
            <p class="text-muted mb-0"><?= htmlspecialchars($client['company_name']) ?></p>
        </div>
        <div>
            <a href="?action=view&code=<?= urlencode($client['client_code']) ?>" class="btn btn-secondary me-2">
                <i class="bx bx-show me-1"></i> View
            </a>
            <a href="?action=list" class="btn btn-label-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="clientEditForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="client_code" value="<?= htmlspecialchars($client['client_code']) ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Client Code</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($client['client_code']) ?>" readonly style="background:#f0f0f0;">
                    </div>
                    
                    <div class="col-md-8">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="company_name" required value="<?= htmlspecialchars($client['company_name']) ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Industry</label>
                        <select class="form-select" name="industry">
                            <option value="">Select Industry</option>
                            <?php
                            $industries = ['IT & Technology', 'Finance & Banking', 'Healthcare', 'Manufacturing', 'Retail & E-commerce', 'Consulting', 'Telecommunications', 'Energy & Utilities', 'Government', 'Education', 'Other'];
                            foreach ($industries as $ind):
                            ?>
                            <option value="<?= $ind ?>" <?= $client['industry'] === $ind ? 'selected' : '' ?>><?= $ind ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <h6 class="mb-3 text-primary">Primary Contact</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="client_name" required value="<?= htmlspecialchars($client['client_name']) ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($client['email']) ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Alternative Contact</label>
                        <input type="text" class="form-control" name="contact_person" value="<?= htmlspecialchars($client['contact_person'] ?? '') ?>">
                    </div>
                </div>
                
                <h6 class="mb-3 text-primary">Address</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label">Street Address</label>
                        <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($client['city'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Country</label>
                        <select class="form-select" name="country">
                            <?php
                            $countries = ['Belgium', 'Netherlands', 'Luxembourg', 'France', 'Germany', 'United Kingdom', 'Other'];
                            foreach ($countries as $country):
                            ?>
                            <option value="<?= $country ?>" <?= ($client['country'] ?? 'Belgium') === $country ? 'selected' : '' ?>><?= $country ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <h6 class="mb-3 text-primary">Internal Management</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Account Manager <span class="text-danger">*</span></label>
                        <select class="form-select" name="account_manager" required>
                            <?php foreach ($recruiters as $recruiter): ?>
                            <option value="<?= htmlspecialchars($recruiter['user_code']) ?>"
                                    <?= $client['account_manager'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($recruiter['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Internal Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="pt-3 border-top">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bx bx-save me-1"></i> Update Client
                    </button>
                    <a href="?action=view&code=<?= urlencode($client['client_code']) ?>" class="btn btn-label-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#clientEditForm').on('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            $('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Updating...');
            
            const response = await fetch('handlers/update.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.location.href = '?action=view&code=' + result.client_code;
            } else {
                alert('Error: ' + result.message);
                $('button[type="submit"]').prop('disabled', false).html('<i class="bx bx-save me-1"></i> Update Client');
            }
        } catch (error) {
            alert('Network error. Please try again.');
            $('button[type="submit"]').prop('disabled', false).html('<i class="bx bx-save me-1"></i> Update Client');
        }
    });
});
</script>