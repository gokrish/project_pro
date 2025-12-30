<?php
/**
 * Submit Job for Approval Handler
 * Sends job to manager/admin for approval
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Auth;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('jobs', 'edit')) {
    ApiResponse::forbidden();
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    ApiResponse::error('Invalid CSRF token', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $jobCode = $input['job_code'] ?? '';
    
    if (empty($jobCode)) {
        ApiResponse::validation(['job_code' => 'Job code is required']);
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Get job
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        ApiResponse::error('Job not found', 404);
    }
    
    // Check if already submitted
    if ($job['approval_status'] === 'pending_approval') {
        ApiResponse::error('Job is already pending approval', 400);
    }
    
    // Update job
    $stmt = $conn->prepare("
        UPDATE jobs 
        SET approval_status = 'pending_approval',
            submitted_for_approval_at = NOW(),
            status = 'pending_approval',
            updated_at = NOW()
        WHERE job_code = ?
    ");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'submit_approval',
        'jobs',
        $jobCode,
        "Submitted job for approval: {$job['job_title']}"
    );
    
    // Get all managers/admins
    $managersResult = $conn->query("
        SELECT user_code, name, email 
        FROM users 
        WHERE level IN ('manager', 'admin') 
        AND is_active = 1
    ");
    $managers = $managersResult->fetch_all(MYSQLI_ASSOC);
    
    // Send notification emails
    foreach ($managers as $manager) {
        Notification::send(
            $manager['user_code'],
            'job_approval_request',
            'Job Approval Required',
            "Job '{$job['job_title']}' has been submitted for approval",
            'jobs',
            $jobCode
        );
        
        // Send email notification
        try {
            $mailer = new Mailer();
            $mailer->sendFromTemplate(
                'job_approval_request',
                $manager['email'],
                [
                    'job_title' => $job['job_title'],
                    'client_name' => $job['company_name'] ?? 'N/A',
                    'created_by_name' => $user['name'],
                    'approval_url' => BASE_URL . '/panel/modules/jobs/approve.php?code=' . urlencode($jobCode)
                ]
            );
        } catch (Exception $e) {
            Logger::getInstance()->warning('Failed to send approval email', [
                'manager' => $manager['email'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    ApiResponse::success(null, 'Job submitted for approval successfully!');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Submit for approval failed', [
        'error' => $e->getMessage()
    ]);
    
    ApiResponse::error('Failed to submit for approval', 500);
}