<?php
/**
 * Settings - Change Password Handler
 * 
 * @version 5.0 - Production Ready
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Database, CSRFToken, Auth, Logger};

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: /panel/modules/settings/general.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get current user
$currentUser = Auth::user();
$userCode = $currentUser['user_code'];

// Get form data
$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

// Validation
$errors = [];

if (empty($currentPassword)) {
    $errors[] = 'Current password is required';
}

if (empty($newPassword)) {
    $errors[] = 'New password is required';
} elseif (strlen($newPassword) < 6) {
    $errors[] = 'Password must be at least 6 characters';
} elseif (!preg_match('/[0-9]/', $newPassword)) {
    $errors[] = 'Password must contain at least one number';
} elseif (!preg_match('/[A-Z]/', $newPassword)) {
    $errors[] = 'Password must contain at least one uppercase letter';
}

if ($newPassword !== $confirmPassword) {
    $errors[] = 'New passwords do not match';
}

if ($currentPassword === $newPassword) {
    $errors[] = 'New password must be different from current password';
}

// If errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_error'] = implode('<br>', $errors);
    header('Location: /panel/modules/settings/general.php');
    exit;
}

try {
    // Get user's current password hash
    $stmt = $conn->prepare("
        SELECT password 
        FROM users 
        WHERE user_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $userCode);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception('User not found');
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $result['password'])) {
        $_SESSION['flash_error'] = 'Current password is incorrect';
        header('Location: /panel/modules/settings/general.php');
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    // Update password
    $stmt = $conn->prepare("
        UPDATE users 
        SET password = ?,
            password_changed_at = NOW(),
            updated_at = NOW()
        WHERE user_code = ?
    ");
    $stmt->bind_param("ss", $hashedPassword, $userCode);
    
    if ($stmt->execute()) {
        // Log activity
        Logger::getInstance()->info('Password changed', [
            'user_code' => $userCode,
            'name' => $currentUser['name']
        ]);
        
        $_SESSION['flash_success'] = 'Password changed successfully!';
        header('Location: /panel/modules/settings/general.php');
        exit;
    } else {
        throw new Exception('Failed to update password: ' . $conn->error);
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Password change failed', [
        'error' => $e->getMessage(),
        'user_code' => $userCode
    ]);
    
    $_SESSION['flash_error'] = 'Failed to change password. Please try again.';
    header('Location: /panel/modules/settings/general.php');
    exit;
}
