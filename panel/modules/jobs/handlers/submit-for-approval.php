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
use ProConsultancy\Core\Mailer;
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
    
    // Update job status
    $stmt = $conn->prepare("UPDATE jobs SET approval_status = 'pending_approval', submitted_for_approval_at = NOW() WHERE job_code = ?");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();

    // Get job details for email
    $jobStmt = $conn->prepare("
        SELECT j.*, c.company_name 
        FROM jobs j 
        LEFT JOIN clients c ON j.client_code = c.client_code 
        WHERE j.job_code = ?
    ");
    $jobStmt->bind_param("s", $jobCode);
    $jobStmt->execute();
    $job = $jobStmt->get_result()->fetch_assoc();

    // Get all managers (level = 'manager' OR 'admin' OR 'super_admin')
    $managerStmt = $conn->prepare("
        SELECT email, name 
        FROM users 
        WHERE level IN ('manager', 'admin', 'super_admin') 
        AND is_active = 1
    ");
    $managerStmt->execute();
    $managers = $managerStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Send email to each manager
    foreach ($managers as $manager) {
        Mailer::send(
            $manager['email'],
            "New Job Awaiting Approval: {$job['job_title']}",
            'job_approval_request',
            [
                'manager_name' => $manager['name'],
                'job_title' => $job['job_title'],
                'job_code' => $job['job_code'],
                'client_name' => $job['company_name'],
                'location' => $job['location'],
                'submitted_by' => Auth::user()['name'],
                'submitted_at' => date('d/m/Y H:i'),
                'approval_url' => BASE_URL . '/panel/modules/jobs/approve.php?code=' . $job['job_code']
            ]
        );
    }

    Logger::getInstance()->info(
        'jobs', 
        'submit_for_approval', 
        $jobCode, 
        "Job submitted for approval, {$count($managers)} managers notified"
    );

    FlashMessage::success("Job submitted for approval. Managers have been notified.");
    
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