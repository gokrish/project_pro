<?php
/**
 * Common Bootstrap File
 * Loaded by all panel pages
 * 
 * @version 2.0 FINAL
 * @package ProConsultancy
 */
require_once __DIR__ . '/includes/_helpers.php';
// Prevent direct access
if (!defined('PANEL_ACCESS')) {
    define('PANEL_ACCESS', true);
}

// Error reporting (development - change for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define root path
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
define('PANEL_PATH', ROOT_PATH . '/panel');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Autoloader
spl_autoload_register(function ($class) {
    
    $prefix = 'ProConsultancy\\';
    $base_dir = INCLUDES_PATH . '/';
    
    // Check if class uses our namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Try without namespace (for backward compatibility)
        $file = $base_dir . 'Core/' . $class . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
        return;
    }
    
    // Get relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separator with directory separator
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration
require_once INCLUDES_PATH . '/config/config.php';
require_once INCLUDES_PATH . '/config/database.php';

// Load core classes
use ProConsultancy\Core\Branding;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Session;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\PermissionException;


// Initialize logger
$logger = Logger::getInstance();

// Check if user is authenticated
$user = Auth::user();

// If not authenticated and not on login page, redirect
$currentPage = basename($_SERVER['PHP_SELF']);
$allowedPages = ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php'];

if (!$user && !in_array($currentPage, $allowedPages)) {
    // Store intended URL
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . '/panel/login.php');
    exit();
}

// Global exception handler for PermissionException
set_exception_handler(function($exception) {
    if ($exception instanceof PermissionException) {
        http_response_code(403);
        
        // If AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage()
            ]);
        } else {
            // Regular page request
            include PANEL_PATH . '/includes/header.php';
            echo '<div class="container-xxl flex-grow-1 container-p-y">';
            echo '<div class="alert alert-danger">';
            echo '<h4 class="alert-heading"><i class="bx bx-error me-2"></i>Access Denied</h4>';
            echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
            echo '<hr>';
            echo '<p class="mb-0">If you believe this is an error, please contact your administrator.</p>';
            echo '</div>';
            echo '<a href="' . BASE_URL . '/panel/dashboard.php" class="btn btn-primary">Go to Dashboard</a>';
            echo '</div>';
            include PANEL_PATH . '/includes/footer.php';
        }
        exit();
    }
    
    // Other exceptions - log and show generic error
    Logger::getInstance()->error('Unhandled exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo '<h1>An error occurred</h1>';
    echo '<p>Please try again later or contact support if the problem persists.</p>';
    exit();
});

