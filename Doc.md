# API Documentation — Northwind BMS (Business Management System)

เอกสารข้อกำหนดและการเรียกใช้งาน RESTful API สำหรับระบบบริหารจัดการธุรกิจ Northwind BMS  
พัฒนาด้วย **Pure PHP (API Architecture) + PDO (OOP Style) + Bootstrap 5 + JWT Authentication**  
ภาคการศึกษา 2/2569 — สาขาวิชาวิศวกรรมคอมพิวเตอร์ (CPE PSRU)

---

## 1. ภาพรวมสถาปัตยกรรม (System Architecture Overview)

- **Base URL:** `/api`
- **Content-Type:** `application/json; charset=utf-8`
- **Authentication:** `Bearer Token (JWT)` ใน HTTP Header `Authorization: Bearer {token}`
- **Standard Response Format:**
  - **Success Response (HTTP 200 / 201):**
    ```json
    {
      "success": true,
      "message": "ข้อความแจ้งสถานะการทำงาน",
      "data": {}
    }
    ```
  - **Validation Error (HTTP 422):**
    ```json
    {
      "success": false,
      "message": "ข้อมูลไม่ผ่านการตรวจสอบความถูกต้อง",
      "errors": ["ชื่อสินค้าต้องยาวไม่เกิน 50 ตัวอักษร"]
    }
    ```
  - **Error Response (HTTP 400 / 401 / 404 / 500):**
    ```json
    {
      "success": false,
      "message": "คำอธิบายข้อผิดพลาด"
    }
    ```

---

## 2. รายการ Endpoints ทั้งหมด (API Endpoints Summary)

