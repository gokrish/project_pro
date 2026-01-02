<?php
/**
* Candidate Unified Profile View
* Complete candidate journey
*
* Features:
* - SQL injection protection (100% prepared statements)
* - Job activity tracking (submissions)
* - HR comments & notes
* - Document preview (in-page PDF/image viewer)
* - Activity timeline
* - Permission-based access
*
* @version 2.0
*/
require_once __DIR__ . '/../_common.php';
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\CSRFToken;
function getInternalStatusColor($status) {
    return match($status) {
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'withdrawn' => 'secondary',
        default => 'info'
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
        default => 'info'
    };
}

// Check permission - SQL injection safe (no user input here)
if (!Permission::can('candidates', 'view_all') && !Permission::can('candidates', 'view_own')) {
    header('Location: /path?error=' . urlencode('You do not have permission to view this candidate'));
    exit;
}

$user = Auth::user();
$userCode = Auth::userCode();
$userLevel = $user['level'] ?? 'user';

// Get candidate code from URL - SANITIZE INPUT
$candidateCode = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
if (empty($candidateCode)) {
    header('Location: /panel/modules/candidates/list.php?error=' . urlencode('Candidate code is missing'));
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================================
// GET CANDIDATE DATA - SQL INJECTION SAFE
// ============================================================================
// Get candidate with access control
$accessFilter = Permission::getAccessibleCandidates();
$whereClause = $accessFilter ? "candidate_code = ? AND ({$accessFilter})" : "candidate_code = ?";
$stmt = $conn->prepare("
    SELECT c.*, u.name as assigned_to_name
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    WHERE {$whereClause}
    AND c.deleted_at IS NULL
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

if (!$candidate) {
    redirectWithMessage('/panel/modules/candidates/list.php', 'Candidate not found or access denied', 'error');
}

// ============================================================================
// GET SUBMISSIONS DATA - SQL INJECTION SAFE
// ============================================================================
$stmt = $conn->prepare("
    SELECT
        s.submission_code,
        s.job_code,
        s.candidate_code,
        s.internal_status,
        s.client_status,
        s.submission_notes,
        s.created_at as submitted_at,
        s.approved_at as reviewed_at,
        s.approval_notes as review_notes,
        j.job_title,
        j.location as job_location,
        c.company_name as client_name,
        u1.name as submitted_by_name,
        u2.name as approved_by_name
    FROM submissions s  -- ✅ CORRECT TABLE
    LEFT JOIN jobs j ON s.job_code = j.job_code
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u1 ON s.submitted_by = u1.user_code
    LEFT JOIN users u2 ON s.approved_by = u2.user_code
    WHERE s.candidate_code = ?
    AND s.deleted_at IS NULL
    ORDER BY s.created_at DESC
");



// ============================================================================
// GET DOCUMENTS - SQL INJECTION SAFE
// ============================================================================
$stmt = $conn->prepare("
    SELECT *
    FROM candidate_documents
    WHERE candidate_code = ?
    AND deleted_at IS NULL
    ORDER BY uploaded_at DESC
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET NOTES - SQL INJECTION SAFE
// ============================================================================
$stmt = $conn->prepare("
    SELECT n.*, u.name as created_by_name
    FROM candidate_notes n
    LEFT JOIN users u ON n.created_by = u.user_code
    WHERE n.candidate_code = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET ACTIVITY LOGS - SQL INJECTION SAFE
// ============================================================================
$stmt = $conn->prepare("
    SELECT al.*, u.name as user_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_code = u.user_code
    WHERE al.module = 'candidates'
    AND al.record_id = ?
    ORDER BY al.created_at DESC
    LIMIT 100
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$activityLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================
function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[count($words)-1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function getStatusBadgeColor($status) {
    $colors = [
        'new' => 'primary',
        'contacted' => 'info',
        'proposed_submission' => 'warning',
        'submitted' => 'secondary',
        'offered' => 'warning',
        'placed' => 'success',
        'rejected' => 'danger',
        'archived' => 'secondary',
        // Submission statuses
        'draft' => 'secondary',
        'pending_review' => 'warning',
        'approved' => 'success',
        'submitted_to_client' => 'info',
        'accepted' => 'success',
        'on_hold' => 'warning'
    ];
    return $colors[strtolower($status)] ?? 'secondary';
}

function getLeadTypeBadge($leadType) {
    $badges = [
        'hot' => '<span class="badge bg-danger">🔥 Hot Lead</span>',
        'warm' => '<span class="badge bg-warning text-dark">🌡️ Warm Lead</span>',
        'cold' => '<span class="badge bg-info">❄️ Cold Lead</span>',
        'blacklist' => '<span class="badge bg-dark">⛔ Blacklist</span>'
    ];
    return $badges[strtolower($leadType)] ?? '<span class="badge bg-secondary">Unknown</span>';
}

function can($resource, $permission) {
    return Permission::can($resource, $permission);
}

function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount, $currency = 'EUR') {
    if (empty($amount) || $amount <= 0) return 'Not specified';
    return '€' . number_format($amount, 0, '.', ',');
}

function getRatingStars($rating) {
    if (empty($rating)) return '<span class="text-muted">Not rated</span>';
    $stars = str_repeat('⭐', intval($rating));
    $empty = str_repeat('☆', 5 - intval($rating));
    return $stars . $empty . ' (' . $rating . '/5)';
}

function formatDateTime($dateTime) {
    if (empty($dateTime) || $dateTime == '0000-00-00 00:00:00') return 'Not set';
    $date = new DateTime($dateTime);
    return $date->format('M d, Y H:i');
}

// Parse skills
$skills = [];
if (!empty($candidate['skills'])) {
    // Try JSON first
    $skillsDecoded = json_decode($candidate['skills'], true);
    if (is_array($skillsDecoded)) {
        $skills = $skillsDecoded;
    } else {
        // Fallback to comma-separated
        $skills = array_filter(array_map('trim', explode(',', $candidate['skills'])));
    }
}

// Log view activity - SQL INJECTION SAFE
Logger::getInstance()->logActivity('view', 'candidates', $candidateCode, 'Viewed candidate profile');

// Page configuration
$pageTitle = 'Candidate Profile - ' . htmlspecialchars($candidate['candidate_name']);
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php'],
    ['title' => htmlspecialchars($candidate['candidate_name']), 'url' => '']
];
$customCSS = ['/panel/assets/css/modules/candidates-view.css'];
$customJS = ['/panel/assets/js/modules/candidates-view.js'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- Main Content (70%) -->
    <div class="col-lg-8">
        <!-- Profile Header -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div class="d-flex align-items-start">
                        <!-- Avatar -->
                        <div class="avatar avatar-xl me-3">
                            <span class="avatar-initial rounded-circle bg-label-primary">
                                <?= getInitials($candidate['candidate_name']) ?>
                            </span>
                        </div>
                        <!-- Name & Status -->
                        <div>
                            <h4 class="mb-2"><?= escape($candidate['candidate_name']) ?></h4>
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                <?= getLeadTypeBadge($candidate['lead_type']) ?>
                                <span class="badge bg-label-<?= getStatusBadgeColor($candidate['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $candidate['status'])) ?>
                                </span>
                                <?php if (!empty($candidate['assigned_to_name'])): ?>
                                    <span class="text-muted">
                                        <i class="bx bx-user me-1"></i> <?= escape($candidate['assigned_to_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-3 text-muted small">
                                <span><i class="bx bx-phone me-1"></i> <?= escape($candidate['phone'] ?: 'No phone') ?></span>
                                <span><i class="bx bx-envelope me-1"></i> <?= escape($candidate['email'] ?: 'No email') ?></span>
                                <?php if ($candidate['status'] !== 'new'): ?>
                                    <span><i class="bx bx-time-five me-1"></i> In process for <?= $candidate['days_in_status'] ?? 0 ?> days</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (can('candidates', 'edit')): ?>
                            <a href="/panel/modules/candidates/edit.php?code=<?= urlencode($candidateCode) ?>"
                                class="btn btn-primary">
                                <i class="bx bx-edit me-1"></i> Edit Profile
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($candidate['email'])): ?>
                            <a href="mailto:<?= escape($candidate['email']) ?>"
                                class="btn btn-outline-primary">
                                <i class="bx bx-envelope me-1"></i> Email
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($candidate['phone'])): ?>
                            <a href="tel:<?= escape($candidate['phone']) ?>"
                                class="btn btn-outline-primary">
                                <i class="bx bx-phone me-1"></i> Call
                            </a>
                        <?php endif; ?>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bx bx-dots-horizontal-rounded"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <?php if (can('candidates', 'add_note')): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                            <i class="bx bx-message-dots me-2"></i> Add Note
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (can('candidates', 'upload_document')): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                            <i class="bx bx-upload me-2"></i> Upload Document
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if (can('candidates', 'change_status')): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                                            <i class="bx bx-refresh me-2"></i> Change Status
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($candidate['status'] === 'new'): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#screeningNotesModal">
                                            <i class="bx bx-phone-call me-2"></i> Log First Contact
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($candidate['status'] === 'proposed_submission'): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#submitToJobModal">
                                            <i class="bx bx-send me-2"></i> Submit to Job
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="window.print()">
                                        <i class="bx bx-printer me-2"></i> Print Profile
                                    </a>
                                </li>
                                <?php if (can('candidates', 'delete')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" id="deleteCandidate">
                                            <i class="bx bx-trash me-2"></i> Delete Candidate
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-phone me-1"></i> Contact Information</h5>
                <?php if (can('candidates', 'edit')): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#updatePhoneModal">
                        <i class="bx bx-edit"></i> Update
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Primary Phone</label>
                            <div id="display-phone" class="form-control-plaintext">
                                <?= escape($candidate['phone'] ?: 'Not provided') ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address</label>
                            <div class="form-control-plaintext">
                                <?= escape($candidate['email'] ?: 'Not provided') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Alternate Phone</label>
                            <div id="display-phone-alt" class="form-control-plaintext">
                                <?= escape($candidate['phone_alternate'] ?: 'Not provided') ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <div class="form-control-plaintext">
                                <?= escape($candidate['city'] . ', ' . $candidate['country']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nav Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                    <i class="bx bx-user me-1"></i> Overview
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#professional" type="button" role="tab">
                    <i class="bx bx-briefcase me-1"></i> Professional
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#job-activity" type="button" role="tab">
                    <i class="bx bx-line-chart me-1"></i> Job Activity
                    <?php if (count($submissions) + count($applications) > 0): ?>
                        <span class="badge bg-primary rounded-pill ms-1"><?= count($submissions) + count($applications) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                    <i class="bx bx-file me-1"></i> Documents
                    <?php if (count($documents) > 0): ?>
                        <span class="badge bg-primary rounded-pill ms-1"><?= count($documents) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#communications" type="button" role="tab">
                    <i class="bx bx-message-dots me-1"></i> Communications
                    <?php if (count($notes) > 0): ?>
                        <span class="badge bg-primary rounded-pill ms-1"><?= count($notes) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                    <i class="bx bx-history me-1"></i> Activity
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- ================================================================ -->
            <!-- TAB 1: OVERVIEW -->
            <!-- ================================================================ -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <?php include __DIR__ . '/components/tab-overview.php'; ?>
            </div>
            <!-- ================================================================ -->
            <!-- TAB 2: PROFESSIONAL -->
            <!-- ================================================================ -->
            <div class="tab-pane fade" id="professional" role="tabpanel">
                <?php include __DIR__ . '/components/tab-professional.php'; ?>
            </div>
            <!-- ================================================================ -->
            <!-- TAB 3: JOB ACTIVITY (Submissions + Interviews + Placements) -->
            <!-- ================================================================ -->
            <div class="tab-pane fade" id="job-activity" role="tabpanel">
                <?php include __DIR__ . '/components/tab-job-activity.php'; ?>
            </div>
            <!-- ================================================================ -->
            <!-- TAB 4: DOCUMENTS -->
            <!-- ================================================================ -->
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <?php include __DIR__ . '/components/tab-documents.php'; ?>
            </div>
            <!-- ================================================================ -->
            <!-- TAB 5: COMMUNICATIONS (Notes & HR Comments) -->
            <!-- ================================================================ -->
            <div class="tab-pane fade" id="communications" role="tabpanel">
                <?php include __DIR__ . '/components/tab-communications.php'; ?>
            </div>
            <!-- ================================================================ -->
            <!-- TAB 6: ACTIVITY TIMELINE -->
            <!-- ================================================================ -->
            <div class="tab-pane fade" id="activity" role="tabpanel">
                <?php include __DIR__ . '/components/tab-activity.php'; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions Sidebar (30%) -->
    <div class="col-lg-4">
        <div class="sticky-top" style="top: 1rem;">
            <!-- Candidate Journey Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bx bx-trending-up me-1"></i> Candidate Journey</h5>
                </div>
                <div class="card-body">
                    <!-- Visual status workflow -->
                    <ul class="timeline timeline-dynamic mb-4">
                        <li class="<?= $candidate['status'] === 'new' ? 'active' : ($candidate['status'] !== 'new' && $candidate['status'] !== 'contacted' && $candidate['status'] !== 'proposed_submission' && $candidate['status'] !== 'submitted' && $candidate['status'] !== 'placed' ? 'completed' : '') ?>">
                            <span class="timeline-bullet"></span>
                            <div class="timeline-content">
                                <small>New</small>
                            </div>
                        </li>
                        <li class="<?= $candidate['status'] === 'contacted' ? 'active' : ($candidate['status'] === 'proposed_submission' || $candidate['status'] === 'submitted' || $candidate['status'] === 'placed' ? 'completed' : '') ?>">
                            <span class="timeline-bullet"></span>
                            <div class="timeline-content">
                                <small>Contacted</small>
                            </div>
                        </li>
                        <li class="<?= $candidate['status'] === 'proposed_submission' ? 'active' : ($candidate['status'] === 'submitted' || $candidate['status'] === 'placed' ? 'completed' : '') ?>">
                            <span class="timeline-bullet"></span>
                            <div class="timeline-content">
                                <small>Proposed Sumbission</small>
                            </div>
                        </li>
                        <li class="<?= $candidate['status'] === 'submitted' ? 'active' : ($candidate['status'] === 'placed' ? 'completed' : '') ?>">
                            <span class="timeline-bullet"></span>
                            <div class="timeline-content">
                                <small>Submitted</small>
                            </div>
                        </li>
                        <li class="<?= $candidate['status'] === 'placed' ? 'active completed' : '' ?>">
                            <span class="timeline-bullet"></span>
                            <div class="timeline-content">
                                <small>Placed</small>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bx bx-fast-forward me-1"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($candidate['status'] === 'new'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#screeningNotesModal">
                                <i class="bx bx-phone-call me-1"></i> Log First Contact
                            </button>
                            <button class="btn btn-outline-primary disabled" disabled>
                                <i class="bx bx-send me-1"></i> Submit to Job
                            </button>
                        <?php elseif ($candidate['status'] === 'contacted'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-status="proposed_submission">
                                <i class="bx bx-check me-1"></i> Mark as Proposal for Submission (Not Ready)
                            </button>
                            <button class="btn btn-outline-primary disabled" disabled>
                                <i class="bx bx-send me-1"></i> Submit to Job (Complete Screening First)
                            </button>
                        <?php elseif ($candidate['status'] === 'proposed_submission'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitToJobModal">
                                <i class="bx bx-send me-1"></i> Submit to Job
                            </button>
                            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-status="contacted">
                                <i class="bx bx-arrow-back me-1"></i> Return to Screening
                            </button>
                        <?php elseif ($candidate['status'] === 'submitted'): ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-status="placed">
                                <i class="bx bx-check-circle me-1"></i> Mark as Placed
                            </button>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#changeStatusModal" data-status="rejected">
                                <i class="bx bx-x-circle me-1"></i> Mark as Rejected
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline-primary disabled" disabled>
                                <i class="bx bx-check me-1"></i> Workflow Complete
                            </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($candidate['next_follow_up'])): ?>
                            <div class="mt-3 p-2 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-calendar me-2 text-primary fs-4"></i>
                                    <div>
                                        <small>Next Follow-up</small>
                                        <h6 class="mb-0"><?= date('M d, Y', strtotime($candidate['next_follow_up'])) ?></h6>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Summary -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bx bx-stats me-1"></i> Activity Summary</h5>
                </div>

                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: <?= min(100, count($applications) * 25) ?>%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <small>Interviews</small>
                        <span class="badge bg-success"><?= count($interviews) ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, count($interviews) * 33) ?>%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <small>Placements</small>
                        <span class="badge bg-label-success"><?= count($placements) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php include __DIR__ . '/modals/add-note-modal.php'; ?>
<?php include __DIR__ . '/modals/upload-document-modal.php'; ?>
<?php include __DIR__ . '/modals/change-status-modal.php'; ?>

<!-- Phone Update Modal -->
<div class="modal fade" id="updatePhoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="updatePhoneForm">
                <div class="modal-header">
                    <h5 class="modal-title">Update Contact Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate(); ?>">
                    <input type="hidden" name="candidate_code" value="<?= $candidate['candidate_code']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Primary Phone</label>
                        <input type="text" class="form-control" name="phone"
                            value="<?= escape($candidate['phone']); ?>"
                            placeholder="+32 XXX XX XX XX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alternate Phone</label>
                        <input type="text" class="form-control" name="phone_alternate"
                            value="<?= escape($candidate['phone_alternate']); ?>"
                            placeholder="+32 XXX XX XX XX">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Screening Notes Modal -->
<div class="modal fade" id="screeningNotesModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="handlers/add_screening_notes.php" id="screeningNotesForm">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="candidate_code" value="<?= escape($candidateCode) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-note me-2"></i>
                        Initial Screening Notes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Purpose:</strong> Capture key information from contact conversation for job requirments.
                        Candidate status will automatically change to "Contacted".
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interest Level <span class="text-danger">*</span></label>
                            <select name="interest_level" class="form-select" required>
                                <option value="high">🔥 High - Very Interested</option>
                                <option value="medium" selected>👍 Medium - Somewhat Interested</option>
                                <option value="low">😐 Low - Not Very Interested</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Availability Any changes</label>
                            <input type="text" name="availability" class="form-control"
                                placeholder="e.g., Availble Immediate, 2 weeks, 1 month">
                            <small class="text-muted">When can they start?</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Any changes in expections?</label>
                        <input type="text" name="salary_expectation" class="form-control"
                            placeholder="if yes, add notes">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Key Discussion Points <span class="text-danger">*</span></label>
                        <textarea name="screening_notes" class="form-control" rows="5" required
                            placeholder="Summarize the conversation:
- What motivated them to look for new opportunities?
- What are they looking for in next role?
- Key skills confirmed
- Any concerns or red flags
- Overall impression"><?= escape($candidate['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Next Follow-up Date</label>
                        <input type="date" name="next_follow_up" class="form-control"
                            min="<?= date('Y-m-d') ?>">
                        <small class="text-muted">When should you contact them next?</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Save Screening Notes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Submit to Job Modal -->
<div class="modal fade" id="submitToJobModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="handlers/submit_to_job.php" id="submitJobForm">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="candidate_code" value="<?= escape($candidateCode) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-send me-2"></i>
                        Submit Candidate to Job
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Candidate Summary -->
                    <div class="alert alert-light border">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?= escape($candidate['candidate_name']) ?></strong>
                                <div class="text-muted small">
                                    <?= escape($candidate['current_position']) ?> •
                                    <?= $candidate['total_experience'] ?> years exp
                                </div>
                            </div>
                            <span class="badge bg-<?= getStatusBadgeColor($candidate['lead_type']) ?>">
                                <?= ucfirst($candidate['lead_type']) ?>
                            </span>
                        </div>
                    </div>
                    <!-- Job Selection -->
                    <div class="mb-3">
                        <label class="form-label">Select Job <span class="text-danger">*</span></label>
                        <select name="job_code" class="form-select" required id="jobSelect">
                            <option value="">-- Choose Job --</option>
                            <?php
                            // Get active jobs
                            $stmt = $conn->prepare("
                                SELECT j.job_code, j.job_title, c.company_name, j.location
                                FROM jobs j
                                LEFT JOIN clients c ON j.client_code = c.client_code
                                WHERE j.status = 'active' AND j.deleted_at IS NULL
                                ORDER BY j.created_at DESC
                                LIMIT 50
                            ");
                            $stmt->execute();
                            $activeJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            foreach ($activeJobs as $job):
                            ?>
                            <option value="<?= escape($job['job_code']) ?>">
                                <?= escape($job['job_title']) ?> - <?= escape($job['company_name']) ?>
                                (<?= escape($job['location']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only active jobs are shown</small>
                    </div>
                    <!-- Why Good Fit -->
                    <div class="mb-3">
                        <label class="form-label">Why is this candidate a good fit? <span class="text-danger">*</span></label>
                        <textarea name="fit_reason" class="form-control" rows="4" required
                            placeholder="Explain why this candidate matches the job requirements:
- Relevant skills match
- Industry experience
- Cultural fit
- Location/availability alignment
- Any other key selling points"></textarea>
                    </div>
                    <!-- Proposed Rate -->
                    <div class="mb-3">
                        <label class="form-label">Proposed Rate/Salary</label>
                        <input type="text" name="proposed_rate" class="form-control"
                            placeholder="e.g., $85,000/year, $50/hour, €60K">
                        <small class="text-muted">Leave blank if not determined yet</small>
                    </div>
                    <!-- Additional Notes -->
                    <div class="mb-3">
                        <label class="form-label">Additional Notes (Internal)</label>
                        <textarea name="notes" class="form-control" rows="2"
                            placeholder="Any additional context for the hiring team..."></textarea>
                    </div>
                    <!-- Warning for non-proposed_submission -->
                    <?php if ($candidate['status'] !== 'proposed_submission'): ?>
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Note:</strong> This candidate is not marked as "Qualified" yet.
                        Status will automatically change to "Submitted" after submission.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-send me-1"></i> Submit to Job
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Candidate code for JavaScript
const candidateCode = '<?= escape($candidateCode) ?>';
const csrfToken = '<?= CSRFToken::getToken() ?>';
const candidateStatus = '<?= escape($candidate['status']) ?>';

// Update phone form submission
document.getElementById('updatePhoneForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('handlers/update_phone.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            // Update displayed values
            document.getElementById('display-phone').textContent = data.data.phone || 'Not provided';
            document.getElementById('display-phone-alt').textContent = data.data.phone_alternate || 'Not provided';
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('updatePhoneModal')).hide();
            
            // Show success notification
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to update phone numbers'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Network error occurred while updating phone numbers'
        });
    }
});

// Submit to job form validation
document.getElementById('submitJobForm').addEventListener('submit', function(e) {
    const jobCode = document.getElementById('jobSelect').value;
    const fitReason = document.querySelector('textarea[name="fit_reason"]').value.trim();
    
    if (!jobCode) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Missing Job',
            text: 'Please select a job for this candidate'
        });
        return;
    }
    
    if (!fitReason || fitReason.length < 20) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Fit Reason Too Short',
            text: 'Please provide a detailed explanation (at least 20 characters) of why this candidate is a good fit'
        });
        return;
    }
});

