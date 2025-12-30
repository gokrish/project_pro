<?php
/**
 * Submission Detail View
 * Shows complete submission details with timeline and actions
 */
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get submission code
$submission_code = input('code');
if (!$submission_code) {
    redirectBack('Submission not found');
}

// Check permission
$canViewAll = Permission::can('submissions', 'view_all');
$canViewOwn = Permission::can('submissions', 'view_own');

if (!$canViewAll && !$canViewOwn) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Get submission with all related data
$sql = "
    SELECT 
        s.*,
        c.candidate_name,
        c.email as candidate_email,
        c.phone as candidate_phone,
        c.current_location,
        c.current_position,
        c.current_company,
        c.skills,
        c.total_experience,
        c.linkedin_url,
        c.resume_path,
        j.job_title,
        j.job_code,
        j.description as job_description,
        j.location as job_location,
        j.employment_type,
        j.client_code,
        cl.company_name,
        cl.contact_person,
        cl.email as client_email,
        cl.phone as client_phone,
        u_submitted.name as submitted_by_name,
        u_submitted.email as submitted_by_email,
        u_approved.name as approved_by_name,
        u_sent.name as sent_by_name
    FROM submissions s
    JOIN candidates c ON s.candidate_code = c.candidate_code
    JOIN jobs j ON s.job_code = j.job_code
    JOIN clients cl ON j.client_code = cl.client_code
    LEFT JOIN users u_submitted ON s.submitted_by = u_submitted.user_code
    LEFT JOIN users u_approved ON s.approved_by = u_approved.user_code
    LEFT JOIN users u_sent ON s.sent_to_client_by = u_sent.user_code
    WHERE s.submission_code = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $submission_code);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    redirectBack('Submission not found');
}

// Permission check for own submissions
if (!$canViewAll && $canViewOwn) {
    if ($submission['submitted_by'] !== $user['user_code']) {
        header('Location: /panel/errors/403.php');
        exit;
    }
}

// Get status history
$historySQL = "
    SELECT *
    FROM submission_status_history
    WHERE submission_code = ?
    ORDER BY changed_at DESC
";
$stmt = $conn->prepare($historySQL);
$stmt->bind_param("s", $submission_code);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get notes
$notesSQL = "
    SELECT n.*, u.name as created_by_name
    FROM notes n
    LEFT JOIN users u ON n.created_by = u.user_code
    WHERE n.entity_type = 'submission' AND n.entity_code = ?
    ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($notesSQL);
$stmt->bind_param("s", $submission_code);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Page config
$pageTitle = "Submission: {$submission['candidate_name']} → {$submission['job_title']}";
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Submissions', 'url' => '/panel/modules/submissions/list.php'],
    ['title' => $submission['submission_code'], 'url' => '']
];

// Determine available actions
$canApprove = Permission::can('submissions', 'approve') && $submission['internal_status'] === 'pending';
$canSendToClient = Permission::can('submissions', 'send_client') && 
                   $submission['internal_status'] === 'approved' && 
                   $submission['client_status'] === 'not_sent';
$canUpdateStatus = Permission::can('submissions', 'update_status') && 
                   $submission['internal_status'] === 'approved' &&
                   in_array($submission['client_status'], ['submitted', 'interviewing', 'offered']);
$canWithdraw = Permission::can('submissions', 'withdraw') && 
               !in_array($submission['client_status'], ['placed', 'rejected', 'withdrawn']);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 40px;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    padding-left: 80px;
    padding-bottom: 30px;
}
.timeline-marker {
    position: absolute;
    left: 32px;
    top: 0;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 3px solid #fff;
}
.timeline-marker.active {
    background: #0d6efd;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.2);
}
.timeline-marker.completed {
    background: #198754;
}
.timeline-marker.pending {
    background: #6c757d;
    border-color: #dee2e6;
}
.info-card {
    border-left: 4px solid #0d6efd;
}
.action-card {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    transition: all 0.2s;
}
.action-card:hover {
    border-color: #0d6efd;
    background: #fff;
}
</style>

