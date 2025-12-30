<?php
namespace ProConsultancy\Core;

class Logger {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        $this->db = Database::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log activity to database
     * 
     * @param string $action Action type (create, update, delete, view, etc.)
     * @param string $module Module name (candidates, jobs, users, etc.)
     * @param string $recordId Related record identifier
     * @param string $description Human-readable description
     * @param array $details Additional metadata (old_value, new_value, etc.)
     * @param string $level Severity level (info, warning, error, critical)
     */
    public function logActivity(
        string $action,
        string $module,
        $recordId,
        string $description,
        array $details = [],
        string $level = 'info'
    ) {
        try {
            $conn = $this->db->getConnection();
            
            // Get user info
            $user = Auth::check() ? Auth::user() : null;
            $userCode = $user['user_code'] ?? null;
            $userName = $user['name'] ?? null;
            
            // Get request context
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $requestUri = $_SERVER['REQUEST_URI'] ?? null;
            
            // Prepare details JSON
            $detailsJson = !empty($details) ? json_encode($details) : null;
            
            // Insert log
            $stmt = $conn->prepare("
                INSERT INTO activity_log (
                    user_code,
                    user_name,
                    module,
                    action,
                    record_id,
                    description,
                    details,
                    level,
                    ip_address,
                    user_agent,
                    request_method,
                    request_uri,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param(
                "ssssssssssss",
                $userCode,
                $userName,
                $module,
                $action,
                $recordId,
                $description,
                $detailsJson,
                $level,
                $ipAddress,
                $userAgent,
                $requestMethod,
                $requestUri
            );
            
            $stmt->execute();
            
        } catch (\Exception $e) {
            // Don't throw - logging should never break the app
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    /**
     * Log info level activity
     */
    public function info(string $module, string $action, string $recordId, string $description, array $details = []) {
        $this->logActivity($action, $module, $recordId, $description, $details, 'info');
    }
    
    /**
     * Log warning level activity
     */
    public function warning(string $module, string $action, string $recordId, string $description, array $details = []) {
        $this->logActivity($action, $module, $recordId, $description, $details, 'warning');
    }
    
    /**
     * Log error
     */
    public function error(string $message, array $context = []) {
        $this->logActivity(
            'error',
            $context['module'] ?? 'system',
            $context['record_id'] ?? null,
            $message,
            $context,
            'error'
        );
    }
    
    /**
     * Log critical error
     */
    public function critical(string $module, string $action, string $recordId, string $description, array $details = []) {
        $this->logActivity($action, $module, $recordId, $description, $details, 'critical');
    }
}