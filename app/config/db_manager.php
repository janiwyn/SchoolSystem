<?php
/**
 * Database Connection Manager
 * Simulates connection pooling
 */

class DatabaseManager {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        // Private constructor to prevent direct instantiation
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        if ($this->connection === null || !$this->connection->ping()) {
            // Reconnect if connection is lost
            require __DIR__ . '/db.php';
            $this->connection = $mysqli;
        }
        return $this->connection;
    }
    
    public function closeConnection() {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }
}
?>
