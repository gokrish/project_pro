<?php
namespace ProConsultancy\Core;

use mysqli;
use Exception;
use ProConsultancy\Core\Logger;
/**
 * Database Singleton Class
 * Manages database connections with error handling
 * 
 * @version 5.1
 */
class Database {
    private static ?Database $instance = null;
    private ?mysqli $connection = null;
    private array $config;
    private int $queryCount = 0;
    private array $queryLog = [];
    private Logger $logger;
    private bool $debugMode;
    
    /**
     * Private constructor - Singleton pattern
     */
    private function __construct() {
        $this->config = require __DIR__ . '/../../config/database.php';
        $appConfig = require __DIR__ . '/../../config/app.php';
        $this->debugMode = $appConfig['app_debug'] ?? false;
        $this->logger = Logger::getInstance();
        $this->connect();
    }
    
    /**
     * Get singleton instance
     * 
     * @return Database
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     * 
     * @throws Exception
     */
    private function connect(): void {
        try {
            // Use info level for connection attempts
            $this->logger->info('system', 'database_connection_attempt', 'n/a', 'Attempting database connection', [
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'port' => $this->config['port']
            ]);
            
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            $this->connection = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database'],
                (int)$this->config['port']
            );
            
            $this->connection->set_charset($this->config['charset']);
            
            // Set timezone
            $appConfig = require __DIR__ . '/../../config/app.php';
            $this->connection->query("SET time_zone = '{$appConfig['app_timezone']}'");
            
            $this->logger->info('system', 'database_connection_success', 'n/a', 'Database connected successfully', [
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'charset' => $this->config['charset']
            ]);
            
        } catch (Exception $e) {
            $this->logger->critical('system', 'database_connection', 'n/a', 'Database connection failed', [
                'error' => $e->getMessage(),
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'port' => $this->config['port']
            ]);
            
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if connection is alive and reconnect if needed
     */
    private function ensureConnection(): void {
        if ($this->connection === null || !$this->connection->ping()) {
            $this->logger->warning('system', 'database_reconnect', 'n/a', 'Database connection lost, attempting reconnect');
            $this->connect();
        }
    }
    
    /**
     * Get database connection
     * 
     * @return mysqli
     * @throws Exception
     */
    public function getConnection(): mysqli {
        $this->ensureConnection();
        return $this->connection;
    }
    
    /**
     * Execute prepared statement
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @param string $types Parameter types (s=string, i=int, d=double, b=blob)
     * @return mysqli_stmt
     * @throws Exception
     */
    public function prepare(string $query, array $params = [], string $types = ''): mysqli_stmt {
        try {
            $this->ensureConnection();
            $this->queryCount++;
            $startTime = microtime(true);
            
            // Log query preparation at info level instead of debug
            if ($this->debugMode) {
                $this->logger->info('system', 'database_prepare', 'n/a', 'Preparing database query', [
                    'query' => substr($query, 0, 200) . (strlen($query) > 200 ? '...' : ''),
                    'param_count' => count($params)
                ]);
            }
            
            $stmt = $this->connection->prepare($query);
            
            if ($stmt === false) {
                throw new Exception('Prepare failed: ' . $this->connection->error);
            }
            
            if (!empty($params)) {
                // Auto-detect types if not provided
                if (empty($types)) {
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                    }
                }
                
                $stmt->bind_param($types, ...$params);
                
                if ($this->debugMode) {
                    $this->logger->info('system', 'database_bind_params', 'n/a', 'Binding parameters to prepared statement', [
                        'types' => $types,
                        'params' => array_map(function($param) {
                            return is_scalar($param) ? (string)$param : gettype($param);
                        }, $params)
                    ]);
                }
            }
            
            $result = $stmt->execute();
            $executionTime = microtime(true) - $startTime;
            
            if ($result === false) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            // Log query performance - use warning for slow queries, info for normal
            $logData = [
                'query' => $this->debugMode ? substr($query, 0, 200) . (strlen($query) > 200 ? '...' : '') : 'query_executed',
                'time' => round($executionTime, 4),
                'affected_rows' => $stmt->affected_rows,
                'query_count' => $this->queryCount
            ];
            
            if ($executionTime > 1.0) {
                $this->logger->warning('system', 'database_slow_query', 'n/a', 'Slow query detected', $logData);
            } elseif ($this->debugMode) {
                $this->logger->info('system', 'database_query_executed', 'n/a', 'Query executed successfully', $logData);
            }
            
            // Store in query log for debug mode
            if ($this->debugMode) {
                $this->queryLog[] = [
                    'query' => $query,
                    'params' => $params,
                    'time' => $executionTime,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            
            return $stmt;
            
        } catch (Exception $e) {
            $this->logger->error('Database query execution failed', [
                'module' => 'system',
                'record_id' => 'n/a',
                'query' => substr($query, 0, 200) . (strlen($query) > 200 ? '...' : ''),
                'params' => $params,
                'error' => $e->getMessage(),
                'error_code' => $this->connection->errno ?? 0
            ]);
            
            throw new Exception('Database query failed: ' . $e->getMessage() . ' (Error: ' . ($this->connection->errno ?? 0) . ')');
        }
    }
    
    /**
     * Execute raw query (use with caution)
     * 
     * @param string $query
     * @return mysqli_result|bool
     * @throws Exception
     */
    public function query(string $query) {
        try {
            $this->ensureConnection();
            $this->queryCount++;
            $startTime = microtime(true);
            
            if ($this->debugMode) {
                $this->logger->info('system', 'database_raw_query', 'n/a', 'Executing raw database query', [
                    'query' => substr($query, 0, 200) . (strlen($query) > 200 ? '...' : '')
                ]);
            }
            
            $result = $this->connection->query($query);
            $executionTime = microtime(true) - $startTime;
            
            if ($result === false) {
                throw new Exception('Query failed: ' . $this->connection->error);
            }
            
            if ($this->debugMode) {
                $this->logger->info('system', 'database_raw_query_executed', 'n/a', 'Raw query executed successfully', [
                    'query' => substr($query, 0, 200) . (strlen($query) > 200 ? '...' : ''),
                    'time' => round($executionTime, 4),
                    'affected_rows' => $this->connection->affected_rows
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Raw database query failed', [
                'module' => 'system',
                'record_id' => 'n/a',
                'query' => substr($query, 0, 200) . (strlen($query) > 200 ? '...' : ''),
                'error' => $e->getMessage(),
                'error_code' => $this->connection->errno ?? 0
            ]);
            
            throw new Exception('Database raw query failed: ' . $e->getMessage() . ' (Error: ' . ($this->connection->errno ?? 0) . ')');
        }
    }
    
    /**
     * Execute prepared statement (alias for prepare with automatic fetching)
     * 
     * @param string $query
     * @param array $params
     * @return mysqli_stmt
     * @throws Exception
     */
    public function execute(string $query, array $params = []): mysqli_stmt {
        return $this->prepare($query, $params);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): void {
        try {
            $this->ensureConnection();
            $this->connection->begin_transaction();
            $this->logger->info('system', 'database_transaction_start', 'n/a', 'Transaction started');
        } catch (Exception $e) {
            $this->logger->error('Failed to begin transaction', [
                'module' => 'system',
                'record_id' => 'n/a',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Commit transaction
     */
    public function commit(): void {
        try {
            $this->ensureConnection();
            $this->connection->commit();
            $this->logger->info('system', 'database_transaction_commit', 'n/a', 'Transaction committed');
        } catch (Exception $e) {
            $this->logger->error('Failed to commit transaction', [
                'module' => 'system',
                'record_id' => 'n/a',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): void {
        try {
            $this->ensureConnection();
            $this->connection->rollback();
            $this->logger->info('system', 'database_transaction_rollback', 'n/a', 'Transaction rolled back');
        } catch (Exception $e) {
            $this->logger->error('Failed to rollback transaction', [
                'module' => 'system',
                'record_id' => 'n/a',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get last insert ID
     * 
     * @return int
     */
    public function lastInsertId(): int {
        $id = $this->connection->insert_id;
        if ($this->debugMode) {
            $this->logger->info('system', 'database_last_insert_id', 'n/a', 'Retrieved last insert ID', [
                'insert_id' => $id
            ]);
        }
        return $id;
    }
    
    /**
     * Get affected rows
     * 
     * @return int
     */
    public function affectedRows(): int {
        $rows = $this->connection->affected_rows;
        if ($this->debugMode) {
            $this->logger->info('system', 'database_affected_rows', 'n/a', 'Retrieved affected rows', [
                'affected_rows' => $rows
            ]);
        }
        return $rows;
    }
    
    /**
     * Escape string
     * 
     * @param string $value
     * @return string
     */
    public function escape(string $value): string {
        $escaped = $this->connection->real_escape_string($value);
        if ($this->debugMode) {
            $this->logger->info('system', 'database_escape', 'n/a', 'String escaped', [
                'original_length' => strlen($value),
                'escaped_length' => strlen($escaped)
            ]);
        }
        return $escaped;
    }
    
    /**
     * Get query count
     * 
     * @return int
     */
    public function getQueryCount(): int {
        return $this->queryCount;
    }
    
    /**
     * Get query log
     * 
     * @return array
     */
    public function getQueryLog(): array {
        return $this->queryLog;
    }
    
    /**
     * Get database statistics
     * 
     * @return array
     */
    public function getStats(): array {
        return [
            'query_count' => $this->queryCount,
            'query_log_size' => count($this->queryLog),
            'connected' => $this->connection !== null && $this->connection->ping(),
            'debug_mode' => $this->debugMode
        ];
    }
    
    /**
     * Close connection
     */
    public function close(): void {
        if ($this->connection !== null) {
            $this->logger->info('system', 'database_disconnect', 'n/a', 'Closing database connection', [
                'total_queries' => $this->queryCount
            ]);
            $this->connection->close();
            $this->connection = null;
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Close connection on destruct
     */
    public function __destruct() {
        $this->close();
    }
}