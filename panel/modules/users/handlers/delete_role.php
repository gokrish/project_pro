<?php
require_once __DIR__ . '/../../_common.php';

header('Content-Type: application/json');

Permission::require('users', 'manage_roles');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $roleId = (int)$input['role_id'];
    
    // Verify role exists and is not a system role
    $roleStmt = $conn->prepare("SELECT * FROM roles WHERE role_id = ?");
    $roleStmt->bind_param('i', $roleId);
    $roleStmt->execute();
    $role = $roleStmt->get_result()->fetch_assoc();
    
    if (!$role) {
        throw new Exception('Role not found');
    }
    
    if ($role['is_system']) {
        throw new Exception('System roles cannot be deleted');
    }
    
    // Check if any users have this role
    $userCheckStmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
    $userCheckStmt->bind_param('i', $roleId);
    $userCheckStmt->execute();
    $userCount = $userCheckStmt->get_result()->fetch_assoc()['count'];
    
    if ($userCount > 0) {
        throw new Exception("Cannot delete role: {$userCount} user(s) assigned to this role");
    }
    
    // Delete role (permissions will be cascade deleted)
    $deleteStmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
    $deleteStmt->bind_param('i', $roleId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete role');
    }
    
    // Log activity
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log (user_id, module, action, description, created_at)
        VALUES (?, 'users', 'delete_role', ?, NOW())
    ");
    $activityDesc = "Deleted role: {$role['role_name']}";
    $activityStmt->bind_param('is', $user['id'], $activityDesc);
    $activityStmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Role deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>