// Helper functions
if (!function_exists('escape')) {
    /**
     * Escape HTML output
     */
    function escape($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value (for form repopulation after validation errors)
     */
    function old($key, $default = '') {
        return $_SESSION['old'][$key] ?? $default;
    }
}

if (!function_exists('clearOld')) {
    /**
     * Clear old input values
     */
    function clearOld() {
        unset($_SESSION['old']);
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to URL
     */
    function redirect($url, $statusCode = 302) {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
}

if (!function_exists('back')) {
    /**
     * Redirect back to previous page
     */
    function back() {
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/panel/dashboard.php';
        redirect($referer);
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format date according to system settings
     */
    function formatDate($date, $format = null) {
        if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        $format = $format ?? 'd/m/Y';
        return date($format, strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Format datetime according to system settings
     */
    function formatDateTime($datetime, $format = null) {
        if (!$datetime || $datetime === '0000-00-00 00:00:00') {
            return '-';
        }
        
        $format = $format ?? 'd/m/Y H:i';
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('formatMoney')) {
    /**
     * Format money amount
     */
    function formatMoney($amount, $currency = 'EUR') {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('timeAgo')) {
    /**
     * Convert timestamp to human-readable "time ago"
     */
    function timeAgo($datetime) {
        if (!$datetime || $datetime === '0000-00-00 00:00:00') {
            return 'Never';
        }
        
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return formatDate($datetime);
        }
    }
}

if (!function_exists('getStatusBadge')) {
    /**
     * Get Bootstrap badge class for status
     */
    function getStatusBadge($status) {
        $badges = [
            // Contact statuses
            'new' => 'primary',
            'contacted' => 'info',
            'qualified' => 'success',
            'nurturing' => 'warning',
            'converted' => 'success',
            'not_interested' => 'secondary',
            'unresponsive' => 'danger',
            
            // Candidate/Job statuses
            'active' => 'success',
            'inactive' => 'secondary',
            'open' => 'success',
            'closed' => 'secondary',
            'draft' => 'warning',
            'filled' => 'info',
            
            // Submission statuses
            'pending_review' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'submitted' => 'info',
            'accepted' => 'success',
            'rejected_by_client' => 'danger',
            
            // Application statuses
            'screening' => 'info',
            'interviewing' => 'primary',
            'offered' => 'success',
            'placed' => 'success',
            'rejected' => 'danger'
        ];
        
        return $badges[$status] ?? 'secondary';
    }
}

if (!function_exists('getPriorityBadge')) {
    /**
     * Get Bootstrap badge class for priority
     */
    function getPriorityBadge($priority) {
        $badges = [
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'info'
        ];
        
        return $badges[$priority] ?? 'secondary';
    }
}

if (!function_exists('generateCode')) {
    /**
     * Generate unique code (fallback if DB function not available)
     */
    function generateCode($prefix = 'GEN') {
        return $prefix . '-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

// Set global variables for templates
$currentUser = $user;
$userLevel = $user['level'] ?? 'guest';
$userName = $user['name'] ?? 'Guest';
$userEmail = $user['email'] ?? '';

// ============================================================================
// REQUEST INPUT HELPERS
// ============================================================================
if (!function_exists('input')) {
    /**
     * Get input from GET, POST, or REQUEST
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function input($key, $default = '') {
        return $_REQUEST[$key] ?? $_GET[$key] ?? $_POST[$key] ?? $default;
    }
}

if (!function_exists('inputInt')) {
    function inputInt($key, $default = 0) {
        return (int)(input($key, $default));
    }
}

if (!function_exists('inputArray')) {
    function inputArray($key, $default = []) {
        $value = input($key, $default);
        return is_array($value) ? $value : $default;
    }
}

// ============================================================================
// FLASH MESSAGE HELPER
// ============================================================================
if (!function_exists('setFlash')) {
    function setFlash($message, $type = 'info') {
        FlashMessage::set($message, $type);
    }
}

if (!function_exists('setSuccess')) {
    function setSuccess($message) {
        FlashMessage::set($message, 'success');
    }
}

if (!function_exists('setError')) {
    function setError($message) {
        FlashMessage::set($message, 'danger');
    }
}

// ============================================================================
// URL HELPERS
// ============================================================================
if (!function_exists('url')) {
    function url($path = '') {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('panelUrl')) {
    function panelUrl($path = '') {
        return PANEL_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        return BASE_URL . '/panel/assets/' . ltrim($path, '/');
    }
}

// ============================================================================
// VALIDATION HELPERS
// ============================================================================
if (!function_exists('isEmail')) {
    function isEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('isPhone')) {
    function isPhone($phone) {
        return preg_match('/^\+?[0-9\s\-\(\)]{8,20}$/', $phone);
    }
}

// ============================================================================
// PERMISSION HELPERS
// ============================================================================
if (!function_exists('can')) {
    function can($module, $action) {
        return Permission::can($module, $action);
    }
}

if (!function_exists('cannot')) {
    function cannot($module, $action) {
        return !Permission::can($module, $action);
    }
}
function redirectWithMessage(string $url, string $message, string $type = 'info') {
    if (!isset($_SESSION)) session_start();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: {$url}");
    exit;
}

function redirectBack(string $message = '', string $type = 'error') {
    $referer = $_SERVER['HTTP_REFERER'] ?? '/panel/dashboard.php';
    if (!empty($message)) {
        redirectWithMessage($referer, $message, $type);
    } else {
        header("Location: {$referer}");
        exit;
    }
}
// Flash messages
$flashMessage = FlashMessage::get();