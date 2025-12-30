<?php
/**
 * Job Approval Page
 * Manager interface to review and approve/reject jobs
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('jobs', 'approve');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get job code
$job_code = input('code');
if (!$job_code) {
    redirectBack('Job not found');
}

// Get job details
$sql = "
    SELECT 
        j.*,
        c.company_name,
        c.contact_person,
        c.email as client_email,
        u_created.name as created_by_name,
        u_assigned.name as assigned_recruiter_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u_created ON j.created_by = u_created.user_code
    LEFT JOIN users u_assigned ON j.assigned_recruiter = u_assigned.user_code
    WHERE j.job_code = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $job_code);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    redirectBack('Job not found');
}

// Check if already approved/rejected
if ($job['approval_status'] !== 'pending_approval') {
    redirectWithMessage(
        "/panel/modules/jobs/?action=view&code={$job_code}",
        'This job has already been ' . $job['approval_status'],
        'warning'
    );
}

$pageTitle = 'Approve Job';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Jobs', 'url' => '/panel/modules/jobs/?action=list'],
    ['title' => 'Approve Job', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.approval-card {
    border-left: 4px solid #ffc107;
}
.info-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}
</style>

<!-- Header Alert -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning d-flex align-items-center">
            <i class="bx bx-info-circle fs-4 me-3"></i>
            <div>
                <strong>Job Pending Your Approval</strong><br>
                Submitted <?= timeAgo($job['submitted_for_approval_at']) ?> by <?= escape($job['created_by_name']) ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Job Details -->
    <div class="col-lg-8">
        <div class="card approval-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-briefcase"></i> Job Details for Review
                </h5>
            </div>
            <div class="card-body">
                <!-- Basic Info -->
                <div class="info-section">
                    <h6 class="text-primary mb-3">Basic Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <strong>Job Code:</strong><br>
                            <?= escape($job['job_code']) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Client:</strong><br>
                            <?= escape($job['company_name']) ?>
                        </div>
                        <div class="col-12 mb-2">
                            <strong>Job Title:</strong><br>
                            <h5 class="mb-0"><?= escape($job['job_title']) ?></h5>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Location:</strong><br>
                            <?= escape($job['location']) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Positions:</strong><br>
                            <?= $job['positions_total'] ?> position(s)
                        </div>
                    </div>
                </div>

                <!-- Salary Info -->
                <?php if ($job['salary_min'] || $job['salary_max']): ?>
                <div class="info-section">
                    <h6 class="text-primary mb-3">Salary Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <strong>Salary Range:</strong><br>
                            €<?= number_format($job['salary_min'], 0) ?> - €<?= number_format($job['salary_max'], 0) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Public Visibility:</strong><br>
                            <?php if ($job['show_salary']): ?>
                                <span class="badge bg-success">Will be shown publicly</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not public</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Description -->
                <div class="info-section">
                    <h6 class="text-primary mb-3">Job Description</h6>
                    <div class="job-description">
                        <?= nl2br(escape($job['description'])) ?>
                    </div>
                </div>

                <!-- Internal Notes -->
                <?php if ($job['notes']): ?>
                <div class="info-section">
                    <h6 class="text-primary mb-3">Internal Notes</h6>
                    <div class="text-muted">
                        <?= nl2br(escape($job['notes'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Assignment -->
                <div class="info-section">
                    <h6 class="text-primary mb-3">Assignment</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Created By:</strong><br>
                            <?= escape($job['created_by_name']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Assigned Recruiter:</strong><br>
                            <?= escape($job['assigned_recruiter_name'] ?: 'Unassigned') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Actions -->
    <div class="col-lg-4">
        <!-- Approve Action -->
        <div class="card border-success mb-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bx bx-check-circle"></i> Approve Job</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Approving this job will:
                </p>
                <ul class="small">
                    <li>Change status to "Open"</li>
                    <li>Generate public reference number</li>
                    <li>Publish on job board</li>
                    <li>Allow submissions</li>
                    <li>Notify recruiter</li>
                </ul>
                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="bx bx-check-circle"></i> Approve & Publish
                </button>
            </div>
        </div>

        <!-- Reject Action -->
        <div class="card border-danger mb-3">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="bx bx-x-circle"></i> Reject Job</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Rejecting this job will:
                </p>
                <ul class="small">
                    <li>Return to draft status</li>
                    <li>Not be published</li>
                    <li>Recruiter can edit & resubmit</li>
                    <li>Notify recruiter with reason</li>
                </ul>
                <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bx bx-x-circle"></i> Reject with Reason
                </button>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Client Information</h6>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>Company:</strong><br>
                    <?= escape($job['company_name']) ?>
                </p>
                <?php if ($job['contact_person']): ?>
                <p class="mb-2">
                    <strong>Contact:</strong><br>
                    <?= escape($job['contact_person']) ?>
                </p>
                <?php endif; ?>
                <?php if ($job['client_email']): ?>
                <p class="mb-0">
                    <strong>Email:</strong><br>
                    <a href="mailto:<?= escape($job['client_email']) ?>">
                        <?= escape($job['client_email']) ?>
                    </a>
                </p>
                <?php endif; ?>
                <hr>
                <a href="../clients/?action=view&code=<?= escape($job['client_code']) ?>" 
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="bx bx-link-external"></i> View Client
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/approve-job.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="job_code" value="<?= escape($job_code) ?>">
            
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bx bx-check-circle"></i> Approve Job</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <strong>This job will be published immediately!</strong><br>
                        It will be visible on the public job board and start accepting submissions.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Approval Notes (Optional)</label>
                        <textarea name="approval_notes" class="form-control" rows="3" 
                                  placeholder="Add any comments for the recruiter..."></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmApprove" required>
                        <label class="form-check-label" for="confirmApprove">
                            I confirm this job is ready to be published
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-check-circle"></i> Approve & Publish
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/reject-job.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="job_code" value="<?= escape($job_code) ?>">
            
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bx bx-x-circle"></i> Reject Job</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>This job will be returned to draft status.</strong><br>
                        The recruiter can edit and resubmit after addressing your feedback.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required
                                  placeholder="Explain what needs to be improved..."></textarea>
                        <small class="text-muted">Be specific so the recruiter knows what to fix</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-x-circle"></i> Reject Job
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>