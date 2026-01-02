<?php
/**
 * Candidate View Page - REFACTORED FOR NEW SYSTEM
 * 
 * @version 5.0 - Production Ready
 * @date January 2, 2026
 * 
 * CHANGES FROM OLD VERSION:
 * - Uses correct submissions table with dual-status (internal_status + client_status)
 * - Removed references to non-existent tables (applications, interviews, placements)
 * - Fixed candidate status workflow (new → screening → qualified → active → placed)
 * - All queries use prepared statements (SQL injection safe)
 * - Proper error handling and logging
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Auth, Database, Permission, Logger, CSRFToken};

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get badge HTML for internal status
 */
function getInternalStatusBadge($status) {
    return match($status) {
        'pending' => '<span class="badge bg-warning">⏳ Pending Review</span>',
        'approved' => '<span class="badge bg-success">✓ Approved</span>',
        'rejected' => '<span class="badge bg-danger">✗ Rejected</span>',
        'withdrawn' => '<span class="badge bg-secondary">↩ Withdrawn</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

/**
 * Get badge HTML for client status
 */
function getClientStatusBadge($status) {
    return match($status) {
        'not_sent' => '<span class="badge bg-secondary">Not Sent</span>',
        'submitted' => '<span class="badge bg-info">📤 Submitted</span>',
        'interviewing' => '<span class="badge bg-primary">💼 Interviewing</span>',
        'offered' => '<span class="badge bg-warning">🎯 Offered</span>',
        'placed' => '<span class="badge bg-success">✓ Placed</span>',
        'rejected' => '<span class="badge bg-danger">✗ Rejected</span>',
        'withdrawn' => '<span class="badge bg-secondary">↩ Withdrawn</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

/**
 * Get candidate status badge
 */
function getCandidateStatusBadge($status) {
    $badges = [
        'new' => 'primary',
        'screening' => 'info',
        'qualified' => 'success',
        'active' => 'warning',
        'placed' => 'success',
        'rejected' => 'danger',
        'archived' => 'secondary'
    ];
    $color = $badges[$status] ?? 'secondary';
    $label = ucfirst(str_replace('_', ' ', $status));
    return "<span class='badge bg-{$color}'>{$label}</span>";
}

/**
 * Format currency (helper function)
 */
function formatCurrency($amount) {
    if (empty($amount) || $amount <= 0) return 'Not specified';
    return '€' . number_format($amount, 0, '.', ',');
}

/**
 * Time ago helper
 */
function timeAgo($datetime) {
    if (empty($datetime)) return 'Never';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date('M d, Y', $time);
}

/**
 * Get user initials
 */
function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[count($words)-1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

/**
 * Escape output
 */
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

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
$userLevel = $user['level'] ?? 'recruiter';

// ============================================================================
// GET CANDIDATE CODE FROM URL
// ============================================================================

$candidateCode = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);

if (empty($candidateCode)) {
    $_SESSION['flash_error'] = 'Candidate code is missing';
    header('Location: /panel/modules/candidates/list.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================================
// GET CANDIDATE DATA (SQL INJECTION SAFE)
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        c.*,
        u.name as assigned_to_name
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    WHERE c.candidate_code = ?
    AND c.deleted_at IS NULL
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

if (!$candidate) {
    $_SESSION['flash_error'] = 'Candidate not found or access denied';
    header('Location: /panel/modules/candidates/list.php');
    exit;
}

// Check access permission (own candidates only for recruiters)
if (Permission::can('candidates', 'view_own') && !Permission::can('candidates', 'view_all')) {
    if ($candidate['assigned_to'] !== $userCode) {
        $_SESSION['flash_error'] = 'You can only view your own assigned candidates';
        header('Location: /panel/modules/candidates/list.php');
        exit;
    }
}

// ============================================================================
// GET SUBMISSIONS DATA (CORRECT QUERY - DUAL STATUS)
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.submission_code,
        s.candidate_code,
        s.job_code,
        s.submitted_by,
        s.internal_status,
        s.client_status,
        s.submission_notes,
        s.approved_by,
        s.approved_at,
        s.approval_notes,
        s.rejection_reason,
        s.sent_to_client_at,
        s.sent_to_client_by,
        s.interview_date,
        s.interview_notes,
        s.interview_result,
        s.offer_date,
        s.offer_notes,
        s.placement_date,
        s.placement_notes,
        s.rejected_date,
        s.rejected_by as submission_rejected_by,
        s.rejected_reason as submission_rejected_reason,
        s.withdrawn_date,
        s.withdrawal_reason,
        s.created_at,
        s.updated_at,
        j.job_title,
        j.location as job_location,
        j.job_refno,
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
// CALCULATE SUBMISSION STATISTICS
// ============================================================================

$stats = [
    'total_submissions' => count($submissions),
    'pending_approval' => 0,
    'approved' => 0,
    'sent_to_client' => 0,
    'interviewing' => 0,
    'offered' => 0,
    'placed' => 0,
    'rejected' => 0
];

foreach ($submissions as $sub) {
    // Internal status counts
    if ($sub['internal_status'] === 'pending') $stats['pending_approval']++;
    if ($sub['internal_status'] === 'approved') $stats['approved']++;
    
    // Client status counts
    if ($sub['client_status'] === 'submitted') $stats['sent_to_client']++;
    if ($sub['client_status'] === 'interviewing') $stats['interviewing']++;
    if ($sub['client_status'] === 'offered') $stats['offered']++;
    if ($sub['client_status'] === 'placed') $stats['placed']++;
    if ($sub['client_status'] === 'rejected' || $sub['internal_status'] === 'rejected') $stats['rejected']++;
}

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
// GET NOTES
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        n.*,
        u.name as created_by_name
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
// GET ACTIVITY HISTORY (Status Changes)
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        csh.*,
        u.name as changed_by_name
    FROM candidate_status_history csh
    LEFT JOIN users u ON csh.changed_by = u.user_code
    WHERE csh.candidate_code = ?
    ORDER BY csh.changed_at DESC
    LIMIT 50
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$statusHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET FIELD CHANGE HISTORY
// ============================================================================

$stmt = $conn->prepare("
    SELECT 
        cei.*,
        u.name as edited_by_name
    FROM candidates_edit_info cei
    LEFT JOIN users u ON cei.edited_by = u.user_code
    WHERE cei.candidate_code = ?
    ORDER BY cei.edited_at DESC
    LIMIT 50
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$editHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Merge activity logs
$activityLogs = array_merge($statusHistory, $editHistory);
usort($activityLogs, function($a, $b) {
    $timeA = strtotime($a['changed_at'] ?? $a['edited_at']);
    $timeB = strtotime($b['changed_at'] ?? $b['edited_at']);
    return $timeB - $timeA;
});

// ============================================================================
// PARSE SKILLS
// ============================================================================

$skills = [];
if (!empty($candidate['skills'])) {
    $skillsDecoded = json_decode($candidate['skills'], true);
    if (is_array($skillsDecoded)) {
        $skills = $skillsDecoded;
    } else {
        $skills = array_filter(array_map('trim', explode(',', $candidate['skills'])));
    }
}

// ============================================================================
// LOG VIEW ACTIVITY
// ============================================================================

Logger::getInstance()->logActivity('view', 'candidates', $candidateCode, 'Viewed candidate profile');

// ============================================================================
// PAGE CONFIGURATION
// ============================================================================

$pageTitle = 'Candidate Profile - ' . escape($candidate['candidate_name']);
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php'],
    ['title' => escape($candidate['candidate_name']), 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Continue with HTML structure in next part... -->