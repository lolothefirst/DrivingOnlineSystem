<?php
// Database configuration
class Database {
    private $host = "localhost";
    private $db_name = "driving_test_system";
    private $username = "root";
    private $password = "";
    private $conn;

    // Get database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            // Don't echo errors - let calling code handle it
            throw $exception;
        }

        return $this->conn;
    }
}
?>
