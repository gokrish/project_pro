<?php
/**
 * Application Configuration
 * 
 * @package ProConsultancy
 * @version 2.0
 */

return [
    // ============================================================================
    // APPLICATION SETTINGS
    // ============================================================================
    'app_name' => 'ProConsultancy',
    'app_version' => '5.0.0',
    'app_env' => 'development', 
    'app_debug' => true,
    'app_url' => 'http://proconsultancy.test',
    'app_timezone' => 'Europe/Brussels',
    
    // ============================================================================
    // DEVELOPMENT MODE (for testing without login)
    // ============================================================================
    'dev_mode' => true,                    // Set to FALSE in production!
    'dev_user_code' => 'ADMIN',           // User code to auto-login as
    'dev_auto_login' => true,             // Automatically create token & login
    'dev_show_banner' => true,            // Show dev mode banner
    'dev_token_expiry_days' => 365,       // Dev token valid for 1 year
    
    // ============================================================================
    // SESSION SETTINGS
    // ============================================================================
    'session_lifetime' => 1800, // 30 minutes (in seconds)
    'session_name' => 'proconsultancy_session',
    
    // ============================================================================
    // SECURITY SETTINGS
    // ============================================================================
    'csrf_token_name' => 'csrf_token',
    'password_min_length' => 6,
    'password_require_special' => false,
    'password_require_number' => true,
    'password_require_uppercase' => true,
    'max_login_attempts' => 5,
    'account_lock_duration' => 30, // minutes
    
    // ============================================================================
    // TOKEN SETTINGS (for authentication)
    // ============================================================================
    'token_expiry_hours' => 24,           // Normal token expires in 24 hours
    'token_remember_days' => 30,          // Remember me token expires in 30 days
    'token_cleanup_enabled' => true,      // Auto-cleanup expired tokens
    
    // ============================================================================
    // FILE UPLOAD SETTINGS
    // ============================================================================
    'upload_max_size' => 5242880, // 5MB in bytes
    'upload_allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
    
    // ============================================================================
    // PAGINATION SETTINGS
    // ============================================================================
    'items_per_page' => 25,
    
    // ============================================================================
    // EMAIL SETTINGS
    // ============================================================================
    'from_email' => 'noreply@proconsultancy.com',
    'from_name' => 'ProConsultancy',
    
    // ============================================================================
    // LOGGING SETTINGS
    // ============================================================================
    'log_level' => 'info', // debug, info, warning, error
    
    // ============================================================================
    // FEATURE FLAGS
    // ============================================================================
    'enable_resume_parsing' => false,
    'enable_email_notifications' => false,
    'enable_activity_log' => true,
];