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

if (!$userCode) {
    ApiResponse::error('User code is required', 400);
}

try {
    // Generate random password
    $newPassword = bin2hex(random_bytes(4)); // 8-character password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE user_code = ?");
    $stmt->bind_param("ss", $hashedPassword, $userCode);
    
    if ($stmt->execute()) {
        Logger::getInstance()->logActivity('update', 'users', $userCode, 'Password reset');
        ApiResponse::success([
            'user_code' => $userCode, 
            'new_password' => $newPassword
        ], 'Password reset successfully');
    } else {
        ApiResponse::error('Failed to reset password', 500);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
