<?php
namespace ProConsultancy\Core;

use Exception;

/**
 * Authentication Class - ULTRA SIMPLIFIED VERSION
 * This WILL work - stripped to bare essentials
 * 
 * @version 6.0 - BULLETPROOF
 */
class Auth {
    
    /**
     * Attempt login
     */
    public static function attempt(string $identifier, string $password, bool $remember = false): bool {
        try {
            $identifier = trim($identifier);
            
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Find user
            $stmt = $conn->prepare("
                SELECT id, user_code, name, email, level, password
                FROM users 
                WHERE (email = ? OR user_code = ?) 
                AND is_active = 1 
                LIMIT 1
            ");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if (!$user) {
                return false;
            }

            // Verify password
            $isBcrypt = (strpos($user['password'], '$2y$') === 0);
            
            if ($isBcrypt) {
                $valid = password_verify($password, $user['password']);
            } else {
                $valid = ($password === $user['password']);
                
                // Auto-upgrade to bcrypt
                if ($valid) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upd->bind_param("si", $newHash, $user['id']);
                    $upd->execute();
                }
            }

            if (!$valid) {
                return false;
            }

            // Set session - SIMPLE & DIRECT
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Set ALL session data at once
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_code'] = $user['user_code'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_level'] = $user['level'];
            
            // Force session write
            session_write_close();
            session_start();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Auth attempt failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'user_code' => $_SESSION['user_code'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'level' => $_SESSION['user_level'] ?? null,
        ];
    }
    
    /**
     * Get user ID
     */
    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get user code
     */
    public static function userCode(): ?string {
        return $_SESSION['user_code'] ?? null;
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole(string $role): bool {
        $userLevel = $_SESSION['user_level'] ?? null;
        return $userLevel === $role;
    }
    
    /**
     * Check if user has any of the roles
     */
    public static function hasAnyRole(array $roles): bool {
        $userLevel = $_SESSION['user_level'] ?? null;
        return in_array($userLevel, $roles);
    }
    
    /**
     * Logout user
     */
    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
}