<?php
/**
 * Create Job Handler
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('jobs', 'create');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    redirectBack('Invalid security token');
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $db->beginTransaction();
    
    // Get form data
    $jobCode = $_POST['job_code'] ?? null;
    $jobTitle = trim($_POST['job_title'] ?? '');
    $clientCode = $_POST['client_code'] ?? null;
    $description = trim($_POST['description'] ?? '');
    $assignedTo = $_POST['assigned_to'] ?? Auth::userCode();
    $internalNotes = trim($_POST['internal_notes'] ?? '');
    
    // Optional fields
    $salaryMin = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
    $salaryMax = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
    
    // Validation
    if (empty($jobTitle)) throw new Exception('Job title is required');
    if (empty($clientCode)) throw new Exception('Client is required');
    if (empty($description)) throw new Exception('Job description is required');
    
    // Determine status based on action
    $action = $_POST['action'] ?? 'save_draft';
    $status = ($action === 'publish') ? 'open' : 'draft';
    $isPublished = ($action === 'publish') ? 1 : 0;
    $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;
    
    // Insert job
    $stmt = $conn->prepare("
        INSERT INTO jobs (
            job_code,
            client_code,
            job_title,
            description,
            salary_min,
            salary_max,
            status,
            assigned_to,
            is_published,
            published_at,
            created_by,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $createdBy = Auth::user();
    
    $stmt->bind_param(
        "ssssddssiss",
        $jobCode,
        $clientCode,
        $jobTitle,
        $description,
        $salaryMin,
        $salaryMax,
        $status,
        $assignedTo,
        $isPublished,
        $publishedAt,
        $createdBy
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create job: ' . $stmt->error);
    }
    
    // Add internal notes if provided
    if (!empty($internalNotes)) {
        $noteStmt = $conn->prepare("
            INSERT INTO job_notes (job_code, note, note_type, is_internal, created_by, created_at)
            VALUES (?, ?, 'internal', 1, ?, NOW())
        ");
        $noteStmt->bind_param("sss", $jobCode, $internalNotes, $createdBy);
        $noteStmt->execute();
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'create',
        'jobs',
        $job_code,
        "Job created: {$job_title} for client {$client_name}",
        ['client' => $client_code, 'created_by' => $user['user_code']]
    );
    
    $db->commit();
    
    $message = $isPublished ? 'Job created and published!' : 'Job saved as draft!';
    redirectWithMessage("/panel/modules/jobs/view.php?code={$jobCode}", $message, 'success');
    
} catch (Exception $e) {
    $db->rollback();
    
    Logger::getInstance()->error('Job creation failed', [
        'error' => $e->getMessage()
    ]);
    
    redirectBack('Failed to create job: ' . $e->getMessage());
}