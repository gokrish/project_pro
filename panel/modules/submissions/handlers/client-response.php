<?php
/**
 * Record Client Response Handler
 * File: panel/modules/submissions/handlers/client-response.php
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Permission::can('submissions', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $submissionCode = $input['submission_code'] ?? '';
    $clientResponse = $input['client_response'] ?? '';
    $clientFeedback = $input['client_feedback'] ?? '';
    
    if (empty($submissionCode)) {
        throw new Exception('Submission code is required');
    }
    
    if (empty($clientResponse)) {
        throw new Exception('Client response is required');
    }
    
    $validResponses = ['interested', 'not_interested', 'interview', 'on_hold'];
    if (!in_array($clientResponse, $validResponses)) {
        throw new Exception('Invalid client response');
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
    
    if ($submission['status'] !== 'submitted' && $submission['status'] !== 'approved') {
        throw new Exception('Can only record response for submitted/approved submissions');
    }
    
    // Update submission
    $newStatus = $clientResponse === 'interested' ? 'accepted' : 'submitted';
    
    $stmt = $conn->prepare("
        UPDATE candidate_submissions 
        SET 
            client_response = ?,
            client_feedback = ?,
            client_response_date = NOW(),
            status = ?,
            updated_at = NOW()
        WHERE submission_code = ?
    ");
    $stmt->bind_param('ssss', $clientResponse, $clientFeedback, $newStatus, $submissionCode);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to record client response');
    }
    
    // Add note
    $noteText = "Client response: " . ucwords(str_replace('_', ' ', $clientResponse));
    if (!empty($clientFeedback)) {
        $noteText .= "\nFeedback: " . $clientFeedback;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO submission_notes (submission_code, note, note_type, created_by, created_at)
        VALUES (?, ?, 'client_feedback', ?, NOW())
    ");
    $stmt->bind_param('sss', $submissionCode, $noteText, $user['user_code']);
    $stmt->execute();
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'client_response',
            'submissions',
            $submissionCode,
            "Client responded: {$clientResponse}"
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Client response recorded',
        'can_convert' => $clientResponse === 'interested'
    ]);
    
} catch (Exception $e) {
    error_log('Client response error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}