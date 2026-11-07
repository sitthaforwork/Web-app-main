<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * SupplierController.php — Suppliers Full CRUD Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * หน้าจอที่ 09: ผู้จัดจำหน่าย (Suppliers · CRUD)
 * ─────────────────────────────────────────────────────────────────────────────
 */

class SupplierController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/suppliers — ดึงผู้จัดจำหน่ายทั้งหมด + ค้นหา */
    public function index(): void
    {
        try {
            $search = trim($_GET['search'] ?? '');
            $sql = "SELECT s.i_SupplierID AS id, 
                           s.c_SupplierName AS name, 
                           s.c_ContactName AS contactName,
                           s.c_Address AS address,
                           s.c_City AS city,
                           s.c_PostalCode AS postalCode,
                           s.c_Country AS country,
                           s.c_Phone AS phone,
                           COUNT(p.i_ProductID) AS productCount
                    FROM tb_suppliers s
                    LEFT JOIN tb_products p ON s.i_SupplierID = p.i_SupplierID
                    WHERE 1=1";

            $params = [];
            if ($search !== '') {
                $sql .= " AND (s.c_SupplierName LIKE :search 
                               OR s.c_ContactName LIKE :search2 
                               OR s.c_City LIKE :search3 
                               OR s.c_Country LIKE :search4)";
                $params[':search']  = "%{$search}%";
                $params[':search2'] = "%{$search}%";
                $params[':search3'] = "%{$search}%";
                $params[':search4'] = "%{$search}%";
            }

            $sql .= " GROUP BY s.i_SupplierID, s.c_SupplierName, s.c_ContactName, s.c_Address, s.c_City, s.c_PostalCode, s.c_Country, s.c_Phone
                      ORDER BY s.i_SupplierID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            Response::success($stmt->fetchAll());
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลผู้จัดจำหน่ายได้: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/suppliers/{id} — ดึงผู้จัดจำหน่ายรายตัว */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT i_SupplierID AS id, 
                           c_SupplierName AS name, 
                           c_ContactName AS contactName,
                           c_Address AS address,
                           c_City AS city,
                           c_PostalCode AS postalCode,
                           c_Country AS country,
                           c_Phone AS phone
                    FROM tb_suppliers
                    WHERE i_SupplierID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $supplier = $stmt->fetch();
            if (!$supplier) {
                Response::notFound('ไม่พบผู้จัดจำหน่ายรหัสนี้');
                return;
            }

            Response::success($supplier);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลผู้จัดจำหน่ายได้: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/suppliers — เพิ่มผู้จัดจำหน่ายใหม่ */
    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $name    = trim($data['name'] ?? '');
            $contact = trim($data['contactName'] ?? '');
            $address = trim($data['address'] ?? '');
            $city    = trim($data['city'] ?? '');
            $postal  = trim($data['postalCode'] ?? '');
            $country = trim($data['country'] ?? '');
            $phone   = trim($data['phone'] ?? '');

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อบริษัทผู้จัดจำหน่าย']);
                return;
            }

            $stmtMax = $this->conn->query("SELECT MAX(i_SupplierID) AS maxId FROM tb_suppliers");
            $nextId = (int)($stmtMax->fetch()['maxId'] ?? 0) + 1;

            $sql = "INSERT INTO tb_suppliers (i_SupplierID, c_SupplierName, c_ContactName, c_Address, c_City, c_PostalCode, c_Country, c_Phone)
                    VALUES (:id, :name, :contact, :address, :city, :postal, :country, :phone)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id'      => $nextId,
                ':name'    => $name,
                ':contact' => $contact,
                ':address' => $address,
                ':city'    => $city,
                ':postal'  => $postal,
                ':country' => $country,
                ':phone'   => $phone
            ]);

            Response::success(['id' => $nextId], 'เพิ่มผู้จัดจำหน่ายเรียบร้อยแล้ว', 201);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถเพิ่มผู้จัดจำหน่ายได้: ' . $e->getMessage(), 500);
        }
    }

    /** PUT /api/suppliers/{id} — แก้ไขผู้จัดจำหน่าย */
    public function update(string $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $name    = trim($data['name'] ?? '');
            $contact = trim($data['contactName'] ?? '');
            $address = trim($data['address'] ?? '');
            $city    = trim($data['city'] ?? '');
            $postal  = trim($data['postalCode'] ?? '');
            $country = trim($data['country'] ?? '');
            $phone   = trim($data['phone'] ?? '');

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อบริษัทผู้จัดจำหน่าย']);
                return;
            }

            $sql = "UPDATE tb_suppliers SET
                        c_SupplierName = :name,
                        c_ContactName  = :contact,
                        c_Address      = :address,
                        c_City         = :city,
                        c_PostalCode   = :postal,
                        c_Country      = :country,
                        c_Phone        = :phone
                    WHERE i_SupplierID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':name'    => $name,
                ':contact' => $contact,
                ':address' => $address,
                ':city'    => $city,
                ':postal'  => $postal,
                ':country' => $country,
                ':phone'   => $phone,
                ':id'      => (int)$id
            ]);

            Response::success(['id' => (int)$id], 'แก้ไขข้อมูลผู้จัดจำหน่ายเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขผู้จัดจำหน่ายได้: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE /api/suppliers/{id} — ลบผู้จัดจำหน่าย */
    public function destroy(string $id): void
    {
        try {
            $sql = "DELETE FROM tb_suppliers WHERE i_SupplierID = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            Response::success(null, 'ลบผู้จัดจำหน่ายเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถลบผู้จัดจำหน่ายได้: ' . $e->getMessage(), 500);
        }
    }
}