| หมวดหมู่ | Method | Endpoint Path | หน้าที่การทำงาน | ต้องใช้ JWT Token |
|:---|:---:|:---|:---|:---:|
| **Authentication** | `POST` | `/api/auth/login` | ตรวจสอบชื่อผู้ใช้/รหัสผ่าน และออก JWT Token | ❌ |
| | `GET` | `/api/auth/me` | ตรวจสอบสถานะและสิทธิ์ของ Token | ✅ |
| **Dashboard** | `GET` | `/api/dashboard` | ดึงข้อมูลสรุป KPI, กราฟ และคำสั่งซื้อล่าสุด | ✅ |
| **Products** | `GET` | `/api/products` | ดึงรายการสินค้าทั้งหมด (+ ค้นหา & ตัวกรอง) | ✅ |
| | `GET` | `/api/products/{id}` | ดึงข้อมูลสินค้ารายตัวตาม ID | ✅ |
| | `POST` | `/api/products` | เพิ่มข้อมูลสินค้าใหม่ | ✅ |
| | `PUT` | `/api/products/{id}` | แก้ไขข้อมูลสินค้า | ✅ |
| | `DELETE`| `/api/products/{id}` | ลบข้อมูลสินค้า | ✅ |
| **Categories** | `GET` | `/api/categories` | ดึงรายการหมวดหมู่สินค้าทั้งหมด | ✅ |
| | `GET` | `/api/categories/{id}` | ดึงข้อมูลหมวดหมู่รายตัว | ✅ |
| | `POST` | `/api/categories` | เพิ่มหมวดหมู่สินค้าใหม่ | ✅ |
| | `PUT` | `/api/categories/{id}` | แก้ไขข้อมูลหมวดหมู่ | ✅ |
| | `DELETE`| `/api/categories/{id}` | ลบหมวดหมู่สินค้า | ✅ |
| **Suppliers** | `GET` | `/api/suppliers` | ดึงรายการผู้จัดจำหน่ายทั้งหมด | ✅ |
| | `GET` | `/api/suppliers/{id}` | ดึงข้อมูลผู้จัดจำหน่ายรายตัว | ✅ |
| | `POST` | `/api/suppliers` | เพิ่มผู้จัดจำหน่ายใหม่ | ✅ |
| | `PUT` | `/api/suppliers/{id}` | แก้ไขข้อมูลผู้จัดจำหน่าย | ✅ |
| | `DELETE`| `/api/suppliers/{id}` | ลบผู้จัดจำหน่าย | ✅ |
| **Customers** | `GET` | `/api/customers` | ดึงทะเบียนลูกค้าทั้งหมด (+ ค้นหา & ประเทศ) | ✅ |
| | `GET` | `/api/customers/countries` | ดึงรายชื่อประเทศทั้งหมดที่มีลูกค้า | ✅ |
| | `GET` | `/api/customers/{id}` | ดึงข้อมูลลูกค้ารายตัว | ✅ |
| | `POST` | `/api/customers` | เพิ่มลูกค้าใหม่ | ✅ |
| | `PUT` | `/api/customers/{id}` | แก้ไขข้อมูลลูกค้า | ✅ |
| | `DELETE`| `/api/customers/{id}` | ลบข้อมูลลูกค้า | ✅ |
| **Employees** | `GET` | `/api/employees` | ดึงรายชื่อพนักงานทั้งหมด | ✅ |
| | `GET` | `/api/employees/{id}` | ดึงข้อมูลพนักงานรายคน | ✅ |
| | `POST` | `/api/employees` | เพิ่มพนักงานใหม่ | ✅ |
| | `PUT` | `/api/employees/{id}` | แก้ไขข้อมูลพนักงาน | ✅ |
| | `DELETE`| `/api/employees/{id}` | ลบข้อมูลพนักงาน | ✅ |
| **Shippers** | `GET` | `/api/shippers` | ดึงรายชื่อบริษัทขนส่งทั้งหมด | ✅ |
| | `GET` | `/api/shippers/{id}` | ดึงข้อมูลบริษัทขนส่งรายตัว | ✅ |
| | `POST` | `/api/shippers` | เพิ่มบริษัทขนส่งใหม่ | ✅ |
| | `PUT` | `/api/shippers/{id}` | แก้ไขข้อมูลบริษัทขนส่ง | ✅ |
| | `DELETE`| `/api/shippers/{id}` | ลบบริษัทขนส่ง | ✅ |
| **Orders** | `GET` | `/api/orders` | ดึงรายการคำสั่งซื้อ (+ ค้นหา & กรองสถานะ) | ✅ |
| | `GET` | `/api/orders/{id}` | ดึงรายละเอียดคำสั่งซื้อและรายการสินค้า | ✅ |
| | `POST` | `/api/orders` | สร้างคำสั่งซื้อใหม่พร้อมรายการสินค้า | ✅ |
| | `PUT` | `/api/orders/{id}` | แก้ไขข้อมูลคำสั่งซื้อ | ✅ |
| | `DELETE`| `/api/orders/{id}` | ลบคำสั่งซื้อ | ✅ |
| **Reports** | `GET` | `/api/reports/summary` | สรุปรายงานยอดขาย สินค้าขายดี ลูกค้าชั้นนำ | ✅ |

---

## 3. รายละเอียด Endpoint และตัวอย่าง Request/Response

### 3.1 Authentication (ระบบยืนยันตัวตน)

