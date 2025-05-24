<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

class Database {
    private $host = "localhost";
    private $db_name = "your_DB";
    private $username = "your_user";
    private $password = "your_password";
    public $conn;

    // เชื่อมต่อฐานข้อมูล
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
    
    // ฟังก์ชั่นสำหรับป้องกัน SQL Injection
    public function sanitize($data) {
        return htmlspecialchars(strip_tags($data));
    }
}
?>