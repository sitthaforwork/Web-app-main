<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * EmployeeController.php — Employees Full CRUD Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * หน้าจอที่ 10: พนักงาน (Employees · CRUD, การ์ดแสดงผล, วันเกิด, ตำแหน่ง)
 * ─────────────────────────────────────────────────────────────────────────────
 */

class EmployeeController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/employees — ดึงพนักงานทั้งหมด */
    public function index(): void
    {
        try {
            $sql = "SELECT i_EmployeeID AS id,
                           c_FirstName AS firstName,
                           c_LastName  AS lastName,
                           CONCAT(c_FirstName, ' ', c_LastName) AS fullName,
                           c_BirthDate AS birthDate,
                           c_Photo     AS photo,
                           c_Notes     AS notes
                    FROM tb_employees
                    ORDER BY i_EmployeeID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $employees = $stmt->fetchAll();

            // เสริม Title และ Avatar Initials
            $titles = [
                1 => 'พนักงานขาย (Sales Representative)',
                2 => 'ผู้จัดการฝ่ายขาย (Vice President, Sales)',
                3 => 'พนักงานขาย (Sales Representative)',
                4 => 'พนักงานขาย (Sales Representative)',
                5 => 'ผู้จัดการฝ่ายขาย (Sales Manager)',
                6 => 'พนักงานขาย (Sales Representative)',
                7 => 'พนักงานขาย (Sales Representative)',
                8 => 'ผู้ประสานงานขาย (Inside Sales Coordinator)',
                9 => 'พนักงานขาย (Sales Representative)',
                10=> 'ที่ปรึกษาพิเศษ (Special Advisor)'
            ];

            foreach ($employees as &$emp) {
                $emp['title'] = $titles[$emp['id']] ?? 'พนักงานขาย';
                $emp['initials'] = strtoupper(substr($emp['firstName'], 0, 1) . substr($emp['lastName'], 0, 1));
            }

            Response::success($employees);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลพนักงานได้: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/employees/{id} — ดึงพนักงานรายคน */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT i_EmployeeID AS id,
                           c_FirstName AS firstName,
                           c_LastName  AS lastName,
                           CONCAT(c_FirstName, ' ', c_LastName) AS fullName,
                           c_BirthDate AS birthDate,
                           c_Photo     AS photo,
                           c_Notes     AS notes
                    FROM tb_employees
                    WHERE i_EmployeeID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $emp = $stmt->fetch();
            if (!$emp) {
                Response::notFound('ไม่พบข้อมูลพนักงานรหัสนี้');
                return;
            }

            Response::success($emp);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลพนักงานได้: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/employees — เพิ่มพนักงานใหม่ */
    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $firstName = trim($data['firstName'] ?? '');
            $lastName  = trim($data['lastName'] ?? '');
            $birthDate = trim($data['birthDate'] ?? '');
            $notes     = trim($data['notes'] ?? '');

            if ($firstName === '' || $lastName === '') {
                Response::validationError(['กรุณากรอกชื่อและนามสกุลพนักงาน']);
                return;
            }

            $stmtMax = $this->conn->query("SELECT MAX(i_EmployeeID) AS maxId FROM tb_employees");
            $nextId = (int)($stmtMax->fetch()['maxId'] ?? 0) + 1;
            $photo = "EmpID{$nextId}.pic";

            $sql = "INSERT INTO tb_employees (i_EmployeeID, c_FirstName, c_LastName, c_BirthDate, c_Photo, c_Notes)
                    VALUES (:id, :fname, :lname, :bdate, :photo, :notes)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id'    => $nextId,
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':bdate' => $birthDate ?: date('Y-m-d'),
                ':photo' => $photo,
                ':notes' => $notes
            ]);

            Response::success(['id' => $nextId], 'เพิ่มข้อมูลพนักงานเรียบร้อยแล้ว', 201);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถเพิ่มข้อมูลพนักงานได้: ' . $e->getMessage(), 500);
        }
    }

    /** PUT /api/employees/{id} — แก้ไขข้อมูลพนักงาน */
    public function update(string $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $firstName = trim($data['firstName'] ?? '');
            $lastName  = trim($data['lastName'] ?? '');
            $birthDate = trim($data['birthDate'] ?? '');
            $notes     = trim($data['notes'] ?? '');

            if ($firstName === '' || $lastName === '') {
                Response::validationError(['กรุณากรอกชื่อและนามสกุลพนักงาน']);
                return;
            }

            $sql = "UPDATE tb_employees SET
                        c_FirstName = :fname,
                        c_LastName  = :lname,
                        c_BirthDate = :bdate,
                        c_Notes     = :notes
                    WHERE i_EmployeeID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':bdate' => $birthDate,
                ':notes' => $notes,
                ':id'    => (int)$id
            ]);

            Response::success(['id' => (int)$id], 'แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขข้อมูลพนักงานได้: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE /api/employees/{id} — ลบพนักงาน */
    public function destroy(string $id): void
    {
        try {
            $sql = "DELETE FROM tb_employees WHERE i_EmployeeID = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            Response::success(null, 'ลบข้อมูลพนักงานเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถลบข้อมูลพนักงานได้: ' . $e->getMessage(), 500);
        }
    }
}
