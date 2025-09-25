<?php
// Database connection configuration for OptiCrew WMS
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // --- CHANGED: Default credentials updated for a standard XAMPP setup ---
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'opticrew_wms'; // CHANGED: Database name to match yours
        $this->username = $_ENV['DB_USER'] ?? 'root';         // CHANGED: Default XAMPP username
        $this->password = $_ENV['DB_PASS'] ?? '';             // Standard XAMPP has no password
        $this->port = $_ENV['DB_PORT'] ?? '3306';             // CHANGED: Default MySQL port
    }

    public function getConnection() {
        $this->conn = null;
        try {
            // --- CHANGED: DSN updated from 'pgsql' to 'mysql' ---
            // The port is usually not needed if it's the default 3306, but we'll leave it out for simplicity.
            $dsn = "mysql:host={$this->host};dbname={$this->db_name}";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Global database connection function
function getDB() {
    $database = new Database();
    return $database->getConnection();
}
?>