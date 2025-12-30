<?php
require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\Logger;

Permission::require('users', 'edit');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token', 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

$userCode = $_POST['user_code'] ?? null;
$isActive = (int)($_POST['is_active'] ?? 0);

if (!$userCode) {
    ApiResponse::error('User code is required', 400);
}

if ($userCode === Auth::userCode()) {
    ApiResponse::error('You cannot deactivate yourself', 400);
}

try {
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_code = ?");
    $stmt->bind_param("is", $isActive, $userCode);
    
    if ($stmt->execute()) {
        $action = $isActive ? 'activated' : 'deactivated';
        Logger::getInstance()->logActivity('update', 'users', $userCode, "User {$action}");
        ApiResponse::success(['user_code' => $userCode, 'is_active' => $isActive], "User {$action} successfully");
    } else {
        ApiResponse::error('Failed to update user status', 500);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}