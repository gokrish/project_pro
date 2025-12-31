<?php
namespace ProConsultancy\Core;

use Exception;

/**
 * Authentication Class - TOKEN-BASED
 * Uses tokens table for persistent authentication
 * Supports DEV_MODE for testing
 * 
 * @version 2.0
 */
class Auth {
    private static ?array $user = null;
    private static bool $checkedAuth = false;
    private static ?string $currentToken = null;
    
    /**
     * Attempt login
     */
    public static function attempt(string $identifier, string $password, bool $remember = false): bool {
        try {
            $identifier = trim($identifier);
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Find user by email or user_code
            $stmt = $conn->prepare("
                SELECT * FROM users 
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

            // Verify password (supports both bcrypt and plain text)
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

            // Create token and login
            return self::createTokenAndLogin($user, $remember);
            
        } catch (Exception $e) {
            error_log("Auth::attempt error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create token and set session
     */
    private static function createTokenAndLogin(array $user, bool $remember = false): bool {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime($remember ? '+30 days' : '+24 hours'));
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Insert token into database
            $stmt = $conn->prepare("
                INSERT INTO tokens (user_code, token, expires_at, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sssss", $user['user_code'], $token, $expiresAt, $ipAddress, $userAgent);
            
            if (!$stmt->execute()) {
                error_log("Failed to create token: " . $conn->error);
                return false;
            }
            
            // Start session if needed
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['auth_token'] = $token;
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_code'] = $user['user_code'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_level'] = $user['level'];
            $_SESSION['login_time'] = time();
            
            // Cache user
            self::$user = $user;
            self::$currentToken = $token;
            self::$checkedAuth = true;
            
            // Set cookie if remember me
            if ($remember) {
                setcookie('auth_token', $token, strtotime($expiresAt), '/', '', false, true);
            }
            
            // Update last login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            return true;
            
        } catch (Exception $e) {
            error_log("createTokenAndLogin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is authenticated
     * Validates token in database on each request
     */
    public static function check(): bool {
        // Return cached result if already checked
        if (self::$checkedAuth) {
            return self::$user !== null;
        }
        
        try {
            // Start session if needed
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Get token from session or cookie
            $token = $_SESSION['auth_token'] ?? $_COOKIE['auth_token'] ?? null;
            
            if (!$token) {
                self::$checkedAuth = true;
                return false;
            }
            
            // Validate token in database
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("
                SELECT u.*, t.expires_at, t.token
                FROM users u
                INNER JOIN tokens t ON u.user_code = t.user_code
                WHERE t.token = ? 
                AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            if (!$data) {
                self::$checkedAuth = true;
                return false;
            }
            
            // Check if token expired
            if (strtotime($data['expires_at']) < time()) {
                // Token expired - delete it
                $stmt = $conn->prepare("DELETE FROM tokens WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                
                self::$checkedAuth = true;
                return false;
            }
            
            // Valid token - refresh session variables
            $_SESSION['auth_token'] = $data['token'];
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $data['id'];
            $_SESSION['user_code'] = $data['user_code'];
            $_SESSION['user_name'] = $data['name'];
            $_SESSION['user_email'] = $data['email'];
            $_SESSION['user_level'] = $data['level'];
            
            // Cache user
            self::$user = [
                'id' => $data['id'],
                'user_code' => $data['user_code'],
                'name' => $data['name'],
                'email' => $data['email'],
                'level' => $data['level'],
            ];
            self::$currentToken = $data['token'];
            self::$checkedAuth = true;
            
            return true;
            
        } catch (Exception $e) {
            error_log("Auth::check error: " . $e->getMessage());
            self::$checkedAuth = true;
            return false;
        }
    }

    /**
     * Get current user
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        
        return self::$user;
    }
    
    /**
     * Get user ID
     */
    public static function id(): ?int {
        return self::$user['id'] ?? null;
    }
    
    /**
     * Get user code
     */
    public static function userCode(): ?string {
        return self::$user['user_code'] ?? null;
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole(string $role): bool {
        $user = self::user();
        return $user && $user['level'] === $role;
    }
    
    /**
     * Check if user has any of the roles
     */
    public static function hasAnyRole(array $roles): bool {
        $user = self::user();
        return $user && in_array($user['level'], $roles);
    }
    
    /**
     * Logout user
     */
    public static function logout(): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Get token
            $token = $_SESSION['auth_token'] ?? $_COOKIE['auth_token'] ?? null;
            
            if ($token) {
                // Delete token from database
                $db = Database::getInstance();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("DELETE FROM tokens WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
            }
            
            // Clear session
            $_SESSION = [];
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Clear auth cookie
            if (isset($_COOKIE['auth_token'])) {
                setcookie('auth_token', '', time() - 3600, '/', '', false, true);
            }
            
            // Destroy session
            session_destroy();
            
            // Reset static variables
            self::$user = null;
            self::$currentToken = null;
            self::$checkedAuth = false;
            
        } catch (Exception $e) {
            error_log("Auth::logout error: " . $e->getMessage());
        }
    }
    
    /**
     * Change password
     */
    public static function changePassword(string $userCode, string $oldPassword, string $newPassword): array {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Get current user
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_code = ?");
            $stmt->bind_param('s', $userCode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows !== 1) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            $user = $result->fetch_assoc();
            
            // Verify old password
            $isBcrypt = (strpos($user['password'], '$2y$') === 0);
            $passwordValid = $isBcrypt 
                ? password_verify($oldPassword, $user['password'])
                : ($oldPassword === $user['password']);
            
            if (!$passwordValid) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Hash new password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_code = ?");
            $stmt->bind_param('ss', $newHash, $userCode);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update password'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }
}