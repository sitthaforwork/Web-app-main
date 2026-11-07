<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * connDB.php — Database Connection Module (PDO - OOP Style)
 * ─────────────────────────────────────────────────────────────────────────────
 * ออกแบบตามข้อกำหนดที่ 1 ของโครงงาน:
 * "Database Connection: PDO แบบ OOP Style (เชื่อมต่อและจัดการฐานข้อมูลด้วย PDO Class)"
 * ตัวอย่าง Code ที่ถูกต้อง:
 *   $db = new Database();
 *   $conn = $db->getConnection();
 * ─────────────────────────────────────────────────────────────────────────────
 */

class Database
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $dbname;
    private string $charset = 'utf8mb4';
    private ?PDO $conn = null;

    public function __construct()
    {
        // 1. ดึงการตั้งค่าจาก Environment Variables (รองรับ Railway, VPS, Docker, Localhost)
        $this->host     = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
$this->port     = (int)(getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: 3306));
$this->username = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
$this->password = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: '');
$this->dbname   = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'railway');

        // Fallback เพิ่มเติมกรณีรัน Localhost XAMPP / MAMP ทั่วไป
        if ($this->host === 'localhost' || $this->host === '127.0.0.1') {
            $this->port     = (int)(getenv('DB_PORT') ?: 3306);
            $this->username = getenv('DB_USER') ?: 'root';
            $this->password = getenv('DB_PASS') ?: '';
            $this->dbname   = getenv('DB_NAME') ?: 'northwind';
        }
    }

    /**
     * คืนค่า PDO Connection Instance (Singleton ภายในคลาส)
     */
    public function getConnection(): PDO
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // ป้องกัน SQL Injection ระดับ Native PDO
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;

        } catch (PDOException $e) {
            // กรณีเชื่อมต่อไม่ได้ ส่ง HTTP 500 พร้อมข้อความ JSON
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'message' => 'Database connection error: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// สร้าง Instance และตัวแปร $conn เผื่อโค้ดส่วนอื่นเรียกใช้แบบตรง
$db = new Database();
$conn = $db->getConnection();
