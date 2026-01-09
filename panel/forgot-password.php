<?php
/**
 * Forgot Password Page
 * Request password reset link
 * 
 * @version 2.0
 */
// Define PANEL_ACCESS before loading anything
if (!defined('PANEL_ACCESS')) {
    define('PANEL_ACCESS', true);
}

// Define paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', ROOT_PATH . '/includes');
}
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/Core/Database.php';
require_once __DIR__ . '/../includes/Core/Logger.php';
require_once __DIR__ . '/../includes/Core/Session.php';
require_once __DIR__ . '/../includes/Core/CSRFToken.php';
require_once __DIR__ . '/../includes/Core/Validator.php';
require_once __DIR__ . '/../includes/Core/Mailer.php';

use ProConsultancy\Core\Session;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\Mailer;
use ProConsultancy\Core\Logger;

Session::start();

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF validation
        if (!CSRFToken::verifyRequest()) {
            throw new Exception('Invalid request. Please try again.');
        }
        
        // Validate email
        $validator = new Validator($_POST);
        if (!$validator->validate(['email' => 'required|email'])) {
            $error = 'Please enter a valid email address';
        } else {
            $email = trim($_POST['email']);
            
            // Check if user exists
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT user_code, name FROM users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token
                $stmt = $conn->prepare("
                    INSERT INTO password_resets (user_code, token, expires_at) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
                ");
                $stmt->bind_param("sssss", $user['user_code'], $token, $expiresAt, $token, $expiresAt);
                $stmt->execute();
                
                // Send email
                $resetUrl = "http://{$_SERVER['HTTP_HOST']}/panel/reset-password.php?token={$token}";
                
                $mailer = new Mailer();
                $mailer->sendFromTemplate($email, 'password_reset', [
                    'name' => $user['name'],
                    'reset_url' => $resetUrl
                ]);
                
                Logger::getInstance()->info('Password reset requested', [
                    'user_code' => $user['user_code'],
                    'email' => $email
                ]);
            }
            
            // Always show success (security - don't reveal if email exists)
            $success = true;
        }
        
    } catch (Exception $e) {
        Logger::getInstance()->error('Forgot password error', [
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
    <title>Forgot Password - ProConsultancy</title>
    
    <link rel="icon" type="image/x-icon" href="/panel/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #1e3a8a; /* Thick blue background */
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
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-icon {
            font-size: 64px;
            color: #1e3a8a;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e3a8a 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
        }
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-login a {
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="reset-icon">
                <i class="bx bx-lock-open"></i>
            </div>
            <h1>Forgot Password?</h1>
            <p class="text-muted">Enter your email to receive reset instructions</p>
        </div>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bx bx-check-circle me-2"></i>
            If an account exists with that email, you will receive password reset instructions.
        </div>
        <?php else: ?>
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bx bx-error-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= CSRFToken::field() ?>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           placeholder="Enter your email"
                           required
                           autofocus>
                </div>
                
                <button type="submit" class="btn btn-reset">
                    <i class="bx bx-send me-2"></i>Send Reset Link
                </button>
            </form>
        <?php endif; ?>
        
        <div class="back-to-login">
            <a href="/panel/login.php">
                <i class="bx bx-arrow-back me-1"></i>Back to Login
            </a>
        </div>
    </div>
</body>
</html>