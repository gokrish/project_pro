<?php
/**
 * User Management - Update User Handler
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Permission, Database, CSRFToken, Auth, Logger};

// Check permission
Permission::require('users', 'edit');

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: /panel/modules/users/list.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get form data
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$level = trim($_POST['level'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

// Validation
$errors = [];

if (!$userId) {
    $errors[] = 'Invalid user ID';
}

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($level) || !in_array($level, ['super_admin', 'admin', 'manager', 'senior_recruiter', 'recruiter', 'coordinator'])) {
    $errors[] = 'Valid role is required';
}

// Check if user exists
$stmt = $conn->prepare("SELECT user_code FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userResult = $stmt->get_result()->fetch_assoc();

if (!$userResult) {
    $errors[] = 'User not found';
}

// Check if email already exists (exclude current user)
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
$stmt->bind_param("si", $email, $userId);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $errors[] = 'Email already exists';
}

// If errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_error'] = implode('<br>', $errors);
    header('Location: /panel/modules/users/edit.php?id=' . $userId);
    exit;
}

try {
    // Get role_id based on level
    $stmt = $conn->prepare("SELECT id FROM roles WHERE role_code = ?");
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $roleResult = $stmt->get_result()->fetch_assoc();
    $roleId = $roleResult['id'] ?? null;
    
    // Update user
    $stmt = $conn->prepare("
        UPDATE users 
        SET name = ?,
            email = ?,
            phone = ?,
            level = ?,
            role_id = ?,
            is_active = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param(
        "ssssiis",
        $name,
        $email,
        $phone,
        $level,
        $roleId,
        $isActive,
        $userId
    );
    
    if ($stmt->execute()) {
        // Log activity
        Logger::getInstance()->info('User updated', [
            'user_id' => $userId,
            'user_code' => $userResult['user_code'],
            'name' => $name,
            'email' => $email,
            'level' => $level,
            'updated_by' => Auth::userCode()
        ]);
        
        $_SESSION['flash_success'] = 'User updated successfully!';
        header('Location: /panel/modules/users/list.php');
        exit;
    } else {
        throw new Exception('Failed to update user: ' . $conn->error);
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('User update failed', [
        'error' => $e->getMessage(),
        'user_id' => $userId
    ]);
    
    $_SESSION['flash_error'] = 'Failed to update user. Please try again.';
    header('Location: /panel/modules/users/edit.php?id=' . $userId);
    exit;
}
