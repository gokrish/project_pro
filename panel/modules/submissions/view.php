<?php
/**
 * View Submission Details
 * File: panel/modules/submissions/view.php
 */

if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

$db = Database::getInstance();
$conn = $db->getConnection();
$user = Auth::user();

// Get submission
$submissionCode = input('code', '');
$submissionId = (int)input('id', 0);

if (empty($submissionCode) && !$submissionId) {
    throw new Exception('Submission code or ID required');
}

$query = "
    SELECT 
        s.*,
        c.candidate_name, c.email as candidate_email, c.phone as candidate_phone,
        c.current_position, c.experience_years, c.current_location,
        j.title as job_title, j.job_code,
        cl.company_name as client_name, cl.client_name as client_contact, cl.email as client_email,
        submitter.name as submitted_by_name,
        reviewer.name as reviewed_by_name
    FROM candidate_submissions s
    LEFT JOIN candidates c ON s.candidate_code = c.candidate_code
    LEFT JOIN jobs j ON s.job_code = j.job_code
    LEFT JOIN clients cl ON s.client_code = cl.client_code
    LEFT JOIN users submitter ON s.submitted_by = submitter.user_code
    LEFT JOIN users reviewer ON s.reviewed_by = reviewer.user_code
    WHERE " . ($submissionCode ? "s.submission_code = ?" : "s.submission_id = ?") . "
    AND s.deleted_at IS NULL
";

$stmt = $conn->prepare($query);
if ($submissionCode) {
    $stmt->bind_param('s', $submissionCode);
} else {
    $stmt->bind_param('i', $submissionId);
}
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    throw new Exception('Submission not found');
}

// Get notes
$notesQuery = "
    SELECT n.*, u.name as created_by_name
    FROM submission_notes n
    LEFT JOIN users u ON n.created_by = u.user_code
    WHERE n.submission_code = ?
    ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($notesQuery);
$stmt->bind_param('s', $submission['submission_code']);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check permissions
$canEdit = ($submission['status'] === 'draft' && $submission['submitted_by'] === $user['user_code']) || $user['level'] === 'admin';
$canApprove = ($submission['status'] === 'pending_review') && ($user['level'] === 'admin' || $user['level'] === 'manager');
$canRecordResponse = $submission['status'] === 'submitted' && Permission::can('submissions', 'edit');

