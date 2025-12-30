<?php
/**
 * Update Job Handler
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
    
    $user = Auth::user();
    
    // Validation
    if (empty($job_code) || empty($client_code) || empty($job_title) || empty($description)) {
        throw new Exception('Job code, client, title, and description are required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check job exists
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $job_code);
    $stmt->execute();
    $oldJob = $stmt->get_result()->fetch_assoc();
    
    if (!$oldJob) {
        throw new Exception('Job not found');
    }
    
    // Update job
    $stmt = $conn->prepare("
        UPDATE jobs
        SET client_code = ?,
            job_title = ?,
            description = ?,
            notes = ?,
            location = ?,
            salary_min = ?,
            salary_max = ?,
            show_salary = ?,
            positions_total = ?,
            assigned_recruiter = ?,
            updated_at = NOW()
        WHERE job_code = ?
    ");
    
    $stmt->bind_param("sssssddiiss",
        $client_code, $job_title, $description, $notes,
        $location, $salary_min, $salary_max, $show_salary,
        $positions_total, $assigned_recruiter, $job_code
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update job: ' . $conn->error);
    }
    
    // Log activity
    $changes = [];
    if ($oldJob['job_title'] !== $job_title) $changes['job_title'] = ['from' => $oldJob['job_title'], 'to' => $job_title];
    if ($oldJob['client_code'] !== $client_code) $changes['client_code'] = ['from' => $oldJob['client_code'], 'to' => $client_code];
    if ($oldJob['assigned_recruiter'] !== $assigned_recruiter) $changes['assigned_recruiter'] = ['from' => $oldJob['assigned_recruiter'], 'to' => $assigned_recruiter];
    
    Logger::getInstance()->logActivity(
        'update',
        'jobs',
        $job_code,
        "Job updated: {$job_title}",
        ['changes' => $changes, 'updated_by' => $user['user_code']]
    );
    
    redirectWithMessage(
        "/panel/modules/jobs/?action=view&code={$job_code}",
        'Job updated successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Job update failed', [
        'error' => $e->getMessage(),
        'job_code' => $job_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to update job: ' . $e->getMessage());
}