<?php
/**
 * Reject Submission Handler
 * File: panel/modules/submissions/handlers/reject.php
 */

require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $submissionCode = $input['submission_code'] ?? '';
    $reason = $input['reason'] ?? '';
    
    if (empty($submissionCode)) {
        throw new Exception('Submission code is required');
    }
    
    if (empty($reason)) {
        throw new Exception('Rejection reason is required');
    }
    
    // Get submission
    $stmt = $conn->prepare("
        SELECT * FROM candidate_submissions 
        WHERE submission_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param('s', $submissionCode);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE candidate_submissions 
        SET 
            status = 'rejected',
            reviewed_by = ?,
            reviewed_at = NOW(),
            review_notes = ?,
            updated_at = NOW()
        WHERE submission_code = ?
    ");
    $stmt->bind_param('sss', $user['user_code'], $reason, $submissionCode);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to reject submission');
    }
    
    // Add note
    $noteText = "Submission rejected by {$user['name']}\nReason: {$reason}";
    $stmt = $conn->prepare("
        INSERT INTO submission_notes (submission_code, note, note_type, created_by, created_at)
        VALUES (?, ?, 'internal', ?, NOW())
    ");
    $stmt->bind_param('sss', $submissionCode, $noteText, $user['user_code']);
    $stmt->execute();
    
    // Notify recruiter
    // TODO: Send notification
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission rejected'
    ]);
    
} catch (Exception $e) {
    error_log('Submission rejection error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}