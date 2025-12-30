<?php
/**
 * Publish Job Handler
 * Controls job publication with notifications
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('jobs', 'edit')) {
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
    $showOnCareer = (int)($input['show_on_career_page'] ?? 1);
    $notifyRecruiter = (int)($input['notify_recruiter'] ?? 1);
    
    if (empty($jobCode)) {
        echo ApiResponse::validationError(['job_code' => 'Job code is required']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Get job
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        echo ApiResponse::error('Job not found', 404);
        exit;
    }
    
    // Check if already published
    if ($job['status'] === 'open') {
        echo ApiResponse::error('Job is already published', 400);
        exit;
    }
    
    // Update job
    $stmt = $conn->prepare("
        UPDATE jobs 
        SET status = 'open',
            show_on_career_page = ?,
            updated_at = NOW()
        WHERE job_code = ?
    ");
    $stmt->bind_param("is", $showOnCareer, $jobCode);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'publish',
        'jobs',
        $jobCode,
        "Published job: {$job['job_title']}",
        ['show_on_career_page' => $showOnCareer]
    );
    
    // Send notification
    if ($notifyRecruiter && $job['assigned_to']) {
        Notification::send(
            $job['assigned_to'],
            'job_published',
            'Job Published',
            "Job '{$job['job_title']}' is now live and accepting applications",
            'jobs',
            $jobCode
        );
    }
    
    echo ApiResponse::success(null, 'Job published successfully!');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Publish job failed', [
        'error' => $e->getMessage()
    ]);
    
    echo ApiResponse::error('Failed to publish job', 500);
}