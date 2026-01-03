<?php
/**
 * Approve/Reject Submission Handler
 * Manager approves or rejects submission
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('submissions', 'approve');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid request token');
}

try {
    $submission_code = input('submission_code');
    $action = input('action'); // 'approve' or 'reject'
    $notes = input('approval_notes', '');
    
    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Get submission
    $stmt = $conn->prepare("
        SELECT s.*, c.candidate_name, j.job_title
        FROM submissions s
        JOIN candidates c ON s.candidate_code = c.candidate_code
        JOIN jobs j ON s.job_code = j.job_code
        WHERE s.submission_code = ?
    ");
    $stmt->bind_param("s", $submission_code);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    if ($submission['internal_status'] !== 'pending') {
        throw new Exception(
            'This submission has already been ' . $submission['internal_status']
        );
    }
    
    // Update submission
    if ($action === 'approve') {
        $stmt = $conn->prepare("
            UPDATE submissions
            SET internal_status = 'approved',
                approved_by = ?,
                approved_at = NOW(),
                approval_notes = ?
            WHERE submission_code = ?
        ");
        $stmt->bind_param("sss", $user['user_code'], $notes, $submission_code);
        $stmt->execute();
        
        $message = "Submission approved successfully";
        $log_message = "Approved by {$user['name']}";
        
    } else {
        $stmt = $conn->prepare("
            UPDATE submissions
            SET internal_status = 'rejected',
                approved_by = ?,
                approved_at = NOW(),
                rejection_reason = ?
            WHERE submission_code = ?
        ");
        $stmt->bind_param("sss", $user['user_code'], $notes, $submission_code);
        $stmt->execute();
        
        $message = "Submission rejected";
        $log_message = "Rejected by {$user['name']}";
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        $action,
        'submissions',
        $submission_code,
        "{$log_message}: {$submission['candidate_name']} → {$submission['job_title']}",
        [
            'approved_by' => $user['user_code'],
            'notes' => $notes
        ]
    );
    
    // Get submission details with recruiter info
    $subStmt = $conn->prepare("
        SELECT s.*, c.name as candidate_name, j.job_title,
            u.email as recruiter_email, u.name as recruiter_name
        FROM submissions s
        JOIN candidates c ON s.candidate_code = c.candidate_code
        JOIN jobs j ON s.job_code = j.job_code
        JOIN users u ON s.submitted_by = u.user_code
        WHERE s.submission_code = ?
    ");
    $subStmt->bind_param("s", $submissionCode);
    $subStmt->execute();
    $sub = $subStmt->get_result()->fetch_assoc();

    // Send approval email
    Mailer::send(
        $sub['recruiter_email'],
        "Submission Approved: {$sub['candidate_name']} for {$sub['job_title']}",
        'submission_approved',
        [
            'recruiter_name' => $sub['recruiter_name'],
            'candidate_name' => $sub['candidate_name'],
            'job_title' => $sub['job_title'],
            'approved_by' => Auth::user()['name'],
            'approval_notes' => $_POST['approval_notes'] ?? '',
            'submission_url' => BASE_URL . '/panel/modules/submissions/view.php?code=' . $submissionCode
        ]
    );
    
    redirectWithMessage(
        "/panel/modules/submissions/view.php?code={$submission_code}",
        $message,
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Submission approval failed', [
        'error' => $e->getMessage(),
        'submission' => $submission_code ?? null
    ]);
    
    redirectBack('Action failed: ' . $e->getMessage());
}