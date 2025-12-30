<?php
/**
 * Approve Job Page
 * Managers/Admins approve or reject job postings
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;

// Check permission
Permission::require('jobs', 'approve');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get job code
$jobCode = input('code');
if (!$jobCode) {
    redirectWithMessage('/panel/modules/jobs/list.php', 'Job not found', 'error');
}

// Get job details
$stmt = $conn->prepare("
    SELECT j.*,
           c.company_name,
           u1.name as created_by_name,
           u2.name as assigned_to_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u1 ON j.created_by = u1.user_code
    LEFT JOIN users u2 ON j.assigned_to = u2.user_code
    WHERE j.job_code = ? AND j.deleted_at IS NULL
");
$stmt->bind_param("s", $jobCode);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    redirectWithMessage('/panel/modules/jobs/list.php', 'Job not found', 'error');
}

// Check if job is pending approval
if ($job['approval_status'] !== 'pending_approval') {
    redirectWithMessage(
        "/panel/modules/jobs/view.php?code={$jobCode}",
        'This job is not pending approval',
        'warning'
    );
}

// Page configuration
$pageTitle = 'Approve Job - ' . $job['job_title'];
$breadcrumbs = [
    ['title' => 'Jobs', 'url' => '/panel/modules/jobs/list.php'],
    ['title' => 'Approve', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <!-- Approval Notice -->
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <span class="alert-icon">
                <i class="bx bx-bell"></i>
            </span>
            <div>
                <strong>Approval Required</strong><br>
                This job is waiting for your approval before it can be published.
            </div>
        </div>
        
        <!-- Job Preview Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Job Details for Approval</h5>
            </div>
            <div class="card-body">
                <!-- Job Info -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Job Title</label>
                        <p><?= htmlspecialchars($job['job_title']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Job Code</label>
                        <p><?= htmlspecialchars($job['job_code']) ?></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Client</label>
                        <p><?= htmlspecialchars($job['company_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Created By</label>
                        <p><?= htmlspecialchars($job['created_by_name']) ?></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Assigned To</label>
                        <p><?= htmlspecialchars($job['assigned_to_name'] ?? 'Unassigned') ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Submitted On</label>
                        <p><?= date('M d, Y g:i A', strtotime($job['submitted_for_approval_at'])) ?></p>
                    </div>
                </div>
                
                <?php if ($job['location']): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Location</label>
                        <p><?= htmlspecialchars($job['location']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Type</label>
                        <p><?= ucwords($job['employment_type']) ?> - <?= ucwords($job['work_type']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($job['salary_min'] || $job['salary_max']): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Salary Range</label>
                        <p>
                            <?php if ($job['salary_min'] && $job['salary_max']): ?>
                                €<?= number_format($job['salary_min']) ?> - €<?= number_format($job['salary_max']) ?>
                            <?php elseif ($job['salary_min']): ?>
                                From €<?= number_format($job['salary_min']) ?>
                            <?php elseif ($job['salary_max']): ?>
                                Up to €<?= number_format($job['salary_max']) ?>
                            <?php endif; ?>
                            / <?= $job['salary_period'] ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <!-- Job Description -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Job Description</label>
                    <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                        <?= $job['description'] ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Approval Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Approval Decision</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="handlers/approve-job.php" id="approvalForm">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="job_code" value="<?= htmlspecialchars($job['job_code']) ?>">
                    <input type="hidden" name="action" id="approvalAction" value="">
                    
                    <!-- Rejection Reason (shown when rejecting) -->
                    <div id="rejectionReasonField" style="display: none;" class="mb-3">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea class="form-control" name="rejection_reason" rows="4" 
                                  placeholder="Please provide a reason for rejection so the creator can make necessary changes..."></textarea>
                    </div>
                    
                    <!-- Approval Note (optional) -->
                    <div class="mb-3">
                        <label class="form-label">Internal Note (Optional)</label>
                        <textarea class="form-control" name="approval_note" rows="2" 
                                  placeholder="Add any internal notes about this approval decision..."></textarea>
                    </div>
                    
                    <!-- Publish immediately checkbox -->
                    <div id="publishCheckbox" class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="publish_immediately" 
                                   value="1" checked id="publishImmediately">
                            <label class="form-check-label" for="publishImmediately">
                                Publish job immediately after approval
                            </label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between">
                        <a href="list.php?tab=pending_approval" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        
                        <div>
                            <button type="button" class="btn btn-danger me-2" onclick="rejectJob()">
                                <i class="bx bx-x-circle"></i> Reject
                            </button>
                            <button type="button" class="btn btn-success" onclick="approveJob()">
                                <i class="bx bx-check-circle"></i> Approve Job
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function approveJob() {
    if (!confirm('Approve this job posting?')) return;
    
    document.getElementById('approvalAction').value = 'approve';
    document.getElementById('approvalForm').submit();
}

function rejectJob() {
    // Show rejection reason field
    document.getElementById('rejectionReasonField').style.display = 'block';
    document.getElementById('publishCheckbox').style.display = 'none';
    document.querySelector('[name="rejection_reason"]').required = true;
    
    if (!confirm('Reject this job posting? Make sure to provide a clear reason.')) {
        // Reset if cancelled
        document.getElementById('rejectionReasonField').style.display = 'none';
        document.getElementById('publishCheckbox').style.display = 'block';
        document.querySelector('[name="rejection_reason"]').required = false;
        return;
    }
    
    const reason = document.querySelector('[name="rejection_reason"]').value.trim();
    if (!reason) {
        alert('Please provide a rejection reason');
        document.querySelector('[name="rejection_reason"]').focus();
        return;
    }
    
    document.getElementById('approvalAction').value = 'reject';
    document.getElementById('approvalForm').submit();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
