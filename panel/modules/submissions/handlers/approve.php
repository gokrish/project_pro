<?php
/**
 * Approve Submission Handler
 * File: panel/modules/submissions/handlers/approve.php
 */

require_once __DIR__ . '/../../_common.php';


use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only managers and admins can approve
$user = Auth::user();
if (!in_array($user['level'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $submissionCode = $input['submission_code'] ?? '';
    $notes = $input['notes'] ?? '';
    
    if (empty($submissionCode)) {
        throw new Exception('Submission code is required');
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
    
    if ($submission['status'] !== 'pending_review') {
        throw new Exception('Only pending submissions can be approved');
    }
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE candidate_submissions 
        SET 
            status = 'approved',
            reviewed_by = ?,
            reviewed_at = NOW(),
            review_notes = ?,
            submitted_to_client_at = NOW(),
            client_notified = 1,
            updated_at = NOW()
        WHERE submission_code = ?
    ");
    $stmt->bind_param('sss', $user['user_code'], $notes, $submissionCode);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to approve submission');
    }
    
    // Add note
    $noteText = 'Submission approved by ' . $user['name'];
    if (!empty($notes)) {
        $noteText .= "\nNotes: " . $notes;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO submission_notes (submission_code, note, note_type, created_by, created_at)
        VALUES (?, ?, 'internal', ?, NOW())
    ");
    $stmt->bind_param('sss', $submissionCode, $noteText, $user['user_code']);
    $stmt->execute();
    
    // TODO: Send email notification to client
    // TODO: Notify recruiter who created submission
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'approve',
            'submissions',
            $submissionCode,
            "Approved submission by {$user['name']}"
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission approved and sent to client'
    ]);
    
} catch (Exception $e) {
    error_log('Submission approval error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}