<?php
require_once __DIR__ . '/../../_common.php';

header('Content-Type: application/json');

Permission::require('users', 'manage_roles');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }
    
    $roleId = (int)$_POST['role_id'];
    $permissions = $_POST['permissions'] ?? [];
    
    // Verify role exists and is not a system role
    $roleStmt = $conn->prepare("SELECT * FROM roles WHERE role_id = ?");
    $roleStmt->bind_param('i', $roleId);
    $roleStmt->execute();
    $role = $roleStmt->get_result()->fetch_assoc();
    
    if (!$role) {
        throw new Exception('Role not found');
    }
    
    if ($role['is_system']) {
        throw new Exception('System roles cannot be modified');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Delete existing permissions
    $deleteStmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $deleteStmt->bind_param('i', $roleId);
    $deleteStmt->execute();
    
    // Insert new permissions
    if (!empty($permissions)) {
        $insertStmt = $conn->prepare("
            INSERT INTO role_permissions (role_id, permission_id, granted)
            VALUES (?, ?, 1)
        ");
        
        foreach ($permissions as $permissionId) {
            $permId = (int)$permissionId;
            $insertStmt->bind_param('ii', $roleId, $permId);
            $insertStmt->execute();
        }
    }
    
    // Clear permission cache
    Permission::clearCache();
    
    // Log activity
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log (user_id, module, action, description, created_at)
        VALUES (?, 'users', 'update_permissions', ?, NOW())
    ");
    $activityDesc = "Updated permissions for role: {$role['role_name']}";
    $activityStmt->bind_param('is', $user['id'], $activityDesc);
    $activityStmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Permissions updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>