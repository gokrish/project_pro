<?php
/**
 * Job Detail View
 * Shows job info, submissions, and linked candidates
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get job code
$job_code = input('code');
if (!$job_code) {
    redirectBack('Job not found');
}

// Check permission
$canViewAll = Permission::can('jobs', 'view_all');
$canViewOwn = Permission::can('jobs', 'view_own');

if (!$canViewAll && !$canViewOwn) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Helper functions (MOVED TO TOP)
function getInternalStatusColor($status) {
    return match($status) {
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'withdrawn' => 'secondary',
        default => 'secondary'
    };
}

function getClientStatusColor($status) {
    return match($status) {
        'not_sent' => 'secondary',
        'submitted' => 'info',
        'interviewing' => 'primary',
        'offered' => 'success',
        'placed' => 'success',
        'rejected' => 'danger',
        'withdrawn' => 'secondary',
        default => 'secondary'
    };
}

// Get job details
$sql = "
    SELECT 
        j.*,
        c.company_name,
        c.contact_person,
        c.email as client_email,
        c.phone as client_phone,
        u_created.name as created_by_name,
        u_assigned.name as assigned_recruiter_name,
        u_approved.name as approved_by_name,
        u_rejected.name as rejected_by_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u_created ON j.created_by = u_created.user_code
    LEFT JOIN users u_assigned ON j.assigned_recruiter = u_assigned.user_code
    LEFT JOIN users u_approved ON j.approved_by = u_approved.user_code
    LEFT JOIN users u_rejected ON j.rejected_by = u_rejected.user_code
    WHERE j.job_code = ?
    AND j.deleted_at IS NULL
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $job_code);  // ✅ FIXED: was $jobCode
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    redirectBack('Job not found');
}

// Permission check for own jobs
if (!$canViewAll && $canViewOwn) {
    if ($job['created_by'] !== $user['user_code'] && $job['assigned_recruiter'] !== $user['user_code']) {
        header('Location: /panel/errors/403.php');
        exit;
    }
}

// Get submissions for this job
$submissionsSQL = "
    SELECT 
        s.*,
        c.candidate_name,
        c.email as candidate_email,
        c.phone as candidate_phone,
        c.current_position,
        c.candidate_code,  -- ✅ ADDED: Missing candidate_code
        u.name as submitted_by_name
    FROM submissions s
    JOIN candidates c ON s.candidate_code = c.candidate_code
    LEFT JOIN users u ON s.submitted_by = u.user_code
    WHERE s.job_code = ?
    AND s.deleted_at IS NULL
    ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($submissionsSQL);
$stmt->bind_param("s", $job_code);  // ✅ FIXED: was $jobCode
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$submissionsCount = count($submissions);
$placedCandidates = array_filter($submissions, fn($s) => $s['client_status'] === 'placed');
$interviewingCount = count(array_filter($submissions, fn($s) => $s['client_status'] === 'interviewing'));
$pendingApprovalCount = count(array_filter($submissions, fn($s) => $s['internal_status'] === 'pending'));

// Page config
$pageTitle = $job['job_title'];
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Jobs', 'url' => '/panel/modules/jobs/?action=list'],
    ['title' => $job['job_title'], 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.info-card {
    border-left: 4px solid #0d6efd;
}
.submission-row {
    transition: background-color 0.2s;
    cursor: pointer;
}
.submission-row:hover {
    background-color: #f8f9fa;
}
.stat-card {
    border-radius: 8px;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
}
</style>

<!-- Header Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card info-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2">
                            <i class="bx bx-briefcase text-primary"></i>
                            <?= escape($job['job_title']) ?>
                        </h3>
                        <p class="text-muted mb-0">
                            <span class="badge bg-<?= $job['status'] === 'open' ? 'info' : ($job['status'] === 'filling' ? 'primary' : 'secondary') ?> me-2">
                                <?= ucfirst($job['status']) ?>
                            </span>
                            <span class="badge bg-<?= $job['approval_status'] === 'approved' ? 'success' : 'warning' ?> me-2">
                                <?= ucfirst(str_replace('_', ' ', $job['approval_status'])) ?>
                            </span>
                            <?php if ($job['is_published']): ?>
                                <span class="badge bg-success me-2">
                                    <i class="bx bx-globe"></i> Published
                                </span>
                            <?php endif; ?>
                            <strong>Code:</strong> <?= escape($job['job_code']) ?> |
                            <?php if ($job['job_refno']): ?>
                                <strong>Ref:</strong> <?= escape($job['job_refno']) ?> |
                            <?php endif; ?>
                            <strong>Company:</strong> <?= escape($job['company_name']) ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if (Permission::can('jobs', 'edit')): ?>
                            <a href="?action=edit&code=<?= escape($job_code) ?>" class="btn btn-primary me-2">
                                <i class="bx bx-edit"></i> Edit
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($job['status'] === 'draft' && $job['approval_status'] === 'draft'): ?>
                            <form method="POST" action="handlers/submit-for-approval.php" class="d-inline">
                                <?= CSRFToken::field() ?>
                                <input type="hidden" name="job_code" value="<?= escape($job_code) ?>">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bx bx-check-circle"></i> Submit for Approval
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($job['approval_status'] === 'pending_approval' && Permission::can('jobs', 'approve')): ?>
                            <a href="?action=approve&code=<?= escape($job_code) ?>" class="btn btn-warning">
                                <i class="bx bx-check-circle"></i> Review & Approve
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <div class="card-body text-center">
                <i class="bx bx-file display-4 text-info mb-2"></i>
                <h3 class="mb-0"><?= $submissionsCount ?></h3>
                <small class="text-muted">Total Submissions</small>
                <?php if ($pendingApprovalCount > 0): ?>
                    <div class="mt-2">
                        <span class="badge bg-warning"><?= $pendingApprovalCount ?> Pending</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <div class="card-body text-center">
                <i class="bx bx-user-voice display-4 text-primary mb-2"></i>
                <h3 class="mb-0"><?= $interviewingCount ?></h3>
                <small class="text-muted">Interviewing</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <div class="card-body text-center">
                <i class="bx bx-trophy display-4 text-success mb-2"></i>
                <h3 class="mb-0"><?= count($placedCandidates) ?></h3>
                <small class="text-muted">Placements</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card">
            <div class="card-body text-center">
                <i class="bx bx-target-lock display-4 text-warning mb-2"></i>
                <h3 class="mb-0"><?= $job['positions_filled'] ?> / <?= $job['positions_total'] ?></h3>
                <small class="text-muted">Positions</small>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <!-- Left Column: Job Details & Submissions -->
    <div class="col-md-8">
        <!-- Job Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-info-circle"></i> Job Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Location:</strong> <?= escape($job['location']) ?></p>
                        <p class="mb-2"><strong>Positions:</strong> <?= $job['positions_total'] ?></p>
                        <p class="mb-2"><strong>Employment Type:</strong> <?= ucfirst(str_replace('_', ' ', $job['employment_type'] ?? 'Not specified')) ?></p>
                    </div>
                    <div class="col-md-6">
                        <?php if ($job['salary_min'] || $job['salary_max']): ?>
                            <p class="mb-2">
                                <strong>Salary:</strong> 
                                €<?= number_format($job['salary_min'], 0) ?> - €<?= number_format($job['salary_max'], 0) ?>
                                <?php if (!$job['show_salary']): ?>
                                    <span class="badge bg-secondary">Not Public</span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <p class="mb-2"><strong>Assigned to:</strong> <?= escape($job['assigned_recruiter_name'] ?: 'Unassigned') ?></p>
                        <p class="mb-2"><strong>Created by:</strong> <?= escape($job['created_by_name']) ?></p>
                    </div>
                </div>
                
                <hr>
                
                <h6>Description</h6>
                <div class="job-description">
                    <?= nl2br(escape($job['description'])) ?>
                </div>
                
                <?php if ($job['notes']): ?>
                    <hr>
                    <h6>Internal Notes</h6>
                    <div class="text-muted">
                        <?= nl2br(escape($job['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bx bx-user-check"></i> Submissions 
                    <span class="badge bg-secondary"><?= $submissionsCount ?></span>
                </h5>
                <?php if (Permission::can('submissions', 'create')): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                        <i class="bx bx-plus"></i> Submit Candidate
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($submissions)): ?>
                    <div class="text-center py-5">
                        <i class="bx bx-user-x display-1 text-muted"></i>
                        <p class="text-muted mt-3">No submissions yet</p>
                        <?php if (Permission::can('submissions', 'create')): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                                <i class="bx bx-plus"></i> Submit First Candidate
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Candidate</th>
                                    <th>Internal Status</th>
                                    <th>Client Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $sub): ?>
                                    <tr class="submission-row" onclick="window.location='../submissions/?action=view&code=<?= escape($sub['submission_code']) ?>'">
                                        <td>
                                            <strong><?= escape($sub['candidate_name']) ?></strong><br>
                                            <small class="text-muted"><?= escape($sub['candidate_email']) ?></small>
                                            <?php if ($sub['current_position']): ?>
                                                <br><small class="text-muted"><?= escape($sub['current_position']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getInternalStatusColor($sub['internal_status']) ?>">
                                                <?= ucfirst($sub['internal_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($sub['internal_status'] === 'approved'): ?>
                                                <span class="badge bg-<?= getClientStatusColor($sub['client_status']) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $sub['client_status'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?= date('M d, Y', strtotime($sub['created_at'])) ?><br>
                                                by <?= escape($sub['submitted_by_name']) ?>
                                            </small>
                                        </td>
                                        <td onclick="event.stopPropagation()">
                                            <a href="../submissions/?action=view&code=<?= escape($sub['submission_code']) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Submission">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            <a href="../candidates/?action=view&code=<?= escape($sub['candidate_code']) ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="View Candidate">
                                                <i class="bx bx-user"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Placed Candidates -->
        <?php if (!empty($placedCandidates)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-trophy"></i> Placed Candidates 
                    <span class="badge bg-success"><?= count($placedCandidates) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($placedCandidates as $placed): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bx bx-user-check text-success"></i>
                                        <?= escape($placed['candidate_name']) ?>
                                    </h6>
                                    <p class="mb-2">
                                        <small class="text-muted">
                                            Placed on <?= date('M d, Y', strtotime($placed['placement_date'])) ?>
                                        </small>
                                    </p>
                                    <?php if ($placed['placement_notes']): ?>
                                        <p class="mb-2 small"><?= escape($placed['placement_notes']) ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2">
                                        <a href="../candidates/?action=view&code=<?= escape($placed['candidate_code']) ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bx bx-user"></i> View Candidate
                                        </a>
                                        <a href="../submissions/?action=view&code=<?= escape($placed['submission_code']) ?>" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bx bx-show"></i> Submission
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Client & Actions -->
    <div class="col-md-4">
        <!-- Client Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-building"></i> Client Information</h5>
            </div>
            <div class="card-body">
                <h6><?= escape($job['company_name']) ?></h6>
                <?php if ($job['contact_person']): ?>
                    <p class="mb-2">
                        <small class="text-muted">Contact:</small><br>
                        <strong><?= escape($job['contact_person']) ?></strong>
                    </p>
                <?php endif; ?>
                <?php if ($job['client_email']): ?>
                    <p class="mb-2">
                        <small class="text-muted">Email:</small><br>
                        <a href="mailto:<?= escape($job['client_email']) ?>"><?= escape($job['client_email']) ?></a>
                    </p>
                <?php endif; ?>
                <?php if ($job['client_phone']): ?>
                    <p class="mb-2">
                        <small class="text-muted">Phone:</small><br>
                        <a href="tel:<?= escape($job['client_phone']) ?>"><?= escape($job['client_phone']) ?></a>
                    </p>
                <?php endif; ?>
                <hr>
                <a href="../clients/?action=view&code=<?= escape($job['client_code']) ?>" 
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="bx bx-link-external"></i> View Client Profile
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-cog"></i> Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (Permission::can('jobs', 'edit')): ?>
                        <a href="?action=edit&code=<?= escape($job_code) ?>" class="btn btn-primary">
                            <i class="bx bx-edit"></i> Edit Job
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($job['status'] === 'open' && Permission::can('jobs', 'close')): ?>
                        <form method="POST" action="handlers/close.php" onsubmit="return confirm('Close this job?')">
                            <?= CSRFToken::field() ?>
                            <input type="hidden" name="job_code" value="<?= escape($job_code) ?>">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bx bx-x-circle"></i> Close Job
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($job['is_published']): ?>
                        <a href="/public/job-detail.php?ref=<?= escape($job['job_refno']) ?>" 
                           class="btn btn-info" target="_blank">
                            <i class="bx bx-link-external"></i> View Public Page
                        </a>
                    <?php endif; ?>
                    
                    <a href="?action=list" class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submit Candidate Modal -->
<div class="modal fade" id="addCandidateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Candidate to Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="submitCandidateForm" action="/panel/modules/jobs/handlers/submit-candidate.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="job_code" value="<?= escape($job_code) ?>">  <!-- ✅ FIXED -->
                
                <div class="modal-body">
                    <!-- Search Candidate -->
                    <div class="mb-3">
                        <label class="form-label">Search Candidate</label>
                        <select class="form-select" id="candidateSearch" name="candidate_code" required>
                            <option value="">Type to search candidates...</option>
                        </select>
                        <small class="text-muted">Search by name, email, or skills</small>
                    </div>
                    
                    <!-- Or Create New -->
                    <div class="text-center mb-3">
                        <span class="text-muted">OR</span>
                    </div>
                    
                    <div class="text-center mb-3">
                        <a href="/panel/modules/candidates/?action=create&job=<?= escape($job_code) ?>" 
                           class="btn btn-outline-primary">
                            <i class="bx bx-plus"></i> Create New Candidate
                        </a>
                    </div>
                    
                    <hr>
                    
                    <!-- Submission Details -->
                    <div class="mb-3">
                        <label class="form-label">Submission Notes</label>
                        <textarea class="form-control" name="submission_notes" rows="4" 
                                  placeholder="Why is this candidate a good fit?"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="notify_manager" value="1" id="notifyMgr" checked>
                        <label class="form-check-label" for="notifyMgr">
                            Notify manager for approval
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-send"></i> Submit Candidate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include Select2 CSS/JS if not already included -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Initialize Select2 for candidate search
$(document).ready(function() {
    $('#candidateSearch').select2({
        dropdownParent: $('#addCandidateModal'),
        ajax: {
            url: '/panel/modules/candidates/handlers/search.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    status: 'active,qualified,screening' // Only active candidates
                };
            },
            processResults: function(data) {
                if (!data.success || !data.results) {
                    return { results: [] };
                }
                return {
                    results: data.results.map(c => ({
                        id: c.candidate_code,
                        text: c.candidate_name + ' (' + c.email + ')',
                        data: c
                    }))
                };
            }
        },
        minimumInputLength: 2,
        placeholder: 'Type to search candidates...'
    });
});

// Form submission
document.getElementById('submitCandidateForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Reload to show new submission
        } else {
            alert('Error: ' + (result.message || 'Failed to submit candidate'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        alert('Error: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>