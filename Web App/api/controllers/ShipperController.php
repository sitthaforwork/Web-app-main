<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * ShipperController.php — Shippers Full CRUD Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * หน้าจอที่ 11: บริษัทขนส่ง (Shippers · CRUD, การ์ดแสดงผล, สถิติการจัดส่ง)
 * ─────────────────────────────────────────────────────────────────────────────
 */

class ShipperController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/shippers — ดึงบริษัทขนส่งทั้งหมดพร้อมจำนวนครั้งที่จัดส่ง */
    public function index(): void
    {
        try {
            $sql = "SELECT s.i_ShipperID AS id,
                           s.c_ShipperName AS name,
                           s.c_Phone AS phone,
                           COUNT(o.i_OrderID) AS shipmentCount
                    FROM tb_shippers s
                    LEFT JOIN tb_orders o ON s.i_ShipperID = o.i_ShipperID
                    GROUP BY s.i_ShipperID, s.c_ShipperName, s.c_Phone
                    ORDER BY s.i_ShipperID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $shippers = $stmt->fetchAll();

            $icons = [1 => '🚀', 2 => '📦', 3 => '🛳'];
            foreach ($shippers as &$shp) {
                $shp['icon']   = $icons[$shp['id']] ?? '🚚';
                $shp['status'] = 'พร้อมให้บริการ';
            }

            Response::success($shippers);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลบริษัทขนส่งได้: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/shippers/{id} — ดึงบริษัทขนส่งรายตัว */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT i_ShipperID AS id, c_ShipperName AS name, c_Phone AS phone
                    FROM tb_shippers
                    WHERE i_ShipperID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $shipper = $stmt->fetch();
            if (!$shipper) {
                Response::notFound('ไม่พบข้อมูลบริษัทขนส่งรหัสนี้');
                return;
            }

            Response::success($shipper);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลบริษัทขนส่งได้: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/shippers — เพิ่มบริษัทขนส่งใหม่ */
    public function store(): void
    {
        try {
            $data  = json_decode(file_get_contents('php://input'), true);
            $name  = trim($data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อบริษัทขนส่ง']);
                return;
            }

            $stmtMax = $this->conn->query("SELECT MAX(i_ShipperID) AS maxId FROM tb_shippers");
            $nextId = (int)($stmtMax->fetch()['maxId'] ?? 0) + 1;

            $sql = "INSERT INTO tb_shippers (i_ShipperID, c_ShipperName, c_Phone) 
                    VALUES (:id, :name, :phone)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id'    => $nextId,
                ':name'  => $name,
                ':phone' => $phone
            ]);

            Response::success(['id' => $nextId], 'เพิ่มข้อมูลบริษัทขนส่งเรียบร้อยแล้ว', 201);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถเพิ่มข้อมูลบริษัทขนส่งได้: ' . $e->getMessage(), 500);
        }
    }

    /** PUT /api/shippers/{id} — แก้ไขบริษัทขนส่ง */
    public function update(string $id): void
    {
        try {
            $data  = json_decode(file_get_contents('php://input'), true);
            $name  = trim($data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อบริษัทขนส่ง']);
                return;
            }

            $sql = "UPDATE tb_shippers SET c_ShipperName = :name, c_Phone = :phone WHERE i_ShipperID = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':name'  => $name,
                ':phone' => $phone,
                ':id'    => (int)$id
            ]);

            Response::success(['id' => (int)$id], 'แก้ไขข้อมูลบริษัทขนส่งเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขข้อมูลบริษัทขนส่งได้: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE /api/shippers/{id} — ลบบริษัทขนส่ง */
    public function destroy(string $id): void
    {
        try {
            $sql = "DELETE FROM tb_shippers WHERE i_ShipperID = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            Response::success(null, 'ลบข้อมูลบริษัทขนส่งเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถลบข้อมูลบริษัทขนส่งได้: ' . $e->getMessage(), 500);
        }
    }
}
