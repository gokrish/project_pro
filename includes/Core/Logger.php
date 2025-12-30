<?php
namespace ProConsultancy\Core;

class Logger {
    private static $instance = null;
    private $db = null;
    
    private function getDb(): ?Database {
        if ($this->db === null) {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                error_log("Logger DB unavailable: " . $e->getMessage());
                return null;
            }
        }
        return $this->db;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Smart argument parser to support both:
     * 1. Activity: ->info('users', 'login', '123', 'User logged in')
     * 2. System:   ->info('PHP Error: message', ['file' => '...'])
     */
    private function parseArgs(string $val1, $val2, array $rest): array {
        if (is_array($val2)) {
            return [
                'module' => $val2['module'] ?? 'system',
                'action' => $val2['action'] ?? 'log',
                'recordId' => $val2['record_id'] ?? 'n/a',
                'description' => $val1,
                'details' => $val2
            ];
        }
        return [
            'module' => $val1,
            'action' => (string)$val2,
            'recordId' => $rest[0] ?? 'n/a',
            'description' => $rest[1] ?? '',
            'details' => $rest[2] ?? []
        ];
    }

    public function info(string $arg1, $arg2 = null, ...$rest) {
        $p = $this->parseArgs($arg1, $arg2, $rest);
        $this->logActivity($p['action'], $p['module'], $p['recordId'], $p['description'], $p['details'], 'info');
    }

    public function warning(string $arg1, $arg2 = null, ...$rest) {
        $p = $this->parseArgs($arg1, $arg2, $rest);
        $this->logActivity($p['action'], $p['module'], $p['recordId'], $p['description'], $p['details'], 'warning');
    }

    public function error(string $message, array $context = []) {
        $this->logActivity($context['action'] ?? 'error', $context['module'] ?? 'system', $context['record_id'] ?? 'n/a', $message, $context, 'error');
    }

    public function critical(string $message, array $context = []): void {
        $this->logActivity('critical', $context['module'] ?? 'system', $context['record_id'] ?? 'n/a', $message, $context, 'critical');
    }

       public function logActivity(string $action, string $module, $recordId, string $description, array $details = [], string $level = 'info') {
    // Flag to prevent recursion
    static $isLogging = false;
    if ($isLogging) return;
    $isLogging = true;

    try {
        $db = $this->getDb();
        if (!$db) {
            $isLogging = false;
            return;
        }
        $conn = $db->getConnection();
        
        // Use session directly to avoid Auth::check() recursion
        $userId = $_SESSION['user_id'] ?? null;
        
        $detailsJson = !empty($details) ? json_encode($details) : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, details, level, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("issssssss", $userId, $action, $module, $recordId, $description, $detailsJson, $level, $ip, $ua);
            $stmt->execute();
            $stmt->close();
        }
    } catch (\Throwable $e) {
        error_log("Logging failed: " . $e->getMessage());
    } finally {
        $isLogging = false;
    }
}
}