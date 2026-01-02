<?php
/**
 * Assign CV Handler
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\ApiResponse;

// Check permission
if (!Permission::can('cv_inbox', 'edit')) {
    ApiResponse::forbidden('You do not have permission to assign CVs');
}

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token');
}

// Validate input
if (empty($_POST['cv_id'])) {
    ApiResponse::error('CV ID is required');
}

if (empty($_POST['assigned_to'])) {
    ApiResponse::error('Assigned to user is required');
}

$cvId = (int)$_POST['cv_id'];
$assignedTo = $_POST['assigned_to'];
$user = Auth::user();

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $db->beginTransaction();
    
    // Get current CV data
    $stmt = $conn->prepare("
        SELECT cv_code, applicant_name, assigned_to as current_assignee
        FROM cv_inbox 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    $result = $stmt->get_result();
    $cv = $result->fetch_assoc();
    
    if (!$cv) {
        ApiResponse::notFound('CV not found');
    }
    
    // Verify assigned user exists
    $stmt = $conn->prepare("
        SELECT user_code, name 
        FROM users 
        WHERE user_code = ? AND is_active = 1
    ");
    $stmt->bind_param("s", $assignedTo);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignedUser = $result->fetch_assoc();
    
    if (!$assignedUser) {
        ApiResponse::error('Assigned user not found or inactive');
    }
    
    // Update assignment
    $stmt = $conn->prepare("
        UPDATE cv_inbox 
        SET assigned_to = ?,
            assigned_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param("si", $assignedTo, $cvId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update assignment');
    }
    
    // Log activity
    $oldAssignee = $cv['current_assignee'] ?? 'Unassigned';
    
    Logger::getInstance()->logActivity(
        'update',
        'cv_inbox',
        $cv['cv_code'],
        "Reassigned CV: {$oldAssignee} → {$assignedUser['name']}",
        [
            'cv_id' => $cvId,
            'applicant_name' => $cv['applicant_name'],
            'old_assignee' => $oldAssignee,
            'new_assignee' => $assignedTo,
            'reassigned_by' => $user['user_code']
        ]
    );
    
    $db->commit();
    
    ApiResponse::success([
        'cv_id' => $cvId,
        'cv_code' => $cv['cv_code'],
        'assigned_to' => $assignedTo,
        'assigned_to_name' => $assignedUser['name'],
        'assigned_at' => date('Y-m-d H:i:s')
    ], 'CV assigned successfully');
    
} catch (Exception $e) {
    $db->rollback();
    
    Logger::getInstance()->error('Failed to assign CV', [
        'cv_id' => $cvId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    ApiResponse::serverError('Failed to assign CV', [
        'error' => $e->getMessage()
    ]);
}