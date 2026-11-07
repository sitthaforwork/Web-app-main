# Web-app
> **Course Project Overview**

---

## Assignment Requirements

### 1. Cloud Platform 
* ใช้งาน Cloud Platform เพื่อ Deploy Web App และ Database ([Railway](https://railway.com/))

### 2. Database Setup
* ใช้ Database **Northwind** จากไฟล์ `dbNorthwind.sql` 

### 3. Architecture
* ออกแบบ Web App โดยใช้หลักการ **CRUD ผ่าน API** 

### 4. Web Application Features
ระบบต้องมีฟังก์ชันการทำงานดังนี้:
* **ค้นหา** (Search)
* **เพิ่มข้อมูลสินค้า** (Create)
* **ดู / แสดงรายการของสินค้า** (Read)
* **แก้ไขข้อมูลสินค้า** (Update)
* **ลบข้อมูลสินค้า** (Delete)

### 5. Validation
* ตรวจสอบความถูกต้องของข้อมูลที่กรอกก่อนบันทึกเข้าระบบ

### 6. Alert & Feedback
* แสดง Alert / Popup แจ้งเตือนบนหน้าจอหลังจากทำรายการเสร็จสิ้น หรือมีการเปลี่ยนแปลง/อัปเดตข้อมูล:
  * แจ้งเตือนเมื่อ **เพิ่มข้อมูลสินค้า** สำเร็จ
  * แจ้งเตือนเมื่อ **แก้ไขข้อมูลสินค้า** สำเร็จ
  * แจ้งเตือนเมื่อ **ลบข้อมูลสินค้า** สำเร็จ

---

## Tech Stack

* **Frontend:** HTML, CSS, JavaScript, Bootstrap 5
* **Backend:** PHP
* **Database:** MySQL

---

## สิ่งที่ต้องส่ง (Deliverables)

1. **Live URL** หลังจาก Deploy ผ่าน Railway
2. **Document Link** (Google Docs) อธิบายขั้นตอนการทำงานและขั้นตอนการ Deploy โดยละเอียด
3. **Source Code** ทั้งหมด

---

## Project Structure

ระบบนี้พัฒนาด้วย **PHP** และ **MySQL** โดยแบ่งออกเป็น 2 ส่วนหลักคือ Frontend และ Backend API

```
Web App/
│
├── index.html                         # หน้าเว็บหลัก (แสดงตารางสินค้า, ฟอร์มบันทึกข้อมูล และ Modal แจ้งเตือน)
│
├── css/
│   └── style.css                      # สไตล์ตกแต่งหน้าเว็บ (Custom CSS ร่วมกับ Bootstrap 5)
│
├── js/
│   ├── app.js                         # ควบคุมการทำงานหลัก (ดึงตารางสินค้า, ระบบค้นหา, ปุ่มแก้ไข และปุ่มลบ)
│   ├── form.js                        # จัดการฟอร์มสินค้า (ตรวจสอบความถูกต้อง Validation, ส่งข้อมูล และแจ้งเตือน Alert)
│   └── loaddata.js                    # ฟังก์ชันโหลดตัวเลือก Dropdown (หมวดหมู่ และ ผู้จัดจำหน่าย) จาก API
│
├── inc/
│   └── connDB.php                     # ฟังก์ชันเชื่อมต่อฐานข้อมูล MySQL ด้วย PDO (รองรับทั้ง Local และ Cloud)
│
└── api/
    ├── .htaccess                      # กำหนด Rewrite URL เพื่อส่งทุกคำขอมาประมวลผลที่ index.php
    ├── index.php                      # จุดรับคำขอหลักของ API (Entry Point) และลงทะเบียน Route ทั้งหมด
    │
    ├── core/
    │   ├── Router.php                 # ตัวจัดการเส้นทาง API (ตรวจสอบ Method และ Path เช่น /products/{id})
    │   └── Response.php               # คลาสจัดรูปแบบข้อมูลตอบกลับเป็น JSON มาตรฐานเดียวกันทั้งระบบ
    │
    └── controllers/
        ├── ProductController.php      # จัดการข้อมูลสินค้า CRUD (ค้นหา, เพิ่ม, ดึงข้อมูล, แก้ไข และลบ)
        ├── CategoryController.php     # ดึงข้อมูลหมวดหมู่สินค้าสำหรับ Dropdown (Read-only)
        └── SupplierController.php     # ดึงข้อมูลผู้จัดจำหน่ายสินค้าสำหรับ Dropdown (Read-only)
```

---

## API Endpoints

การติดต่อสื่อสารระหว่างหน้าเว็บกับฐานข้อมูลจะกระทำผ่าน RESTful API โดยส่งและรับข้อมูลในรูปแบบ **JSON** ผ่าน Endpoint ดังต่อไปนี้:

| Method | Endpoint Path | หน้าที่การทำงาน | ข้อมูลที่ส่งกลับ |
|:---|:---|:---|:---|
| `GET` | `/api/products` | ดึงรายการสินค้าทั้งหมด (รองรับ Parameter `?search=คำค้น`) | รายการสินค้าแบบ JSON Array |
| `GET` | `/api/products/{id}` | ดึงข้อมูลสินค้าเฉพาะรายการตามรหัส ID | ข้อมูลสินค้าแบบ JSON Object |
| `POST` | `/api/products` | เพิ่มข้อมูลสินค้าใหม่เข้าระบบ | สถานะการทำงาน และ ID ของสินค้าใหม่ |
| `PUT` | `/api/products/{id}` | บันทึกการแก้ไขข้อมูลสินค้าตามรหัส ID | สถานะการอัปเดตข้อมูล |
| `DELETE` | `/api/products/{id}` | ลบข้อมูลสินค้าออกจากระบบตามรหัส ID | สถานะการลบข้อมูล |
| `GET` | `/api/categories` | ดึงรายชื่อหมวดหมู่สินค้าทั้งหมดสำหรับ Dropdown | รายชื่อหมวดหมู่แบบ JSON Array |
| `GET` | `/api/suppliers` | ดึงรายชื่อผู้จัดจำหน่ายทั้งหมดสำหรับ Dropdown | รายชื่อผู้จัดจำหน่ายแบบ JSON Array |
