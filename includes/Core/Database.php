<?php
namespace ProConsultancy\Core;

use mysqli;
use Exception;

/**
 * Database Class - Singleton Pattern
 * Handles MySQL connection with lazy Logger initialization
 * 
 * @version 5.0
 */
class Database {
    private static ?Database $instance = null;
    private ?mysqli $connection = null;
    private array $config;
    private ?Logger $logger = null;
    private bool $debugMode;
    
    private function __construct() {
        $this->config = require __DIR__ . '/../config/database.php';
        $appConfig = require __DIR__ . '/../config/app.php';
        $this->debugMode = $appConfig['app_debug'] ?? false;
        $this->connect();
        
        // NOTE: Logger is initialized lazily to avoid circular dependency
        // Database → Logger → Database (circular!)
        // So we only get Logger when we actually need it
    }
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get logger instance lazily (only when needed)
     */
    private function getLogger(): ?Logger {
        if ($this->logger === null) {
            try {
                // Only initialize logger after Database is fully constructed
                $this->logger = Logger::getInstance();
            } catch (\Throwable $e) {
                // Logger not available - fail silently
                error_log("Logger initialization failed: " . $e->getMessage());
                return null;
            }
        }
        return $this->logger;
    }
    
    private function connect(): void {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            $this->connection = new mysqli(
                $this->config['host'], 
                $this->config['username'], 
                $this->config['password'], 
                $this->config['database'], 
                (int)$this->config['port']
            );
            
            $this->connection->set_charset($this->config['charset']);
            
        } catch (Exception $e) {
            // Don't use Logger here - we're in constructor!
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    private function ensureConnection(): void {
        if ($this->connection === null ) {
            $logger = $this->getLogger();
            if ($logger) {
                $logger->warning('system', 'reconnect', 'n/a', 'Database connection lost, reconnecting');
            } else {
                error_log("Database connection lost, reconnecting");
            }
            $this->connect();
        }
    }

    public function getConnection(): mysqli {
        $this->ensureConnection();
        return $this->connection;
    }
    
    public function beginTransaction(): void {
        $this->getConnection()->begin_transaction();
    }

    public function commit(): void {
        $this->getConnection()->commit();
    }

    public function rollback(): void {
        $this->getConnection()->rollback();
    }
    
    /**
     * Prepare and execute statement with automatic parameter binding
     * 
     * @param string $query SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @param string $types Type string (s=string, i=int, d=double, b=blob) - auto-detected if empty
     * @return \mysqli_stmt Executed statement
     */
    public function prepare(string $query, array $params = [], string $types = ''): \mysqli_stmt {
        try {
            $this->ensureConnection();
            $stmt = $this->connection->prepare($query);
            
            if ($stmt === false) {
                throw new Exception("Failed to prepare statement: " . $this->connection->error);
            }
            
            if (!empty($params)) {
                // Auto-detect types if not provided
                if (empty($types)) {
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param) || is_double($param)) {
                            $types .= 'd';
                        } elseif (is_string($param)) {
                            $types .= 's';
                        } else {
                            $types .= 's'; // Default to string
                        }
                    }
                }
                
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            return $stmt;
            
        } catch (Exception $e) {
            // Try to log error, but don't fail if logger unavailable
            $logger = $this->getLogger();
            if ($logger) {
                $logger->error("Database Error: " . $e->getMessage(), [
                    'query' => $query,
                    'params' => $params
                ]);
            } else {
                error_log("Database Error: " . $e->getMessage() . " | Query: " . $query);
            }
            throw $e;
        }
    }
    
    /**
     * Execute a simple query without parameters
     */
    public function query(string $query) {
        $this->ensureConnection();
        return $this->connection->query($query);
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId(): int {
        return $this->connection->insert_id;
    }
    
    /**
     * Get affected rows
     */
    public function affectedRows(): int {
        return $this->connection->affected_rows;
    }
    
    /**
     * Escape string for SQL
     */
    public function escape(string $value): string {
        $this->ensureConnection();
        return $this->connection->real_escape_string($value);
    }

    public function close(): void {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    public function __destruct() {
        $this->close();
    }
}