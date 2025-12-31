<?php
/**
 * Login Page - TOKEN BASED with DEV MODE
 */

// Define PANEL_ACCESS before loading config
if (!defined('PANEL_ACCESS')) {
    define('PANEL_ACCESS', true);
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', ROOT_PATH . '/includes');
}

// Load configuration
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/Core/Database.php';
require_once __DIR__ . '/../includes/Core/Session.php';
require_once __DIR__ . '/../includes/Core/Auth.php';
require_once __DIR__ . '/../includes/Core/CSRFToken.php';

use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Session;
use ProConsultancy\Core\CSRFToken;

// ============================================================================
// DEV MODE: Auto-login and redirect
// ============================================================================
if (defined('DEV_MODE') && DEV_MODE && defined('DEV_AUTO_LOGIN') && DEV_AUTO_LOGIN) {
    Session::start();
    
    // If not authenticated, setup dev login
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        try {
            $devConn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($devConn) {
                mysqli_set_charset($devConn, 'utf8mb4');
                
                $stmt = $devConn->prepare("SELECT * FROM users WHERE user_code = ? AND is_active = 1 LIMIT 1");
                $stmt->bind_param("s", DEV_USER_CODE);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($user = $result->fetch_assoc()) {
                    // Create dev token
                    $devToken = 'DEV_TOKEN_' . md5(DEV_USER_CODE . 'proconsultancy');
                    
                    // Check if dev token exists
                    $checkStmt = $devConn->prepare("SELECT id FROM tokens WHERE token = ?");
                    $checkStmt->bind_param("s", $devToken);
                    $checkStmt->execute();
                    $tokenExists = $checkStmt->get_result()->num_rows > 0;
                    
                    // Create token if doesn't exist
                    if (!$tokenExists) {
                        $expiryDays = defined('DEV_TOKEN_EXPIRY_DAYS') ? DEV_TOKEN_EXPIRY_DAYS : 365;
                        $insertStmt = $devConn->prepare(
                            "INSERT INTO tokens (user_code, token, expires_at, ip_address, user_agent, created_at) 
                             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), '127.0.0.1', 'DEV_MODE', NOW())"
                        );
                        $insertStmt->bind_param("ssi", DEV_USER_CODE, $devToken, $expiryDays);
                        $insertStmt->execute();
                    }
                    
                    // Set session variables
                    $_SESSION['auth_token'] = $devToken;
                    $_SESSION['authenticated'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_code'] = $user['user_code'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_level'] = $user['level'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['dev_mode'] = true;
                    
                    // Set cookie
                    setcookie('auth_token', $devToken, time() + 86400, '/', '', false, true);
                    
                    error_log("DEV MODE: Auto-logged in as {$user['user_code']} ({$user['name']})");
                    
                    // Redirect to route
                    header('Location: /panel/route.php');
                    exit;
                }
                
                $devConn->close();
            }
        } catch (Exception $e) {
            error_log("DEV MODE ERROR: " . $e->getMessage());
            // Continue to show login form
        }
    } else {
        // Already authenticated in dev mode - redirect
        header('Location: /panel/dashboard.php');
        exit;
    }
}

// ============================================================================
// NORMAL AUTH CHECK (Production mode)
// ============================================================================
Session::start();

// Check if already logged in (production mode)
if (Auth::check()) {
    header('Location: /panel/route.php');
    exit;
}

$error = null;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF
        if (!CSRFToken::verifyRequest()) {
            throw new Exception('Invalid request. Please refresh and try again.');
        }
        
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($identifier) || empty($password)) {
            throw new Exception('User code/Email and password are required');
        }
        
        // Attempt login
        if (Auth::attempt($identifier, $password, $remember)) {
            // Success - redirect
            header('Location: /panel/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email/user code or password';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ProConsultancy</title>
    
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
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 10px 0;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control {
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
        }
        .alert {
            border-radius: 8px;
        }
        .dev-mode-notice {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #f59e0b;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php if (defined('DEV_MODE') && DEV_MODE && defined('DEV_SHOW_BANNER') && DEV_SHOW_BANNER): ?>
    <div class="dev-mode-notice">
        🔧 DEV MODE: Auto-redirecting to dashboard...
    </div>
    <?php endif; ?>
    
    <div class="login-container">
        <div class="login-header">
            <h1><i class="bx bx-briefcase"></i> ProConsultancy</h1>
            <p>Professional Recruitment Management</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bx bx-error-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= CSRFToken::field() ?>
                
                <div class="mb-3">
                    <label class="form-label">User Code or Email</label>
                    <input type="text" 
                           class="form-control" 
                           name="identifier" 
                           placeholder="Enter your user code or email"
                           value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                           required
                           autofocus>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" 
                           class="form-control" 
                           name="password" 
                           placeholder="Enter your password"
                           required>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" name="remember" id="remember">
                    <label class="form-check-label" for="remember">
                        Remember me
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bx bx-log-in me-2"></i>Sign In
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="/panel/forgot-password.php" style="color: #667eea;">
                    <i class="bx bx-lock-open me-1"></i>Forgot your password?
                </a>
            </div>
        </div>
    </div>
</body>
</html>