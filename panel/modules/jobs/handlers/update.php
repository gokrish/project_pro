<?php
/**
 * Update Job Handler
 * Processes job edit form
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

// Check permission
Permission::require('jobs', 'edit');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    redirectBack('Invalid security token');
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $db->beginTransaction();
    
    $jobCode = $_POST['job_code'] ?? null;
    
    if (!$jobCode) {
        throw new Exception('Job code is required');
    }
    
    // Get existing job
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    $existingJob = $stmt->get_result()->fetch_assoc();
    
    if (!$existingJob) {
        throw new Exception('Job not found');
    }
    
    // Get form data
    $jobTitle = trim($_POST['job_title'] ?? '');
    $clientCode = $_POST['client_code'] ?? null;
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $assignedTo = $_POST['assigned_to'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $employmentType = $_POST['employment_type'] ?? 'contract';
    $salaryMin = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
    $salaryMax = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
    $salaryPeriod = $_POST['salary_period'] ?? 'yearly';
    $internalNotes = trim($_POST['internal_notes'] ?? '');
    
    // Validation
    if (empty($jobTitle)) throw new Exception('Job title is required');
    if (empty($clientCode)) throw new Exception('Client is required');
    if (empty($description)) throw new Exception('Job description is required');
    
    // Update job
    $stmt = $conn->prepare("
        UPDATE jobs SET
            job_title = ?,
            client_code = ?,
            description = ?,
            requirements = ?,
            location = ?,
            employment_type = ?,
            salary_min = ?,
            salary_max = ?,
            salary_period = ?,
            assigned_to = ?,
            updated_at = NOW()
        WHERE job_code = ?
    ");
    
    $stmt->bind_param(
        "ssssssddsss",
        $jobTitle,
        $clientCode,
        $description,
        $requirements,
        $location,
        $employmentType,
        $salaryMin,
        $salaryMax,
        $salaryPeriod,
        $assignedTo,
        $jobCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update job: ' . $stmt->error);
    }
    
    // Update internal notes
    $conn->query("DELETE FROM job_notes WHERE job_code = '{$jobCode}' AND is_internal = 1");
    
    if (!empty($internalNotes)) {
        $noteStmt = $conn->prepare("
            INSERT INTO job_notes (job_code, note, note_type, is_internal, created_by, created_at)
            VALUES (?, ?, 'internal', 1, ?, NOW())
        ");
        $updatedBy = Auth::userCode();
        $noteStmt->bind_param("sss", $jobCode, $internalNotes, $updatedBy);
        $noteStmt->execute();
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'update',
        'jobs',
        $jobCode,
        "Updated job: {$jobTitle}"
    );
    
    $db->commit();
    
    redirectWithMessage("/panel/modules/jobs/view.php?code={$jobCode}", 
                       'Job updated successfully!', 'success');
    
} catch (Exception $e) {
    $db->rollback();
    
    Logger::getInstance()->error('Job update failed', [
        'job_code' => $_POST['job_code'] ?? 'unknown',
        'error' => $e->getMessage()
    ]);
    
    redirectBack('Failed to update job: ' . $e->getMessage());
}