#### `POST /api/auth/login`
- **คำอธิบาย:** ตรวจสอบผู้ใช้จากตาราง `tb_users` และออก JWT Token สำหรับนำไปใช้เรียก Endpoint อื่น ๆ
- **Headers:** `Content-Type: application/json`
- **Request Body (JSON):**
  ```json
  {
    "username": "admin",
    "password": "admin1234"
  }
  ```
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "message": "เข้าสู่ระบบสำเร็จ",
    "data": {
      "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "user": {
        "id": 1,
        "username": "admin",
        "role": "Administrator",
        "name": "วชิรา ล. (Admin)"
      }
    }
  }
  ```
- **Response 401 Unauthorized:**
  ```json
  {
    "success": false,
    "message": "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"
  }
  ```

---

### 3.2 Dashboard (หน้าจอที่ 02: แดชบอร์ดภาพรวม)

#### `GET /api/dashboard`
- **Headers:** `Authorization: Bearer {token}`
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "message": "ดึงข้อมูลแดชบอร์ดสำเร็จ",
    "data": {
      "kpis": {
        "totalOrders": 196,
        "totalProducts": 79,
        "totalCustomers": 91,
        "totalSuppliers": 29
      },
      "categories": [
        { "id": 1, "name": "Beverages", "productCount": 12 },
        { "id": 2, "name": "Condiments", "productCount": 13 }
      ],
      "recentOrders": [
        {
          "id": 10443,
          "orderDate": "12/2/1997",
          "customerName": "Ernst Handel",
          "employeeName": "Anne Dodsworth",
          "shipperName": "Federal Shipping",
          "itemCount": 2
        }
      ],
      "monthlyOrders": [
        { "month": "เม.ย.", "count": 12 },
        { "month": "พ.ค.", "count": 18 }
      ]
    }
  }
  ```

---

### 3.3 Products (หน้าจอที่ 03 & 04: สินค้า)

#### `GET /api/products`
- **Headers:** `Authorization: Bearer {token}`
- **Query Parameters:**
  - `search` (string, optional): ค้นหาชื่อสินค้า, หมวดหมู่ หรือผู้จัดจำหน่าย
  - `category_id` (integer, optional): กรองตามหมวดหมู่
  - `supplier_id` (integer, optional): กรองตามผู้จัดจำหน่าย
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "message": "ดึงข้อมูลสินค้าสำเร็จ",
    "total": 79,
    "data": [
      {
        "id": 1,
        "productName": "Chai",
        "supplierId": 1,
        "categoryId": 1,
        "unit": "10 boxes x 20 bags",
        "price": 18.00,
        "categoryName": "Beverages",
        "supplierName": "Exotic Liquid",
        "status": "ใช้งานอยู่"
      }
    ]
  }
  ```

#### `POST /api/products`
- **Headers:** 
  - `Authorization: Bearer {token}`
  - `Content-Type: application/json`
- **Request Body (JSON):**
  ```json
  {
    "productName": "ชาเขียวออร์แกนิค",
    "categoryId": 1,
    "supplierId": 1,
    "unit": "10 กล่อง x 20 ซอง",
    "price": 45.00
  }
  ```
- **Response 201 Created:**
  ```json
  {
    "success": true,
    "message": "เพิ่มข้อมูลสินค้าเรียบร้อยแล้ว",
    "data": { "id": 108 }
  }
  ```

#### `PUT /api/products/{id}`
- **Headers:** `Authorization: Bearer {token}`, `Content-Type: application/json`
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "message": "แก้ไขข้อมูลสินค้าเรียบร้อยแล้ว",
    "data": { "id": 108 }
  }
  ```

#### `DELETE /api/products/{id}`
- **Headers:** `Authorization: Bearer {token}`
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "message": "ลบสินค้าเรียบร้อยแล้ว",
    "data": null
  }
  ```

---

### 3.4 Categories (หน้าจอที่ 05: หมวดหมู่สินค้า)

#### `GET /api/categories`
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 1,
        "name": "Beverages",
        "description": "Soft drinks, coffees, teas, beers",
        "productCount": 12
      }
    ]
  }
  ```

#### `POST /api/categories`
- **Request Body (JSON):**
  ```json
  {
    "name": "ขนมหวานเพื่อสุขภาพ",
    "description": "เบเกอรี่และของหวานน้ำตาลต่ำ"
  }
  ```
- **Response 201 Created:**
  ```json
  {
    "success": true,
    "message": "เพิ่มหมวดหมู่สินค้าเรียบร้อยแล้ว",
    "data": { "id": 10 }
  }
  ```

---

### 3.5 Orders & Details (หน้าจอที่ 06 & 07: คำสั่งซื้อ)

