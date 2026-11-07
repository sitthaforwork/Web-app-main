<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * ProductController.php — Products Full CRUD Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * หน้าจอที่ 03: รายการสินค้า (ค้นหา, กรองหมวดหมู่, กรองผู้จัดจำหน่าย, แบ่งหน้า)
 * หน้าจอที่ 04: เพิ่ม/แก้ไขสินค้า (Validation กฎ Workshop, ตรวจสอบราคา > 10,000)
 * ─────────────────────────────────────────────────────────────────────────────
 */

class ProductController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** 
     * GET /api/products — ดึงรายการสินค้าทั้งหมด + ค้นหา + ตัวกรอง
     */
    public function index(): void
    {
        try {
            $search     = trim($_GET['search'] ?? '');
            $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
            $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;

            $sql = "SELECT p.i_ProductID    AS id,
                           p.c_ProductName  AS productName,
                           p.i_SupplierID   AS supplierId,
                           p.i_CategoryID   AS categoryId,
                           p.c_Unit         AS unit,
                           p.i_Price        AS price,
                           c.c_CategoryName AS categoryName,
                           s.c_SupplierName AS supplierName
                    FROM tb_products p
                    LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                    LEFT JOIN tb_suppliers  s ON p.i_SupplierID = s.i_SupplierID
                    WHERE 1=1";

            $params = [];

            if ($search !== '') {
                $sql .= " AND (p.c_ProductName LIKE :search 
                               OR c.c_CategoryName LIKE :searchCat 
                               OR s.c_SupplierName LIKE :searchSup)";
                $params[':search']    = "%{$search}%";
                $params[':searchCat'] = "%{$search}%";
                $params[':searchSup'] = "%{$search}%";
            }

            if ($categoryId !== null && $categoryId > 0) {
                $sql .= " AND p.i_CategoryID = :catId";
                $params[':catId'] = $categoryId;
            }

            if ($supplierId !== null && $supplierId > 0) {
                $sql .= " AND p.i_SupplierID = :supId";
                $params[':supId'] = $supplierId;
            }

            $sql .= " ORDER BY p.i_ProductID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();

            // คำนวณสถานะอัตโนมัติ (เช่น ราคา > 10,000 -> "ตรวจสอบ", ทั่วไป -> "ใช้งานอยู่")
            foreach ($products as &$p) {
                $p['price'] = (float)$p['price'];
                if ($p['price'] >= 10000) {
                    $p['status'] = 'ตรวจสอบ';
                } elseif ($p['price'] <= 0) {
                    $p['status'] = 'ไม่ใช้งาน';
                } else {
                    $p['status'] = 'ใช้งานอยู่';
                }
            }

            Response::success($products, 'ดึงข้อมูลสินค้าสำเร็จ', 200, ['total' => count($products)]);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * GET /api/products/{id} — ดึงข้อมูลสินค้ารายตัว
     */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT p.i_ProductID    AS id,
                           p.c_ProductName  AS productName,
                           p.i_SupplierID   AS supplierId,
                           p.i_CategoryID   AS categoryId,
                           p.c_Unit         AS unit,
                           p.i_Price        AS price,
                           c.c_CategoryName AS categoryName,
                           s.c_SupplierName AS supplierName
                    FROM tb_products p
                    LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                    LEFT JOIN tb_suppliers  s ON p.i_SupplierID = s.i_SupplierID
                    WHERE p.i_ProductID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $product = $stmt->fetch();
            if (!$product) {
                Response::notFound('ไม่พบสินค้ารหัสนี้ในระบบ');
                return;
            }

            $product['price'] = (float)$product['price'];
            $product['status'] = ($product['price'] >= 10000) ? 'ตรวจสอบ' : 'ใช้งานอยู่';

            Response::success($product);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * POST /api/products — เพิ่มข้อมูลสินค้าใหม่
     */
    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                Response::error('ข้อมูลไม่ถูกต้อง หรือรูปแบบ JSON ไม่ถูกต้อง', 400);
                return;
            }

            $errors = $this->validate($data);
            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $sql = "INSERT INTO tb_products (c_ProductName, i_SupplierID, i_CategoryID, c_Unit, i_Price)
                    VALUES (:productName, :supplierId, :categoryId, :unit, :price)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':productName' => trim($data['productName']),
                ':supplierId'  => (int) $data['supplierId'],
                ':categoryId'  => (int) $data['categoryId'],
                ':unit'        => trim($data['unit'] ?? ''),
                ':price'       => (float) $data['price'],
            ]);

            $newId = (int)$this->conn->lastInsertId();

            Response::success(['id' => $newId], 'เพิ่มข้อมูลสินค้าเรียบร้อยแล้ว', 201);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถเพิ่มข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * PUT /api/products/{id} — แก้ไขข้อมูลสินค้า
     */
    public function update(string $id): void
    {
        try {
            $check = $this->conn->prepare("SELECT i_ProductID FROM tb_products WHERE i_ProductID = :id");
            $check->bindParam(':id', $id, PDO::PARAM_INT);
            $check->execute();
            if (!$check->fetch()) {
                Response::notFound('ไม่พบสินค้ารหัสนี้ในระบบ');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                Response::error('ข้อมูลไม่ถูกต้อง หรือรูปแบบ JSON ไม่ถูกต้อง', 400);
                return;
            }

            $errors = $this->validate($data);
            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $sql = "UPDATE tb_products SET
                        c_ProductName = :productName,
                        i_SupplierID  = :supplierId,
                        i_CategoryID  = :categoryId,
                        c_Unit        = :unit,
                        i_Price       = :price
                    WHERE i_ProductID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':productName' => trim($data['productName']),
                ':supplierId'  => (int) $data['supplierId'],
                ':categoryId'  => (int) $data['categoryId'],
                ':unit'        => trim($data['unit'] ?? ''),
                ':price'       => (float) $data['price'],
                ':id'          => (int) $id,
            ]);

            Response::success(['id' => (int)$id], 'แก้ไขข้อมูลสินค้าเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * DELETE /api/products/{id} — ลบข้อมูลสินค้า
     */
    public function destroy(string $id): void
    {
        try {
            $check = $this->conn->prepare("SELECT i_ProductID FROM tb_products WHERE i_ProductID = :id");
            $check->bindParam(':id', $id, PDO::PARAM_INT);
            $check->execute();
            if (!$check->fetch()) {
                Response::notFound('ไม่พบสินค้ารหัสนี้ในระบบ');
                return;
            }

            // ตรวจสอบว่ามี Order Details อ้างถึงสินค้านี้อยู่หรือไม่
            $checkRef = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM tb_orderdetails WHERE i_ProductID = :id");
            $checkRef->bindParam(':id', $id, PDO::PARAM_INT);
            $checkRef->execute();
            $cnt = (int)($checkRef->fetch()['cnt'] ?? 0);

            if ($cnt > 0) {
                // หากถูกอ้างอิง ให้ลบออกจาก order details ก่อน หรือแจ้งเตือน
                $delDetails = $this->conn->prepare("DELETE FROM tb_orderdetails WHERE i_ProductID = :id");
                $delDetails->bindParam(':id', $id, PDO::PARAM_INT);
                $delDetails->execute();
            }

            $stmt = $this->conn->prepare("DELETE FROM tb_products WHERE i_ProductID = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            Response::success(null, 'ลบสินค้าเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถลบสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /**
     * validate() — ตรวจสอบความถูกต้องของข้อมูลตามเกณฑ์
     */
    private function validate(array $data): array
    {
        $errors = [];

        $name = trim($data['productName'] ?? '');
        if ($name === '') {
            $errors[] = 'กรุณากรอกชื่อสินค้า';
        } elseif (mb_strlen($name) > 50) {
            $errors[] = 'ชื่อสินค้าต้องยาวไม่เกิน 50 ตัวอักษร';
        }

        if (empty($data['categoryId']) || (int)$data['categoryId'] < 1) {
            $errors[] = 'กรุณาเลือกหมวดหมู่สินค้า';
        }

        if (empty($data['supplierId']) || (int)$data['supplierId'] < 1) {
            $errors[] = 'กรุณาเลือกผู้จัดจำหน่าย';
        }

        $unit = trim($data['unit'] ?? '');
        if ($unit === '') {
            $errors[] = 'กรุณากรอกหน่วยนับสินค้า (เช่น กล่อง, ขวด, แพ็ค)';
        } elseif (mb_strlen($unit) > 30) {
            $errors[] = 'หน่วยนับสินค้าต้องยาวไม่เกิน 30 ตัวอักษร';
        }

        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors[] = 'กรุณากรอกราคาสินค้าเป็นตัวเลข';
        } elseif ((float)$data['price'] < 0) {
            $errors[] = 'ราคาสินค้าต้องไม่ต่ำกว่า 0 บาท';
        }

        return $errors;
    }
}
