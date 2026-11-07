<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * CategoryController.php — Categories Full CRUD Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * หน้าจอที่ 05: หมวดหมู่สินค้า (CRUD · Categories)
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CategoryController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/categories — ดึงหมวดหมู่ทั้งหมดพร้อมจำนวนสินค้าในแต่ละหมวด */
    public function index(): void
    {
        try {
            $sql = "SELECT c.i_CategoryID AS id, 
                           c.c_CategoryName AS name, 
                           c.c_Description AS description,
                           COUNT(p.i_ProductID) AS productCount
                    FROM tb_categories c
                    LEFT JOIN tb_products p ON c.i_CategoryID = p.i_CategoryID
                    GROUP BY c.i_CategoryID, c.c_CategoryName, c.c_Description
                    ORDER BY c.i_CategoryID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            Response::success($stmt->fetchAll());
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลหมวดหมู่ได้: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/categories/{id} — ดึงหมวดหมู่รายตัว */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT i_CategoryID AS id, c_CategoryName AS name, c_Description AS description
                    FROM tb_categories
                    WHERE i_CategoryID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $category = $stmt->fetch();
            if (!$category) {
                Response::notFound('ไม่พบหมวดหมู่รหัสนี้');
                return;
            }

            Response::success($category);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลหมวดหมู่ได้: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/categories — เพิ่มหมวดหมู่ใหม่ */
    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $name = trim($data['name'] ?? '');
            $desc = trim($data['description'] ?? '');

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อหมวดหมู่']);
                return;
            }

            // หา ID ถัดไป (เนื่องจากตารางเดิมใน Northwind ไม่ได้เป็น AUTO_INCREMENT)
            $stmtMax = $this->conn->query("SELECT MAX(i_CategoryID) AS maxId FROM tb_categories");
            $nextId = (int)($stmtMax->fetch()['maxId'] ?? 0) + 1;

            $sql = "INSERT INTO tb_categories (i_CategoryID, c_CategoryName, c_Description) 
                    VALUES (:id, :name, :desc)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id'   => $nextId,
                ':name' => $name,
                ':desc' => $desc
            ]);

            Response::success(['id' => $nextId], 'เพิ่มหมวดหมู่สินค้าเรียบร้อยแล้ว', 201);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถเพิ่มหมวดหมู่ได้: ' . $e->getMessage(), 500);
        }
    }

    /** PUT /api/categories/{id} — แก้ไขข้อมูลหมวดหมู่ */
    public function update(string $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $name = trim($data['name'] ?? '');
            $desc = trim($data['description'] ?? '');

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อหมวดหมู่']);
                return;
            }

            $sql = "UPDATE tb_categories 
                    SET c_CategoryName = :name, c_Description = :desc 
                    WHERE i_CategoryID = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':desc' => $desc,
                ':id'   => (int)$id
            ]);

            Response::success(['id' => (int)$id], 'แก้ไขหมวดหมู่สินค้าเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขหมวดหมู่ได้: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE /api/categories/{id} — ลบหมวดหมู่ */
    public function destroy(string $id): void
    {
        try {
            $sql = "DELETE FROM tb_categories WHERE i_CategoryID = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            Response::success(null, 'ลบหมวดหมู่สินค้าเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถลบหมวดหมู่ได้: ' . $e->getMessage(), 500);
        }
    }
}
