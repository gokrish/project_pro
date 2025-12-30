<?php
/**
 * Edit Job Form
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('jobs', 'edit');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get job code
$job_code = input('code');
if (!$job_code) {
    redirectBack('Job not found');
}

// Get job
$stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
$stmt->bind_param("s", $job_code);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    redirectBack('Job not found');
}

// Get clients
$clientsSQL = "
    SELECT client_code, company_name 
    FROM clients 
    WHERE status = 'active' 
    AND deleted_at IS NULL 
    ORDER BY company_name
";
$clients = $conn->query($clientsSQL)->fetch_all(MYSQLI_ASSOC);

// Get recruiters
$recruitersSQL = "
    SELECT user_code, name 
    FROM users 
    WHERE is_active = 1 
    ORDER BY name
";
$recruiters = $conn->query($recruitersSQL)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Edit Job';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Jobs', 'url' => '/panel/modules/jobs/?action=list'],
    ['title' => $job['job_title'], 'url' => '?action=view&code=' . $job_code],
    ['title' => 'Edit', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Job Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="handlers/update.php">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="job_code" value="<?= escape($job_code) ?>">
                    
                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">Basic Information</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Job Code</label>
                            <input type="text" class="form-control" value="<?= escape($job_code) ?>" readonly>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Client <span class="text-danger">*</span></label>
                            <select name="client_code" class="form-select" required>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['client_code'] ?>" 
                                            <?= $job['client_code'] === $client['client_code'] ? 'selected' : '' ?>>
                                        <?= escape($client['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Job Title <span class="text-danger">*</span></label>
                            <input type="text" name="job_title" class="form-control" required 
                                   value="<?= escape($job['job_title']) ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Job Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="8" required><?= escape($job['description']) ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" 
                                   value="<?= escape($job['location']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Number of Positions</label>
                            <input type="number" name="positions_total" class="form-control" 
                                   value="<?= $job['positions_total'] ?>" min="1" max="100">
                        </div>
                    </div>
                    
                    <!-- Salary Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">Salary Information</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Minimum Salary (EUR)</label>
                            <input type="number" name="salary_min" class="form-control" 
                                   step="0.01" value="<?= $job['salary_min'] ?>">
                        </div>
                        
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Maximum Salary (EUR)</label>
                            <input type="number" name="salary_max" class="form-control" 
                                   step="0.01" value="<?= $job['salary_max'] ?>">
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" name="show_salary" value="1" 
                                       class="form-check-input" id="showSalaryCheck"
                                       <?= $job['show_salary'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="showSalaryCheck">
                                    Show on website
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Internal Management -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">Internal Management</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assign Recruiter</label>
                            <select name="assigned_recruiter" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach ($recruiters as $recruiter): ?>
                                    <option value="<?= $recruiter['user_code'] ?>" 
                                            <?= $job['assigned_recruiter'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                                        <?= escape($recruiter['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="4"><?= escape($job['notes']) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="pt-3 border-top">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bx bx-save"></i> Update Job
                        </button>
                        <a href="?action=view&code=<?= escape($job_code) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Info Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Job Status</h6>
            </div>
            <div class="card-body">
                <p><strong>Status:</strong> 
                    <span class="badge bg-secondary"><?= ucfirst($job['status']) ?></span>
                </p>
                <p><strong>Approval:</strong> 
                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $job['approval_status'])) ?></span>
                </p>
                <p><strong>Submissions:</strong> <?= $job['total_submissions'] ?></p>
                <p><strong>Placements:</strong> <?= $job['total_placements'] ?></p>
                <hr>
                <p class="text-muted small mb-0">
                    <strong>Created:</strong> <?= date('M d, Y', strtotime($job['created_at'])) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>