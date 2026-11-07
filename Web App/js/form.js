/**
 * ─────────────────────────────────────────────────────────────────────────────
 * form.js — Form Management & Validation Controller
 * ─────────────────────────────────────────────────────────────────────────────
 * [Full-stack Dev Note]:
 * รับผิดชอบการกรอกข้อมูลและส่ง Request เข้าสู่ API:
 * 1. โหลดข้อมูลลง Select Dropdown ด้วย loadSelectOptions() จาก Workshop-w10
 * 2. Client-side Validation ตรวจสอบก่อนส่งข้อมูล (ชื่อไม่เกิน 30 ตัว, ราคา >= 0)
 * 3. จัดการ State ระหว่าง "โหมดเพิ่มสินค้า (Insert)" และ "โหมดแก้ไขสินค้า (Update)"
 * 4. รับมือ Response ทั้งกรณีสำเร็จ (200/201) และกรณี Server Validation Error (422)
 * 5. ฟังก์ชัน resetForm() คืนค่าฟอร์มกลับสู่สถานะเริ่มต้น
 * ─────────────────────────────────────────────────────────────────────────────
 */

const FORM_API_BASE = 'api';

document.addEventListener('DOMContentLoaded', function () {

    /* ── DOM Elements ── */
    const form          = document.getElementById('productForm');
    const btnSubmit     = document.getElementById('btnSubmit');
    const btnSubmitText = document.getElementById('btnSubmitText');
    const btnSubmitIcon = document.getElementById('btnSubmitIcon');
    const btnReset      = document.getElementById('btnReset');
    const serverError   = document.getElementById('serverErrorAlert');
    const serverErrList = document.getElementById('serverErrorList');
    const updateBanner  = document.getElementById('updateStatusBanner');

    /* ── โหลดตัวเลือกใน Dropdown หมวดหมู่ และ ผู้จำหน่าย จาก API (เทคนิคจาก Workshop-w10) ── */
    loadSelectOptions(`${FORM_API_BASE}/categories`, 'categoryId', '-- เลือกหมวดหมู่สินค้า --');
    loadSelectOptions(`${FORM_API_BASE}/suppliers`,  'supplierId', '-- เลือกผู้จัดจำหน่าย --');

    /* ══════════════════════════════════════════════════════════════════════════
       1. Client-side Validation (ตรวจสอบความถูกต้องก่อนยิง Request)
       ══════════════════════════════════════════════════════════════════════════ */
    function validateForm() {
        let isValid = true;

        // เคลียร์สถานะ Error เก่าออกก่อน
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        // 1. ตรวจสอบชื่อสินค้า
        const productName = document.getElementById('productName');
        const pNameVal    = productName.value.trim();
        if (!pNameVal) {
            setInvalid(productName, 'กรุณากรอกชื่อสินค้า');
            isValid = false;
        } else if (pNameVal.length > 30) {
            setInvalid(productName, 'ชื่อสินค้าต้องยาวไม่เกิน 30 ตัวอักษร');
            isValid = false;
        }

        // 2. ตรวจสอบหมวดหมู่สินค้า
        const categoryId = document.getElementById('categoryId');
        if (!categoryId.value) {
            setInvalid(categoryId, 'กรุณาเลือกหมวดหมู่สินค้า');
            isValid = false;
        }

        // 3. ตรวจสอบผู้จัดจำหน่าย
        const supplierId = document.getElementById('supplierId');
        if (!supplierId.value) {
            setInvalid(supplierId, 'กรุณาเลือกผู้จัดจำหน่าย');
            isValid = false;
        }

        // 4. ตรวจสอบหน่วยนับสินค้า
        const unit    = document.getElementById('unit');
        const unitVal = unit.value.trim();
        if (!unitVal) {
            setInvalid(unit, 'กรุณากรอกหน่วยนับสินค้า (เช่น กล่อง, ขวด, ชิ้น)');
            isValid = false;
        } else if (unitVal.length > 30) {
            setInvalid(unit, 'หน่วยนับสินค้าต้องยาวไม่เกิน 30 ตัวอักษร');
            isValid = false;
        }

        // 5. ตรวจสอบราคาสินค้า
        const price = document.getElementById('price');
        if (!price.value || isNaN(price.value)) {
            setInvalid(price, 'กรุณากรอกราคาสินค้าเป็นตัวเลข');
            isValid = false;
        } else if (parseFloat(price.value) < 0) {
            setInvalid(price, 'ราคาสินค้าต้องไม่ต่ำกว่า 0 บาท');
            isValid = false;
        }

        return isValid;
    }

    function setInvalid(el, message) {
        el.classList.add('is-invalid');
        const parent = el.closest('.input-group') || el.parentElement;
        let feedback = parent.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            parent.appendChild(feedback);
        }
        feedback.textContent = message;
    }

    /* ══════════════════════════════════════════════════════════════════════════
       2. จัดการ Form Submission (Insert / Update)
       ══════════════════════════════════════════════════════════════════════════ */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        serverError.classList.add('d-none');

        // ตรวจสอบข้อมูลก่อน ถ้าไม่ผ่านให้หยุดทำงาน
        if (!validateForm()) return;

        // ปรับปุ่ม Submit เป็นสถานะกำลังประมวลผล (ป้องกันการกดส่งข้อมูลซ้ำ)
        btnSubmit.disabled        = true;
        btnSubmitText.textContent = 'กำลังบันทึกข้อมูล...';
        btnSubmitIcon.className   = 'spinner-border spinner-border-sm me-2';

        const action    = document.getElementById('formAction').value; // 'insert' หรือ 'update'
        const productId = document.getElementById('productId').value;

        // ประกอบ Payload ตาม JSON Contract ของ API
        const payload = {
            productName: document.getElementById('productName').value.trim(),
            categoryId:  document.getElementById('categoryId').value,
            supplierId:  document.getElementById('supplierId').value,
            unit:        document.getElementById('unit').value.trim(),
            price:       document.getElementById('price').value
        };

        try {
            let url, method;

            if (action === 'update') {
                url    = `${FORM_API_BASE}/products/${productId}`;
                method = 'PUT';
            } else {
                url    = `${FORM_API_BASE}/products`;
                method = 'POST';
            }

            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            // ตรวจสอบผลลัพธ์
            if (!response.ok || !result.success) {
                // หากมี Error จากฝั่ง Server Validation (HTTP 422)
                if (result.errors && Array.isArray(result.errors)) {
                    showServerErrors(result.errors);
                } else {
                    throw new Error(result.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                }
                return;
            }

            // แจ้งเตือนเมื่อสำเร็จ
            const resultId     = result.data?.id || productId || '-';
            const successTitle = action === 'update' ? 'อัปเดตข้อมูลสำเร็จ!' : 'บันทึกข้อมูลสำเร็จ!';
            const successMsg   = action === 'update' ? 'แก้ไขข้อมูลสินค้าเรียบร้อยแล้ว' : 'เพิ่มสินค้าใหม่เข้าระบบเรียบร้อยแล้ว';

            showSuccessModal(successTitle, successMsg, `
                <div class="mb-1"><strong>รหัสสินค้า:</strong> <span class="badge bg-primary fs-6">#${resultId}</span></div>
                <div class="mb-1"><strong>ชื่อสินค้า:</strong> ${escapeHtml(payload.productName)}</div>
                <div class="mb-1"><strong>หน่วยนับ:</strong> ${escapeHtml(payload.unit)}</div>
                <div class="mb-1"><strong>ราคา:</strong> ฿${formatPrice(payload.price)}</div>
            `);

            // รีเซ็ตฟอร์มและรีเฟรชตารางข้อมูล
            resetForm();
            loadProducts(document.getElementById('searchInput').value);

        } catch (error) {
            showToast('danger', error.message);
            console.error('Submit form error:', error);
        } finally {
            btnSubmit.disabled = false;
            const isUpdate     = document.getElementById('formAction').value === 'update';
            btnSubmitText.textContent = isUpdate ? 'อัปเดตข้อมูลสินค้า' : 'บันทึกข้อมูลสินค้า';
            btnSubmitIcon.className   = isUpdate ? 'bi bi-pencil-square me-2' : 'bi bi-cloud-arrow-up-fill me-2';
        }
    });

    /* ══════════════════════════════════════════════════════════════════════════
       3. แสดงข้อผิดพลาดที่ตอบกลับมาจาก Server (Server-side Error List)
       ══════════════════════════════════════════════════════════════════════════ */
    function showServerErrors(errors) {
        serverError.classList.remove('d-none');
        serverErrList.innerHTML = errors.map(err => `<li>${escapeHtml(err)}</li>`).join('');
        serverError.scrollIntoView({ behavior: 'smooth' });
    }

    /* ══════════════════════════════════════════════════════════════════════════
       4. คืนค่าเริ่มต้นให้กับฟอร์ม (Reset Form)
       ══════════════════════════════════════════════════════════════════════════ */
    window.resetForm = function () {
        form.reset();
        document.getElementById('productId').value  = '';
        document.getElementById('formAction').value = 'insert';
        updateBanner.classList.add('d-none');

        // คืนค่าปุ่ม Submit กลับเป็นโหมด Insert
        btnSubmitText.textContent = 'บันทึกข้อมูลสินค้า';
        btnSubmitIcon.className   = 'bi bi-cloud-arrow-up-fill me-2';

        // ล้างสถานะ validation
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        serverError.classList.add('d-none');

        // รีเซ็ต Dropdown
        document.getElementById('categoryId').selectedIndex = 0;
        document.getElementById('supplierId').selectedIndex = 0;
    };

    btnReset.addEventListener('click', resetForm);

    // Event สำหรับปุ่ม "เพิ่มสินค้าใหม่" ภายใน Success Modal
    const modalResetBtn = document.getElementById('modalResetBtn');
    if (modalResetBtn) {
        modalResetBtn.addEventListener('click', () => {
            resetForm();
            document.getElementById('formSection').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('productName').focus();
        });
    }
});
