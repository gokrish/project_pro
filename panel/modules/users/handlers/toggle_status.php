<?php
/**
 * User Management - Toggle User Status Handler
 * Activate/Deactivate user
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, Logger};

// Check permission
Permission::require('users', 'toggle_status');

// Set JSON header
header('Content-Type: application/json');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get parameters
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$newStatus = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

// Validation
if (!$userId || ($newStatus !== 0 && $newStatus !== 1)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

try {
    // Check if user exists
    $stmt = $conn->prepare("
        SELECT user_code, name, email, is_active 
        FROM users 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Prevent deactivating yourself
    $currentUser = Auth::user();
    if ($user['user_code'] === $currentUser['user_code'] && $newStatus === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'You cannot deactivate your own account'
        ]);
        exit;
    }
    
    // Update status
    $stmt = $conn->prepare("
        UPDATE users 
        SET is_active = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $newStatus, $userId);
    
    if ($stmt->execute()) {
        // Log activity
        $action = $newStatus === 1 ? 'activated' : 'deactivated';
        Logger::getInstance()->info("User {$action}", [
            'user_id' => $userId,
            'user_code' => $user['user_code'],
            'name' => $user['name'],
            'new_status' => $newStatus,
            'changed_by' => Auth::userCode()
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User status updated successfully',
            'new_status' => $newStatus
        ]);
    } else {
        throw new Exception('Database update failed');
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Toggle user status failed', [
        'error' => $e->getMessage(),
        'user_id' => $userId
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update user status'
    ]);
}
