<?php
/**
 * User Management - Reset Password Handler
 * Generate new temporary password for user
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, Logger};

// Check permission
Permission::require('users', 'reset_password');

// Set JSON header
header('Content-Type: application/json');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get parameters
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

// Validation
if (!$userId) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID'
    ]);
    exit;
}

try {
    // Check if user exists
    $stmt = $conn->prepare("
        SELECT user_code, name, email 
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
    
    // Generate new password (12 characters)
    $newPassword = generatePassword(12);
    
    // Hash password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    // Update password
    $stmt = $conn->prepare("
        UPDATE users 
        SET password = ?,
            password_changed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("si", $hashedPassword, $userId);
    
    if ($stmt->execute()) {
        // Log activity
        Logger::getInstance()->info('Password reset', [
            'user_id' => $userId,
            'user_code' => $user['user_code'],
            'name' => $user['name'],
            'reset_by' => Auth::userCode()
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully',
            'new_password' => $newPassword
        ]);
    } else {
        throw new Exception('Database update failed');
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Password reset failed', [
        'error' => $e->getMessage(),
        'user_id' => $userId
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to reset password'
    ]);
}

/**
 * Generate random password
 * 
 * @param int $length Password length
 * @return string Generated password
 */
function generatePassword($length = 12) {
    $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $password = '';
    
    // Ensure at least one uppercase and one number
    $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[rand(0, 25)];
    $password .= '0123456789'[rand(0, 9)];
    
    // Fill rest randomly
    for ($i = 2; $i < $length; $i++) {
        $password .= $charset[rand(0, strlen($charset) - 1)];
    }
    
    // Shuffle
    return str_shuffle($password);
}
