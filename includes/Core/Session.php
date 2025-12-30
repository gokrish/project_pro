<?php
namespace ProConsultancy\Core;

class Session {
    private static bool $started = false;
    
    public static function start(): void {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        self::$started = true;
    }

    // --- ADD THIS METHOD ---
    /**
     * Check if a key exists in the session
     */
    public static function has(string $key): bool {
        self::start();
        return isset($_SESSION[$key]);
    }
    // -----------------------

    public static function set(string $key, $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get(string $key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function remove(string $key): void {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}