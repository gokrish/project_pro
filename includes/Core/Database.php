<?php
namespace ProConsultancy\Core;

use mysqli;
use Exception;

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
        // Set logger after connection to avoid loops
        $this->logger = Logger::getInstance();
    }
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect(): void {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->connection = new mysqli($this->config['host'], $this->config['username'], $this->config['password'], $this->config['database'], (int)$this->config['port']);
        $this->connection->set_charset($this->config['charset']);
    }

    private function ensureConnection(): void {
        if ($this->connection === null) {
            $this->logger?->warning('system', 'reconnect', 'n/a', 'Database connection lost, reconnecting');
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
    public function prepare(string $query, array $params = [], string $types = ''): \mysqli_stmt {
        try {
            $this->ensureConnection();
            $stmt = $this->connection->prepare($query);
            if (!empty($params)) {
                if (empty($types)) {
                    $types = str_repeat('s', count($params)); 
                }
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            return $stmt;
        } catch (Exception $e) {
            $this->logger?->error("Database Error: " . $e->getMessage(), ['query' => $query]);
            throw $e;
        }
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