<!-- Header Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card info-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-2">
                            <i class="bx bx-user-circle"></i>
                            <?= escape($submission['candidate_name']) ?>
                            <i class="bx bx-right-arrow-alt"></i>
                            <?= escape($submission['job_title']) ?>
                        </h4>
                        <p class="text-muted mb-0">
                            <strong>Company:</strong> <?= escape($submission['company_name']) ?> |
                            <strong>Submitted:</strong> <?= date('M d, Y g:i A', strtotime($submission['created_at'])) ?> by <?= escape($submission['submitted_by_name']) ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php
                        $internalBadge = [
                            'pending' => ['warning', 'time-five'],
                            'approved' => ['success', 'check-circle'],
                            'rejected' => ['danger', 'x-circle'],
                            'withdrawn' => ['secondary', 'minus-circle']
                        ];
                        $clientBadge = [
                            'not_sent' => ['secondary', 'circle'],
                            'submitted' => ['info', 'send'],
                            'interviewing' => ['primary', 'user-voice'],
                            'offered' => ['warning', 'gift'],
                            'placed' => ['success', 'trophy'],
                            'rejected' => ['danger', 'x'],
                            'withdrawn' => ['secondary', 'minus']
                        ];
                        
                        $iBadge = $internalBadge[$submission['internal_status']] ?? ['secondary', 'circle'];
                        $cBadge = $clientBadge[$submission['client_status']] ?? ['secondary', 'circle'];
                        ?>
                        <div class="mb-2">
                            <small class="text-muted d-block">Internal Status</small>
                            <span class="badge bg-<?= $iBadge[0] ?> px-3 py-2">
                                <i class="bx bx-<?= $iBadge[1] ?>"></i>
                                <?= ucfirst($submission['internal_status']) ?>
                            </span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Client Status</small>
                            <span class="badge bg-<?= $cBadge[0] ?> px-3 py-2">
                                <i class="bx bx-<?= $cBadge[1] ?>"></i>
                                <?= ucfirst(str_replace('_', ' ', $submission['client_status'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <!-- Left Column: Details -->
    <div class="col-md-8">
        <!-- Candidate Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-user"></i> Candidate Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Email:</strong> 
                            <a href="mailto:<?= escape($submission['candidate_email']) ?>">
                                <?= escape($submission['candidate_email']) ?>
                            </a>
                        </p>
                        <p><strong>Phone:</strong> 
                            <a href="tel:<?= escape($submission['candidate_phone']) ?>">
                                <?= escape($submission['candidate_phone']) ?>
                            </a>
                        </p>
                        <p><strong>Location:</strong> <?= escape($submission['current_location'] ?: 'N/A') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Current Position:</strong> <?= escape($submission['current_position'] ?: 'N/A') ?></p>
                        <p><strong>Company:</strong> <?= escape($submission['current_company'] ?: 'N/A') ?></p>
                        <p><strong>Experience:</strong> <?= $submission['total_experience'] ? $submission['total_experience'] . ' years' : 'N/A' ?></p>
                    </div>
                    <div class="col-12">
                        <p><strong>Skills:</strong></p>
                        <p class="text-muted"><?= nl2br(escape($submission['skills'] ?: 'No skills listed')) ?></p>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="/panel/modules/candidates/view.php?code=<?= escape($submission['candidate_code']) ?>" 
                       class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="bx bx-link-external"></i> View Full Candidate Profile
                    </a>
                    <?php if ($submission['resume_path']): ?>
                        <a href="/<?= escape($submission['resume_path']) ?>" 
                           class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bx bx-download"></i> Download Resume
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Job Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-briefcase"></i> Job Information</h5>
            </div>
            <div class="card-body">
                <h6><?= escape($submission['job_title']) ?></h6>
                <p class="text-muted mb-3">
                    <?= escape($submission['company_name']) ?> | 
                    <?= escape($submission['job_location'] ?: 'Location TBD') ?> |
                    <?= ucfirst($submission['employment_type']) ?>
                </p>
                <p><strong>Job Code:</strong> <?= escape($submission['job_code']) ?></p>
                <?php if ($submission['job_description']): ?>
                    <div class="mt-3">
                        <strong>Description:</strong>
                        <div class="text-muted mt-2" style="max-height: 200px; overflow-y: auto;">
                            <?= nl2br(escape($submission['job_description'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="/panel/modules/jobs/view.php?code=<?= escape($submission['job_code']) ?>" 
                       class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="bx bx-link-external"></i> View Full Job Details
                    </a>
                </div>
            </div>
        </div>

        <!-- Submission Notes -->
        <?php if ($submission['submission_notes'] || $submission['approval_notes'] || $submission['interview_notes'] || $submission['offer_notes'] || $submission['placement_notes']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-note"></i> Submission Notes</h5>
            </div>
            <div class="card-body">
                <?php if ($submission['submission_notes']): ?>
                    <div class="mb-3">
                        <strong>Initial Submission Notes:</strong>
                        <p class="text-muted mt-1"><?= nl2br(escape($submission['submission_notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($submission['approval_notes']): ?>
                    <div class="mb-3">
                        <strong>Approval Notes:</strong>
                        <p class="text-muted mt-1"><?= nl2br(escape($submission['approval_notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($submission['interview_notes']): ?>
                    <div class="mb-3">
                        <strong>Interview Notes:</strong>
                        <p class="text-muted mt-1"><?= nl2br(escape($submission['interview_notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($submission['offer_notes']): ?>
                    <div class="mb-3">
                        <strong>Offer Notes:</strong>
                        <p class="text-muted mt-1"><?= nl2br(escape($submission['offer_notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($submission['placement_notes']): ?>
                    <div class="mb-3">
                        <strong>Placement Notes:</strong>
                        <p class="text-muted mt-1"><?= nl2br(escape($submission['placement_notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-time"></i> Timeline</h5>
            </div>
            <div class="card-body">
                <ul class="timeline">
                    <!-- Created -->
                    <li class="timeline-item">
                        <div class="timeline-marker completed"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Submission Created</h6>
                            <p class="text-muted small mb-1">
                                <?= date('M d, Y g:i A', strtotime($submission['created_at'])) ?>
                                by <?= escape($submission['submitted_by_name']) ?>
                            </p>
                            <span class="badge bg-secondary">pending</span>
                        </div>
                    </li>

                    <!-- Approval/Rejection -->
                    <?php if ($submission['approved_at']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker <?= $submission['internal_status'] === 'approved' ? 'completed' : 'active' ?>"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1"><?= $submission['internal_status'] === 'approved' ? 'Approved' : 'Rejected' ?></h6>
                                <p class="text-muted small mb-1">
                                    <?= date('M d, Y g:i A', strtotime($submission['approved_at'])) ?>
                                    by <?= escape($submission['approved_by_name']) ?>
                                </p>
                                <span class="badge bg-<?= $submission['internal_status'] === 'approved' ? 'success' : 'danger' ?>">
                                    <?= $submission['internal_status'] ?>
                                </span>
                            </div>
                        </li>
                    <?php endif; ?>

                    <!-- Sent to Client -->
                    <?php if ($submission['sent_to_client_at']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker completed"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Sent to Client</h6>
                                <p class="text-muted small mb-1">
                                    <?= date('M d, Y g:i A', strtotime($submission['sent_to_client_at'])) ?>
                                    by <?= escape($submission['sent_by_name']) ?>
                                </p>
                                <span class="badge bg-info">submitted</span>
                            </div>
                        </li>
                    <?php endif; ?>

                    <!-- Interview -->
                    <?php if ($submission['interview_date']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker completed"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Interview Scheduled</h6>
                                <p class="text-muted small mb-1">
                                    <?= date('M d, Y g:i A', strtotime($submission['interview_date'])) ?>
                                </p>
                                <span class="badge bg-primary">interviewing</span>
                                <?php if ($submission['interview_result']): ?>
                                    <span class="badge bg-<?= $submission['interview_result'] === 'positive' ? 'success' : ($submission['interview_result'] === 'negative' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($submission['interview_result']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endif; ?>

                    <!-- Offer -->
                    <?php if ($submission['offer_date']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker completed"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Offer Extended</h6>
                                <p class="text-muted small mb-1">
                                    <?= date('M d, Y', strtotime($submission['offer_date'])) ?>
                                </p>
                                <span class="badge bg-warning">offered</span>
                            </div>
                        </li>
                    <?php endif; ?>

                    <!-- Placement/Rejection -->
                    <?php if ($submission['placement_date']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker completed"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Successfully Placed</h6>
                                <p class="text-muted small mb-1">
                                    <?= date('M d, Y', strtotime($submission['placement_date'])) ?>
                                </p>
                                <span class="badge bg-success">placed</span>
                            </div>
                        </li>
                    <?php elseif ($submission['rejected_date']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker active"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Rejected</h6>
                                <p class="text-muted small mb-1">
                                    <?= date('M d, Y', strtotime($submission['rejected_date'])) ?>
                                    <?= $submission['rejected_by'] ? 'by ' . ucfirst($submission['rejected_by']) : '' ?>
                                </p>
                                <span class="badge bg-danger">rejected</span>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Actions & Client Info -->
    <div class="col-md-4">
        <!-- Actions Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bx bx-cog"></i> Actions</h5>
            </div>
            <div class="card-body">
                <?php if ($canApprove): ?>
                    <div class="action-card p-3 mb-3 text-center">
                        <h6>Approval Required</h6>
                        <p class="text-muted small mb-3">Review and approve or reject this submission</p>
                        <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                            <i class="bx bx-check"></i> Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bx bx-x"></i> Reject
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($canSendToClient): ?>
                    <div class="action-card p-3 mb-3 text-center">
                        <h6>Ready to Send</h6>
                        <p class="text-muted small mb-3">This submission has been approved</p>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#sendToClientModal">
                            <i class="bx bx-send"></i> Send to Client
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($canUpdateStatus): ?>
                    <div class="action-card p-3 mb-3">
                        <h6 class="text-center">Update Status</h6>
                        <div class="d-grid gap-2 mt-3">
                            <?php if ($submission['client_status'] === 'submitted'): ?>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#interviewModal">
                                    <i class="bx bx-calendar"></i> Schedule Interview
                                </button>
                            <?php endif; ?>
                            
                            <?php if (in_array($submission['client_status'], ['submitted', 'interviewing'])): ?>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#offerModal">
                                    <i class="bx bx-gift"></i> Record Offer
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectByClientModal">
                                    <i class="bx bx-x"></i> Mark as Rejected
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($submission['client_status'] === 'offered'): ?>
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#placementModal">
                                    <i class="bx bx-trophy"></i> Record Placement
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($canWithdraw): ?>
                    <div class="action-card p-3 mb-3 text-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                            <i class="bx bx-x-circle"></i> Withdraw Submission
                        </button>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <a href="list.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bx bx-arrow-back"></i> Back to List
                    </a>
                </div>
            </div>
        </div>

        <!-- Client Contact Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-building"></i> Client Contact</h5>
            </div>
            <div class="card-body">
                <h6><?= escape($submission['company_name']) ?></h6>
                <?php if ($submission['contact_person']): ?>
                    <p class="mb-1">
                        <strong>Contact:</strong> <?= escape($submission['contact_person']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($submission['client_email']): ?>
                    <p class="mb-1">
                        <strong>Email:</strong> 
                        <a href="mailto:<?= escape($submission['client_email']) ?>">
                            <?= escape($submission['client_email']) ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if ($submission['client_phone']): ?>
                    <p class="mb-1">
                        <strong>Phone:</strong> 
                        <a href="tel:<?= escape($submission['client_phone']) ?>">
                            <?= escape($submission['client_phone']) ?>
                        </a>
                    </p>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="/panel/modules/clients/view.php?code=<?= escape($submission['client_code']) ?>" 
                       class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="bx bx-link-external"></i> View Client Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php include __DIR__ . '/modals/approval-modal.php'; ?>
<?php include __DIR__ . '/modals/send-to-client-modal.php'; ?>
<?php include __DIR__ . '/modals/interview-modal.php'; ?>
<?php include __DIR__ . '/modals/offer-modal.php'; ?>
<?php include __DIR__ . '/modals/placement-modal.php'; ?>
<?php include __DIR__ . '/modals/reject-modal.php'; ?>
<?php include __DIR__ . '/modals/withdraw-modal.php'; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>