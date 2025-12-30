<?php
namespace ProConsultancy\Core;

/**
 * Session Management Class
 * Handles secure session management
 * 
 * @version 5.0
 */
class Session {
    private static bool $started = false;
    
    /**
     * Start session securely
     */
    public static function start(): void {
        if (self::$started) {
            return;
        }
        
        $config = require __DIR__ . '/../../config/app.php';
        
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
        
        session_name($config['session_name']);
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > $config['session_lifetime'])) {
            self::destroy();
            return;
        }
        
        $_SESSION['last_activity'] = time();
        self::$started = true;
    }
    
    /**
     * Set session value
     */
    public static function set(string $key, $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session value
     */
    public static function get(string $key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session key exists
     */
    public static function has(string $key): bool {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     */
    public static function remove(string $key): void {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session
     */
    public static function destroy(): void {
        self::start();
        $_SESSION = [];
        session_destroy();
        self::$started = false;
    }
    
    /**
     * Regenerate session ID
     */
    public static function regenerate(): void {
        self::start();
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}