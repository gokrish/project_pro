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
    
    $roleName = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($roleName)) {
        throw new Exception('Role name is required');
    }
    
    // Generate role code
    $roleCode = strtolower(str_replace(' ', '_', $roleName));
    
    // Check if role code already exists
    $checkStmt = $conn->prepare("SELECT role_id FROM roles WHERE role_code = ?");
    $checkStmt->bind_param('s', $roleCode);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        throw new Exception('A role with this name already exists');
    }
    
    // Insert role
    $stmt = $conn->prepare("
        INSERT INTO roles (role_code, role_name, description, is_system, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param('sss', $roleCode, $roleName, $description);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create role');
    }
    
    $roleId = $conn->insert_id;
    
    // Log activity
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log (user_id, module, action, description, created_at)
        VALUES (?, 'users', 'create_role', ?, NOW())
    ");
    $activityDesc = "Created role: {$roleName}";
    $activityStmt->bind_param('is', $user['id'], $activityDesc);
    $activityStmt->execute();
    
    echo json_encode([
        'success' => true,
        'role_id' => $roleId,
        'message' => 'Role created successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>