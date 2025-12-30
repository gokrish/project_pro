<?php
/**
 * Assign CV to Recruiter Handler
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('cv_inbox', 'assign')) {
    echo ApiResponse::forbidden();
    exit;
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    echo ApiResponse::error('Invalid CSRF token', 403);
    exit;
}

try {
    $cvId = (int)input('cv_id');
    $assignTo = input('assign_to');
    
    if (!$cvId || empty($assignTo)) {
        echo ApiResponse::validationError(['assign_to' => 'Recruiter is required']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Verify CV exists
    $stmt = $conn->prepare("SELECT * FROM cv_inbox WHERE id = ?");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    $cv = $stmt->get_result()->fetch_assoc();
    
    if (!$cv) {
        echo ApiResponse::error('CV not found', 404);
        exit;
    }
    
    // Verify recruiter exists
    $stmt = $conn->prepare("SELECT user_code, name FROM users WHERE user_code = ? AND is_active = 1");
    $stmt->bind_param("s", $assignTo);
    $stmt->execute();
    $recruiter = $stmt->get_result()->fetch_assoc();
    Logger::getInstance()->logActivity(
    'assign',
    'cv_inbox',
    $cvId,
    "Assigned CV to {$recruiter['name']} by " . Auth::userName()  // ADD THIS
);

    if (!empty($cv['assigned_to']) && $cv['assigned_to'] !== $assignTo) {
        // Get previous recruiter name
        $stmt = $conn->prepare("SELECT name FROM users WHERE user_code = ?");
        $stmt->bind_param("s", $cv['assigned_to']);
        $stmt->execute();
        $prevRecruiter = $stmt->get_result()->fetch_assoc();
        
        Logger::getInstance()->logActivity(
            'reassign',
            'cv_inbox',
            $cvId,
            "Reassigned from {$prevRecruiter['name']} to {$recruiter['name']}"
        );
    }
    if (!$recruiter) {
        echo ApiResponse::error('Invalid recruiter selected', 400);
        exit;
    }
    
    // Update assignment
    $stmt = $conn->prepare("UPDATE cv_inbox SET assigned_to = ? WHERE id = ?");
    $stmt->bind_param("si", $assignTo, $cvId);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'assign',
        'cv_inbox',
        $cvId,
        "Assigned CV to {$recruiter['name']}"
    );
    
    // Send notification
    Notification::send(
        $assignTo,
        'cv_assigned',
        'CV Application Assigned',
        "CV application for {$cv['candidate_name']} has been assigned to you",
        'cv_inbox',
        $cvId
    );
    
    echo ApiResponse::success(null, 'CV assigned successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Assign CV failed', [
        'error' => $e->getMessage()
    ]);
    
    echo ApiResponse::error('Failed to assign CV', 500);
}