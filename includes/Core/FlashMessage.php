<?php
namespace ProConsultancy\Core;

/**
 * Flash Message Handler
 * Handles session-based flash messages for user feedback
 * 
 * Usage:
 *   FlashMessage::success('Record created successfully!');
 *   FlashMessage::error('Invalid email address');
 *   $message = FlashMessage::get(); // Returns and clears message
 * 
 * @version 5.0
 * @package ProConsultancy\Core
 */
class FlashMessage {
    
    /**
     * Session key for flash messages
     */
    private const SESSION_KEY = 'flash_message';
    
    /**
     * Set flash message
     * 
     * @param string $type Message type (success, error, warning, info)
     * @param string $message Message text
     * @return void
     */
    public static function set(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION[self::SESSION_KEY] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];
    }
    
    /**
     * Set success message
     * 
     * @param string $message Success message
     * @return void
     */
    public static function success(string $message): void {
        self::set('success', $message);
    }
    
    /**
     * Set error message
     * 
     * @param string $message Error message
     * @return void
     */
    public static function error(string $message): void {
        self::set('error', $message);
    }
    
    /**
     * Set warning message
     * 
     * @param string $message Warning message
     * @return void
     */
    public static function warning(string $message): void {
        self::set('warning', $message);
    }
    
    /**
     * Set info message
     * 
     * @param string $message Info message
     * @return void
     */
    public static function info(string $message): void {
        self::set('info', $message);
    }
    
    /**
     * Get flash message and clear it from session
     * 
     * @return array|null Message array with 'type' and 'message' keys, or null if no message
     */
    public static function get(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION[self::SESSION_KEY])) {
            $message = $_SESSION[self::SESSION_KEY];
            unset($_SESSION[self::SESSION_KEY]);
            return $message;
        }
        
        return null;
    }
    
    /**
     * Check if flash message exists
     * 
     * @return bool True if message exists, false otherwise
     */
    public static function has(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION[self::SESSION_KEY]);
    }
    
    /**
     * Clear flash message without retrieving it
     * 
     * @return void
     */
    public static function clear(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION[self::SESSION_KEY])) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }
    
    /**
     * Get message type only (without clearing)
     * 
     * @return string|null Message type or null
     */
    public static function getType(): ?string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION[self::SESSION_KEY]['type'] ?? null;
    }
    
    /**
     * Get message text only (without clearing)
     * 
     * @return string|null Message text or null
     */
    public static function getMessage(): ?string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION[self::SESSION_KEY]['message'] ?? null;
    }
    
    /**
     * Render flash message as HTML
     * Used in flash-messages.php include file
     * 
     * @return string HTML markup or empty string
     */
    public static function render(): string {
        $message = self::get();
        
        if (!$message) {
            return '';
        }
        
        $alertClass = match($message['type']) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-secondary'
        };
        
        $icon = match($message['type']) {
            'success' => 'bx-check-circle',
            'error' => 'bx-error',
            'warning' => 'bx-error-circle',
            'info' => 'bx-info-circle',
            default => 'bx-bell'
        };
        
        return sprintf(
            '<div class="alert %s alert-dismissible fade show" role="alert">
                <i class="bx %s me-2"></i>
                %s
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>',
            htmlspecialchars($alertClass),
            htmlspecialchars($icon),
            htmlspecialchars($message['message'])
        );
    }
}