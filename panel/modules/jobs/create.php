<?php
/**
 * Create Job Form
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('jobs', 'create');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Generate job code
$job_code = 'JOB' . date('Ymd') . strtoupper(substr(uniqid(), -4));

// Get clients
$clientsSQL = "
    SELECT client_code, company_name 
    FROM clients 
    WHERE status = 'active' 
    AND deleted_at IS NULL 
    ORDER BY company_name
";
$clients = $conn->query($clientsSQL)->fetch_all(MYSQLI_ASSOC);

// Get recruiters for assignment
$recruitersSQL = "
    SELECT user_code, name 
    FROM users 
    WHERE is_active = 1 
    ORDER BY name
";
$recruiters = $conn->query($recruitersSQL)->fetch_all(MYSQLI_ASSOC);

// Pre-fill client if passed
$preselected_client = input('client_code', '');

$pageTitle = 'Create New Job';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Jobs', 'url' => '/panel/modules/jobs/?action=list'],
    ['title' => 'Create', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Job Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="handlers/create.php" id="jobForm">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="job_code" value="<?= $job_code ?>">
                    
                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary">Basic Information</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Job Code</label>
                            <input type="text" class="form-control" value="<?= $job_code ?>" readonly>
                            <small class="text-muted">Auto-generated</small>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Client <span class="text-danger">*</span></label>
                            <select name="client_code" class="form-select" required>
                                <option value="">Select Client...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['client_code'] ?>" 
                                            <?= $preselected_client === $client['client_code'] ? 'selected' : '' ?>>
                                        <?= escape($client['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Job Title <span class="text-danger">*</span></label>
                            <input type="text" name="job_title" class="form-control" required 
                                   placeholder="e.g., Senior PHP Developer">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Job Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="8" required 
                                      placeholder="Describe the role, responsibilities, and requirements..."></textarea>
                            <small class="text-muted">This will be visible on public job board when published</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" 
                                   value="Belgium" placeholder="e.g., Belgium, Remote, Hybrid">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Number of Positions</label>
                            <input type="number" name="positions_total" class="form-control" 
                                   value="1" min="1" max="100">
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
                                   step="0.01" placeholder="40000">
                            <small class="text-muted">Annual salary in EUR</small>
                        </div>
                        
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Maximum Salary (EUR)</label>
                            <input type="number" name="salary_max" class="form-control" 
                                   step="0.01" placeholder="60000">
                            <small class="text-muted">Annual salary in EUR</small>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" name="show_salary" value="1" 
                                       class="form-check-input" id="showSalaryCheck">
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
                                            <?= $recruiter['user_code'] === $user['user_code'] ? 'selected' : '' ?>>
                                        <?= escape($recruiter['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Primary recruiter handling this job</small>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="Add internal notes (not visible to public)..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="pt-3 border-top">
                        <button type="submit" name="action" value="save_draft" class="btn btn-secondary me-2">
                            <i class="bx bx-save"></i> Save as Draft
                        </button>
                        <button type="submit" name="action" value="submit_approval" class="btn btn-primary">
                            <i class="bx bx-check-circle"></i> Save & Submit for Approval
                        </button>
                        <a href="?action=list" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Help Panel -->
    <div class="col-lg-4">
        <div class="card bg-label-info mb-3">
            <div class="card-body">
                <h6><i class="bx bx-info-circle"></i> Quick Tips</h6>
                <ul class="mb-0 small">
                    <li class="mb-2">Job Code is auto-generated</li>
                    <li class="mb-2">Description will be public when published</li>
                    <li class="mb-2">Salary info is optional</li>
                    <li class="mb-2">Save as draft to edit later</li>
                    <li class="mb-2">Submit for approval to publish</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Workflow</h6>
            </div>
            <div class="card-body">
                <ol class="small mb-0">
                    <li class="mb-2">Create job (draft)</li>
                    <li class="mb-2">Submit for approval</li>
                    <li class="mb-2">Manager approves</li>
                    <li class="mb-2">Job goes live</li>
                    <li class="mb-2">Accept submissions</li>
                    <li class="mb-2">Make placements</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>