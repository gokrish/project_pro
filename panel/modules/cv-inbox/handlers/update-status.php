<?php
/**
 * Update CV Status Handler
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('cv_inbox', 'edit')) {
    ApiResponse::forbidden();
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    ApiResponse::error('Invalid CSRF token', 403);
}

try {
    $cvId = (int)input('cv_id');
    $status = input('status');
    
    $validStatuses = ['new', 'reviewed', 'converted', 'rejected', 'spam'];
    
    if (!$cvId || !in_array($status, $validStatuses)) {
        ApiResponse::validation(['status' => 'Invalid status']);
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get current status
    $stmt = $conn->prepare("SELECT status FROM cv_inbox WHERE id = ?");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        ApiResponse::error('CV not found', 404);
    }
    
    $currentStatus = $result->fetch_assoc()['status'];

    // Prevent invalid transitions
    $invalidTransitions = [
        'converted' => ['new', 'reviewed'], // Can't unconvert
        'spam' => ['converted'], // Spam can't be converted
    ];

    if (isset($invalidTransitions[$currentStatus]) && 
        in_array($status, $invalidTransitions[$currentStatus])) {
        ApiResponse::error(
            "Cannot change status from {$currentStatus} to {$status}", 
            400
        );
    }

    // Special handling for rejection
    if ($status === 'rejected') {
        $rejectionReason = input('reason', '');
        
        if (empty($rejectionReason)) {
            ApiResponse::validation([
                'reason' => 'Rejection reason is required'
            ]);
        }
        
        // Update with reason
        $stmt = $conn->prepare("
            UPDATE cv_inbox 
            SET status = ?, 
                rejection_reason = ?,
                rejected_by = ?,
                rejected_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sssi", $status, $rejectionReason, Auth::userCode(), $cvId);
        $stmt->execute();
        
        // Optional: Send rejection email
        // $mailer = new Mailer();
        // $mailer->sendRejectionEmail(...);
        
    } else {
        // Normal status update
        $stmt = $conn->prepare("UPDATE cv_inbox SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $cvId);
        $stmt->execute();
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'update',
        'cv_inbox',
        $cvId,
        "Changed CV status to: {$status}"
    );
    
    ApiResponse::success(null, 'Status updated successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Update status failed', [
        'error' => $e->getMessage()
    ]);
    
    ApiResponse::error('Failed to update status', 500);
}