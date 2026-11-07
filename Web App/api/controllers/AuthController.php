<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * AuthController.php — User Authentication & JWT Handling
 * ─────────────────────────────────────────────────────────────────────────────
 * รองรับ:
 *   POST /api/auth/login  — ตรวจสอบผู้ใช้และออก JWT Token
 *   GET  /api/auth/me     — ตรวจสอบสถานะการล็อกอินด้วย Bearer Token
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AuthController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * POST /api/auth/login
     * รับ JSON: { "username": "admin", "password": "..." }
     */
    public function login(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $username = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');

            if ($username === '' || $password === '') {
                Response::error('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 400);
                return;
            }

            // ค้นหาผู้ใช้จากตาราง tb_users ด้วย Bind Parameter
            $sql = "SELECT i_usr_id AS id, i_usr_uname AS username, i_usr_pwd AS password 
                    FROM tb_users 
                    WHERE i_usr_uname = :uname 
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':uname', $username);
            $stmt->execute();
            $user = $stmt->fetch();

            if (!$user) {
                Response::error('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
                return;
            }

            // ตรวจสอบรหัสผ่าน (รองรับทั้ง Plain Text จากฐานข้อมูลดั้งเดิม และ password_hash)
            $passwordValid = false;
            if ($user['password'] === $password) {
                $passwordValid = true;
            } elseif (password_verify($password, $user['password'])) {
                $passwordValid = true;
            }

            if (!$passwordValid) {
                Response::error('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
                return;
            }

            // สร้าง JWT Payload
            $payload = [
                'sub'      => $user['id'],
                'username' => $user['username'],
                'role'     => ($user['username'] === 'admin') ? 'Administrator' : 'Staff',
                'name'     => ($user['username'] === 'admin') ? 'วชิรา ล. (Admin)' : ucfirst($user['username']),
            ];

            $token = JWT::encode($payload);

            Response::success([
                'token' => $token,
                'user'  => [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'role'     => $payload['role'],
                    'name'     => $payload['name']
                ]
            ], 'เข้าสู่ระบบสำเร็จ');

        } catch (PDOException $e) {
            Response::error('เกิดข้อผิดพลาดในการตรวจสอบสิทธิ์: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/auth/me
     * ตรวจสอบความถูกต้องของ JWT Token ที่ส่งมาใน Header
     */
    public function me(): void
    {
        $token = JWT::getBearerToken();
        if (!$token) {
            Response::unauthorized('ไม่พบ Token สำหรับยืนยันตัวตน');
            return;
        }

        $payload = JWT::decode($token);
        if (!$payload) {
            Response::unauthorized('Token ไม่ถูกต้อง หรือหมดอายุแล้ว');
            return;
        }

        Response::success([
            'id'       => $payload['sub'],
            'username' => $payload['username'],
            'role'     => $payload['role'] ?? 'User',
            'name'     => $payload['name'] ?? $payload['username'],
        ], 'Token ถูกต้อง');
    }
}
