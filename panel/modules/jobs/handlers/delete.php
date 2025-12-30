<?php
/**
 * Delete Job Handler
 * Deletes job and related data
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('jobs', 'delete')) {
    echo ApiResponse::forbidden();
    exit;
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    echo ApiResponse::error('Invalid CSRF token', 403);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $jobCode = $input['job_code'] ?? '';
    
    if (empty($jobCode)) {
        echo ApiResponse::validationError(['job_code' => 'Job code is required']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get job
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        echo ApiResponse::error('Job not found', 404);
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete related records
        $conn->query("DELETE FROM cv_inbox WHERE job_code = '{$jobCode}'");
        $conn->query("DELETE FROM candidate_submissions WHERE job_code = '{$jobCode}'");
        $conn->query("DELETE FROM job_comments WHERE job_code = '{$jobCode}'");
        $conn->query("DELETE FROM activity_log WHERE module = 'jobs' AND record_id = '{$jobCode}'");
        
        // Delete job
        $stmt = $conn->prepare("DELETE FROM jobs WHERE job_code = ?");
        $stmt->bind_param("s", $jobCode);
        $stmt->execute();
        
        // Commit
        $conn->commit();
        
        // Log deletion
        Logger::getInstance()->logActivity(
            'delete',
            'jobs',
            $jobCode,
            "Deleted job: {$job['job_title']}",
            ['client' => $job['client_name']]
        );
        
        echo ApiResponse::success(null, 'Job deleted successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Delete job failed', [
        'error' => $e->getMessage()
    ]);
    
    echo ApiResponse::error('Failed to delete job', 500);
}