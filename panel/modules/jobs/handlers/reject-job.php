<?php
/**
 * Reject Job Handler
 * Rejects job with reason
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('jobs', 'approve');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $job_code = input('job_code');
    $rejection_reason = input('rejection_reason');
    $user = Auth::user();
    
    if (empty($job_code) || empty($rejection_reason)) {
        throw new Exception('Job code and rejection reason are required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check job exists
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $job_code);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        throw new Exception('Job not found');
    }
    
    if ($job['approval_status'] !== 'pending_approval') {
        throw new Exception('This job is not pending approval');
    }
    
    // Reject job
    $stmt = $conn->prepare("
        UPDATE jobs
        SET approval_status = 'rejected',
            status = 'draft',
            rejected_by = ?,
            rejected_at = NOW(),
            rejection_reason = ?,
            updated_at = NOW()
        WHERE job_code = ?
    ");
    
    $stmt->bind_param("sss", $user['user_code'], $rejection_reason, $job_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to reject job: ' . $conn->error);
    }
    
    // Add rejection note
    $stmt = $conn->prepare("
        INSERT INTO notes (entity_type, entity_code, note_type, content, created_by, created_at)
        VALUES ('job', ?, 'rejection', ?, ?, NOW())
    ");
    $stmt->bind_param("sss", $job_code, $rejection_reason, $user['user_code']);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'reject',
        'jobs',
        $job_code,
        "Job rejected: {$job['job_title']}",
        [
            'rejected_by' => $user['user_code'],
            'rejection_reason' => $rejection_reason
        ]
    );
    
    // TODO: Send email notification to recruiter
    
    redirectWithMessage(
        "/panel/modules/jobs/?action=list",
        'Job rejected and returned to draft status',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Job rejection failed', [
        'error' => $e->getMessage(),
        'job_code' => $job_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to reject job: ' . $e->getMessage());
}