<?php
/**
 * Candidate View Page - COMPLETE REBUILD
 * Professional tabbed interface with HR Comments
 * 
 * @version 6.0 - Production Ready
 * @date January 4, 2026
 * 
 * FEATURES:
 * - 7 tabs (Overview, Professional, Submissions, Communications, HR Comments, Documents, Activity)
 * - Quick actions (Edit, Update Status, Submit to Job, Add Note)
 * - GDPR compliance indicator
 * - Full audit trail
 * - Mobile responsive
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Auth, Database, Permission, Logger, CSRFToken};

// ============================================================================
// PERMISSION CHECK
// ============================================================================

if (!Permission::can('candidates', 'view_all') && !Permission::can('candidates', 'view_own')) {
    $_SESSION['flash_error'] = 'You do not have permission to view candidates';
    header('Location: /panel/modules/candidates/list.php');
    exit;
}

$user = Auth::user();
$userCode = Auth::userCode();
$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================================
// GET CANDIDATE CODE
// ============================================================================

$candidateCode = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);

if (empty($candidateCode)) {
    $_SESSION['flash_error'] = 'Candidate code is missing';
    header('Location: /panel/modules/candidates/list.php');
    exit;
}

// ============================================================================
// GET CANDIDATE DATA
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        c.*,
        u.name as assigned_to_name,
        wa.status_name as work_auth_name
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    LEFT JOIN work_authorization wa ON c.work_authorization_id = wa.id
    WHERE c.candidate_code = ?
    AND c.deleted_at IS NULL
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

if (!$candidate) {
    $_SESSION['flash_error'] = 'Candidate not found';
    header('Location: /panel/modules/candidates/list.php');
    exit;
}

// Check permission (own candidates only for recruiters)
if (Permission::can('candidates', 'view_own') && !Permission::can('candidates', 'view_all')) {
    if ($candidate['assigned_to'] !== $userCode) {
        $_SESSION['flash_error'] = 'You can only view your own assigned candidates';
        header('Location: /panel/modules/candidates/list.php');
        exit;
    }
}

// ============================================================================
// GET SKILLS
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        cs.*,
        ts.skill_category
    FROM candidate_skills cs
    LEFT JOIN technical_skills ts ON cs.skill_name = ts.skill_name
    WHERE cs.candidate_code = ?
    ORDER BY cs.is_primary DESC, cs.proficiency_level DESC, cs.skill_name
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET SUBMISSIONS
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        s.*,
        j.job_title,
        j.job_refno,
        j.location as job_location,
        c.company_name as client_name,
        u1.name as submitted_by_name,
        u2.name as approved_by_name
    FROM submissions s
    LEFT JOIN jobs j ON s.job_code = j.job_code
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u1 ON s.submitted_by = u1.user_code
    LEFT JOIN users u2 ON s.approved_by = u2.user_code
    WHERE s.candidate_code = ?
    AND s.deleted_at IS NULL
    ORDER BY s.created_at DESC
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET COMMUNICATIONS
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        cc.*,
        u.name as contacted_by_name
    FROM candidate_communications cc
    LEFT JOIN users u ON cc.contacted_by = u.user_code
    WHERE cc.candidate_code = ?
    ORDER BY cc.contacted_at DESC
    LIMIT 50
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$communications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET HR COMMENTS
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        hr.*,
        u.name as created_by_name
    FROM candidate_hr_comments hr
    LEFT JOIN users u ON hr.created_by = u.user_code
    WHERE hr.candidate_code = ?
    ORDER BY hr.created_at DESC
    LIMIT 50
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$hrComments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET DOCUMENTS
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
// GET ACTIVITY LOG (Status History + Edit History)
// ============================================================================

// Status changes
$stmt = $conn->prepare("
    SELECT 
        'status_change' as type,
        csh.old_status as old_value,
        csh.new_status as new_value,
        'status' as field_name,
        csh.changed_by as user_code,
        u.name as user_name,
        csh.changed_at as timestamp,
        csh.reason
    FROM candidate_status_history csh
    LEFT JOIN users u ON csh.changed_by = u.user_code
    WHERE csh.candidate_code = ?
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$statusHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Field changes
$stmt = $conn->prepare("
    SELECT 
        'field_edit' as type,
        cei.field_name,
        cei.old_value,
        cei.new_value,
        cei.edited_by as user_code,
        u.name as user_name,
        cei.edited_at as timestamp,
        NULL as reason
    FROM candidate_edit_history cei
    LEFT JOIN users u ON cei.edited_by = u.user_code
    WHERE cei.candidate_code = ?
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$editHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Merge and sort
$activityLog = array_merge($statusHistory, $editHistory);
usort($activityLog, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// ============================================================================
// GET AVAILABLE JOBS FOR SUBMISSION
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        j.job_code,
        j.job_title,
        j.job_refno,
        c.company_name,
        j.location
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    WHERE j.status = 'open'
    AND j.approval_status = 'approved'
    AND j.deleted_at IS NULL
    ORDER BY j.created_at DESC
    LIMIT 100
");
$stmt->execute();
$availableJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getStatusBadge($status) {
    return match($status) {
        'new' => '<span class="badge bg-primary">New</span>',
        'screening' => '<span class="badge bg-info">Screening</span>',
        'qualified' => '<span class="badge bg-success">Qualified</span>',
        'active' => '<span class="badge bg-warning">Active</span>',
        'placed' => '<span class="badge bg-success">Placed</span>',
        'on_hold' => '<span class="badge bg-secondary">On Hold</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'archived' => '<span class="badge bg-secondary">Archived</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

function getLeadTypeBadge($leadType) {
    return match($leadType) {
        'Hot' => '<span class="badge bg-danger">🔥 Hot</span>',
        'Warm' => '<span class="badge bg-warning">⚡ Warm</span>',
        'Cold' => '<span class="badge bg-info">❄️ Cold</span>',
        'Blacklist' => '<span class="badge bg-dark">🚫 Blacklist</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

function getInternalStatusBadge($status) {
    return match($status) {
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'approved' => '<span class="badge bg-success">Approved</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'withdrawn' => '<span class="badge bg-secondary">Withdrawn</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

function getClientStatusBadge($status) {
    return match($status) {
        'not_sent' => '<span class="badge bg-secondary">Not Sent</span>',
        'submitted' => '<span class="badge bg-info">Submitted</span>',
        'interviewing' => '<span class="badge bg-primary">Interviewing</span>',
        'offered' => '<span class="badge bg-warning">Offered</span>',
        'placed' => '<span class="badge bg-success">Placed</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'withdrawn' => '<span class="badge bg-secondary">Withdrawn</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

function formatNoticePeriod($days) {
    if (empty($days) || $days == 0) return 'Immediate';
    if ($days <= 7) return $days . ' days';
    if ($days <= 30) return round($days/7) . ' weeks';
    if ($days <= 60) return round($days/30) . ' months';
    return $days . ' days';
}

function formatCurrency($amount, $type = 'annual') {
    if (empty($amount) || $amount <= 0) return 'Not specified';
    return '€' . number_format($amount, 0, '.', ',') . ($type === 'daily' ? '/day' : '/year');
}

function timeAgo($datetime) {
    if (empty($datetime)) return 'Never';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date('M d, Y', $time);
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Parse languages
$languages = [];
if (!empty($candidate['languages'])) {
    $languages = json_decode($candidate['languages'], true) ?: [];
}

// Log view activity
Logger::getInstance()->logActivity('view', 'candidates', $candidateCode, 'Viewed candidate profile');

// Page config
$pageTitle = 'Candidate Profile - ' . e($candidate['candidate_name']);
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php'],
    ['title' => e($candidate['candidate_name']), 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="content-container">
    <!-- Header Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3">
                        <!-- Avatar -->
                        <div class="avatar-initial bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php
                            $nameParts = explode(' ', $candidate['candidate_name']);
                            $initials = strtoupper(substr($nameParts[0], 0, 1));
                            if (count($nameParts) > 1) {
                                $initials .= strtoupper(substr($nameParts[count($nameParts)-1], 0, 1));
                            }
                            echo $initials;
                            ?>
                        </div>
                        
                        <!-- Info -->
                        <div class="flex-grow-1">
                            <h4 class="mb-1"><?= e($candidate['candidate_name']) ?></h4>
                            
                            <?php if (!empty($candidate['current_position'])): ?>
                                <p class="text-muted mb-2">
                                    <?= e($candidate['current_position']) ?>
                                    <?php if (!empty($candidate['current_employer'])): ?>
                                        @ <?= e($candidate['current_employer']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?= getStatusBadge($candidate['status']) ?>
                                <?= getLeadTypeBadge($candidate['lead_type']) ?>
                                
                                <?php if (!empty($candidate['lead_type_role'])): ?>
                                    <span class="badge bg-secondary"><?= e($candidate['lead_type_role']) ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($candidate['current_working_status'])): ?>
                                    <span class="badge bg-light text-dark">
                                        <?= str_replace('_', ' ', e($candidate['current_working_status'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="small text-muted">
                                <i class='bx bx-envelope'></i> <?= e($candidate['email']) ?>
                                <?php if (!empty($candidate['phone'])): ?>
                                    <span class="ms-3">
                                        <i class='bx bx-phone'></i> <?= e($candidate['phone']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($candidate['linkedin_url'])): ?>
                                    <span class="ms-3">
                                        <i class='bx bxl-linkedin'></i> 
                                        <a href="<?= e($candidate['linkedin_url']) ?>" target="_blank">LinkedIn</a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="d-flex flex-column gap-2">
                        <?php if (Permission::can('candidates', 'edit')): ?>
                            <a href="/panel/modules/candidates/edit.php?code=<?= e($candidateCode) ?>" 
                               class="btn btn-primary">
                                <i class='bx bx-edit'></i> Edit Profile
                            </a>
                        <?php endif; ?>
                        
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary" id="updateStatusBtn">
                                <i class='bx bx-refresh'></i> Update Status
                            </button>
                            <button type="button" class="btn btn-outline-success" id="submitToJobBtn">
                                <i class='bx bx-paper-plane'></i> Submit to Job
                            </button>
                        </div>
                        
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class='bx bx-note'></i> Add Note
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- GDPR Warning -->
            <?php if (!$candidate['consent_given']): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class='bx bx-shield-alt-2'></i>
                    <strong>GDPR Notice:</strong> 
                    No consent record found for this candidate. Ensure GDPR compliance before processing data or submitting to clients.
                    <?php if (Permission::can('candidates', 'edit')): ?>
                        <a href="/panel/modules/candidates/edit.php?code=<?= e($candidateCode) ?>#consent" 
                           class="alert-link">Update consent status</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1"><?= $candidate['total_submissions'] ?></h3>
                    <p class="text-muted mb-0 small">Total Submissions</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1"><?= $candidate['total_placements'] ?></h3>
                    <p class="text-muted mb-0 small">Placements</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1"><?= formatNoticePeriod($candidate['notice_period_days']) ?></h3>
                    <p class="text-muted mb-0 small">Notice Period</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1"><?= timeAgo($candidate['last_contacted_date']) ?></h3>
                    <p class="text-muted mb-0 small">Last Contacted</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#overview">
                <i class='bx bx-user'></i> Overview
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#professional">
                <i class='bx bx-briefcase'></i> Professional Details
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#submissions">
                <i class='bx bx-send'></i> Submissions (<?= count($submissions) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#communications">
                <i class='bx bx-conversation'></i> Communications (<?= count($communications) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#hr-comments">
                <i class='bx bx-comment-detail'></i> HR Comments (<?= count($hrComments) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#documents">
                <i class='bx bx-file'></i> Documents (<?= count($documents) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#activity">
                <i class='bx bx-history'></i> Activity Log
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Skills -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Skills & Expertise</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($skills)): ?>
                                <div class="row g-3">
                                    <?php foreach ($skills as $skill): ?>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= e($skill['skill_name']) ?></strong>
                                                    <?php if ($skill['is_primary']): ?>
                                                        <span class="badge bg-primary badge-sm ms-1">Primary</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge bg-<?php
                                                    echo match($skill['proficiency_level']) {
                                                        'expert' => 'success',
                                                        'advanced' => 'info',
                                                        'intermediate' => 'warning',
                                                        'beginner' => 'secondary',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?= ucfirst($skill['proficiency_level']) ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($skill['years_experience'])): ?>
                                                <small class="text-muted">
                                                    <?= $skill['years_experience'] ?> years experience
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No skills added yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Professional Summary -->
                    <?php if (!empty($candidate['professional_summary'])): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Professional Summary</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?= nl2br(e($candidate['professional_summary'])) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <!-- Contact Information -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Contact Information</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Email:</td>
                                    <td><a href="mailto:<?= e($candidate['email']) ?>"><?= e($candidate['email']) ?></a></td>
                                </tr>
                                <?php if (!empty($candidate['alternate_email'])): ?>
                                    <tr>
                                        <td class="text-muted">Alt Email:</td>
                                        <td><a href="mailto:<?= e($candidate['alternate_email']) ?>"><?= e($candidate['alternate_email']) ?></a></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="text-muted">Phone:</td>
                                    <td><a href="tel:<?= e($candidate['phone']) ?>"><?= e($candidate['phone']) ?></a></td>
                                </tr>
                                <?php if (!empty($candidate['phone_alternate'])): ?>
                                    <tr>
                                        <td class="text-muted">Alt Phone:</td>
                                        <td><a href="tel:<?= e($candidate['phone_alternate']) ?>"><?= e($candidate['phone_alternate']) ?></a></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="text-muted">Location:</td>
                                    <td><?= e($candidate['current_location']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Languages -->
                    <?php if (!empty($languages)): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Languages</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($languages as $lang): ?>
                                        <span class="badge bg-light text-dark"><?= e($lang) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Availability -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Availability</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Notice Period:</td>
                                    <td><strong><?= formatNoticePeriod($candidate['notice_period_days']) ?></strong></td>
                                </tr>
                                <?php if (!empty($candidate['available_from'])): ?>
                                    <tr>
                                        <td class="text-muted">Available From:</td>
                                        <td><?= date('M d, Y', strtotime($candidate['available_from'])) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($candidate['work_auth_name'])): ?>
                                    <tr>
                                        <td class="text-muted">Work Auth:</td>
                                        <td><?= e($candidate['work_auth_name']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <!-- Compensation -->
                    <?php if (!empty($candidate['current_salary']) || !empty($candidate['current_daily_rate'])): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Compensation</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <?php if (!empty($candidate['current_salary'])): ?>
                                        <tr>
                                            <td class="text-muted">Current:</td>
                                            <td><?= formatCurrency($candidate['current_salary'], 'annual') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Expected:</td>
                                            <td><?= formatCurrency($candidate['expected_salary'], 'annual') ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($candidate['current_daily_rate'])): ?>
                                        <tr>
                                            <td class="text-muted">Current Rate:</td>
                                            <td><?= formatCurrency($candidate['current_daily_rate'], 'daily') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Expected Rate:</td>
                                            <td><?= formatCurrency($candidate['expected_daily_rate'], 'daily') ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Assignment -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Assignment</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Assigned To:</td>
                                    <td><?= !empty($candidate['assigned_to_name']) ? e($candidate['assigned_to_name']) : '<em>Unassigned</em>' ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Created:</td>
                                    <td><?= date('M d, Y', strtotime($candidate['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Updated:</td>
                                    <td><?= timeAgo($candidate['updated_at']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Details Tab -->
        <div class="tab-pane fade" id="professional">
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th width="200">Current Position</th>
                            <td><?= e($candidate['current_position'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th>Current Employer</th>
                            <td><?= e($candidate['current_employer'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th>Current Agency</th>
                            <td><?= e($candidate['current_agency'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th>Working Status</th>
                            <td><?= !empty($candidate['current_working_status']) ? str_replace('_', ' ', e($candidate['current_working_status'])) : '-' ?></td>
                        </tr>
                        <tr>
                            <th>Role Addressed</th>
                            <td><?= nl2br(e($candidate['role_addressed'] ?? '-')) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Submissions Tab -->
        <div class="tab-pane fade" id="submissions">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($submissions)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Job</th>
                                        <th>Client</th>
                                        <th>Internal Status</th>
                                        <th>Client Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $sub): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($sub['job_title']) ?></strong><br>
                                                <small class="text-muted"><?= e($sub['job_refno']) ?></small>
                                            </td>
                                            <td><?= e($sub['client_name']) ?></td>
                                            <td><?= getInternalStatusBadge($sub['internal_status']) ?></td>
                                            <td><?= getClientStatusBadge($sub['client_status']) ?></td>
                                            <td>
                                                <?= date('M d, Y', strtotime($sub['created_at'])) ?><br>
                                                <small class="text-muted">by <?= e($sub['submitted_by_name']) ?></small>
                                            </td>
                                            <td>
                                                <a href="/panel/modules/submissions/view.php?code=<?= e($sub['submission_code']) ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class='bx bx-send' style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">No submissions yet</p>
                            <button type="button" class="btn btn-primary btn-sm" id="submitToJobBtn2">
                                <i class='bx bx-paper-plane'></i> Submit to Job
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Communications Tab -->
        <div class="tab-pane fade" id="communications">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Communication History</h6>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCommunicationModal">
                        <i class='bx bx-plus'></i> Add Communication
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($communications)): ?>
                        <div class="timeline">
                            <?php foreach ($communications as $comm): ?>
                                <div class="timeline-item mb-4">
                                    <div class="d-flex">
                                        <div class="timeline-icon bg-<?php
                                            echo match($comm['communication_type']) {
                                                'call' => 'info',
                                                'email' => 'primary',
                                                'meeting' => 'success',
                                                'sms' => 'warning',
                                                default => 'secondary'
                                            };
                                        ?> rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px; min-width: 40px;">
                                            <i class='bx <?php
                                                echo match($comm['communication_type']) {
                                                    'call' => 'bx-phone',
                                                    'email' => 'bx-envelope',
                                                    'meeting' => 'bx-calendar',
                                                    'sms' => 'bx-message',
                                                    default => 'bx-conversation'
                                                };
                                            ?> text-white'></i>
                                        </div>
                                        <div class="ms-3 flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong><?= ucfirst($comm['communication_type']) ?></strong>
                                                <small class="text-muted"><?= timeAgo($comm['contacted_at']) ?></small>
                                            </div>
                                            <?php if (!empty($comm['subject'])): ?>
                                                <div class="fw-semibold"><?= e($comm['subject']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($comm['notes'])): ?>
                                                <p class="mb-1"><?= nl2br(e($comm['notes'])) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($comm['outcome'])): ?>
                                                <div class="small text-muted">
                                                    <strong>Outcome:</strong> <?= e($comm['outcome']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <?= e($comm['direction']) ?> | by <?= e($comm['contacted_by_name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class='bx bx-conversation' style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">No communication history</p>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommunicationModal">
                                <i class='bx bx-plus'></i> Add First Communication
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- HR Comments Tab -->
        <div class="tab-pane fade" id="hr-comments">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">HR Comments & Screening Notes</h6>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addHRCommentModal">
                        <i class='bx bx-plus'></i> Add Comment
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($hrComments)): ?>
                        <?php foreach ($hrComments as $comment): ?>
                            <div class="card mb-3 <?= $comment['is_private'] ? 'border-danger' : '' ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <span class="badge bg-<?php
                                                echo match($comment['comment_type']) {
                                                    'screening' => 'info',
                                                    'interview' => 'primary',
                                                    'reference' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?= ucfirst($comment['comment_type']) ?>
                                            </span>
                                            <?php if ($comment['is_private']): ?>
                                                <span class="badge bg-danger">Private</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= timeAgo($comment['created_at']) ?></small>
                                    </div>
                                    <p class="mb-2"><?= nl2br(e($comment['comment'])) ?></p>
                                    <small class="text-muted">
                                        <i class='bx bx-user'></i> <?= e($comment['created_by_name']) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class='bx bx-comment-detail' style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">No HR comments yet</p>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addHRCommentModal">
                                <i class='bx bx-plus'></i> Add First Comment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Documents Tab -->
        <div class="tab-pane fade" id="documents">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Documents</h6>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                        <i class='bx bx-upload'></i> Upload Document
                    </button>
                </div>
                <div class="card-body">
                    <!-- Candidate CV -->
                    <?php if (!empty($candidate['candidate_cv'])): ?>
                        <div class="alert alert-info">
                            <i class='bx bx-file-blank'></i>
                            <strong>Original CV:</strong>
                            <a href="<?= e($candidate['candidate_cv']) ?>" target="_blank" class="alert-link">
                                Download CV
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Formatted CV -->
                    <?php if (!empty($candidate['consultancy_cv'])): ?>
                        <div class="alert alert-success">
                            <i class='bx bx-file'></i>
                            <strong>Formatted CV:</strong>
                            <a href="<?= e($candidate['consultancy_cv']) ?>" target="_blank" class="alert-link">
                                Download Formatted CV
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Additional Documents -->
                    <?php if (!empty($documents)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td><?= e($doc['document_name']) ?></td>
                                            <td><?= e($doc['document_type']) ?></td>
                                            <td><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                                            <td>
                                                <a href="<?= e($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class='bx bx-download'></i> Download
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
        </div>

        <!-- Activity Log Tab -->
        <div class="tab-pane fade" id="activity">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($activityLog)): ?>
                        <div class="timeline">
                            <?php foreach ($activityLog as $log): ?>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex">
                                        <div class="timeline-icon bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 32px; height: 32px; min-width: 32px;">
                                            <i class='bx <?= $log['type'] === 'status_change' ? 'bx-refresh' : 'bx-edit' ?> text-white' style="font-size: 0.875rem;"></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="small">
                                                <?php if ($log['type'] === 'status_change'): ?>
                                                    <strong><?= e($log['user_name'] ?? 'System') ?></strong> 
                                                    changed <strong>status</strong> from 
                                                    <code><?= e($log['old_value']) ?></code> to 
                                                    <code><?= e($log['new_value']) ?></code>
                                                    <?php if (!empty($log['reason'])): ?>
                                                        <br><em><?= e($log['reason']) ?></em>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <strong><?= e($log['user_name'] ?? 'System') ?></strong> 
                                                    changed <strong><?= e($log['field_name']) ?></strong> 
                                                    <?php if (!empty($log['old_value']) && !empty($log['new_value'])): ?>
                                                        from <code><?= e(substr($log['old_value'], 0, 50)) ?></code> to 
                                                        <code><?= e(substr($log['new_value'], 0, 50)) ?></code>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted">
                                                <?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class='bx bx-history' style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">No activity logged yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Candidate Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="updateStatusForm" method="POST" action="/panel/modules/candidates/handlers/update-status.php">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="candidate_code" value="<?= e($candidateCode) ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Status</label>
                        <input type="text" class="form-control" value="<?= ucfirst($candidate['status']) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            New Status <span class="text-danger">*</span>
                        </label>
                        <select name="new_status" class="form-select" required>
                            <option value="">Select status...</option>
                            <?php
                            $allowedStatuses = match($candidate['status']) {
                                'new' => ['screening', 'rejected', 'archived'],
                                'screening' => ['qualified', 'rejected', 'archived'],
                                'qualified' => ['active', 'on_hold', 'rejected', 'archived'],
                                'active' => ['placed', 'on_hold', 'rejected', 'archived'],
                                'on_hold' => ['active', 'rejected', 'archived'],
                                'placed' => ['active', 'archived'],
                                'rejected' => ['archived'],
                                'archived' => ['active'],
                                default => []
                            };
                            
                            foreach ($allowedStatuses as $status):
                            ?>
                                <option value="<?= $status ?>">
                                    <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only allowed transitions are shown</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason (Optional)</label>
                        <textarea name="reason" class="form-control" rows="3" 
                                  placeholder="Why are you changing the status?"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Submit to Job Modal -->
<div class="modal fade" id="submitToJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Candidate to Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="submitToJobForm" method="POST" action="/panel/modules/candidates/handlers/submit_to_job.php">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="candidate_code" value="<?= e($candidateCode) ?>">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Candidate:</strong> <?= e($candidate['candidate_name']) ?><br>
                        <strong>Skills:</strong> 
                        <?php
                        $skillNames = array_map(fn($s) => $s['skill_name'], array_slice($skills, 0, 5));
                        echo !empty($skillNames) ? implode(', ', $skillNames) : 'No skills listed';
                        ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Select Job <span class="text-danger">*</span>
                        </label>
                        <select name="job_code" class="form-select" required id="jobSelect">
                            <option value="">-- Select Job --</option>
                            <?php foreach ($availableJobs as $job): ?>
                                <option value="<?= e($job['job_code']) ?>">
                                    <?= e($job['job_title']) ?> - <?= e($job['company_name']) ?>
                                    (<?= e($job['job_refno']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only open & approved jobs are shown</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Submission Notes</label>
                        <textarea name="submission_notes" class="form-control" rows="4" 
                                  placeholder="Why is this candidate a good fit for this role?"></textarea>
                        <small class="text-muted">These notes will be visible to managers reviewing the submission</small>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="notifyManager" name="notify_manager" value="1">
                        <label class="form-check-label" for="notifyManager">
                            Notify manager for approval
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-paper-plane'></i> Submit to Job
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Internal Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addNoteForm" method="POST" action="/panel/modules/candidates/handlers/add-note.php">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="candidate_code" value="<?= e($candidateCode) ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea name="note" class="form-control" rows="5" required
                                  placeholder="Add your note here..."></textarea>
                        <small class="text-muted">This note is internal and not visible to the candidate</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add HR Comment Modal -->
<div class="modal fade" id="addHRCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add HR Comment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addHRCommentForm" method="POST" action="/panel/modules/candidates/handlers/add-hr-comment.php">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="candidate_code" value="<?= e($candidateCode) ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Comment Type <span class="text-danger">*</span>
                        </label>
                        <select name="comment_type" class="form-select" required>
                            <option value="general">General</option>
                            <option value="screening">Screening</option>
                            <option value="interview">Interview Feedback</option>
                            <option value="reference">Reference Check</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Comment <span class="text-danger">*</span>
                        </label>
                        <textarea name="comment" class="form-control" rows="5" required
                                  placeholder="Enter your HR comment here..."></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isPrivate" name="is_private" value="1">
                        <label class="form-check-label" for="isPrivate">
                            <strong>Mark as Private</strong>
                            <small class="d-block text-muted">Only HR managers can view private comments</small>
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add HR Comment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Communication Modal -->
<div class="modal fade" id="addCommunicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Communication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCommunicationForm" method="POST" action="/panel/modules/candidates/handlers/add-communication.php">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="candidate_code" value="<?= e($candidateCode) ?>">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Type <span class="text-danger">*</span>
                            </label>
                            <select name="communication_type" class="form-select" required>
                                <option value="call">Phone Call</option>
                                <option value="email">Email</option>
                                <option value="meeting">Meeting</option>
                                <option value="sms">SMS/Text</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Direction <span class="text-danger">*</span>
                            </label>
                            <select name="direction" class="form-select" required>
                                <option value="outbound">Outbound (I contacted them)</option>
                                <option value="inbound">Inbound (They contacted me)</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" 
                                   placeholder="e.g., Initial phone screening">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Notes <span class="text-danger">*</span>
                            </label>
                            <textarea name="notes" class="form-control" rows="4" required
                                      placeholder="What was discussed?"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Outcome</label>
                            <input type="text" name="outcome" class="form-control" 
                                   placeholder="e.g., Candidate interested, will send CV">
                        </div>
                        
                        <div class="col-md-8">
                            <label class="form-label">Next Action Required</label>
                            <input type="text" name="next_action" class="form-control" 
                                   placeholder="e.g., Follow up with job details">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Next Action Date</label>
                            <input type="date" name="next_action_date" class="form-control" 
                                   min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Log Communication</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadDocumentForm" method="POST" action="/panel/modules/candidates/handlers/upload_document_handler.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                <input type="hidden" name="candidate_code" value="<?= e($candidateCode) ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Document Type <span class="text-danger">*</span>
                        </label>
                        <select name="document_type" class="form-select" required>
                            <option value="">Select type...</option>
                            <option value="cv">CV/Resume</option>
                            <option value="cover_letter">Cover Letter</option>
                            <option value="certificate">Certificate</option>
                            <option value="diploma">Diploma</option>
                            <option value="passport">Passport Copy</option>
                            <option value="work_permit">Work Permit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Document Name</label>
                        <input type="text" name="document_name" class="form-control" 
                               placeholder="e.g., Latest CV - January 2026">
                        <small class="text-muted">Leave blank to use filename</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            File <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="document_file" class="form-control" required
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <small class="text-muted">Supported: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Any notes about this document..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-upload'></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Modals -->
<script>
// Update Status Modal
document.getElementById('updateStatusBtn')?.addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
});

// Submit to Job Modal
document.getElementById('submitToJobBtn')?.addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('submitToJobModal'));
    modal.show();
});

document.getElementById('submitToJobBtn2')?.addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('submitToJobModal'));
    modal.show();
});

// Form submissions with AJAX
document.getElementById('updateStatusForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (result.message || 'Failed to update status'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

document.getElementById('submitToJobForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = '/panel/modules/candidates/view.php?code=<?= e($candidateCode) ?>&tab=submissions&success=submitted';
        } else {
            alert('Error: ' + (result.message || 'Failed to submit candidate'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

document.getElementById('addHRCommentForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (result.message || 'Failed to add comment'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

document.getElementById('addCommunicationForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (result.message || 'Failed to log communication'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

// Show success messages if redirected with success param
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('success') === 'submitted') {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show';
    alert.innerHTML = '<i class="bx bx-check-circle"></i> Candidate successfully submitted to job! <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.querySelector('.content-container').prepend(alert);
    
    // Switch to submissions tab
    const submissionsTab = new bootstrap.Tab(document.querySelector('[href="#submissions"]'));
    submissionsTab.show();
}
</script>

<style>
/* Timeline styles for communications */
.timeline {
    position: relative;
}

.timeline-item {
    position: relative;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 20px;
    top: 40px;
    bottom: -20px;
    width: 2px;
    background: #e2e8f0;
}

/* Modal improvements */
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

/* Form improvements */
.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
}

/* Alert improvements */
.alert {
    border-left: 4px solid;
}

.alert-warning {
    border-left-color: #f59e0b;
}

.alert-info {
    border-left-color: #3b82f6;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>