// CREATE: /includes/config/development.php

<?php
if ($_ENV['APP_ENV'] === 'development') {
    // Error display
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Debugging
    define('DEBUG_MODE', true);
    define('DEBUG_QUERIES', true);
    define('DEBUG_EMAILS', true); // Log emails instead of sending
    
    // Development tools
    define('SHOW_QUERY_STATS', true);
    define('SHOW_MEMORY_USAGE', true);
    define('ENABLE_PROFILER', true);
    
    // Relaxed security for dev
    define('CSRF_STRICT_MODE', false); // For testing
    define('RATE_LIMIT_ENABLED', false);
    
    // Database
    define('DB_DEBUG_LOG', true);
    
} else {
    // Production settings
    ini_set('display_errors', 0);
    error_reporting(0);
    define('DEBUG_MODE', false);
    define('DEBUG_QUERIES', false);
    define('DEBUG_EMAILS', false);
}

