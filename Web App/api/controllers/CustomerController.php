<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * CustomerController.php — Customers Full CRUD Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * หน้าจอที่ 08: ลูกค้า (Customers · CRUD, ตัวกรองประเทศ, ค้นหา)
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CustomerController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/customers — ดึงลูกค้าทั้งหมด + ค้นหา + กรองตามประเทศ */
    public function index(): void
    {
        try {
            $search  = trim($_GET['search'] ?? '');
            $country = trim($_GET['country'] ?? '');

            $sql = "SELECT i_customerid AS id,
                           c_customername AS name,
                           c_contactname  AS contactName,
                           c_address      AS address,
                           c_city         AS city,
                           c_postalcode   AS postalCode,
                           c_country      AS country
                    FROM tb_customers
                    WHERE 1=1";

            $params = [];
            if ($search !== '') {
                $sql .= " AND (c_customername LIKE :search 
                               OR c_contactname LIKE :search2 
                               OR c_city LIKE :search3)";
                $params[':search']  = "%{$search}%";
                $params[':search2'] = "%{$search}%";
                $params[':search3'] = "%{$search}%";
            }

            if ($country !== '' && $country !== 'ทุกประเทศ') {
                $sql .= " AND c_country = :country";
                $params[':country'] = $country;
            }

            $sql .= " ORDER BY i_customerid ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            Response::success($stmt->fetchAll());
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลลูกค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/customers/countries — ดึงรายชื่อประเทศทั้งหมดที่มีลูกค้า */
    public function countries(): void
    {
        try {
            $sql = "SELECT DISTINCT c_country AS country 
                    FROM tb_customers 
                    WHERE c_country IS NOT NULL AND c_country != '' 
                    ORDER BY c_country ASC";
            $stmt = $this->conn->query($sql);
            $countries = array_column($stmt->fetchAll(), 'country');

            Response::success($countries);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงรายชื่อประเทศได้: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/customers/{id} — ดึงลูกค้ารายตัว */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT i_customerid AS id,
                           c_customername AS name,
                           c_contactname  AS contactName,
                           c_address      AS address,
                           c_city         AS city,
                           c_postalcode   AS postalCode,
                           c_country      AS country
                    FROM tb_customers
                    WHERE i_customerid = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $customer = $stmt->fetch();
            if (!$customer) {
                Response::notFound('ไม่พบข้อมูลลูกค้ารหัสนี้');
                return;
            }

            Response::success($customer);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลลูกค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/customers — เพิ่มลูกค้าใหม่ */
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

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อบริษัทลูกค้า']);
                return;
            }

            $stmtMax = $this->conn->query("SELECT MAX(i_customerid) AS maxId FROM tb_customers");
            $nextId = (int)($stmtMax->fetch()['maxId'] ?? 0) + 1;

            $sql = "INSERT INTO tb_customers (i_customerid, c_customername, c_contactname, c_address, c_city, c_postalcode, c_country)
                    VALUES (:id, :name, :contact, :address, :city, :postal, :country)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id'      => $nextId,
                ':name'    => $name,
                ':contact' => $contact,
                ':address' => $address,
                ':city'    => $city,
                ':postal'  => $postal,
                ':country' => $country
            ]);

            Response::success(['id' => $nextId], 'เพิ่มข้อมูลลูกค้าเรียบร้อยแล้ว', 201);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถเพิ่มข้อมูลลูกค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** PUT /api/customers/{id} — แก้ไขลูกค้า */
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

            if ($name === '') {
                Response::validationError(['กรุณากรอกชื่อบริษัทลูกค้า']);
                return;
            }

            $sql = "UPDATE tb_customers SET
                        c_customername = :name,
                        c_contactname  = :contact,
                        c_address      = :address,
                        c_city         = :city,
                        c_postalcode   = :postal,
                        c_country      = :country
                    WHERE i_customerid = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':name'    => $name,
                ':contact' => $contact,
                ':address' => $address,
                ':city'    => $city,
                ':postal'  => $postal,
                ':country' => $country,
                ':id'      => (int)$id
            ]);

            Response::success(['id' => (int)$id], 'แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขข้อมูลลูกค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE /api/customers/{id} — ลบลูกค้า */
    public function destroy(string $id): void
    {
        try {
            $sql = "DELETE FROM tb_customers WHERE i_customerid = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            Response::success(null, 'ลบข้อมูลลูกค้าเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถลบข้อมูลลูกค้าได้: ' . $e->getMessage(), 500);
        }
    }
}
