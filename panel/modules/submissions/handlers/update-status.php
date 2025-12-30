<?php
/**
 * Update Status Handler
 * Updates submission client_status
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('submissions', 'update_status');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $submission_code = input('submission_code');
    $new_status = input('client_status');
    $notes = input('status_notes', '');
    $user = Auth::user();
    
    // Validate status
    $validStatuses = ['submitted', 'interviewing', 'offered', 'placed', 'rejected'];
    if (!in_array($new_status, $validStatuses)) {
        throw new Exception('Invalid status');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get current submission
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
    
    // Validate status progression
    if ($submission['internal_status'] !== 'approved') {
        throw new Exception('Can only update approved submissions');
    }
    
    $currentStatus = $submission['client_status'];
    
    // Validate state transitions
    $validTransitions = [
        'not_sent' => ['submitted'],
        'submitted' => ['interviewing', 'rejected'],
        'interviewing' => ['offered', 'rejected'],
        'offered' => ['placed', 'rejected']
    ];
    
    if (isset($validTransitions[$currentStatus]) && !in_array($new_status, $validTransitions[$currentStatus])) {
        throw new Exception("Cannot change status from '{$currentStatus}' to '{$new_status}'");
    }
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE submissions
        SET client_status = ?
        WHERE submission_code = ?
    ");
    $stmt->bind_param("ss", $new_status, $submission_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Database update failed: ' . $conn->error);
    }
    
    // Add note if provided
    if ($notes) {
        $stmt = $conn->prepare("
            INSERT INTO notes (entity_type, entity_code, content, created_by, created_at)
            VALUES ('submission', ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sss", $submission_code, $notes, $user['user_code']);
        $stmt->execute();
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'update_status',
        'submissions',
        $submission_code,
        "Status updated: {$currentStatus} → {$new_status}",
        [
            'old_status' => $currentStatus,
            'new_status' => $new_status,
            'notes' => $notes
        ]
    );
    
    redirectWithMessage(
        "/panel/modules/submissions/view.php?code={$submission_code}",
        "Status updated to " . str_replace('_', ' ', $new_status),
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Status update failed', [
        'error' => $e->getMessage(),
        'submission' => $submission_code ?? null
    ]);
    
    redirectBack('Failed to update status: ' . $e->getMessage());
}