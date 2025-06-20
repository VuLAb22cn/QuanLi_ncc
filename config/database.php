<?php
/**
 * Cấu hình kết nối database
 */
class Database {
    private $host = 'localhost';
    private $db_name = 'quanli_ncc';
    private $username = 'root';
    private $password = 'LE19052004vu@';
    private $charset = 'utf8mb4';
    public $conn;

    /**
     * Kết nối database
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            die("Lỗi kết nối database: " . $exception->getMessage());
        }

        return $this->conn;
    }
}

/**
 * Hàm tiện ích để lấy kết nối database
 */
function getDB() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database->getConnection();
}

// Cấu hình kết nối database
define('DB_HOST', 'localhost');
define('DB_NAME', 'quanli_ncc');
define('DB_USER', 'root');
define('DB_PASS', '');
?>
