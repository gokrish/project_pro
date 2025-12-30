<?php
/**
 * View Job Details
 * Shows complete job information with actions
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

// Check permission
Permission::require('jobs', 'view');

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
    SELECT 
        j.*,
        c.company_name, c.contact_person, c.contact_email, c.contact_phone,
        u1.name as created_by_name,
        u2.name as assigned_to_name,
        u3.name as approved_by_name,
        u4.name as rejected_by_name,
        (SELECT COUNT(*) FROM applications a WHERE a.job_code = j.job_code) as applications_count,
        (SELECT COUNT(*) FROM applications a WHERE a.job_code = j.job_code AND a.status = 'screening') as screening_count,
        (SELECT COUNT(*) FROM applications a WHERE a.job_code = j.job_code AND a.status = 'interview') as interview_count,
        (SELECT COUNT(*) FROM applications a WHERE a.job_code = j.job_code AND a.status = 'offer') as offer_count
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u1 ON j.created_by = u1.user_code
    LEFT JOIN users u2 ON j.assigned_to = u2.user_code
    LEFT JOIN users u3 ON j.approved_by = u3.user_code
    LEFT JOIN users u4 ON j.rejected_by = u4.user_code
    WHERE j.job_code = ? AND j.deleted_at IS NULL
");
$stmt->bind_param("s", $jobCode);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    redirectWithMessage('/panel/modules/jobs/list.php', 'Job not found', 'error');
}

// Get job notes
$stmt = $conn->prepare("
    SELECT n.*, u.name as created_by_name
    FROM job_notes n
    LEFT JOIN users u ON n.created_by = u.user_code
    WHERE n.job_code = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param("s", $jobCode);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Page configuration
$pageTitle = $job['job_title'];
$breadcrumbs = [
    ['title' => 'Jobs', 'url' => '/panel/modules/jobs/list.php'],
    ['title' => $job['job_title'], 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h4 class="fw-bold mb-2"><?= htmlspecialchars($job['job_title']) ?></h4>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <?php
                    $statusColors = [
                        'draft' => 'secondary',
                        'pending_approval' => 'warning',
                        'approved' => 'info',
                        'open' => 'success',
                        'closed' => 'dark'
                    ];
                    $color = $statusColors[$job['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $color ?>">
                        <?= ucwords(str_replace('_', ' ', $job['status'])) ?>
                    </span>
                    
                    <?php if ($job['is_published']): ?>
                        <span class="badge bg-success">
                            <i class="bx bx-globe"></i> Published
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($job['approval_status'] === 'pending_approval'): ?>
                        <span class="badge bg-warning">
                            <i class="bx bx-time"></i> Awaiting Approval
                        </span>
                    <?php endif; ?>
                    
                    <span class="badge bg-label-secondary">
                        <i class="bx bx-building"></i> <?= htmlspecialchars($job['company_name']) ?>
                    </span>
                    
                    <span class="badge bg-label-info">
                        <i class="bx bx-code-alt"></i> <?= htmlspecialchars($job['job_code']) ?>
                    </span>
                </div>
            </div>
            
            <div>
                <a href="list.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bx bx-arrow-back"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Job Details -->
    <div class="col-lg-8">
        <!-- Job Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Job Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Client</label>
                        <p class="mb-0"><?= htmlspecialchars($job['company_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Job Reference</label>
                        <p class="mb-0"><?= htmlspecialchars($job['job_refno'] ?: 'N/A') ?></p>
                    </div>
                </div>
                
                <?php if ($job['location']): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Location</label>
                        <p class="mb-0">
                            <i class="bx bx-map me-1"></i>
                            <?= htmlspecialchars($job['location']) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Employment Type</label>
                        <p class="mb-0"><?= ucfirst($job['employment_type'] ?? 'N/A') ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($job['salary_min'] || $job['salary_max']): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Salary Range</label>
                        <p class="mb-0">
                            <?php if ($job['salary_min']): ?>
                                €<?= number_format($job['salary_min'], 0) ?>
                            <?php endif; ?>
                            <?php if ($job['salary_min'] && $job['salary_max']): ?>
                                -
                            <?php endif; ?>
                            <?php if ($job['salary_max']): ?>
                                €<?= number_format($job['salary_max'], 0) ?>
                            <?php endif; ?>
                            <?php if ($job['salary_period']): ?>
                                / <?= $job['salary_period'] ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Assigned To</label>
                        <p class="mb-0">
                            <i class="bx bx-user me-1"></i>
                            <?= htmlspecialchars($job['assigned_to_name'] ?? 'Unassigned') ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Created</label>
                        <p class="mb-0">
                            <?= date('M d, Y g:i A', strtotime($job['created_at'])) ?><br>
                            <small class="text-muted">by <?= htmlspecialchars($job['created_by_name']) ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Job Description -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Job Description</h5>
            </div>
            <div class="card-body">
                <div class="job-description">
                    <?= $job['description'] ?>
                </div>
            </div>
        </div>
        
        <!-- Requirements (if exists) -->
        <?php if (!empty($job['requirements'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Requirements</h5>
            </div>
            <div class="card-body">
                <div class="job-requirements">
                    <?= $job['requirements'] ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Internal Notes (if any) -->
        <?php if (!empty($notes)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-note text-warning me-2"></i>
                    Internal Notes
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($notes as $note): ?>
                    <div class="mb-3 p-3 border rounded">
                        <div class="d-flex justify-content-between mb-2">
                            <strong><?= htmlspecialchars($note['created_by_name']) ?></strong>
                            <small class="text-muted">
                                <?= date('M d, Y g:i A', strtotime($note['created_at'])) ?>
                            </small>
                        </div>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Approval History -->
        <?php if ($job['approval_status'] === 'approved' || $job['approval_status'] === 'rejected'): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Approval History</h5>
            </div>
            <div class="card-body">
                <?php if ($job['approval_status'] === 'approved'): ?>
                    <div class="alert alert-success">
                        <i class="bx bx-check-circle me-2"></i>
                        <strong>Approved</strong> by <?= htmlspecialchars($job['approved_by_name']) ?>
                        on <?= date('M d, Y g:i A', strtotime($job['approved_at'])) ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="bx bx-x-circle me-2"></i>
                        <strong>Rejected</strong> by <?= htmlspecialchars($job['rejected_by_name']) ?>
                        on <?= date('M d, Y g:i A', strtotime($job['rejected_at'])) ?>
                        <br><br>
                        <strong>Reason:</strong><br>
                        <?= nl2br(htmlspecialchars($job['rejection_reason'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<ul class="nav nav-tabs">
    <li><a href="#details">Details</a></li>
    <li><a href="#submissions">Submissions (<?= $submissionCount ?>)</a></li>
</ul>

<div id="submissions">
    <?php foreach ($submissions as $sub): ?>
        <div class="submission-card">
            <strong><?= $sub['candidate_name'] ?></strong>
            <span class="badge"><?= $sub['client_status'] ?></span>
            <a href="/panel/modules/submissions/view.php?code=<?= $sub['submission_code'] ?>">View</a>
        </div>
    <?php endforeach; ?>
</div>
    <!-- Right Column: Actions & Statistics -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <?php if (Permission::can('jobs', 'edit')): ?>
                    <a href="edit.php?code=<?= urlencode($jobCode) ?>" class="btn btn-primary w-100 mb-2">
                        <i class="bx bx-edit"></i> Edit Job
                    </a>
                <?php endif; ?>
                
                <?php if ($job['status'] === 'draft' && Permission::can('jobs', 'edit')): ?>
                    <button type="button" class="btn btn-warning w-100 mb-2" id="submitApprovalBtn">
                        <i class="bx bx-send"></i> Submit for Approval
                    </button>
                <?php endif; ?>
                
                <?php if ($job['approval_status'] === 'pending_approval' && Permission::can('jobs', 'approve')): ?>
                    <a href="approve.php?code=<?= urlencode($jobCode) ?>" class="btn btn-success w-100 mb-2">
                        <i class="bx bx-check-circle"></i> Review & Approve
                    </a>
                <?php endif; ?>
                
                <?php if ($job['status'] === 'open' && Permission::can('jobs', 'edit')): ?>
                    <button type="button" class="btn btn-outline-secondary w-100 mb-2" id="closeJobBtn">
                        <i class="bx bx-x-circle"></i> Close Job
                    </button>
                <?php endif; ?>
                
                <?php if (Permission::can('jobs', 'delete')): ?>
                    <button type="button" class="btn btn-outline-danger w-100" id="deleteJobBtn">
                        <i class="bx bx-trash"></i> Delete Job
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Application Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Applications</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Total Applications</span>
                    <span class="badge bg-primary badge-lg"><?= $job['applications_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>In Screening</span>
                    <span class="badge bg-info"><?= $job['screening_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>In Interview</span>
                    <span class="badge bg-warning"><?= $job['interview_count'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Offer Stage</span>
                    <span class="badge bg-success"><?= $job['offer_count'] ?></span>
                </div>
                
                <?php if ($job['applications_count'] > 0): ?>
                    <hr>
                    <a href="/panel/modules/applications/list.php?job_code=<?= urlencode($jobCode) ?>" 
                       class="btn btn-sm btn-outline-primary w-100">
                        View All Applications
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Job Link -->
        <?php if ($job['is_published']): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Public Link</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">This job is published and visible to candidates:</p>
                <div class="input-group">
                    <input type="text" class="form-control" readonly 
                           value="<?= BASE_URL ?>/public/job-detail.php?code=<?= urlencode($jobCode) ?>" 
                           id="jobLink">
                    <button class="btn btn-outline-primary" type="button" onclick="copyJobLink()">
                        <i class="bx bx-copy"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden data for JavaScript -->
<div id="jobData" 
     data-code="<?= htmlspecialchars($jobCode) ?>" 
     data-title="<?= htmlspecialchars($job['job_title']) ?>"
     style="display: none;"></div>

<script>
// Submit for approval
document.getElementById('submitApprovalBtn')?.addEventListener('click', function() {
    if (!confirm('Submit this job for approval?')) return;
    
    const jobCode = document.getElementById('jobData').dataset.code;
    
    fetch('handlers/submit-for-approval.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            job_code: jobCode,
            csrf_token: '<?= CSRFToken::generate() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Close job
document.getElementById('closeJobBtn')?.addEventListener('click', function() {
    const reason = prompt('Please enter a reason for closing this job:');
    if (!reason) return;
    
    const jobCode = document.getElementById('jobData').dataset.code;
    
    fetch('handlers/close.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            job_code: jobCode,
            close_reason: reason,
            csrf_token: '<?= CSRFToken::generate() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Delete job
document.getElementById('deleteJobBtn')?.addEventListener('click', function() {
    const jobData = document.getElementById('jobData');
    const jobTitle = jobData.dataset.title;
    
    if (!confirm(`Are you sure you want to delete "${jobTitle}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('handlers/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            job_code: jobData.dataset.code,
            csrf_token: '<?= CSRFToken::generate() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'list.php';
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Copy job link
function copyJobLink() {
    const input = document.getElementById('jobLink');
    input.select();
    document.execCommand('copy');
    alert('Link copied to clipboard!');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>