// Status color mapping
$statusColors = [
    'draft' => 'secondary',
    'pending_review' => 'warning',
    'approved' => 'info',
    'submitted' => 'primary',
    'accepted' => 'success',
    'rejected' => 'danger',
    'withdrawn' => 'dark'
];
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-file text-primary me-2"></i>
                <?= htmlspecialchars($submission['submission_code']) ?>
            </h4>
            <p class="text-muted mb-0">
                <span class="badge bg-<?= $statusColors[$submission['status']] ?? 'secondary' ?>">
                    <?= ucwords(str_replace('_', ' ', $submission['status'])) ?>
                </span>
                <?php if ($submission['converted_to_application']): ?>
                    <span class="badge bg-success ms-2">
                        <i class="bx bx-check-circle"></i> Converted to Application
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <?php if ($canEdit): ?>
            <a href="?action=edit&code=<?= urlencode($submission['submission_code']) ?>" class="btn btn-primary me-2">
                <i class="bx bx-edit me-1"></i> Edit
            </a>
            <?php endif; ?>
            
            <?php if ($canApprove): ?>
            <button class="btn btn-success me-2" onclick="approveSubmission()">
                <i class="bx bx-check me-1"></i> Approve
            </button>
            <button class="btn btn-danger me-2" onclick="rejectSubmission()">
                <i class="bx bx-x me-1"></i> Reject
            </button>
            <?php endif; ?>
            
            <?php if ($canRecordResponse): ?>
            <button class="btn btn-info me-2" onclick="recordClientResponse()">
                <i class="bx bx-message-dots me-1"></i> Client Response
            </button>
            <?php endif; ?>
            
            <a href="?action=list" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Candidate & Job Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Submission Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Candidate</h6>
                            <h5><?= htmlspecialchars($submission['candidate_name']) ?></h5>
                            <p class="mb-1">
                                <i class="bx bx-envelope me-1"></i>
                                <?= htmlspecialchars($submission['candidate_email']) ?>
                            </p>
                            <?php if ($submission['candidate_phone']): ?>
                            <p class="mb-1">
                                <i class="bx bx-phone me-1"></i>
                                <?= htmlspecialchars($submission['candidate_phone']) ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($submission['current_position']): ?>
                            <p class="mb-1">
                                <i class="bx bx-briefcase me-1"></i>
                                <?= htmlspecialchars($submission['current_position']) ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($submission['experience_years']): ?>
                            <p class="mb-0">
                                <i class="bx bx-time me-1"></i>
                                <?= $submission['experience_years'] ?> years experience
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-2">Job & Client</h6>
                            <h5><?= htmlspecialchars($submission['job_title']) ?></h5>
                            <p class="mb-1">
                                <i class="bx bx-building me-1"></i>
                                <strong><?= htmlspecialchars($submission['client_name']) ?></strong>
                            </p>
                            <p class="mb-1">
                                <i class="bx bx-user me-1"></i>
                                Contact: <?= htmlspecialchars($submission['client_contact']) ?>
                            </p>
                            <a href="../jobs/view.php?code=<?= urlencode($submission['job_code']) ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-link-external me-1"></i> View Job
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Proposal Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Rate Proposal</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-muted mb-1">Proposed Rate</h6>
                            <h4 class="mb-0 text-primary">
                                <?= $submission['currency'] ?> <?= number_format($submission['proposed_rate'], 2) ?>
                                <small class="text-muted">/<?= $submission['rate_type'] ?></small>
                            </h4>
                        </div>
                        <?php if ($submission['availability_date']): ?>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-1">Available From</h6>
                            <p class="mb-0"><?= date('M d, Y', strtotime($submission['availability_date'])) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($submission['contract_duration']): ?>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-1">Contract Duration</h6>
                            <p class="mb-0"><?= $submission['contract_duration'] ?> months</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Assessment -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Candidate Assessment</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="text-primary mb-2">Why This Candidate?</h6>
                        <p><?= nl2br(htmlspecialchars($submission['fit_reason'])) ?></p>
                    </div>
                    
                    <?php if ($submission['key_strengths']): ?>
                    <div class="mb-4">
                        <h6 class="text-primary mb-2">Key Strengths</h6>
                        <p><?= nl2br(htmlspecialchars($submission['key_strengths'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($submission['concerns']): ?>
                    <div class="alert alert-warning">
                        <h6 class="mb-2"><i class="bx bx-error-circle me-2"></i>Internal Concerns</h6>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($submission['concerns'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Client Response -->
            <?php if ($submission['client_response']): ?>
            <div class="card mb-4 border-<?= $submission['client_response'] === 'interested' ? 'success' : 'danger' ?>">
                <div class="card-header bg-label-<?= $submission['client_response'] === 'interested' ? 'success' : 'danger' ?>">
                    <h5 class="mb-0">
                        <i class="bx bx-message-dots me-2"></i>Client Response
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Response:</strong> 
                        <span class="badge bg-<?= $submission['client_response'] === 'interested' ? 'success' : 'danger' ?>">
                            <?= ucwords(str_replace('_', ' ', $submission['client_response'])) ?>
                        </span>
                    </div>
                    <?php if ($submission['client_response_date']): ?>
                    <div class="mb-2">
                        <strong>Date:</strong> <?= date('M d, Y H:i', strtotime($submission['client_response_date'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($submission['client_feedback']): ?>
                    <div class="mt-3">
                        <strong>Feedback:</strong>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($submission['client_feedback'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($submission['client_response'] === 'interested' && !$submission['converted_to_application']): ?>
                    <div class="mt-3">
                        <button class="btn btn-success" onclick="convertToApplication()">
                            <i class="bx bx-check-double me-1"></i> Convert to Application
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Activity Notes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Activity & Notes</h5>
                </div>
                <div class="card-body">
                    <!-- Add Note Form -->
                    <form id="addNoteForm" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        <input type="hidden" name="submission_code" value="<?= htmlspecialchars($submission['submission_code']) ?>">
                        <div class="input-group">
                            <select name="note_type" class="form-select" style="max-width: 150px;">
                                <option value="general">General</option>
                                <option value="internal">Internal</option>
                                <option value="client_feedback">Client Feedback</option>
                                <option value="followup">Follow-up</option>
                            </select>
                            <input type="text" name="note" class="form-control" placeholder="Add a note..." required>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-plus"></i> Add
                            </button>
                        </div>
                    </form>

                    <!-- Notes Timeline -->
                    <?php if (empty($notes)): ?>
                        <p class="text-muted text-center py-3">No notes yet</p>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($notes as $note): ?>
                            <div class="timeline-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge bg-label-secondary"><?= ucfirst($note['note_type']) ?></span>
                                        <strong class="ms-2"><?= htmlspecialchars($note['created_by_name']) ?></strong>
                                        <small class="text-muted">- <?= date('M d, Y H:i', strtotime($note['created_at'])) ?></small>
                                    </div>
                                </div>
                                <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Submission Timeline</h5>
                </div>
                <div class="card-body">
                    <ul class="timeline">
                        <li class="timeline-item timeline-item-transparent">
                            <span class="timeline-point timeline-point-primary"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">Created</h6>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($submission['created_at'])) ?></small>
                                </div>
                                <p class="mb-0">By <?= htmlspecialchars($submission['submitted_by_name']) ?></p>
                            </div>
                        </li>
                        
                        <?php if ($submission['reviewed_at']): ?>
                        <li class="timeline-item timeline-item-transparent">
                            <span class="timeline-point timeline-point-<?= $submission['status'] === 'approved' ? 'success' : 'danger' ?>"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">Reviewed</h6>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($submission['reviewed_at'])) ?></small>
                                </div>
                                <p class="mb-0">By <?= htmlspecialchars($submission['reviewed_by_name']) ?></p>
                                <?php if ($submission['review_notes']): ?>
                                <p class="small mb-0 mt-1"><?= htmlspecialchars($submission['review_notes']) ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($submission['submitted_to_client_at']): ?>
                        <li class="timeline-item timeline-item-transparent">
                            <span class="timeline-point timeline-point-info"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">Submitted to Client</h6>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($submission['submitted_to_client_at'])) ?></small>
                                </div>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($submission['client_response_date']): ?>
                        <li class="timeline-item timeline-item-transparent">
                            <span class="timeline-point timeline-point-<?= $submission['client_response'] === 'interested' ? 'success' : 'warning' ?>"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">Client Response</h6>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($submission['client_response_date'])) ?></small>
                                </div>
                                <p class="mb-0"><?= ucwords(str_replace('_', ' ', $submission['client_response'])) ?></p>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($submission['converted_to_application']): ?>
                        <li class="timeline-item timeline-item-transparent">
                            <span class="timeline-point timeline-point-success"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">Converted to Application</h6>
                                </div>
                                <a href="../applications/view.php?id=<?= $submission['application_id'] ?>" class="btn btn-sm btn-success">
                                    View Application
                                </a>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Metadata -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">Submission Code:</dt>
                        <dd class="col-6"><?= htmlspecialchars($submission['submission_code']) ?></dd>
                        
                        <dt class="col-6">Submitted By:</dt>
                        <dd class="col-6"><?= htmlspecialchars($submission['submitted_by_name']) ?></dd>
                        
                        <dt class="col-6">Type:</dt>
                        <dd class="col-6"><?= ucwords(str_replace('_', ' ', $submission['submission_type'])) ?></dd>
                        
                        <?php if ($submission['followup_count'] > 0): ?>
                        <dt class="col-6">Follow-ups:</dt>
                        <dd class="col-6"><?= $submission['followup_count'] ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#addNoteForm').on('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('handlers/add-note.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Failed to add note');
        }
    });
});

async function approveSubmission() {
    const notes = prompt('Approval notes (optional):');
    if (notes === null) return;
    
    try {
        const response = await fetch('handlers/approve.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                submission_code: '<?= $submission['submission_code'] ?>',
                notes: notes
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to approve submission');
    }
}

function rejectSubmission() {
    // Similar to approve
    const reason = prompt('Rejection reason:');
    if (!reason) return;
    
    // Call reject handler
}

function recordClientResponse() {
    // Open modal to record client feedback
}

async function convertToApplication() {
    if (!confirm('Convert this submission to an application? This will create an entry in the applications pipeline.')) {
        return;
    }
    
    try {
        const response = await fetch('handlers/convert-to-application.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                submission_code: '<?= $submission['submission_code'] ?>'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = '../applications/view.php?id=' + result.application_id;
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to convert submission');
    }
}
</script>