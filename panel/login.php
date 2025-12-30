<?php
/**
 * Login Page
 * User authentication interface
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/Core/Logger.php';
require_once __DIR__ . '/../includes/Core/Database.php';
require_once __DIR__ . '/../includes/Core/ErrorHandler.php';
require_once __DIR__ . '/../includes/Core/Session.php';
require_once __DIR__ . '/../includes/Core/Auth.php';
require_once __DIR__ . '/../includes/Core/CSRFToken.php';
require_once __DIR__ . '/../includes/Core/Validator.php';
require_once __DIR__ . '/../includes/Core/FlashMessage.php';

use ProConsultancy\Core\Session;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Session::start();

// Redirect if already logged in
if (Auth::check()) {
    header('Location: /panel/dashboard.php');
    exit;
}

// Handle login form submission
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Verify CSRF
        if (!CSRFToken::verifyRequest()) {
            throw new Exception('Invalid session. Please refresh and try again.');
        }
        
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // 2. Attempt Login
        if (Auth::attempt($identifier, $password, $remember)) {
            echo "<h3>Auth logging</h3>";
            
            // SUCCESS: Redirect to dashboard
            header('Location: /panel/dashboard.php');
            console_log("Checking Auth loggin: " . $error);
            exit;
        } else {
            // FAILURE: Set error and REDIRECT back to login
            Session::set('login_error', 'Invalid email/user code or password');
            header('Location: login.php'); // This clears the POST data!
            exit;
        }
    } catch (Exception $e) {
        Session::set('login_error', $e->getMessage());
        header('Location: login.php');
        exit;
    }
}

// 3. Get and immediately CLEAR the error (so it won't show on refresh)
$error = Session::get('login_error');
Session::remove('login_error');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title>Login - ProConsultancy</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/panel/assets/images/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
        
        .login-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #2d3748;
        }
        
        .form-control {
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .input-group-text {
            background: white;
            border: 1px solid #e2e8f0;
            border-right: none;
        }
        
        .form-control.with-icon {
            border-left: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="bx bx-briefcase"></i> ProConsultancy</h1>
            <p>Professional Recruitment Management</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bx bx-error-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" autocomplete="off">
                <?= CSRFToken::field() ?>
                
                <div class="mb-3">
                    <label for="identifier" class="form-label">Email or User Code</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bx bx-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control with-icon" 
                               id="identifier" 
                               name="identifier" 
                               placeholder="Enter your email or user code"
                               value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                               required
                               autofocus>
                    </div>
                    <small class="form-text text-muted">
                        You can login with either your email or user code
                    </small>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bx bx-lock-alt"></i>
                        </span>
                        <input type="password" 
                               class="form-control with-icon" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required>
                        <button class="btn btn-outline-secondary" 
                                type="button" 
                                id="togglePassword"
                                style="border-left: none;">
                            <i class="bx bx-show"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Remember me for 30 days
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bx bx-log-in me-2"></i>Sign In
                </button>
            </form>
            
            <div class="forgot-password">
                <a href="/panel/forgot-password.php">
                    <i class="bx bx-lock-open me-1"></i>Forgot your password?
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            } else {
                password.type = 'password';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            }
        });
    </script>
</body>
</html>