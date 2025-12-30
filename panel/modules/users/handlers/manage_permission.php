<?php
require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\Logger;

Permission::require('users', 'manage_permissions');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token', 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

$userCode = $_POST['user_code'] ?? null;
$permissionCode = $_POST['permission_code'] ?? null;
$action = $_POST['action'] ?? null; // grant, revoke, reset

if (!$userCode || !$permissionCode || !$action) {
    ApiResponse::error('Missing required parameters', 400);
}

try {
    // Get permission ID
    $stmt = $conn->prepare("SELECT id FROM permissions WHERE permission_code = ?");
    $stmt->bind_param("s", $permissionCode);
    $stmt->execute();
    $perm = $stmt->get_result()->fetch_assoc();
    
    if (!$perm) {
        ApiResponse::error('Permission not found', 404);
    }
    
    $permissionId = $perm['id'];
    
    if ($action === 'grant') {
        // Grant permission (user-specific)
        $stmt = $conn->prepare("
            INSERT INTO user_permissions (user_code, permission_id, is_granted, granted_by, granted_at)
            VALUES (?, ?, 1, ?, NOW())
            ON DUPLICATE KEY UPDATE is_granted = 1, granted_by = ?, granted_at = NOW()
        ");
        $grantedBy = Auth::userCode();
        $stmt->bind_param("siss", $userCode, $permissionId, $grantedBy, $grantedBy);
        $stmt->execute();
        
        Logger::getInstance()->logActivity('grant_permission', 'users', $userCode, "Granted permission: {$permissionCode}");
        ApiResponse::success([], 'Permission granted');
        
    } elseif ($action === 'revoke') {
        // Revoke permission (user-specific override)
        $stmt = $conn->prepare("
            INSERT INTO user_permissions (user_code, permission_id, is_granted, granted_by, granted_at)
            VALUES (?, ?, 0, ?, NOW())
            ON DUPLICATE KEY UPDATE is_granted = 0, granted_by = ?, granted_at = NOW()
        ");
        $revokedBy = Auth::userCode();
        $stmt->bind_param("siss", $userCode, $permissionId, $revokedBy, $revokedBy);
        $stmt->execute();
        
        Logger::getInstance()->logActivity('revoke_permission', 'users', $userCode, "Revoked permission: {$permissionCode}");
        ApiResponse::success([], 'Permission revoked');
        
    } elseif ($action === 'reset') {
        // Reset to role default (remove user-specific override)
        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_code = ? AND permission_id = ?");
        $stmt->bind_param("si", $userCode, $permissionId);
        $stmt->execute();
        
        Logger::getInstance()->logActivity('reset_permission', 'users', $userCode, "Reset permission: {$permissionCode}");
        ApiResponse::success([], 'Permission reset to role default');
        
    } else {
        ApiResponse::error('Invalid action', 400);
    }
    
    // Clear permission cache
    Permission::clearCache();
    
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