// Pre-fill status in modal when opened
const changeStatusModal = document.getElementById('changeStatusModal');
if (changeStatusModal) {
    changeStatusModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const targetStatus = button.getAttribute('data-status');
        
        if (targetStatus) {
            const statusSelect = document.getElementById('newStatusSelect');
            if (statusSelect) {
                statusSelect.value = targetStatus;
                // Trigger change event to update requirements
                statusSelect.dispatchEvent(new Event('change'));
            }
        }
    });
}

// Status change form validation
document.getElementById('statusForm').addEventListener('submit', function(e) {
    const status = document.getElementById('newStatusSelect').value;
    const notes = document.getElementById('notesTextarea').value.trim();
    const followUp = document.getElementById('nextFollowUpInput').value;
    
    if (!status) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Missing Status',
            text: 'Please select a status'
        });
        return;
    }
    
    // Validate based on status
    if (status === 'contacted') {
        if (!notes) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Missing Notes',
                text: 'Notes are required when marking as contacted'
            });
            return;
        }
        if (!followUp) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Missing Follow-up Date',
                text: 'Next follow-up date is required when marking as contacted'
            });
            return;
        }
    }
    
    if (status === 'rejected' && !notes) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Missing Reason',
            text: 'Rejection reason is required'
        });
        return;
    }
    
    if (status === 'placed' && !notes) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Missing Placement Details',
            text: 'Placement details are required'
        });
        return;
    }
});

