<?php
/**
 * Send to Client Handler
 * Sends approved submission to client
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('submissions', 'send_client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $submission_code = input('submission_code');
    $user = Auth::user();
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get submission with validation
    $stmt = $conn->prepare("
        SELECT s.*, c.candidate_name, j.job_title, cl.company_name, cl.email as client_email
        FROM submissions s
        JOIN candidates c ON s.candidate_code = c.candidate_code
        JOIN jobs j ON s.job_code = j.job_code
        JOIN clients cl ON j.client_code = cl.client_code
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
        throw new Exception('Submission must be approved before sending to client. Current status: ' . $submission['internal_status']);
    }
    
    if ($submission['client_status'] !== 'not_sent') {
        throw new Exception('Submission has already been sent to client');
    }
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE submissions
        SET client_status = 'submitted',
            sent_to_client_at = NOW(),
            sent_to_client_by = ?
        WHERE submission_code = ?
    ");
    $stmt->bind_param("ss", $user['user_code'], $submission_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Database update failed: ' . $conn->error);
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'send_to_client',
        'submissions',
        $submission_code,
        "Submission sent to client: {$submission['candidate_name']} → {$submission['company_name']}",
        [
            'candidate' => $submission['candidate_name'],
            'job' => $submission['job_title'],
            'client' => $submission['company_name'],
            'client_email' => $submission['client_email'],
            'sent_by' => $user['user_code']
        ]
    );
    
    // TODO: Send email notification to client
    // sendEmailNotification($submission, 'client_submission');
    
    redirectWithMessage(
        "/panel/modules/submissions/view.php?code={$submission_code}",
        'Submission sent to client successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Send to client failed', [
        'error' => $e->getMessage(),
        'submission' => $submission_code ?? null,
        'user' => $user['user_code'] ?? null,
        'trace' => $e->getTraceAsString()
    ]);
    
    redirectBack('Failed to send submission: ' . $e->getMessage());
}