<?php
/**
 * Approve/Reject Job Handler
 * Processes approval decision
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
if (!Permission::can('jobs', 'approve')) {
    ApiResponse::forbidden();
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    ApiResponse::error('Invalid CSRF token', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $jobCode = $input['job_code'] ?? '';
    $action = $input['action'] ?? ''; // 'approve' or 'reject'
    $rejectionReason = $input['rejection_reason'] ?? '';
    
    if (empty($jobCode)) {
        ApiResponse::validation(['job_code' => 'Job code is required']);
    }
    
    if (!in_array($action, ['approve', 'reject'])) {
        ApiResponse::validation(['action' => 'Invalid action']);
    }
    
    if ($action === 'reject' && empty($rejectionReason)) {
        ApiResponse::validation(['rejection_reason' => 'Rejection reason is required']);
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
    
    if ($job['approval_status'] !== 'pending_approval') {
        ApiResponse::error('Job is not pending approval', 400);
    }
    
    $db->beginTransaction();
    
    try {
        if ($action === 'approve') {
            // Approve and publish
            $stmt = $conn->prepare("
                UPDATE jobs 
                SET approval_status = 'approved',
                    status = 'open',
                    is_published = 1,
                    published_at = NOW(),
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE job_code = ?
            ");
            $stmt->bind_param("ss", $user['user_code'], $jobCode);
            $stmt->execute();
            
            $message = 'Job approved and published successfully!';
            $emailTemplate = 'job_approved';
            
            Logger::getInstance()->logActivity(
                'approve',
                'jobs',
                $jobCode,
                "Approved job: {$job['job_title']}"
            );
            
        } else {
            // Reject
            $stmt = $conn->prepare("
                UPDATE jobs 
                SET approval_status = 'rejected',
                    status = 'draft',
                    rejected_by = ?,
                    rejected_at = NOW(),
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE job_code = ?
            ");
            $stmt->bind_param("sss", $user['user_code'], $rejectionReason, $jobCode);
            $stmt->execute();
            
            $message = 'Job rejected and sent back for revision';
            $emailTemplate = 'job_rejected';
            
            Logger::getInstance()->logActivity(
                'reject',
                'jobs',
                $jobCode,
                "Rejected job: {$job['job_title']}",
                ['reason' => $rejectionReason]
            );
        }
        
        $db->commit();
        
        // Send notification to job creator
        Notification::send(
            $job['created_by'],
            $emailTemplate,
            $action === 'approve' ? 'Job Approved' : 'Job Rejected',
            $action === 'approve' 
                ? "Your job '{$job['job_title']}' has been approved and published"
                : "Your job '{$job['job_title']}' needs revision",
            'jobs',
            $jobCode
        );
        
        // Send email
        try {
            $creatorStmt = $conn->prepare("SELECT email FROM users WHERE user_code = ?");
            $creatorStmt->bind_param("s", $job['created_by']);
            $creatorStmt->execute();
            $creator = $creatorStmt->get_result()->fetch_assoc();
            
            if ($creator) {
                $mailer = new Mailer();
                $mailer->sendFromTemplate(
                    $emailTemplate,
                    $creator['email'],
                    [
                        'job_title' => $job['job_title'],
                        'rejection_reason' => $rejectionReason ?? '',
                        'job_url' => BASE_URL . '/panel/modules/jobs/view.php?code=' . urlencode($jobCode),
                        'edit_url' => BASE_URL . '/panel/modules/jobs/edit.php?code=' . urlencode($jobCode)
                    ]
                );
            }
        } catch (Exception $e) {
            Logger::getInstance()->warning('Failed to send approval email', [
                'error' => $e->getMessage()
            ]);
        }
        
        ApiResponse::success(null, $message);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Approve/reject job failed', [
        'error' => $e->getMessage()
    ]);
    
    ApiResponse::error('Failed to process approval', 500);
}