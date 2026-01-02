<?php
namespace ProConsultancy\Core;

use Throwable;

/**
 * Global Error Handler
 * Handles all PHP errors and exceptions
 * 
 * @version 5.0
 */
class ErrorHandler {
    private static bool $registered = false;
    private static array $config;
    
    /**
     * Register error handlers
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }
        
        self::$config = require __DIR__ . '/../config/app.php';
        
        // Set error reporting
        if (self::$config['app_debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
        }
        
        // Register handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$registered = true;
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): bool {
        // Don't handle suppressed errors (@)
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $logger = Logger::getInstance();
        
        $errorType = match($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'error',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
            default => 'debug'
        };
        
        $logger->$errorType("PHP Error: {$errstr}", [
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ]);
        
        // Show error page in production
        if (!self::$config['app_debug'] && in_array($errno, [E_ERROR, E_USER_ERROR])) {
            self::showErrorPage('An error occurred', 500);
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException(Throwable $exception): void {
        $logger = Logger::getInstance();
        
        $logger->critical('Uncaught exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Show error page
        if (self::$config['app_debug']) {
            self::showDebugError($exception);
        } else {
            self::showErrorPage('An unexpected error occurred', 500);
        }
        
        exit(1);
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $logger = Logger::getInstance();
            
            $logger->critical('Fatal error', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
            
            if (!self::$config['app_debug']) {
                self::showErrorPage('A fatal error occurred', 500);
            }
        }
    }
    
    /**
     * Show error page (production)
     */
    private static function showErrorPage(string $message, int $code): void {
        http_response_code($code);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error - <?= self::$config['app_name'] ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #2d3748;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 12px;
                    padding: 40px;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                }
                h1 {
                    font-size: 72px;
                    margin: 0;
                    color: #667eea;
                }
                h2 {
                    font-size: 24px;
                    margin: 10px 0 20px;
                    color: #2d3748;
                }
                p {
                    color: #718096;
                    line-height: 1.6;
                }
                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 30px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    transition: background 0.3s;
                }
                a:hover {
                    background: #5568d3;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1><?= $code ?></h1>
                <h2><?= htmlspecialchars($message) ?></h2>
                <p>We're sorry, but something went wrong. Our team has been notified and is working to fix the issue.</p>
                <a href="<?= self::$config['app_url'] ?>">Return to Home</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Show debug error (development)
     */
    private static function showDebugError(Throwable $exception): void {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error - <?= self::$config['app_name'] ?></title>
            <style>
                body {
                    font-family: 'Courier New', monospace;
                    background: #1a202c;
                    color: #e2e8f0;
                    padding: 20px;
                    margin: 0;
                }
                .error-header {
                    background: #f56565;
                    color: white;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .error-header h1 {
                    margin: 0 0 10px 0;
                    font-size: 24px;
                }
                .error-message {
                    font-size: 18px;
                    margin: 0;
                }
                .error-details {
                    background: #2d3748;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .error-details h2 {
                    margin-top: 0;
                    color: #48bb78;
                }
                .trace {
                    background: #1a202c;
                    padding: 15px;
                    border-left: 3px solid #667eea;
                    overflow-x: auto;
                }
                pre {
                    margin: 0;
                    color: #a0aec0;
                }
                .file {
                    color: #fbbf24;
                }
                .line {
                    color: #48bb78;
                }
            </style>
        </head>
        <body>
            <div class="error-header">
                <h1><?= get_class($exception) ?></h1>
                <p class="error-message"><?= htmlspecialchars($exception->getMessage()) ?></p>
            </div>
            
            <div class="error-details">
                <h2>Error Location</h2>
                <p>
                    <span class="file"><?= htmlspecialchars($exception->getFile()) ?></span>
                    <span class="line">:<?= $exception->getLine() ?></span>
                </p>
            </div>
            
            <div class="error-details">
                <h2>Stack Trace</h2>
                <div class="trace">
                    <pre><?= htmlspecialchars($exception->getTraceAsString()) ?></pre>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

// Auto-register on include
ErrorHandler::register();