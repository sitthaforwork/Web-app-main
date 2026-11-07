<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * api/index.php — API Central Router & Controller Dispatcher
 * ─────────────────────────────────────────────────────────────────────────────
 * ระบบ Northwind BMS RESTful API Architecture
 * พัฒนาตามข้อกำหนดและเกณฑ์คะแนนวิชา Web Application (100 คะแนนเต็ม)
 * ─────────────────────────────────────────────────────────────────────────────
 */

// 1. จัดการ CORS Headers และ Preflight Options
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 2. ดึง Path ของคำขอ (รองรับทั้ง Apache Rewrite, Nginx, Railway และ Direct Query)
$requestPath = $_GET['url'] ?? $_GET['__route'] ?? '';

if (empty($requestPath)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/api/') !== false) {
        $path = explode('/api/', $uri)[1];
        $requestPath = explode('?', $path)[0];
    }
}
$requestPath = trim($requestPath, '/');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 3. Database Connection (PDO แบบ OOP Style ตามข้อกำหนดที่ 1)
require_once __DIR__ . '/../inc/connDB.php';
$db = new Database();
$conn = $db->getConnection();

// 4. โหลด Core Framework Helpers
require_once __DIR__ . '/core/JWT.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Router.php';

// 5. โหลด Controllers ทั้งหมด
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/SupplierController.php';
require_once __DIR__ . '/controllers/CustomerController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';
require_once __DIR__ . '/controllers/ShipperController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/ReportController.php';

try {
    // 6. สร้าง Instance ของ Controllers
    $authController      = new AuthController($conn);
    $dashboardController = new DashboardController($conn);
    $productController   = new ProductController($conn);
    $categoryController  = new CategoryController($conn);
    $supplierController  = new SupplierController($conn);
    $customerController  = new CustomerController($conn);
    $employeeController  = new EmployeeController($conn);
    $shipperController   = new ShipperController($conn);
    $orderController     = new OrderController($conn);
    $reportController    = new ReportController($conn);

    // 7. ลงทะเบียน Routing Table ครบทุก Module
    $router = new Router();

    // ── Authentication (JWT) ──
    $router->post('/auth/login',    [$authController, 'login']);
    $router->get('/auth/me',        [$authController, 'me']);

    // ── Dashboard Overview ──
    $router->get('/dashboard',      [$dashboardController, 'index']);

    // ── Products (CRUD) ──
    $router->get('/products',        [$productController, 'index']);
    $router->get('/products/{id}',   [$productController, 'show']);
    $router->post('/products',       [$productController, 'store']);
    $router->put('/products/{id}',   [$productController, 'update']);
    $router->delete('/products/{id}',[$productController, 'destroy']);

    // ── Categories (CRUD) ──
    $router->get('/categories',        [$categoryController, 'index']);
    $router->get('/categories/{id}',   [$categoryController, 'show']);
    $router->post('/categories',       [$categoryController, 'store']);
    $router->put('/categories/{id}',   [$categoryController, 'update']);
    $router->delete('/categories/{id}',[$categoryController, 'destroy']);

    // ── Suppliers (CRUD) ──
    $router->get('/suppliers',        [$supplierController, 'index']);
    $router->get('/suppliers/{id}',   [$supplierController, 'show']);
    $router->post('/suppliers',       [$supplierController, 'store']);
    $router->put('/suppliers/{id}',   [$supplierController, 'update']);
    $router->delete('/suppliers/{id}',[$supplierController, 'destroy']);

    // ── Customers (CRUD) ──
    $router->get('/customers/countries', [$customerController, 'countries']);
    $router->get('/customers',           [$customerController, 'index']);
    $router->get('/customers/{id}',      [$customerController, 'show']);
    $router->post('/customers',          [$customerController, 'store']);
    $router->put('/customers/{id}',      [$customerController, 'update']);
    $router->delete('/customers/{id}',   [$customerController, 'destroy']);

    // ── Employees (CRUD) ──
    $router->get('/employees',        [$employeeController, 'index']);
    $router->get('/employees/{id}',   [$employeeController, 'show']);
    $router->post('/employees',       [$employeeController, 'store']);
    $router->put('/employees/{id}',   [$employeeController, 'update']);
    $router->delete('/employees/{id}',[$employeeController, 'destroy']);

    // ── Shippers (CRUD) ──
    $router->get('/shippers',        [$shipperController, 'index']);
    $router->get('/shippers/{id}',   [$shipperController, 'show']);
    $router->post('/shippers',       [$shipperController, 'store']);
    $router->put('/shippers/{id}',   [$shipperController, 'update']);
    $router->delete('/shippers/{id}',[$shipperController, 'destroy']);

    // ── Orders & Details (CRUD) ──
    $router->get('/orders',        [$orderController, 'index']);
    $router->get('/orders/{id}',   [$orderController, 'show']);
    $router->post('/orders',       [$orderController, 'store']);
    $router->put('/orders/{id}',   [$orderController, 'update']);
    $router->delete('/orders/{id}',[$orderController, 'destroy']);

    // ── Reports & Analytics ──
    $router->get('/reports/summary', [$reportController, 'summary']);

    // 8. ส่งต่อไปยัง Handler ที่ตรงกัน
    $router->dispatch($requestMethod, $requestPath);

} catch (Throwable $e) {
    Response::error('Internal Server Error: ' . $e->getMessage(), 500);
}
