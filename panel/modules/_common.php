<?php
/**
 * Common Bootstrap
 * 
 * @package ProConsultancy
 * @version 2.0
 */

// ============================================================================
// DEFINE ACCESS CONSTANT
// ============================================================================
if (!defined('PANEL_ACCESS')) {
    define('PANEL_ACCESS', true);
}

// ============================================================================
// DEFINE PATHS
// ============================================================================
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
}

if (!defined('PANEL_PATH')) {
    define('PANEL_PATH', ROOT_PATH . '/panel');
}

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', ROOT_PATH . '/includes');
}

// ============================================================================
// AUTOLOADER
// ============================================================================
spl_autoload_register(function ($class) {
    $prefix = 'ProConsultancy\\';
    $base_dir = INCLUDES_PATH . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Try Core directory for non-namespaced classes
        $file = $base_dir . 'Core/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// ============================================================================
// LOAD CONFIGURATION (this defines DEV_MODE constants)
// ============================================================================
require_once INCLUDES_PATH . '/config/config.php';

// ============================================================================
// LOAD CORE CLASSES
// ============================================================================
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Session;

// ============================================================================
// 🔧 DEVELOPMENT MODE AUTO-LOGIN
// ============================================================================
if (defined('DEV_MODE') && DEV_MODE === true) {
    
    // Start session
    Session::start();
    
    // Check if already authenticated
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        
        try {
            // Connect to database directly for dev mode
            $devConn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if (!$devConn) {
                error_log("DEV MODE ERROR: Cannot connect to database - " . mysqli_connect_error());
            } else {
                
                mysqli_set_charset($devConn, 'utf8mb4');
                
                // Find dev user
                $stmt = $devConn->prepare("SELECT * FROM users WHERE user_code = ? AND is_active = 1 LIMIT 1");
                
                if (!$stmt) {
                    error_log("DEV MODE ERROR: Failed to prepare statement - " . $devConn->error);
                } else {
                    
                    $stmt->bind_param("s", DEV_USER_CODE);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($user = $result->fetch_assoc()) {
                        
                        // Generate dev token
                        $devToken = 'DEV_TOKEN_' . md5(DEV_USER_CODE . 'proconsultancy');
                        $expiryDays = defined('DEV_TOKEN_EXPIRY_DAYS') ? DEV_TOKEN_EXPIRY_DAYS : 365;
                        
                        // Check if dev token already exists
                        $checkStmt = $devConn->prepare("SELECT id FROM tokens WHERE token = ?");
                        $checkStmt->bind_param("s", $devToken);
                        $checkStmt->execute();
                        $tokenExists = $checkStmt->get_result()->num_rows > 0;
                        
                        // Create token if doesn't exist
                        if (!$tokenExists) {
                            $insertStmt = $devConn->prepare(
                                "INSERT INTO tokens (user_code, token, expires_at, ip_address, user_agent, created_at) 
                                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), '127.0.0.1', 'DEV_MODE', NOW())"
                            );
                            
                            if ($insertStmt) {
                                $insertStmt->bind_param("ssi", DEV_USER_CODE, $devToken, $expiryDays);
                                
                                if (!$insertStmt->execute()) {
                                    error_log("DEV MODE ERROR: Failed to insert token - " . $devConn->error);
                                } else {
                                    error_log("DEV MODE: Created new token for " . DEV_USER_CODE);
                                }
                                
                                $insertStmt->close();
                            }
                        }
                        
                        // Set session variables (matching Auth::attempt format)
                        $_SESSION['auth_token'] = $devToken;
                        $_SESSION['authenticated'] = true;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_code'] = $user['user_code'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_level'] = $user['level'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['ip_address'] = '127.0.0.1';
                        $_SESSION['dev_mode'] = true;
                        
                        // Set cookie
                        setcookie('auth_token', $devToken, time() + 86400, '/', '', false, true);
                        
                        error_log("DEV MODE: Auto-logged in as {$user['user_code']} ({$user['name']}) - Level: {$user['level']}");
                        
                    } else {
                        error_log("DEV MODE ERROR: User '" . DEV_USER_CODE . "' not found or not active");
                    }
                    
                    $stmt->close();
                }
                
                $devConn->close();
            }
            
        } catch (Exception $e) {
            error_log("DEV MODE EXCEPTION: " . $e->getMessage());
            error_log("DEV MODE TRACE: " . $e->getTraceAsString());
        }
    }
}

