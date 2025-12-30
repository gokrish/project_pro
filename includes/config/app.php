<?php
/**
 * Application Configuration
 * @version 5.0
 */

return [
    // Application
    'app_name' => 'ProConsultancy',
    'app_version' => '5.0.0',
    'app_env' => 'development', // production, staging, development
    'app_debug' => true, // Set to false in production
    'app_url' => 'http://localhost/proconsultancy',
    'app_timezone' => 'Europe/Brussels',
    
    // Security
    'session_lifetime' => 1800, // 30 minutes
    'session_name' => 'PROCONSULTANCY_SESSION',
    'csrf_token_name' => 'csrf_token',
    
    // Uploads
    'upload_max_size' => 5242880, // 5MB in bytes
    'upload_allowed_types' => ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'],
    'upload_path' => __DIR__ . '/../uploads/',
    
    // Pagination
    'items_per_page' => 25,
    
    // Password
    'password_min_length' => 8,
    'password_require_special' => true,
    'password_require_number' => true,
    'password_require_uppercase' => true,
    
    // Email
    'from_email' => 'noreply@proconsultancy.be',
    'from_name' => 'ProConsultancy',

    // SMTP Configuration
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',

    // Email Queue
    'mail_use_queue' => false,

    // API Configuration
    'api_cors_enabled' => false,
    'api_cors_origin' => '*',
    // Logging
    'log_level' => 'debug', // debug, info, warning, error
    'log_path' => __DIR__ . '/../storage/logs/',
    
    // Features
    'enable_resume_parsing' => true,
    'enable_email_notifications' => true,
    'enable_activity_log' => true,
];