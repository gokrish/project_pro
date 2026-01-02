<?php
/**
 * User Management - Update Own Profile Handler
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Database, CSRFToken, Auth, Logger};

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: /panel/modules/users/profile.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get current user
$currentUser = Auth::user();
$userCode = $currentUser['user_code'];

// Get form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

// Check if email already exists (exclude current user)
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND user_code != ? AND deleted_at IS NULL");
$stmt->bind_param("ss", $email, $userCode);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $errors[] = 'Email already in use by another user';
}

// If errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_error'] = implode('<br>', $errors);
    header('Location: /panel/modules/users/profile.php');
    exit;
}

try {
    // Update profile
    $stmt = $conn->prepare("
        UPDATE users 
        SET name = ?,
            email = ?,
            phone = ?,
            updated_at = NOW()
        WHERE user_code = ?
    ");
    
    $stmt->bind_param("ssss", $name, $email, $phone, $userCode);
    
    if ($stmt->execute()) {
        // Update session with new data
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        
        // Log activity
        Logger::getInstance()->info('Profile updated', [
            'user_code' => $userCode,
            'name' => $name,
            'email' => $email
        ]);
        
        $_SESSION['flash_success'] = 'Profile updated successfully!';
        header('Location: /panel/modules/users/profile.php');
        exit;
    } else {
        throw new Exception('Failed to update profile: ' . $conn->error);
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Profile update failed', [
        'error' => $e->getMessage(),
        'user_code' => $userCode
    ]);
    
    $_SESSION['flash_error'] = 'Failed to update profile. Please try again.';
    header('Location: /panel/modules/users/profile.php');
    exit;
}
