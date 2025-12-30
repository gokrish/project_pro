<?php
/**
 * Edit Application
 * File: panel/modules/applications/edit.php
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\CSRFToken;

// Check permission
Permission::require('applications', 'edit');

// Get application ID
$appId = $_GET['id'] ?? null;

if (!$appId) {
    FlashMessage::error('Application ID is required');
    header('Location: index.php?page=list');
    exit();
}

// Fetch application details
$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT 
        a.*,
        c.first_name,
        c.last_name,
        c.email as candidate_email,
        c.phone as candidate_phone,
        j.job_title,
        j.job_code,
        cl.company_name,
        cl.client_code,
        u.name as created_by_name
    FROM applications a
    LEFT JOIN candidates c ON a.candidate_code = c.can_code
    LEFT JOIN jobs j ON a.job_code = j.job_code
    LEFT JOIN clients cl ON a.client_code = cl.client_code
    LEFT JOIN users u ON a.created_by = u.user_code
    WHERE a.id = ? AND a.deleted_at IS NULL
");

$stmt->bind_param("i", $appId);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();

if (!$application) {
    FlashMessage::error('Application not found');
    header('Location: index.php?page=list');
    exit();
}

// Check ownership if user has only edit_own permission
if (!Permission::can('applications', 'edit')) {
    if (Permission::can('applications', 'edit_own')) {
        if ($application['created_by'] !== Auth::userId()) {
            header('Location: /panel/errors/403.php');
            exit();
        }
    } else {
        header('Location: /panel/errors/403.php');
        exit();
    }
}

// Fetch all active jobs for dropdown
$jobsResult = $conn->query("
    SELECT job_code, job_title, client_code 
    FROM jobs 
    WHERE status = 'open' AND deleted_at IS NULL 
    ORDER BY job_title
");
$jobs = $jobsResult->fetch_all(MYSQLI_ASSOC);

// Fetch all active candidates for dropdown
$candidatesResult = $conn->query("
    SELECT can_code, first_name, last_name, email 
    FROM candidates 
    WHERE status = 'active' AND deleted_at IS NULL 
    ORDER BY first_name, last_name
");
$candidates = $candidatesResult->fetch_all(MYSQLI_ASSOC);

// Page configuration
$pageTitle = 'Edit Application';
$breadcrumbs = [
    'Applications' => 'index.php?page=list',
    'Edit Application' => '#'
];

require_once ROOT_PATH . '/panel/includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <span class="text-muted fw-light">Applications /</span> Edit Application
            </h4>
            <p class="text-muted mb-0">Update application details</p>
        </div>
        <div>
            <a href="index.php?page=list" class="btn btn-secondary me-2">
                <i class="bx bx-arrow-back me-1"></i> Back to List
            </a>
            <a href="view.php?id=<?= $application['id'] ?>" class="btn btn-outline-primary">
                <i class="bx bx-show me-1"></i> View Details
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php require_once ROOT_PATH . '/panel/includes/flash-messages.php'; ?>

    <form id="applicationForm" method="POST" action="handlers/edit_handler.php">
        <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Application Information Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Application Information</h5>
                    </div>
                    <div class="card-body">
                        <!-- Application Code (Read-only) -->
                        <div class="mb-3">
                            <label class="form-label">Application Code</label>
                            <input type="text" 
                                   class="form-control bg-light" 
                                   value="<?= escape($application['application_code']) ?>" 
                                   readonly>
                        </div>

                        <!-- Candidate Selection -->
                        <div class="mb-3">
                            <label class="form-label" for="candidate_code">
                                Candidate <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="candidate_code" name="candidate_code" required>
                                <option value="">Select Candidate</option>
                                <?php foreach ($candidates as $candidate): ?>
                                <option value="<?= escape($candidate['can_code']) ?>" 
                                        <?= $candidate['can_code'] === $application['candidate_code'] ? 'selected' : '' ?>>
                                    <?= escape($candidate['first_name'] . ' ' . $candidate['last_name']) ?> 
                                    (<?= escape($candidate['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Job Selection -->
                        <div class="mb-3">
                            <label class="form-label" for="job_code">
                                Job <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="job_code" name="job_code" required>
                                <option value="">Select Job</option>
                                <?php foreach ($jobs as $job): ?>
                                <option value="<?= escape($job['job_code']) ?>" 
                                        <?= $job['job_code'] === $application['job_code'] ? 'selected' : '' ?>>
                                    <?= escape($job['job_title']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label" for="status">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="applied" <?= $application['status'] === 'applied' ? 'selected' : '' ?>>Applied</option>
                                <option value="screening" <?= $application['status'] === 'screening' ? 'selected' : '' ?>>Screening</option>
                                <option value="interviewing" <?= $application['status'] === 'interviewing' ? 'selected' : '' ?>>Interviewing</option>
                                <option value="offered" <?= $application['status'] === 'offered' ? 'selected' : '' ?>>Offered</option>
                                <option value="placed" <?= $application['status'] === 'placed' ? 'selected' : '' ?>>Placed</option>
                                <option value="rejected" <?= $application['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="withdrawn" <?= $application['status'] === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                            </select>
                        </div>

                        <!-- Application Date -->
                        <div class="mb-3">
                            <label class="form-label" for="application_date">Application Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="application_date" 
                                   name="application_date"
                                   value="<?= escape($application['application_date']) ?>">
                        </div>

                        <!-- Expected Salary -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="expected_salary">Expected Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="expected_salary" 
                                           name="expected_salary"
                                           value="<?= escape($application['expected_salary']) ?>"
                                           step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="offered_salary">Offered Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="offered_salary" 
                                           name="offered_salary"
                                           value="<?= escape($application['offered_salary']) ?>"
                                           step="0.01">
                                </div>
                            </div>
                        </div>

                        <!-- Notice Period -->
                        <div class="mb-3">
                            <label class="form-label" for="notice_period">Notice Period (days)</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="notice_period" 
                                   name="notice_period"
                                   value="<?= escape($application['notice_period']) ?>">
                        </div>

                        <!-- Availability Date -->
                        <div class="mb-3">
                            <label class="form-label" for="availability_date">Availability Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="availability_date" 
                                   name="availability_date"
                                   value="<?= escape($application['availability_date']) ?>">
                        </div>

                        <!-- Notes -->
                        <div class="mb-3">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="4"><?= escape($application['notes']) ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="index.php?page=list" class="btn btn-label-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Application Stats Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Application Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Candidate</small>
                            <div>
                                <strong><?= escape($application['first_name'] . ' ' . $application['last_name']) ?></strong>
                            </div>
                            <div class="text-muted small"><?= escape($application['candidate_email']) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Job</small>
                            <div><strong><?= escape($application['job_title']) ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Company</small>
                            <div><?= escape($application['company_name']) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Created By</small>
                            <div><?= escape($application['created_by_name']) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Created At</small>
                            <div><?= formatDateTime($application['created_at']) ?></div>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted">Last Updated</small>
                            <div><?= formatDateTime($application['updated_at']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (Permission::can('applications', 'schedule_interview')): ?>
                            <a href="view.php?id=<?= $application['id'] ?>#interviews" 
                               class="btn btn-outline-primary">
                                <i class="bx bx-calendar me-1"></i> Schedule Interview
                            </a>
                            <?php endif; ?>
                            
                            <?php if (Permission::can('applications', 'make_offer')): ?>
                            <a href="view.php?id=<?= $application['id'] ?>#offers" 
                               class="btn btn-outline-success">
                                <i class="bx bx-envelope me-1"></i> Make Offer
                            </a>
                            <?php endif; ?>
                            
                            <?php if (Permission::can('applications', 'delete')): ?>
                            <button type="button" 
                                    class="btn btn-outline-danger" 
                                    onclick="deleteApplication(<?= $application['id'] ?>)">
                                <i class="bx bx-trash me-1"></i> Delete Application
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Form submission with validation
document.getElementById('applicationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Basic validation
    const candidateCode = document.getElementById('candidate_code').value;
    const jobCode = document.getElementById('job_code').value;
    const status = document.getElementById('status').value;
    
    if (!candidateCode || !jobCode || !status) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Submit form
    this.submit();
});

// Delete application function
function deleteApplication(appId) {
    if (!confirm('Are you sure you want to delete this application? This action cannot be undone.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'handlers/delete_handler.php';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'application_id';
    idInput.value = appId;
    
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = 'csrf_token';
    tokenInput.value = '<?= CSRFToken::generate() ?>';
    
    form.appendChild(idInput);
    form.appendChild(tokenInput);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once ROOT_PATH . '/panel/includes/footer.php'; ?>