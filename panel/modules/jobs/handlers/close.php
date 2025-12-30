<?php
/**
 * Close Job Handler
 * Closes job and unpublishes from website
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('jobs', 'edit');

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
    
    // Check job exists
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $job_code);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        throw new Exception('Job not found');
    }
    
    if ($job['status'] === 'closed') {
        throw new Exception('Job is already closed');
    }
    
    // Close job
    $stmt = $conn->prepare("
        UPDATE jobs
        SET status = 'closed',
            is_published = 0,
            closed_at = NOW(),
            closed_by = ?,
            updated_at = NOW()
        WHERE job_code = ?
    ");
    
    $stmt->bind_param("ss", $user['user_code'], $job_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to close job: ' . $conn->error);
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'close',
        'jobs',
        $job_code,
        "Job closed: {$job['job_title']}",
        ['closed_by' => $user['user_code']]
    );
    
    redirectWithMessage(
        "/panel/modules/jobs/?action=view&code={$job_code}",
        'Job closed successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Job closure failed', [
        'error' => $e->getMessage(),
        'job_code' => $job_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to close job: ' . $e->getMessage());
}