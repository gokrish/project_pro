<?php
/**
 * Login Page
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

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ProConsultancy\\';
    $base_dir = INCLUDES_PATH . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration
require_once INCLUDES_PATH . '/config/config.php';

// Load core classes
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Session;
use ProConsultancy\Core\CSRFToken;

// Start session
Session::start();

// Check if already logged in
if (Auth::check()) {
    header('Location: /panel/dashboard.php');
    exit;
}

$error = null;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF
        if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
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
            // Success - redirect to intended page or dashboard
            $returnTo = $_SESSION['return_to'] ?? '/panel/dashboard.php';
            unset($_SESSION['return_to']);
            header('Location: ' . $returnTo);
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
    
    <link rel="icon" type="image/x-icon" href="/panel/assets/img/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #1e3a8a; /* Thick blue background */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 50px 40px;
            text-align: center;
        }
        
        .company-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .company-icon img {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }
        
        .company-icon i {
            font-size: 40px;
            color: #1e3a8a;
        }
        
        .login-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 10px 0;
            letter-spacing: -0.5px;
        }
        
        .login-header p {
            font-size: 15px;
            margin: 0;
            opacity: 0.95;
        }
        
        .login-body {
            padding: 45px 40px;
        }
        
        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 0.25rem rgba(30, 58, 138, 0.15);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            font-size: 20px;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: #1e3a8a;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 14px 18px;
            margin-bottom: 24px;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .form-check-input:checked {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
        }
        
        .forgot-password-link {
            color: #1e3a8a;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .forgot-password-link:hover {
            color: #1e40af;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="company-icon">
                <!-- Company icon/logo -->
                <?php if (file_exists(ROOT_PATH . '/panel/assets/img/company-logo.png')): ?>
                    <img src="/panel/assets/img/company-logo.png" alt="Company Logo">
                <?php else: ?>
                    <i class="bx bx-briefcase"></i>
                <?php endif; ?>
            </div>
            <h1>ProConsultancy</h1>
            <p>Trusted Recruitment Partner</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bx bx-error-circle me-2"></i>
                <strong><?= htmlspecialchars($error) ?></strong>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= CSRFToken::field() ?>
                
                <div class="mb-4">
                    <label class="form-label">User Code or Email</label>
                    <input type="text" 
                           class="form-control" 
                           name="identifier" 
                           placeholder="Enter your user code or email"
                           value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                           required
                           autofocus>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               id="password"
                               placeholder="Enter your password"
                               required>
                        <button type="button" 
                                class="password-toggle" 
                                onclick="togglePassword()"
                                aria-label="Toggle password visibility">
                            <i class="bx bx-hide" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-4 form-check">
                    <input type="checkbox" 
                           class="form-check-input" 
                           name="remember" 
                           id="remember">
                    <label class="form-check-label" for="remember">
                        Remember me for 30 days
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bx bx-log-in"></i>
                    <span>Sign In</span>
                </button>
            </form>
            
            <div class="text-center mt-4">
                <a href="/panel/forgot-password.php" class="forgot-password-link">
                    <i class="bx bx-lock-open me-1"></i>Forgot your password?
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bx-hide');
                toggleIcon.classList.add('bx-show');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bx-show');
                toggleIcon.classList.add('bx-hide');
            }
        }
        
        // Allow Enter key to toggle password when focused on toggle button
        document.querySelector('.password-toggle').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                togglePassword();
            }
        });
    </script>
</body>
</html>