#### `GET /api/orders`
- **Query Parameters:** `search`, `status`, `shipper_id`
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "message": "ดึงรายการคำสั่งซื้อสำเร็จ",
    "total": 196,
    "data": [
      {
        "id": 10443,
        "orderDate": "12/2/1997",
        "customerId": 66,
        "customerName": "Reggiani Caseifici",
        "employeeId": 8,
        "employeeName": "Laura Callahan",
        "shipperId": 1,
        "shipperName": "Speedy Express",
        "itemCount": 2,
        "totalAmount": 1008.00,
        "status": "จัดส่งแล้ว"
      }
    ]
  }
  ```

#### `GET /api/orders/{id}`
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "data": {
      "id": 10443,
      "orderDate": "12/2/1997",
      "customerName": "Reggiani Caseifici",
      "customerContact": "Maurizio Moroni",
      "customerAddress": "Strada Provinciale 124",
      "customerCity": "Reggio Emilia",
      "customerCountry": "Italy",
      "employeeName": "Laura Callahan",
      "shipperName": "Speedy Express",
      "shipperPhone": "(503) 555-9831",
      "status": "จัดส่งแล้ว",
      "totalAmount": 1008.00,
      "totalUnits": 18,
      "itemCount": 2,
      "items": [
        {
          "orderDetailId": 517,
          "productId": 11,
          "productName": "Queso Cabrales",
          "unit": "1 kg pkg.",
          "price": 21.00,
          "quantity": 6,
          "itemTotal": 126.00
        }
      ]
    }
  }
  ```

#### `POST /api/orders`
- **Request Body (JSON):**
  ```json
  {
    "customerId": 1,
    "employeeId": 1,
    "shipperId": 2,
    "orderDate": "1/11/2026",
    "items": [
      { "productId": 1, "quantity": 10 },
      { "productId": 2, "quantity": 5 }
    ]
  }
  ```

---

### 3.6 Customers, Suppliers, Employees, Shippers (หน้าจอ 08, 09, 10, 11)

- ทุกตารางรองรับ HTTP Methods: `GET`, `GET /{id}`, `POST`, `PUT /{id}`, `DELETE /{id}`
- เขียน SQL ผ่าน PDO Prepared Statement แบบ Bind Parameter ครบทุกคำสั่ง 100%
- ป้องกันข้อผิดพลาด Foreign Key Reference อัตโนมัติ

---

### 3.7 Reports & Analytics (หน้าจอที่ 12: รายงาน)

#### `GET /api/reports/summary`
- **Response 200 OK:**
  ```json
  {
    "success": true,
    "data": {
      "kpis": {
        "totalRevenue": 2450000.00,
        "totalOrders": 196,
        "avgPerOrder": 12500.00,
        "topShipper": "United Package",
        "topShipperCount": 74
      },
      "monthlyTrends": [...],
      "topProducts": [...],
      "topCustomers": [...]
    }
  }
  ```

---

## 4. มาตรการความปลอดภัยและข้อควรระวัง (Security & Rule Compliance)

1. **SQL Injection Prevention:** ทุก Controller ใช้ PDO Prepared Statement (`bindParam` หรือ `$stmt->execute([...])`) ไม่มี string concatenation ในคำสั่ง SQL
2. **Access Control:** ป้องกันสคริปต์ `api/setup_database.php` ด้วย Secret Key (`key=admin1234`) เพื่อป้องกันการสั่ง Reset ฐานข้อมูลโดยพลการ
3. **No External Heavy Frameworks:** ไม่ใช้ Framework หรือ ORM สำเร็จรูป (เช่น Laravel, Yii, CodeIgniter) ตรงตามข้อกำหนดในสไลด์หน้า 8
4. **Environment Isolation:** ระบบดึงการตั้งค่าฐานข้อมูลผ่านตัวแปร Environment Variables (`MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`) เพื่อความปลอดภัยในการ Deploy บน Cloud
