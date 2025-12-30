<?php
namespace ProConsultancy\Core;

use Exception;

/**
 * Authentication Class
 * Handles user authentication and authorization
 * 
 * @version 5.0
 */
class Auth {
    private static ?array $user = null;
    
    /**
     * Attempt login
     * Supports login with email OR user_code
     * Handles both hashed passwords (bcrypt) and plain text passwords (legacy)
     * 
     * @param string $identifier Email or user_code
     * @param string $password Plain text password
     * @param bool $remember Enable remember me
     * @return bool
     */
    public static function attempt(string $identifier, string $password, bool $remember = false): bool {
        $identifier = trim($identifier);
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // 1. Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE (email = ? OR user_code = ?) AND is_active = 1 LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user) return false;

        // 2. Smart Password Check
        // If password starts with $2y$, it's Bcrypt. Otherwise, it's plain text.
        $isBcrypt = (strpos($user['password'], '$2y$') === 0);
        
        if ($isBcrypt) {
            $valid = password_verify($password, $user['password']);
        } else {
            // Plain text check
            $valid = ($password === $user['password']);
            
            // UPGRADE to bcrypt automatically if plain text was correct
            if ($valid) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $newHash, $user['id']);
                $upd->execute();
                $user['password'] = $newHash; // Update memory
            }
        }

        if (!$valid) return false;

        // 3. Finalize
        self::login($user, $remember);
        return true;
    }
    /**
     * Login user (set session)
     */
    private static function login(array $user, bool $remember = false): void {
        Session::start();
        // Session::regenerate();
        
        // Set session data
        Session::set('user_id', $user['id']);
        Session::set('user_code', $user['user_code']);
        Session::set('user_name', $user['name']);
        Session::set('user_email', $user['email']);
        Session::set('user_level', $user['level']);
        Session::set('logged_in', true);
        
        // Remember me functionality
        if ($remember) {
            self::createRememberToken($user['id']);
        }
        
        // Log activity
        Logger::getInstance()->logActivity(
            'login',
            'auth',
            $user['user_code'],
            'User logged in successfully'
        );
    }
    
    /**
     * Check if user is authenticated
     */
    public static function check(): bool {
        Session::start();
        
        if (Session::get('logged_in')) {
            return true;
        }
        
        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            return self::loginFromRememberToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    /**
     * Get current user
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        
        if (self::$user !== null) {
            return self::$user;
        }
        
        // Get fresh user data from database
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $userId = Session::get('user_id');
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            self::$user = $result->fetch_assoc();
            
            return self::$user;
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to get user data', [
                'user_id' => Session::get('user_id'),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get user ID
     */
    public static function id(): ?int {
        return Session::get('user_id');
    }
    
    /**
     * Get user code
     */
    public static function userCode(): ?string {
        return Session::get('user_code');
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole(string $role): bool {
        $userLevel = Session::get('user_level');
        return $userLevel === $role;
    }
    
    /**
     * Check if user has any of the roles
     */
    public static function hasAnyRole(array $roles): bool {
        $userLevel = Session::get('user_level');
        return in_array($userLevel, $roles);
    }
    
    /**
     * Logout user
     */
    public static function logout(): void {
        $userCode = Session::get('user_code');
        
        // Remove remember token
        if (isset($_COOKIE['remember_token'])) {
            self::deleteRememberToken($_COOKIE['remember_token']);
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Log activity
        Logger::getInstance()->logActivity(
            'logout',
            'auth',
            $userCode,
            'User logged out'
        );
        
        // Destroy session
        Session::destroy();
        self::$user = null;
    }
    
    /**
     * Increment failed login attempts
     */
    private static function incrementFailedAttempts(int $userId): void {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Lock account after 5 failed attempts
            $stmt = $conn->prepare("SELECT failed_login_attempts FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['failed_login_attempts'] >= 5) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $stmt = $conn->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
                $stmt->bind_param("si", $lockUntil, $userId);
                $stmt->execute();
                
                Logger::getInstance()->logActivity(
                    'login_failed',
                    'auth',
                    $userId,
                    'Login failed: account locked',
                    [
                        'locked_until' => $userId,
                        'remaining_minutes' => $lockUntil
                    ],
                    'warning'
                );

            }
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to increment login attempts', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create remember token
     */
    private static function createRememberToken(int $userId): void {
        try {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("
                INSERT INTO user_sessions (user_code, session_token, ip_address, user_agent, expires_at)
                SELECT user_code, ?, ?, ?, ?
                FROM users WHERE id = ?
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt->bind_param("ssssi", $token, $ip, $userAgent, $expiresAt, $userId);
            $stmt->execute();
            
            // Set cookie
            setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to create remember token', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Login from remember token
     */
    private static function loginFromRememberToken(string $token): bool {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("
                SELECT u.* 
                FROM users u
                INNER JOIN user_sessions s ON s.user_code = u.user_code
                WHERE s.session_token = ? 
                AND s.expires_at > NOW()
                AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user) {
                self::login($user, true);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to login from remember token', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete remember token
     */
    private static function deleteRememberToken(string $token): void {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to delete remember token', [
                'error' => $e->getMessage()
            ]);
        }
    }
}