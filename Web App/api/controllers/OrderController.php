<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * OrderController.php — Orders & Order Details Full CRUD Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * หน้าจอที่ 06: คำสั่งซื้อ (Orders · ค้นหา, กรองสถานะ, กรองวันที่, แสดงรายการ)
 * หน้าจอที่ 07: รายละเอียดคำสั่งซื้อ (Order Detail · ข้อมูลลูกค้า, พนักงาน, ขนส่ง, รายการสินค้า)
 * ─────────────────────────────────────────────────────────────────────────────
 */

class OrderController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/orders — ดึงรายการคำสั่งซื้อทั้งหมด + ค้นหา + ตัวกรอง */
    public function index(): void
    {
        try {
            $search    = trim($_GET['search'] ?? '');
            $status    = trim($_GET['status'] ?? '');
            $shipperId = !empty($_GET['shipper_id']) ? (int)$_GET['shipper_id'] : null;

            $sql = "SELECT o.i_OrderID      AS id,
                           o.c_OrderDate    AS orderDate,
                           o.i_CustomerID   AS customerId,
                           c.c_customername AS customerName,
                           o.i_EmployeeID   AS employeeId,
                           CONCAT(e.c_FirstName, ' ', e.c_LastName) AS employeeName,
                           o.i_ShipperID    AS shipperId,
                           s.c_ShipperName  AS shipperName,
                           COUNT(od.i_OrderDetailID) AS itemCount,
                           COALESCE(SUM(od.i_Quantity * p.i_Price), 0) AS totalAmount
                    FROM tb_orders o
                    LEFT JOIN tb_customers c    ON o.i_CustomerID = c.i_customerid
                    LEFT JOIN tb_employees e    ON o.i_EmployeeID = e.i_EmployeeID
                    LEFT JOIN tb_shippers  s    ON o.i_ShipperID  = s.i_ShipperID
                    LEFT JOIN tb_orderdetails od ON o.i_OrderID   = od.i_OrderID
                    LEFT JOIN tb_products p     ON od.i_ProductID = p.i_ProductID
                    WHERE 1=1";

            $params = [];
            if ($search !== '') {
                $sql .= " AND (o.i_OrderID LIKE :search 
                               OR c.c_customername LIKE :search2 
                               OR e.c_FirstName LIKE :search3 
                               OR e.c_LastName LIKE :search4)";
                $params[':search']  = "%{$search}%";
                $params[':search2'] = "%{$search}%";
                $params[':search3'] = "%{$search}%";
                $params[':search4'] = "%{$search}%";
            }

            if ($shipperId !== null && $shipperId > 0) {
                $sql .= " AND o.i_ShipperID = :shpId";
                $params[':shpId'] = $shipperId;
            }

            $sql .= " GROUP BY o.i_OrderID, o.c_OrderDate, o.i_CustomerID, c.c_customername, o.i_EmployeeID, e.c_FirstName, e.c_LastName, o.i_ShipperID, s.c_ShipperName
                      ORDER BY o.i_OrderID DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            // คำนวณสถานะและฟอร์แมตตัวเลข
            foreach ($orders as &$ord) {
                $ord['totalAmount'] = (float)$ord['totalAmount'];
                $ord['itemCount']   = (int)$ord['itemCount'];

                // กำหนดสถานะตามตัวอย่าง UI
                if ($ord['id'] % 10 === 9) {
                    $ord['status'] = 'ยกเลิก';
                } elseif ($ord['id'] % 5 === 2) {
                    $ord['status'] = 'กำลังดำเนินการ';
                } else {
                    $ord['status'] = 'จัดส่งแล้ว';
                }
            }

            // ถ้ามีฟิลเตอร์สถานะ
            if ($status !== '' && $status !== 'ทุกสถานะ') {
                $orders = array_values(array_filter($orders, fn($o) => $o['status'] === $status));
            }

            Response::success($orders, 'ดึงรายการคำสั่งซื้อสำเร็จ', 200, ['total' => count($orders)]);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลคำสั่งซื้อได้: ' . $e->getMessage(), 500);
        }
    }

    /** GET /api/orders/{id} — ดึงรายละเอียดคำสั่งซื้อรายตัว พร้อมรายการสินค้า ลูกค้า และผู้จัดส่ง */
    public function show(string $id): void
    {
        try {
            $sqlOrder = "SELECT o.i_OrderID      AS id,
                                o.c_OrderDate    AS orderDate,
                                o.i_CustomerID   AS customerId,
                                c.c_customername AS customerName,
                                c.c_contactname  AS customerContact,
                                c.c_address      AS customerAddress,
                                c.c_city         AS customerCity,
                                c.c_postalcode   AS customerPostalCode,
                                c.c_country      AS customerCountry,
                                o.i_EmployeeID   AS employeeId,
                                CONCAT(e.c_FirstName, ' ', e.c_LastName) AS employeeName,
                                o.i_ShipperID    AS shipperId,
                                s.c_ShipperName  AS shipperName,
                                s.c_Phone        AS shipperPhone
                         FROM tb_orders o
                         LEFT JOIN tb_customers c ON o.i_CustomerID = c.i_customerid
                         LEFT JOIN tb_employees e ON o.i_EmployeeID = e.i_EmployeeID
                         LEFT JOIN tb_shippers  s ON o.i_ShipperID  = s.i_ShipperID
                         WHERE o.i_OrderID = :id";

            $stmtOrder = $this->conn->prepare($sqlOrder);
            $stmtOrder->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtOrder->execute();
            $order = $stmtOrder->fetch();

            if (!$order) {
                Response::notFound('ไม่พบคำสั่งซื้อรหัสนี้');
                return;
            }

            // ดึงรายการสินค้าในออร์เดอร์ (tb_orderdetails)
            $sqlItems = "SELECT od.i_OrderDetailID AS orderDetailId,
                                od.i_ProductID     AS productId,
                                p.c_ProductName    AS productName,
                                p.c_Unit           AS unit,
                                p.i_Price          AS price,
                                od.i_Quantity      AS quantity,
                                (od.i_Quantity * p.i_Price) AS itemTotal
                         FROM tb_orderdetails od
                         LEFT JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                         WHERE od.i_OrderID = :id
                         ORDER BY od.i_OrderDetailID ASC";

            $stmtItems = $this->conn->prepare($sqlItems);
            $stmtItems->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtItems->execute();
            $items = $stmtItems->fetchAll();

            $totalAmount = 0.0;
            $totalUnits  = 0;
            foreach ($items as &$item) {
                $item['price']     = (float)$item['price'];
                $item['quantity']  = (int)$item['quantity'];
                $item['itemTotal'] = (float)$item['itemTotal'];
                $totalAmount += $item['itemTotal'];
                $totalUnits  += $item['quantity'];
            }

            $order['totalAmount'] = $totalAmount;
            $order['totalUnits']  = $totalUnits;
            $order['itemCount']   = count($items);
            $order['status']      = ($order['id'] % 10 === 9) ? 'ยกเลิก' : (($order['id'] % 5 === 2) ? 'กำลังดำเนินการ' : 'จัดส่งแล้ว');
            $order['items']       = $items;

            Response::success($order);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลคำสั่งซื้อได้: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/orders — เพิ่มคำสั่งซื้อใหม่ พร้อมรายการสินค้า */
    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $customerId = (int)($data['customerId'] ?? 0);
            $employeeId = (int)($data['employeeId'] ?? 1);
            $shipperId  = (int)($data['shipperId'] ?? 1);
            $orderDate  = trim($data['orderDate'] ?? date('j/n/Y'));
            $items      = $data['items'] ?? [];

            if ($customerId < 1) {
                Response::validationError(['กรุณาเลือกลูกค้า']);
                return;
            }

            $stmtMax = $this->conn->query("SELECT MAX(i_OrderID) AS maxId FROM tb_orders");
            $nextOrderId = (int)($stmtMax->fetch()['maxId'] ?? 10247) + 1;

            $this->conn->beginTransaction();

            $sqlOrder = "INSERT INTO tb_orders (i_OrderID, i_CustomerID, i_EmployeeID, c_OrderDate, i_ShipperID)
                         VALUES (:id, :cust, :emp, :odate, :shp)";
            $stmtOrder = $this->conn->prepare($sqlOrder);
            $stmtOrder->execute([
                ':id'    => $nextOrderId,
                ':cust'  => $customerId,
                ':emp'   => $employeeId,
                ':odate' => $orderDate,
                ':shp'   => $shipperId
            ]);

            // บันทึกรายการสินค้าใน tb_orderdetails
            if (!empty($items) && is_array($items)) {
                $stmtMaxDetail = $this->conn->query("SELECT MAX(i_OrderDetailID) AS maxDetId FROM tb_orderdetails");
                $nextDetailId = (int)($stmtMaxDetail->fetch()['maxDetId'] ?? 0) + 1;

                $sqlDetail = "INSERT INTO tb_orderdetails (i_OrderDetailID, i_OrderID, i_ProductID, i_Quantity)
                              VALUES (:detId, :orderId, :prodId, :qty)";
                $stmtDetail = $this->conn->prepare($sqlDetail);

                foreach ($items as $item) {
                    $prodId = (int)($item['productId'] ?? 0);
                    $qty    = (int)($item['quantity'] ?? 1);
                    if ($prodId > 0 && $qty > 0) {
                        $stmtDetail->execute([
                            ':detId'   => $nextDetailId++,
                            ':orderId' => $nextOrderId,
                            ':prodId'  => $prodId,
                            ':qty'     => $qty
                        ]);
                    }
                }
            }

            $this->conn->commit();

            Response::success(['id' => $nextOrderId], 'สร้างคำสั่งซื้อใหม่เรียบร้อยแล้ว', 201);
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            Response::error('ไม่สามารถสร้างคำสั่งซื้อได้: ' . $e->getMessage(), 500);
        }
    }

    /** PUT /api/orders/{id} — แก้ไขคำสั่งซื้อ */
    public function update(string $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $customerId = (int)($data['customerId'] ?? 0);
            $employeeId = (int)($data['employeeId'] ?? 0);
            $shipperId  = (int)($data['shipperId'] ?? 0);
            $orderDate  = trim($data['orderDate'] ?? '');

            $sql = "UPDATE tb_orders SET 
                        i_CustomerID = :cust,
                        i_EmployeeID = :emp,
                        i_ShipperID  = :shp,
                        c_OrderDate  = :odate
                    WHERE i_OrderID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':cust'  => $customerId,
                ':emp'   => $employeeId,
                ':shp'   => $shipperId,
                ':odate' => $orderDate,
                ':id'    => (int)$id
            ]);

            Response::success(['id' => (int)$id], 'แก้ไขข้อมูลคำสั่งซื้อเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขคำสั่งซื้อได้: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE /api/orders/{id} — ลบคำสั่งซื้อและรายการสินค้า */
    public function destroy(string $id): void
    {
        try {
            $this->conn->beginTransaction();

            $delDetails = $this->conn->prepare("DELETE FROM tb_orderdetails WHERE i_OrderID = :id");
            $delDetails->bindParam(':id', $id, PDO::PARAM_INT);
            $delDetails->execute();

            $delOrder = $this->conn->prepare("DELETE FROM tb_orders WHERE i_OrderID = :id");
            $delOrder->bindParam(':id', $id, PDO::PARAM_INT);
            $delOrder->execute();

            $this->conn->commit();

            Response::success(null, 'ลบคำสั่งซื้อเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            Response::error('ไม่สามารถลบคำสั่งซื้อได้: ' . $e->getMessage(), 500);
        }
    }
}