// Delete candidate
document.getElementById('deleteCandidate').addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Delete Candidate?',
        text: 'This action cannot be undone. All related data will be deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/panel/modules/candidates/handlers/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    candidate_code: candidateCode,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'Candidate has been deleted.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = '/panel/modules/candidates/list.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to delete candidate',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to delete candidate',
                    icon: 'error'
                });
            });
        }
    });
});
<?php if ($candidate['status'] === 'qualified'): ?>
    <button class="btn btn-primary" 
            data-bs-toggle="modal" 
            data-bs-target="#submitToJobModal">
        <i class="bx bx-send"></i> Submit to Job
    </button>
<?php endif; ?>

<!-- Modal -->
<div class="modal" id="submitToJobModal">
    <form method="POST" action="/panel/modules/submissions/handlers/create.php">
        <input type="hidden" name="candidate_code" value="<?= $candidate['candidate_code'] ?>">
        
        <select name="job_code" required>
            <option value="">Select Job...</option>
            <?php foreach ($openJobs as $job): ?>
                <option value="<?= $job['job_code'] ?>">
                    <?= $job['job_title'] ?> - <?= $job['company_name'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <textarea name="submission_notes" placeholder="Why is this candidate a good fit?"></textarea>
        
        <button type="submit">Submit for Approval</button>
    </form>
</div>

// Remember active tab
document.addEventListener('DOMContentLoaded', function() {
    const activeTab = localStorage.getItem('candidateViewTab');
    if (activeTab) {
        document.querySelector(`button[data-bs-target="${activeTab}"]`).click();
    }
    
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            localStorage.setItem('candidateViewTab', e.target.getAttribute('data-bs-target'));
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>