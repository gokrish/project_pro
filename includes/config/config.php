<?php
/**
 * Main Configuration File
 * Loads and consolidates all configuration settings
 * 
 * @package ProConsultancy
 * @version 2.0
 */

// Prevent direct access
if (!defined('PANEL_ACCESS')) {
    die('Direct access not permitted');
}

// Load sub-configuration files
$appConfig = require __DIR__ . '/app.php';
$dbConfig = require __DIR__ . '/database.php';

// ============================================================================
// 🔧 DEVELOPMENT MODE (Define FIRST - before anything else)
// ============================================================================
define('DEV_MODE', $appConfig['dev_mode']);
define('DEV_USER_CODE', $appConfig['dev_user_code']);
define('DEV_AUTO_LOGIN', $appConfig['dev_auto_login']);
define('DEV_SHOW_BANNER', $appConfig['dev_show_banner']);
define('DEV_TOKEN_EXPIRY_DAYS', $appConfig['dev_token_expiry_days']);

// ============================================================================
// BASE URL CONFIGURATION
// ============================================================================
define('BASE_URL', $appConfig['app_url']);
define('PANEL_URL', BASE_URL . '/panel');
define('API_URL', BASE_URL . '/api');

// ============================================================================
// ENVIRONMENT CONFIGURATION
// ============================================================================
define('APP_ENV', $appConfig['app_env']); // production, staging, development
define('APP_DEBUG', $appConfig['app_debug']);
define('APP_NAME', $appConfig['app_name']);
define('APP_VERSION', $appConfig['app_version']);

// ============================================================================
// PATH CONFIGURATION
// ============================================================================
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('RESUME_PATH', UPLOAD_PATH . '/resumes');
define('DOCUMENT_PATH', UPLOAD_PATH . '/documents');
define('PHOTO_PATH', UPLOAD_PATH . '/photos');
define('CV_INBOX_PATH', UPLOAD_PATH . '/cv_inbox');
define('LOG_PATH', ROOT_PATH . '/logs');
define('TEMP_PATH', ROOT_PATH . '/temp');

// ============================================================================
// DATABASE CONFIGURATION (from database.php)
// ============================================================================
define('DB_HOST', $dbConfig['host']);
define('DB_PORT', $dbConfig['port']);
define('DB_NAME', $dbConfig['database']);
define('DB_USER', $dbConfig['username']);
define('DB_PASS', $dbConfig['password']);
define('DB_CHARSET', $dbConfig['charset']);
define('DB_COLLATION', $dbConfig['collation']);

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================
define('SESSION_LIFETIME', $appConfig['session_lifetime']); // 30 minutes
define('SESSION_NAME', $appConfig['session_name']);
define('SESSION_SECURE', APP_ENV === 'production'); // HTTPS only in production
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Lax');

// ============================================================================
// TOKEN CONFIGURATION (for authentication)
// ============================================================================
define('TOKEN_EXPIRY_HOURS', $appConfig['token_expiry_hours']);
define('TOKEN_REMEMBER_DAYS', $appConfig['token_remember_days']);
define('TOKEN_CLEANUP_ENABLED', $appConfig['token_cleanup_enabled']);

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================
define('CSRF_TOKEN_NAME', $appConfig['csrf_token_name']);
define('PASSWORD_MIN_LENGTH', $appConfig['password_min_length']);
define('PASSWORD_REQUIRE_SPECIAL', $appConfig['password_require_special']);
define('PASSWORD_REQUIRE_NUMBER', $appConfig['password_require_number']);
define('PASSWORD_REQUIRE_UPPERCASE', $appConfig['password_require_uppercase']);
define('MAX_LOGIN_ATTEMPTS', $appConfig['max_login_attempts']);
define('ACCOUNT_LOCK_DURATION', $appConfig['account_lock_duration']); // minutes

// ============================================================================
// FILE UPLOAD CONFIGURATION
// ============================================================================
define('UPLOAD_MAX_SIZE', $appConfig['upload_max_size']); // 5MB in bytes
define('UPLOAD_MAX_SIZE_MB', UPLOAD_MAX_SIZE / 1048576); // For display
define('UPLOAD_ALLOWED_TYPES', $appConfig['upload_allowed_types']);
define('UPLOAD_ALLOWED_IMAGES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_ALLOWED_DOCUMENTS', ['pdf', 'doc', 'docx', 'txt', 'rtf']);

// ============================================================================
// PAGINATION CONFIGURATION
// ============================================================================
define('ITEMS_PER_PAGE', $appConfig['items_per_page']); // 25
define('MAX_PAGINATION_LINKS', 7); // Number of page links to show

// ============================================================================
// EMAIL CONFIGURATION
// ============================================================================
define('MAIL_FROM_EMAIL', $appConfig['from_email']);
define('MAIL_FROM_NAME', $appConfig['from_name']);

// ============================================================================
// LOGGING CONFIGURATION
// ============================================================================
define('LOG_LEVEL', $appConfig['log_level']); // debug, info, warning, error
define('LOG_MAX_FILES', 30); // Keep logs for 30 days
define('LOG_FILENAME_FORMAT', 'Y-m-d'); // One log file per day

// ============================================================================
// FEATURE FLAGS
// ============================================================================
define('FEATURE_RESUME_PARSING', $appConfig['enable_resume_parsing']);
define('FEATURE_EMAIL_NOTIFICATIONS', $appConfig['enable_email_notifications']);
define('FEATURE_ACTIVITY_LOG', $appConfig['enable_activity_log']);
define('FEATURE_TWO_FACTOR_AUTH', false); // Future feature
define('FEATURE_API_ACCESS', false); // Future feature

// ============================================================================
// DATE & TIME CONFIGURATION
// ============================================================================
define('APP_TIMEZONE', $appConfig['app_timezone']);
define('DATE_FORMAT', 'd/m/Y'); // European format
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('TIME_FORMAT', 'H:i');
define('MYSQL_DATE_FORMAT', 'Y-m-d');
define('MYSQL_DATETIME_FORMAT', 'Y-m-d H:i:s');

// ============================================================================
// BUSINESS LOGIC CONSTANTS
// ============================================================================
define('DEFAULT_NOTICE_PERIOD_DAYS', 30);
define('DEFAULT_CURRENCY', 'EUR');
define('SALARY_RANGE_MIN', 0);
define('SALARY_RANGE_MAX', 999999999);

// ============================================================================
// ERROR HANDLING CONFIGURATION
// ============================================================================
if (APP_ENV === 'production') {
    // Production: Hide errors, log everything
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php-errors.log');
} elseif (APP_ENV === 'staging') {
    // Staging: Show errors to developers only
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php-errors.log');
} else {
    // Development: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php-errors.log');
}

// ============================================================================
// TIMEZONE SETUP
// ============================================================================
date_default_timezone_set(APP_TIMEZONE);

// ============================================================================
// DIRECTORY CREATION (ensure required directories exist)
// ============================================================================
$requiredDirs = [
    UPLOAD_PATH,
    RESUME_PATH,
    DOCUMENT_PATH,
    PHOTO_PATH,
    CV_INBOX_PATH,
    LOG_PATH,
    TEMP_PATH
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.cookie_httponly', SESSION_HTTPONLY);
    ini_set('session.cookie_secure', SESSION_SECURE);
    ini_set('session.cookie_samesite', SESSION_SAMESITE);
    ini_set('session.name', SESSION_NAME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

// ============================================================================
// COMPOSER AUTOLOADER (if using composer)
// ============================================================================
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

// Load constants file if exists
if (file_exists(__DIR__ . '/constants.php')) {
    require_once __DIR__ . '/constants.php';
}