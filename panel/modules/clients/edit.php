<?php
/**
 * Edit Client Form
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('clients', 'edit');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get client
$client_code = input('code');
if (!$client_code) {
    redirectBack('Client not found');
}

$stmt = $conn->prepare("SELECT * FROM clients WHERE client_code = ?");
$stmt->bind_param("s", $client_code);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    redirectBack('Client not found');
}

// Get account managers
$managersSQL = "
    SELECT user_code, name 
    FROM users 
    WHERE level IN ('admin', 'manager', 'recruiter') 
    AND is_active = 1 
    ORDER BY name
";
$managers = $conn->query($managersSQL)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Edit Client';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Clients', 'url' => '/panel/modules/clients/?action=list'],
    ['title' => $client['company_name'], 'url' => '?action=view&code=' . $client_code],
    ['title' => 'Edit', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Client Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="handlers/update.php">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="client_code" value="<?= escape($client_code) ?>">
                    
                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">Basic Information</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Client Code</label>
                            <input type="text" class="form-control" value="<?= escape($client_code) ?>" readonly>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required 
                                   value="<?= escape($client['company_name']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" 
                                   value="<?= escape($client['contact_person']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= escape($client['email']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?= escape($client['phone']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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
                                            <?= $client['account_manager'] === $manager['user_code'] ? 'selected' : '' ?>>
                                        <?= escape($manager['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="4"><?= escape($client['notes']) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="pt-3 border-top">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bx bx-save"></i> Update Client
                        </button>
                        <a href="?action=view&code=<?= escape($client_code) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Info Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Client Statistics</h6>
            </div>
            <div class="card-body">
                <?php
                $statsSQL = "
                    SELECT 
                        (SELECT COUNT(*) FROM jobs WHERE client_code = ?) as total_jobs,
                        (SELECT COUNT(*) FROM jobs WHERE client_code = ? AND status IN ('open', 'filling')) as active_jobs,
                        (SELECT COUNT(*) FROM submissions s JOIN jobs j ON s.job_code = j.job_code 
                         WHERE j.client_code = ? AND s.client_status = 'placed') as total_placements
                ";
                $stmt = $conn->prepare($statsSQL);
                $stmt->bind_param("sss", $client_code, $client_code, $client_code);
                $stmt->execute();
                $stats = $stmt->get_result()->fetch_assoc();
                ?>
                <p><strong>Total Jobs:</strong> <?= $stats['total_jobs'] ?></p>
                <p><strong>Active Jobs:</strong> <?= $stats['active_jobs'] ?></p>
                <p><strong>Total Placements:</strong> <?= $stats['total_placements'] ?></p>
                <hr>
                <p class="text-muted small mb-0">
                    <strong>Created:</strong> <?= date('M d, Y', strtotime($client['created_at'])) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>