<?php
/**
 * Submit Job for Approval Handler
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('jobs', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $job_code = input('job_code');
    $user = Auth::user();
    
    if (empty($job_code)) {
        throw new Exception('Job code is required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check job exists and is draft
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $job_code);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        throw new Exception('Job not found');
    }
    
    if ($job['approval_status'] !== 'draft') {
        throw new Exception('This job has already been submitted or approved');
    }
    
    // Validate job is complete
    if (empty($job['job_title']) || empty($job['description'])) {
        throw new Exception('Job title and description are required before submission');
    }
    
    // Update status
    $stmt = $conn->prepare("
        UPDATE jobs
        SET approval_status = 'pending_approval',
            submitted_for_approval_at = NOW(),
            updated_at = NOW()
        WHERE job_code = ?
    ");
    
    $stmt->bind_param("s", $job_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit job: ' . $conn->error);
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'submit_for_approval',
        'jobs',
        $job_code,
        "Job submitted for approval: {$job['job_title']}",
        ['submitted_by' => $user['user_code']]
    );
    
    // TODO: Send email notification to managers
    
    redirectWithMessage(
        "/panel/modules/jobs/?action=view&code={$job_code}",
        'Job submitted for approval successfully. Managers will be notified.',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Job submission failed', [
        'error' => $e->getMessage(),
        'job_code' => $job_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to submit job: ' . $e->getMessage());
}