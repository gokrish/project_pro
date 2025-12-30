<?php
/**
 * Approval Dashboard
 * Manager/Admin interface for approving submissions
 */
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('submissions', 'approve');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

$pageTitle = 'Pending Approvals';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Submissions', 'url' => '/panel/modules/submissions/list.php'],
    ['title' => 'Approvals', 'url' => '']
];

// Get pending submissions
$sql = "
    SELECT 
        s.*,
        c.candidate_name,
        c.email as candidate_email,
        c.phone as candidate_phone,
        c.skills,
        c.current_position,
        c.total_experience,
        j.job_title,
        j.job_code,
        j.description as job_description,
        cl.company_name,
        u.name as submitted_by_name,
        u.email as submitted_by_email,
        DATEDIFF(NOW(), s.created_at) as days_waiting
    FROM submissions s
    JOIN candidates c ON s.candidate_code = c.candidate_code
    JOIN jobs j ON s.job_code = j.job_code
    JOIN clients cl ON j.client_code = cl.client_code
    LEFT JOIN users u ON s.submitted_by = u.user_code
    WHERE s.internal_status = 'pending'
    AND s.deleted_at IS NULL
    ORDER BY s.created_at ASC
";

$pendingSubmissions = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.approval-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid #ffc107;
}
.approval-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<?php if (empty($pendingSubmissions)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bx bx-check-circle display-1 text-success"></i>
            <h4 class="mt-3">All Caught Up!</h4>
            <p class="text-muted">No pending submissions to approve</p>
            <a href="list.php" class="btn btn-primary mt-3">
                <i class="bx bx-list-ul"></i> View All Submissions
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="bx bx-time"></i>
        <strong><?= count($pendingSubmissions) ?></strong> submission(s) waiting for your approval
    </div>

    <div class="row">
        <?php foreach ($pendingSubmissions as $sub): ?>
            <div class="col-md-6 mb-4">
                <div class="card approval-card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?= escape($sub['candidate_name']) ?>
                            </h5>
                            <span class="badge bg-warning">
                                <?= $sub['days_waiting'] ?> day<?= $sub['days_waiting'] != 1 ? 's' : '' ?> waiting
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Job Info -->
                        <div class="mb-3">
                            <h6 class="text-primary">
                                <i class="bx bx-briefcase"></i> <?= escape($sub['job_title']) ?>
                            </h6>
                            <small class="text-muted"><?= escape($sub['company_name']) ?></small>
                        </div>

                        <!-- Candidate Info -->
                        <div class="mb-3">
                            <p class="mb-1">
                                <strong>Position:</strong> <?= escape($sub['current_position'] ?: 'N/A') ?>
                            </p>
                            <p class="mb-1">
                                <strong>Experience:</strong> <?= $sub['total_experience'] ? $sub['total_experience'] . ' years' : 'N/A' ?>
                            </p>
                            <p class="mb-1">
                                <strong>Skills:</strong> 
                                <small class="text-muted"><?= escape(substr($sub['skills'], 0, 100)) ?>...</small>
                            </p>
                        </div>

                        <!-- Submission Notes -->
                        <?php if ($sub['submission_notes']): ?>
                            <div class="mb-3">
                                <strong>Recruiter Notes:</strong>
                                <div class="bg-light p-2 rounded mt-1">
                                    <small><?= nl2br(escape($sub['submission_notes'])) ?></small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Submitted By -->
                        <div class="mb-3">
                            <small class="text-muted">
                                Submitted by <strong><?= escape($sub['submitted_by_name']) ?></strong>
                                on <?= date('M d, Y g:i A', strtotime($sub['created_at'])) ?>
                            </small>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex gap-2">
                            <a href="view.php?code=<?= escape($sub['submission_code']) ?>" 
                               class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="bx bx-show"></i> View Details
                            </a>
                            <button type="button" class="btn btn-sm btn-success" 
                                    onclick="approveSubmission('<?= escape($sub['submission_code']) ?>')">
                                <i class="bx bx-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="rejectSubmission('<?= escape($sub['submission_code']) ?>')">
                                <i class="bx bx-x"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Quick Approve Modal -->
<div class="modal fade" id="quickApproveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/approve.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" id="approveSubmissionCode">
            <input type="hidden" name="action" value="approve">
            
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Quick Approve</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Approval Notes (Optional)</label>
                        <textarea name="approval_notes" class="form-control" rows="3" 
                                  placeholder="Add comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Quick Reject Modal -->
<div class="modal fade" id="quickRejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/approve.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" id="rejectSubmissionCode">
            <input type="hidden" name="action" value="reject">
            
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Quick Reject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="approval_notes" class="form-control" rows="3" required
                                  placeholder="Please explain why..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function approveSubmission(code) {
    document.getElementById('approveSubmissionCode').value = code;
    new bootstrap.Modal(document.getElementById('quickApproveModal')).show();
}

function rejectSubmission(code) {
    document.getElementById('rejectSubmissionCode').value = code;
    new bootstrap.Modal(document.getElementById('quickRejectModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>