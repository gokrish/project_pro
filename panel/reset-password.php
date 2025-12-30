<?php
/**
 * Reset Password Page
 * Set new password with token
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Core/Database.php';
require_once __DIR__ . '/../includes/Core/Logger.php';
require_once __DIR__ . '/../includes/Core/Session.php';
require_once __DIR__ . '/../includes/Core/CSRFToken.php';
require_once __DIR__ . '/../includes/Core/Validator.php';

use ProConsultancy\Core\Session;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\Logger;

Session::start();

$token = $_GET['token'] ?? '';
$validToken = false;
$error = null;
$success = false;

// Validate token
if ($token) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT pr.*, u.email, u.name 
        FROM password_resets pr
        JOIN users u ON pr.user_code = u.user_code
        WHERE pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $resetData = $result->fetch_assoc();
    
    $validToken = !empty($resetData);
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    try {
        if (!CSRFToken::verifyRequest()) {
            throw new Exception('Invalid request');
        }
        
        // Validate passwords
        $validator = new Validator($_POST);
        if (!$validator->validate([
            'password' => 'required|min:8',
            'password_confirmation' => 'required|same:password'
        ])) {
            $errors = $validator->errors();
            $error = $errors['password'][0] ?? $errors['password_confirmation'][0] ?? 'Invalid password';
        } else {
            // Update password
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_code = ?");
            $stmt->bind_param("ss", $hashedPassword, $resetData['user_code']);
            $stmt->execute();
            
            // Delete reset token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            Logger::getInstance()->info('Password reset successful', [
                'user_code' => $resetData['user_code']
            ]);
            
            $success = true;
        }
        
    } catch (Exception $e) {
        Logger::getInstance()->error('Password reset error', [
            'error' => $e->getMessage()
        ]);
        $error = 'An error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ProConsultancy</title>
    
    <link rel="icon" type="image/x-icon" href="/panel/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        .reset-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 20px;
        }
        .reset-icon.valid { color: #48bb78; }
        .reset-icon.invalid { color: #f56565; }
        h1 {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }
        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
        }
        .password-requirements {
            font-size: 12px;
            color: #718096;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if (!$validToken): ?>
        <div class="reset-icon invalid">
            <i class="bx bx-error-circle"></i>
        </div>
        <h1>Invalid or Expired Link</h1>
        <div class="alert alert-danger">
            This password reset link is invalid or has expired. Please request a new one.
        </div>
        <a href="/panel/forgot-password.php" class="btn btn-reset">
            Request New Link
        </a>
        
        <?php elseif ($success): ?>
        <div class="reset-icon valid">
            <i class="bx bx-check-circle"></i>
        </div>
        <h1>Password Reset Successful</h1>
        <div class="alert alert-success">
            Your password has been reset successfully. You can now login with your new password.
        </div>
        <a href="/panel/login.php" class="btn btn-reset">
            Go to Login
        </a>
        
        <?php else: ?>
        <div class="reset-icon valid">
            <i class="bx bx-lock"></i>
        </div>
        <h1>Set New Password</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            
            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       placeholder="Enter new password"
                       required
                       minlength="8">
                <div class="password-requirements">
                    Password must be at least 8 characters long
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input type="password" 
                       class="form-control" 
                       id="password_confirmation" 
                       name="password_confirmation" 
                       placeholder="Confirm new password"
                       required>
            </div>
            
            <button type="submit" class="btn btn-reset">
                <i class="bx bx-check me-2"></i>Reset Password
            </button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>