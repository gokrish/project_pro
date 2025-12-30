<?php
/**
 * Common Bootstrap File - FINAL FIXED VERSION
 * This version WORKS - tested and verified
 */

require_once __DIR__ . '/../../includes/_helpers.php';

if (!defined('PANEL_ACCESS')) {
    define('PANEL_ACCESS', true);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define paths
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
define('PANEL_PATH', ROOT_PATH . '/panel');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ProConsultancy\\';
    $base_dir = INCLUDES_PATH . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
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

// Load config
require_once INCLUDES_PATH . '/config/config.php';
require_once INCLUDES_PATH . '/config/database.php';

// Load core classes
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Session;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\PermissionException;

// Initialize logger
try {
    $logger = Logger::getInstance();
} catch (Exception $e) {
    // Logger failed, continue anyway
}

// ============================================================================
// AUTHENTICATION CHECK - SIMPLIFIED & WORKING
// ============================================================================

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);

// Pages that don't require authentication
$publicPages = ['login.php', 'forgot-password.php', 'reset-password.php'];

// Only check auth if NOT on a public page
if (!in_array($currentPage, $publicPages)) {
    
    // Check if user is authenticated
    if (!Auth::check()) {
        // Store intended URL (without the redirect flag nonsense)
        if (!isset($_SESSION['intended_url'])) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        }
        
        // Redirect to login
        header('Location: /panel/login.php');
        exit;
    }
}

// Get authenticated user (will be null if not logged in)
$user = Auth::user();

// ============================================================================
// EXCEPTION HANDLER
// ============================================================================

set_exception_handler(function($exception) {
    if ($exception instanceof PermissionException) {
        http_response_code(403);
        
        // AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage()
            ]);
            exit();
        }
        
        // Regular page request
        include PANEL_PATH . '/includes/header.php';
        echo '<div class="container-xxl flex-grow-1 container-p-y">';
        echo '<div class="alert alert-danger">';
        echo '<h4 class="alert-heading"><i class="bx bx-error me-2"></i>Access Denied</h4>';
        echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
        echo '<hr>';
        echo '<p class="mb-0">Contact your administrator if you believe this is an error.</p>';
        echo '</div>';
        echo '<a href="/panel/dashboard.php" class="btn btn-primary">Go to Dashboard</a>';
        echo '</div>';
        include PANEL_PATH . '/includes/footer.php';
        exit();
    }
    
    // Other exceptions
    try {
        Logger::getInstance()->error('Unhandled exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    } catch (Exception $e) {
        // Even logging failed
    }
    
    http_response_code(500);
    echo '<h1>An error occurred</h1>';
    echo '<p>Please try again later.</p>';
    exit();
});

// ============================================================================
// GLOBAL VARIABLES
// ============================================================================

$currentUser = $user;
$userLevel = $user['level'] ?? 'guest';
$userName = $user['name'] ?? 'Guest';
$userEmail = $user['email'] ?? '';

// Flash message
try {
    $flashMessage = FlashMessage::get();
} catch (Exception $e) {
    $flashMessage = null;
}