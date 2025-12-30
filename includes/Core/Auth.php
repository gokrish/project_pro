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
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Try to find user by email OR user_code
            $stmt = $conn->prepare("
                SELECT * FROM users 
                WHERE (email = ? OR user_code = ?) AND is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            // Check if user exists
            if (!$user) {
                Logger::getInstance()->logActivity(
                    'login_failed',
                    'auth',
                    null,
                    'Login failed: user not found',
                    [
                        'identifier' => $identifier,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ],
                    'warning'
                );

                return false;
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $remainingTime = ceil((strtotime($user['locked_until']) - time()) / 60);
                
                Logger::getInstance()->logActivity(
                    'login_failed',
                    'auth',
                    $user['user_code'],
                    'Login failed: account locked',
                    [
                        'locked_until' => $user['locked_until'],
                        'remaining_minutes' => $remainingTime
                    ],
                    'warning'
                );

                
                // Set flash message for user feedback
                Session::set('login_error', "Account is locked. Please try again in {$remainingTime} minutes.");
                return false;
            }
            
            // Verify password - Handle both hashed and plain text
            $passwordValid = false;
            $needsRehash = false;
            
            // First, try bcrypt verification (hashed password)
            if (password_verify($password, $user['password'])) {
                $passwordValid = true;
                
                // Check if password needs rehashing (for updated bcrypt cost)
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $needsRehash = true;
                }
            } 
            // Fallback: Check plain text password (legacy support)
            elseif ($password === $user['password']) {
                $passwordValid = true;
                $needsRehash = true; // Always rehash plain text passwords
                
                Logger::getInstance()->logActivity(
                    'security_warning',
                    'auth',
                    $user['user_code'],
                    'Plain text password detected – upgrading',
                    ['email' => $user['email']],
                    'warning'
                );

            }
            
            // Password validation failed
            if (!$passwordValid) {
                // Increment failed attempts
                self::incrementFailedAttempts($user['id']);
                
                Logger::getInstance()->logActivity(
                    'login_failed',
                    'auth',
                    $user['user_code'],
                    'Login failed: invalid password',
                    [
                        'failed_attempts' => $user['failed_login_attempts'] + 1,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ],
                    'warning'
                );

                
                // Set flash message
                $remainingAttempts = 5 - ($user['failed_login_attempts'] + 1);
                if ($remainingAttempts > 0) {
                    Session::set('login_error', "Invalid credentials. {$remainingAttempts} attempts remaining before account lock.");
                } else {
                    Session::set('login_error', "Invalid credentials. Account will be locked.");
                }
                
                return false;
            }
            
            // Login successful - perform post-login tasks
            $db->beginTransaction();
            
            try {
                // If password needs rehashing, update it now
                if ($needsRehash) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $hashedPassword, $user['id']);
                    $updateStmt->execute();
                    
                    Logger::getInstance()->logActivity(
                        'password_upgrade',
                        'auth',
                        $user['user_code'],
                        'Password upgraded to bcrypt'
                    );

                }
                
                // Reset failed attempts and update last login
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET failed_login_attempts = 0, 
                        locked_until = NULL,
                        last_login = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                
                // Update user data with fresh password if rehashed
                if ($needsRehash) {
                    $user['password'] = $hashedPassword;
                }
                
                // Commit transaction
                $db->commit();
                
                // Set user session
                self::login($user, $remember);
                
                Logger::getInstance()->logActivity(
                    'login',
                    'auth',
                    $user['user_code'],
                    'User logged in successfully',
                    [
                        'login_method' => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_code',
                        'remember_me' => $remember
                    ]
                );

                
                // Clear any login errors
                Session::remove('login_error');
                
                return true;
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Login exception', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Session::set('login_error', 'An error occurred during login. Please try again.');
            return false;
        }
    }
    
    /**
     * Login user (set session)
     */
    private static function login(array $user, bool $remember = false): void {
        Session::start();
        Session::regenerate();
        
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