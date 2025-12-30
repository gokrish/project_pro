<?php
/**
 * Create Job Handler
 * Handles both "save as draft" and "submit for approval"
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
    $client_code = input('client_code');
    $job_title = input('job_title');
    $description = input('description');
    $location = input('location', 'Remote');
    $salary_min = input('salary_min') ?: null;
    $salary_max = input('salary_max') ?: null;
    $show_salary = input('show_salary', 0) ? 1 : 0;
    $positions_total = inputInt('positions_total', 1);
    $assigned_recruiter = input('assigned_recruiter', '');
    $notes = input('notes', '');
    $action = input('action', 'save_draft'); // save_draft or submit_approval
    
    $user = Auth::user();
    
    // Validation
    if (empty($job_code) || empty($client_code) || empty($job_title) || empty($description)) {
        throw new Exception('Job code, client, title, and description are required');
    }
    
    if ($positions_total < 1) {
        throw new Exception('Number of positions must be at least 1');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check for duplicate job_code
    $stmt = $conn->prepare("SELECT id FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $job_code);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('A job with this code already exists');
    }
    
    // Determine initial status based on action
    if ($action === 'submit_approval') {
        $status = 'pending_approval';
        $approval_status = 'pending_approval';
        $submitted_for_approval_at = date('Y-m-d H:i:s');
    } else {
        $status = 'draft';
        $approval_status = 'draft';
        $submitted_for_approval_at = null;
    }
    
    // Insert job
    $stmt = $conn->prepare("
        INSERT INTO jobs (
            job_code, client_code, job_title, description, notes,
            location, salary_min, salary_max, show_salary,
            status, approval_status, submitted_for_approval_at,
            positions_total, assigned_recruiter,
            created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("ssssssddssssiss",
        $job_code, $client_code, $job_title, $description, $notes,
        $location, $salary_min, $salary_max, $show_salary,
        $status, $approval_status, $submitted_for_approval_at,
        $positions_total, $assigned_recruiter,
        $user['user_code']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create job: ' . $conn->error);
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'create',
        'jobs',
        $job_code,
        "Job created: {$job_title}" . ($action === 'submit_approval' ? ' and submitted for approval' : ''),
        [
            'job_title' => $job_title,
            'client_code' => $client_code,
            'status' => $status,
            'action' => $action,
            'created_by' => $user['user_code']
        ]
    );
    
    $message = $action === 'submit_approval' 
        ? 'Job created and submitted for approval successfully' 
        : 'Job saved as draft successfully';
    
    redirectWithMessage(
        "/panel/modules/jobs/?action=view&code={$job_code}",
        $message,
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Job creation failed', [
        'error' => $e->getMessage(),
        'job_code' => $job_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to create job: ' . $e->getMessage());
}