<?php
namespace ProConsultancy\Core;

/**
 * CSRF Token Protection
 * Prevents Cross-Site Request Forgery attacks
 * 
 * @version 2.0
 */
class CSRFToken {
    /**
     * Generate CSRF token
     */
    public static function generate(): string {
        Session::start();
        
        if (!Session::has('csrf_token')) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }
        
        return Session::get('csrf_token');
    }
    
    /**
     * Get current token
     */
    public static function get(): string {
        return self::generate();
    }
    
    /**
     * Verify token
     */
    public static function verify(string $token): bool {
        $sessionToken = Session::get('csrf_token');
        
        if (!$sessionToken || !$token) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Verify from request
     */
    public static function verifyRequest(): bool {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        return self::verify($token);
    }
    
    /**
     * Get HTML input field
     */
    public static function field(): string {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Regenerate token
     */
    public static function regenerate(): string {
        Session::remove('csrf_token');
        return self::generate();
    }
}