// ============================================================================
// AUTHENTICATION CHECK (for protected pages)
// ============================================================================
$currentPage = basename($_SERVER['PHP_SELF']);
$publicPages = ['login.php', 'forgot-password.php', 'reset-password.php', 'index.php'];

// Skip auth check for public pages
if (!in_array($currentPage, $publicPages)) {
    
    // Check authentication using Auth::check()
    if (!Auth::check()) {
        // Not authenticated - save intended URL and redirect to login
        Session::start();
        $_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
        header('Location: /panel/login.php');
        exit;
    }
}

// ============================================================================
// GET USER DATA (available for all protected pages)
// ============================================================================
$user = Auth::user();

// Set global user variables
$currentUser = $user;
$userLevel = $user['level'] ?? 'guest';
$userName = $user['name'] ?? 'Guest';
$userEmail = $user['email'] ?? '';
$userCode = $user['user_code'] ?? '';
$userId = $user['id'] ?? null;

// ============================================================================
// DEV MODE BANNER (shows on all pages when active)
// ============================================================================
if (defined('DEV_MODE') && DEV_MODE === true && isset($_SESSION['dev_mode']) && $_SESSION['dev_mode'] === true) {
    
    // Only show banner if DEV_SHOW_BANNER is enabled
    if (defined('DEV_SHOW_BANNER') && DEV_SHOW_BANNER === true) {
        
        // Register shutdown function to add banner at end of page
        register_shutdown_function(function() use ($userName, $userLevel, $userCode) {
            
            // Don't show banner on AJAX requests
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                return;
            }
            
            // Show banner
            echo '<div id="dev-mode-banner" style="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 15px 25px; border-radius: 12px; z-index: 999999; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; box-shadow: 0 8px 24px rgba(0,0,0,0.3); border: 2px solid rgba(255,255,255,0.2); max-width: 300px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div style="font-size: 16px; font-weight: bold;">🔧 DEV MODE</div>
                    <button onclick="document.getElementById(\'dev-mode-banner\').style.display=\'none\'" style="background: none; border: none; color: white; cursor: pointer; font-size: 18px; padding: 0; margin-left: 10px; opacity: 0.8;">&times;</button>
                </div>
                <div style="font-size: 13px; line-height: 1.6; opacity: 0.95;">
                    <strong>User:</strong> ' . htmlspecialchars($userName) . '<br>
                    <strong>Code:</strong> ' . htmlspecialchars($userCode) . '<br>
                    <strong>Level:</strong> <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 3px; font-size: 11px;">' . htmlspecialchars($userLevel) . '</span>
                </div>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.3); font-size: 11px; opacity: 0.85;">
                    Token-based authentication active
                </div>
            </div>';
        });
    }
}

// ============================================================================
// HELPER FUNCTIONS (available globally)
// ============================================================================

/**
 * Check if user has specific permission
 */
function hasPermission(string $module, string $action): bool {
    global $userLevel;
    
    // Super admin has all permissions
    if ($userLevel === ROLE_SUPER_ADMIN) {
        return true;
    }
    
    // Check specific permissions based on role
    // (Implement your permission logic here)
    return false;
}

/**
 * Check if user has role
 */
function hasRole(string $role): bool {
    return Auth::hasRole($role);
}

/**
 * Check if user has any of the roles
 */
function hasAnyRole(array $roles): bool {
    return Auth::hasAnyRole($roles);
}

/**
 * Get current user ID
 */
function getCurrentUserId(): ?int {
    return Auth::id();
}

/**
 * Get current user code
 */
function getCurrentUserCode(): ?string {
    return Auth::userCode();
}

/**
 * Format date for display
 */
function formatDate($date, $format = DATE_FORMAT): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = DATETIME_FORMAT): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($datetime));
}

/**
 * Sanitize output
 */
function e($string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect helper
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Check if current page is active (for navigation)
 */
function isActivePage(string $page): bool {
    return basename($_SERVER['PHP_SELF']) === $page;
}