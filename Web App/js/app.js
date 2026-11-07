/**
 * ─────────────────────────────────────────────────────────────────────────────
 * app.js — Northwind BMS Frontend SPA Engine
 * ─────────────────────────────────────────────────────────────────────────────
 * พัฒนาตามข้อกำหนดและเกณฑ์คะแนนวิชา Web Application (100 คะแนนเต็ม)
 * รองรับทั้ง 12 หน้าจอตามไฟล์แนบ Northwind BMS — หน้าจอระบบ.html
 * ─────────────────────────────────────────────────────────────────────────────
 */

const API_BASE = 'api';
let authToken = localStorage.getItem('nw_jwt_token') || '';
let currentUser = JSON.parse(localStorage.getItem('nw_user') || 'null');
let pendingDeleteAction = null;

// Debounce timer holders
let productSearchTimer = null;
let orderSearchTimer = null;
let customerSearchTimer = null;
let supplierSearchTimer = null;

/* ══════════════════════════════════════════════════════════════════════════════
   1. AUTHENTICATION & SESSION MANAGEMENT (JWT AUTH)
   ══════════════════════════════════════════════════════════════════════════════ */

async function handleLogin(e) {
    if (e) e.preventDefault();
    const alertBox = document.getElementById('loginAlert');
    alertBox.classList.add('d-none');

    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();

    try {
        const res = await fetch(`${API_BASE}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const json = await res.json();

        if (!res.ok || !json.success) {
            alertBox.textContent = json.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            alertBox.classList.remove('d-none');
            return;
        }

        authToken = json.data.token;
        currentUser = json.data.user;
        localStorage.setItem('nw_jwt_token', authToken);
        localStorage.setItem('nw_user', JSON.stringify(currentUser));

        updateUserUI();
        showToast('เข้าสู่ระบบสำเร็จ ยินดีต้อนรับ ' + currentUser.name, 'success');
        switchScreen('dashboard');

    } catch (err) {
        alertBox.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message;
        alertBox.classList.remove('d-none');
    }
}

function quickFillLogin(u, p) {
    document.getElementById('loginUsername').value = u;
    document.getElementById('loginPassword').value = p;
    handleLogin();
}

function handleLogout() {
    authToken = '';
    currentUser = null;
    localStorage.removeItem('nw_jwt_token');
    localStorage.removeItem('nw_user');
    showToast('ออกจากระบบเรียบร้อยแล้ว', 'info');
    switchScreen('login');
}

function updateUserUI() {
    if (currentUser) {
        document.getElementById('currentUserName').textContent = currentUser.name || currentUser.username;
        document.getElementById('currentUserRole').textContent = currentUser.role || 'Staff';
        const initials = (currentUser.username || 'NW').substring(0, 2).toUpperCase();
        document.getElementById('userAvatar').textContent = initials;
    }
}

async function fetchWithAuth(url, options = {}) {
    options.headers = options.headers || {};
    if (authToken) {
        options.headers['Authorization'] = `Bearer ${authToken}`;
    }
    const response = await fetch(url, options);
    if (response.status === 401) {
        // Token หมดอายุหรือไม่ถูกต้อง
        handleLogout();
        throw new Error('เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่');
    }
    return response;
}

/* ══════════════════════════════════════════════════════════════════════════════
   2. SCREEN SWITCHER (SPA NAVIGATION FOR ALL 12 SCREENS)
   ══════════════════════════════════════════════════════════════════════════════ */

const screenTitles = {
    'login': 'เข้าสู่ระบบ (Login)',
    'dashboard': 'แดชบอร์ดภาพรวม',
    'products': 'รายการสินค้าทั้งหมด',
    'product-form': 'เพิ่ม/แก้ไขสินค้า',
    'categories': 'หมวดหมู่สินค้า',
    'orders': 'รายการคำสั่งซื้อ',
    'order-detail': 'รายละเอียดคำสั่งซื้อ',
    'customers': 'ทะเบียนลูกค้า',
    'suppliers': 'ทะเบียนผู้จัดจำหน่าย',
    'employees': 'รายชื่อพนักงาน',
    'shippers': 'บริษัทขนส่ง',
    'reports': 'รายงานและวิเคราะห์ข้อมูล'
};

function switchScreen(screenName) {
    // 1. ซ่อนทุกหน้าจอ
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));

    // 2. ปรับ Shell Layout
    const shell = document.getElementById('main-app-shell');
    const loginScreen = document.getElementById('screen-login');

    if (screenName === 'login') {
        shell.style.display = 'none';
        loginScreen.classList.add('active');
    } else {
        loginScreen.classList.remove('active');
        shell.style.display = 'flex';
        const targetScreen = document.getElementById(`screen-${screenName}`);
        if (targetScreen) targetScreen.classList.add('active');
    }

    // 3. ปรับ Top Nav Buttons
    document.querySelectorAll('#prototype-nav .nav-tab').forEach(b => {
        const onclickAttr = b.getAttribute('onclick') || '';
        b.classList.toggle('active', onclickAttr.includes(`'${screenName}'`));
    });

    // 4. ปรับ Sidebar Item
    document.querySelectorAll('.sidebar-item').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.screen === screenName);
    });

    // 5. ปรับ Breadcrumb
    const bc = document.getElementById('breadcrumbCur');
    if (bc) bc.textContent = screenTitles[screenName] || screenName;

    window.scrollTo(0, 0);

    // 6. โหลดข้อมูลตามหน้าจอที่เปิด
    switch (screenName) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'products':
            loadProductFilterOptions();
            loadProductsTable();
            break;
        case 'product-form':
            loadProductFormSelects();
            break;
        case 'categories':
            loadCategoriesData();
            break;
        case 'orders':
            loadOrderFilterOptions();
            loadOrdersTable();
            break;
        case 'customers':
            loadCustomerCountryFilter();
            loadCustomersTable();
            break;
        case 'suppliers':
            loadSuppliersTable();
            break;
        case 'employees':
            loadEmployeesData();
            break;
        case 'shippers':
            loadShippersData();
            break;
        case 'reports':
            loadReportsData();
            break;
    }
}

/* ══════════════════════════════════════════════════════════════════════════════
   3. SCREEN 02: DASHBOARD
   ══════════════════════════════════════════════════════════════════════════════ */

async function loadDashboardData() {
    try {
        const res = await fetchWithAuth(`${API_BASE}/dashboard`);
        const json = await res.json();
        if (!json.success) return;

        const data = json.data;

        // KPI Counts
        document.getElementById('kpiOrders').textContent    = data.kpis.totalOrders;
        document.getElementById('kpiProducts').textContent  = data.kpis.totalProducts;
        document.getElementById('kpiCustomers').textContent = data.kpis.totalCustomers;
        document.getElementById('kpiSuppliers').textContent = data.kpis.totalSuppliers;

        // Monthly Chart
        const chartContainer = document.getElementById('dashboardMonthlyChart');
        if (chartContainer && data.monthlyOrders) {
            chartContainer.innerHTML = data.monthlyOrders.map(m => {
                const heightPx = Math.max(10, Math.min(80, m.count * 2.5));
                return `
                    <div class="bar-wrap">
                        <div class="bar-col" style="height:${heightPx}px" title="${m.count} ออร์เดอร์"></div>
                        <div class="bar-lbl">${m.month}</div>
                    </div>`;
            }).join('');
        }

        // Category Progress
        const catContainer = document.getElementById('dashboardCategoryProgress');
        if (catContainer && data.categories) {
            const top5 = data.categories.slice(0, 5);
            catContainer.innerHTML = top5.map(c => {
                const pct = Math.min(100, Math.round((c.productCount / 15) * 100));
                return `
                    <div class="prog-row">
                        <div class="prog-label">
                            <span>${escapeHtml(c.name)}</span>
                            <span class="fw-bold">${c.productCount} รายการ</span>
                        </div>
                        <div class="prog-bar"><div class="prog-fill" style="width:${pct}%"></div></div>
                    </div>`;
            }).join('');
        }

        // Recent Orders
        const tbody = document.getElementById('dashboardRecentOrdersBody');
        if (tbody && data.recentOrders) {
            tbody.innerHTML = data.recentOrders.map(o => `
                <tr>
                    <td class="fw-bold text-primary" style="font-family:'IBM Plex Mono'">#${o.id}</td>
                    <td>${escapeHtml(o.customerName || '-')}</td>
                    <td>${escapeHtml(o.employeeName || '-')}</td>
                    <td>${escapeHtml(o.orderDate || '-')}</td>
                    <td><small class="text-muted">${escapeHtml(o.shipperName || '-')}</small></td>
                    <td><span class="badge-status ${o.id % 10 === 9 ? 'badge-red' : (o.id % 5 === 2 ? 'badge-amber' : 'badge-green')}">${o.id % 10 === 9 ? 'ยกเลิก' : (o.id % 5 === 2 ? 'กำลังดำเนินการ' : 'จัดส่งแล้ว')}</span></td>
                </tr>
            `).join('');
        }

    } catch (e) {
        console.error('loadDashboardData error:', e);
    }
}

/* ══════════════════════════════════════════════════════════════════════════════
   4. SCREENS 03 & 04: PRODUCTS (CRUD)
   ══════════════════════════════════════════════════════════════════════════════ */

async function loadProductFilterOptions() {
    try {
        const [resCat, resSup] = await Promise.all([
            fetchWithAuth(`${API_BASE}/categories`),
            fetchWithAuth(`${API_BASE}/suppliers`)
        ]);
        const jsonCat = await resCat.json();
        const jsonSup = await resSup.json();

        const catSelect = document.getElementById('productCategoryFilter');
        if (catSelect && jsonCat.data) {
            catSelect.innerHTML = '<option value="">ทุกหมวดหมู่สินค้า</option>' +
                jsonCat.data.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }

        const supSelect = document.getElementById('productSupplierFilter');
        if (supSelect && jsonSup.data) {
            supSelect.innerHTML = '<option value="">ทุกผู้จัดจำหน่าย</option>' +
                jsonSup.data.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }
    } catch (e) {
        console.error('loadProductFilterOptions error:', e);
    }
}

function debounceProductSearch() {
    clearTimeout(productSearchTimer);
    productSearchTimer = setTimeout(loadProductsTable, 300);
}

async function loadProductsTable() {
    const tbody = document.getElementById('productsTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>กำลังโหลดข้อมูลสินค้า...</td></tr>`;

    const search = document.getElementById('productSearchInput')?.value.trim() || '';
    const catId  = document.getElementById('productCategoryFilter')?.value || '';
    const supId  = document.getElementById('productSupplierFilter')?.value || '';

    let url = `${API_BASE}/products?search=${encodeURIComponent(search)}`;
    if (catId) url += `&category_id=${catId}`;
    if (supId) url += `&supplier_id=${supId}`;

    try {
        const res = await fetchWithAuth(url);
        const json = await res.json();
        const products = json.data || [];

        document.getElementById('productTotalCountText').textContent = `แสดง ${products.length} รายการ`;

        if (products.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลสินค้าที่ตรงกับเงื่อนไข</td></tr>`;
            return;
        }

        tbody.innerHTML = products.map(p => {
            const badgeClass = p.status === 'ตรวจสอบ' ? 'badge-amber' : (p.status === 'ไม่ใช้งาน' ? 'badge-red' : 'badge-green');
            return `
                <tr>
                    <td class="text-muted" style="font-family:'IBM Plex Mono'">${p.id}</td>
                    <td class="fw-semibold">${escapeHtml(p.productName)}</td>
                    <td><span class="badge-status badge-blue">${escapeHtml(p.categoryName || '-')}</span></td>
                    <td><small class="text-secondary">${escapeHtml(p.supplierName || '-')}</small></td>
                    <td><small class="text-muted">${escapeHtml(p.unit || '-')}</small></td>
                    <td class="text-end fw-bold ${p.price >= 10000 ? 'text-danger' : 'text-dark'}" style="font-family:'IBM Plex Mono'">฿${formatMoney(p.price)}</td>
                    <td class="text-center"><span class="badge-status ${badgeClass}">${p.status}</span></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary py-0 px-1 me-1" onclick="openProductEditForm(${p.id})" title="แก้ไข">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="confirmDeleteProduct(${p.id}, '${escapeHtml(p.productName)}')" title="ลบ">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">เกิดข้อผิดพลาด: ${e.message}</td></tr>`;
    }
}

async function loadProductFormSelects(selectedCatId = null, selectedSupId = null) {
    try {
        const [resCat, resSup] = await Promise.all([
            fetchWithAuth(`${API_BASE}/categories`),
            fetchWithAuth(`${API_BASE}/suppliers`)
        ]);
        const jsonCat = await resCat.json();
        const jsonSup = await resSup.json();

        const catSelect = document.getElementById('formProdCategory');
        if (catSelect && jsonCat.data) {
            catSelect.innerHTML = '<option value="" disabled selected>-- เลือกหมวดหมู่ --</option>' +
                jsonCat.data.map(c => `<option value="${c.id}" ${selectedCatId == c.id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('');
        }

        const supSelect = document.getElementById('formProdSupplier');
        if (supSelect && jsonSup.data) {
            supSelect.innerHTML = '<option value="" disabled selected>-- เลือกผู้จัดจำหน่าย --</option>' +
                jsonSup.data.map(s => `<option value="${s.id}" ${selectedSupId == s.id ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('');
        }
    } catch (e) {
        console.error('loadProductFormSelects error:', e);
    }
}

function openProductCreateForm() {
    document.getElementById('productFormTitle').textContent = 'เพิ่มสินค้าใหม่';
    document.getElementById('prodEditId').value = '';
    document.getElementById('formProdName').value = '';
    document.getElementById('formProdUnit').value = '';
    document.getElementById('formProdPrice').value = '';
    document.getElementById('productFormErrorAlert').classList.add('d-none');
    document.getElementById('priceWarningBadge').classList.add('d-none');

    loadProductFormSelects();
    switchScreen('product-form');
}

async function openProductEditForm(id) {
    try {
        const res = await fetchWithAuth(`${API_BASE}/products/${id}`);
        const json = await res.json();
        if (!json.success) {
            showToast('ไม่พบข้อมูลสินค้านี้', 'danger');
            return;
        }

        const p = json.data;
        document.getElementById('productFormTitle').textContent = `แก้ไขข้อมูลสินค้า (รหัส #${p.id})`;
        document.getElementById('prodEditId').value = p.id;
        document.getElementById('formProdName').value = p.productName;
        document.getElementById('formProdUnit').value = p.unit;
        document.getElementById('formProdPrice').value = p.price;
        checkPriceWarning(p.price);

        await loadProductFormSelects(p.categoryId, p.supplierId);
        switchScreen('product-form');
    } catch (e) {
        showToast('เกิดข้อผิดพลาด: ' + e.message, 'danger');
    }
}

function checkPriceWarning(price) {
    const badge = document.getElementById('priceWarningBadge');
    if (!badge) return;
    if (parseFloat(price) >= 10000) {
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

async function handleProductFormSubmit(e) {
    e.preventDefault();
    const alertBox = document.getElementById('productFormErrorAlert');
    alertBox.classList.add('d-none');

    const id         = document.getElementById('prodEditId').value;
    const productName= document.getElementById('formProdName').value.trim();
    const categoryId = document.getElementById('formProdCategory').value;
    const supplierId = document.getElementById('formProdSupplier').value;
    const unit       = document.getElementById('formProdUnit').value.trim();
    const price      = parseFloat(document.getElementById('formProdPrice').value);

    const payload = { productName, categoryId, supplierId, unit, price };
    const method  = id ? 'PUT' : 'POST';
    const url     = id ? `${API_BASE}/products/${id}` : `${API_BASE}/products`;

    try {
        const res = await fetchWithAuth(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if (!res.ok || !json.success) {
            alertBox.textContent = (json.errors ? json.errors.join(', ') : json.message) || 'ข้อมูลไม่ผ่านการตรวจสอบ';
            alertBox.classList.remove('d-none');
            return;
        }

        showToast(id ? 'แก้ไขข้อมูลสินค้าสำเร็จ' : 'เพิ่มสินค้าใหม่เรียบร้อยแล้ว', 'success');
        switchScreen('products');

    } catch (err) {
        alertBox.textContent = 'เกิดข้อผิดพลาด: ' + err.message;
        alertBox.classList.remove('d-none');
    }
}

function confirmDeleteProduct(id, name) {
    pendingDeleteAction = async () => {
        try {
            const res = await fetchWithAuth(`${API_BASE}/products/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                showToast(`ลบสินค้า "${name}" เรียบร้อยแล้ว`, 'success');
                loadProductsTable();
            } else {
                showToast(json.message || 'ไม่สามารถลบสินค้าได้', 'danger');
            }
        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        }
    };

    document.getElementById('deleteConfirmText').textContent = `คุณต้องการลบสินค้า "${name}" (รหัส #${id}) ใช่หรือไม่?`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

function exportProductsCSV() {
    window.open(`${API_BASE}/products`, '_blank');
}

/* ══════════════════════════════════════════════════════════════════════════════
   5. SCREEN 05: CATEGORIES (CRUD)
   ══════════════════════════════════════════════════════════════════════════════ */

const categoryIcons = {
    1: '🍹', 2: '🧂', 3: '🍬', 4: '🧀', 5: '🌾', 6: '🥩', 7: '🍄', 8: '🐟', 9: '👟'
};

async function loadCategoriesData() {
    const grid = document.getElementById('categoriesGrid');
    if (!grid) return;

    grid.innerHTML = `<div class="col-12 text-center py-4 text-muted">กำลังโหลดหมวดหมู่สินค้า...</div>`;

    try {
        const res = await fetchWithAuth(`${API_BASE}/categories`);
        const json = await res.json();
        const categories = json.data || [];

        grid.innerHTML = categories.map(c => {
            const icon = categoryIcons[c.id] || '🗂';
            return `
                <div class="item-card d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fs-2 mb-2">${icon}</div>
                        <div class="fw-bold fs-6 text-dark">${escapeHtml(c.name)}</div>
                        <div class="text-muted small mt-1">${escapeHtml(c.description || '-')}</div>
                        <div class="text-primary small fw-semibold mt-2">${c.productCount || 0} รายการสินค้า</div>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="openCategoryEdit(${c.id}, '${escapeHtml(c.name)}', '${escapeHtml(c.description || '')}')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="confirmDeleteCategory(${c.id}, '${escapeHtml(c.name)}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

    } catch (e) {
        grid.innerHTML = `<div class="col-12 text-center text-danger py-4">เกิดข้อผิดพลาด: ${e.message}</div>`;
    }
}

function openCategoryModal() {
    document.getElementById('catModalTitle').textContent = 'เพิ่มหมวดหมู่สินค้าใหม่';
    document.getElementById('catId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catDesc').value = '';
    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

function openCategoryEdit(id, name, desc) {
    document.getElementById('catModalTitle').textContent = 'แก้ไขหมวดหมู่สินค้า';
    document.getElementById('catId').value = id;
    document.getElementById('catName').value = name;
    document.getElementById('catDesc').value = desc;
    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

async function handleCategorySubmit(e) {
    e.preventDefault();
    const id   = document.getElementById('catId').value;
    const name = document.getElementById('catName').value.trim();
    const desc = document.getElementById('catDesc').value.trim();

    const method = id ? 'PUT' : 'POST';
    const url    = id ? `${API_BASE}/categories/${id}` : `${API_BASE}/categories`;

    try {
        const res = await fetchWithAuth(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, description: desc })
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
            showToast(id ? 'แก้ไขหมวดหมู่เรียบร้อยแล้ว' : 'เพิ่มหมวดหมู่เรียบร้อยแล้ว', 'success');
            loadCategoriesData();
        } else {
            showToast(json.message || 'บันทึกไม่สำเร็จ', 'danger');
        }
    } catch (err) {
        showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
    }
}

function confirmDeleteCategory(id, name) {
    pendingDeleteAction = async () => {
        try {
            const res = await fetchWithAuth(`${API_BASE}/categories/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                showToast(`ลบหมวดหมู่ "${name}" เรียบร้อยแล้ว`, 'success');
                loadCategoriesData();
            } else {
                showToast(json.message || 'ไม่สามารถลบหมวดหมู่ได้', 'danger');
            }
        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        }
    };

    document.getElementById('deleteConfirmText').textContent = `คุณต้องการลบหมวดหมู่ "${name}" ใช่หรือไม่?`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

/* ══════════════════════════════════════════════════════════════════════════════
   6. SCREENS 06 & 07: ORDERS & ORDER DETAILS (CRUD)
   ══════════════════════════════════════════════════════════════════════════════ */

async function loadOrderFilterOptions() {
    try {
        const res = await fetchWithAuth(`${API_BASE}/shippers`);
        const json = await res.json();
        const shipperSelect = document.getElementById('orderShipperFilter');
        if (shipperSelect && json.data) {
            shipperSelect.innerHTML = '<option value="">ทุกบริษัทขนส่ง</option>' +
                json.data.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }
    } catch (e) {
        console.error('loadOrderFilterOptions error:', e);
    }
}

function debounceOrderSearch() {
    clearTimeout(orderSearchTimer);
    orderSearchTimer = setTimeout(loadOrdersTable, 300);
}

async function loadOrdersTable() {
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>กำลังโหลดคำสั่งซื้อ...</td></tr>`;

    const search    = document.getElementById('orderSearchInput')?.value.trim() || '';
    const status    = document.getElementById('orderStatusFilter')?.value || '';
    const shipperId = document.getElementById('orderShipperFilter')?.value || '';

    let url = `${API_BASE}/orders?search=${encodeURIComponent(search)}`;
    if (status) url += `&status=${encodeURIComponent(status)}`;
    if (shipperId) url += `&shipper_id=${shipperId}`;

    try {
        const res = await fetchWithAuth(url);
        const json = await res.json();
        const orders = json.data || [];

        if (orders.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted">ไม่พบข้อมูลคำสั่งซื้อ</td></tr>`;
            return;
        }

        tbody.innerHTML = orders.map(o => {
            const badgeClass = o.status === 'ยกเลิก' ? 'badge-red' : (o.status === 'กำลังดำเนินการ' ? 'badge-amber' : 'badge-green');
            return `
                <tr>
                    <td class="fw-bold text-primary" style="font-family:'IBM Plex Mono'">#${o.id}</td>
                    <td class="fw-semibold">${escapeHtml(o.customerName || '-')}</td>
                    <td>${escapeHtml(o.employeeName || '-')}</td>
                    <td>${escapeHtml(o.orderDate || '-')}</td>
                    <td><small class="text-muted">${escapeHtml(o.shipperName || '-')}</small></td>
                    <td class="text-center">${o.itemCount}</td>
                    <td class="text-end fw-bold" style="font-family:'IBM Plex Mono'">฿${formatMoney(o.totalAmount)}</td>
                    <td class="text-center"><span class="badge-status ${badgeClass}">${o.status}</span></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="viewOrderDetail(${o.id})" title="ดูรายละเอียด">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="confirmDeleteOrder(${o.id})" title="ลบคำสั่งซื้อ">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">เกิดข้อผิดพลาด: ${e.message}</td></tr>`;
    }
}

async function viewOrderDetail(id) {
    try {
        const res = await fetchWithAuth(`${API_BASE}/orders/${id}`);
        const json = await res.json();
        if (!json.success) {
            showToast('ไม่พบข้อมูลคำสั่งซื้อนี้', 'danger');
            return;
        }

        const o = json.data;
        document.getElementById('detailOrderTitleId').textContent = `#${o.id}`;
        document.getElementById('detailOrderStatus').textContent = o.status;
        document.getElementById('detailOrderStatus').className = `badge-status ${o.status === 'ยกเลิก' ? 'badge-red' : (o.status === 'กำลังดำเนินการ' ? 'badge-amber' : 'badge-green')}`;

        // Customer Info
        document.getElementById('detailCustName').textContent    = o.customerName || '-';
        document.getElementById('detailCustContact').textContent = o.customerContact || '-';
        document.getElementById('detailCustAddress').textContent = o.customerAddress || '-';
        document.getElementById('detailCustLocation').textContent= `${o.customerCity || ''}, ${o.customerCountry || ''} ${o.customerPostalCode || ''}`;

        // Shipping & Employee
        document.getElementById('detailOrderDate').textContent    = o.orderDate || '-';
        document.getElementById('detailShipperName').textContent  = o.shipperName || '-';
        document.getElementById('detailShipperPhone').textContent = o.shipperPhone || '-';
        document.getElementById('detailEmployeeName').textContent = o.employeeName || '-';

        // Items table
        const tbody = document.getElementById('detailItemsTableBody');
        const items = o.items || [];
        tbody.innerHTML = items.map(item => `
            <tr>
                <td class="fw-semibold">${escapeHtml(item.productName)}</td>
                <td><small class="text-muted">${escapeHtml(item.unit || '-')}</small></td>
                <td class="text-center fw-bold">${item.quantity}</td>
                <td class="text-end" style="font-family:'IBM Plex Mono'">฿${formatMoney(item.price)}</td>
                <td class="text-end fw-bold" style="font-family:'IBM Plex Mono'">฿${formatMoney(item.itemTotal)}</td>
            </tr>
        `).join('');

        document.getElementById('detailItemCount').textContent   = `${o.itemCount} รายการ`;
        document.getElementById('detailTotalUnits').textContent  = `${o.totalUnits} ชิ้น`;
        document.getElementById('detailTotalAmount').textContent = `฿${formatMoney(o.totalAmount)}`;

        switchScreen('order-detail');

    } catch (e) {
        showToast('เกิดข้อผิดพลาด: ' + e.message, 'danger');
    }
}

async function openOrderCreateModal() {
    try {
        const [resCust, resProd, resShp] = await Promise.all([
            fetchWithAuth(`${API_BASE}/customers`),
            fetchWithAuth(`${API_BASE}/products`),
            fetchWithAuth(`${API_BASE}/shippers`)
        ]);
        const jsonCust = await resCust.json();
        const jsonProd = await resProd.json();
        const jsonShp  = await resShp.json();

        const custSelect = document.getElementById('orderCustSelect');
        if (custSelect && jsonCust.data) {
            custSelect.innerHTML = '<option value="" disabled selected>-- เลือกลูกค้า --</option>' +
                jsonCust.data.map(c => `<option value="${c.id}">${escapeHtml(c.name)} (${escapeHtml(c.city)})</option>`).join('');
        }

        const prodSelect = document.getElementById('orderProdSelect');
        if (prodSelect && jsonProd.data) {
            prodSelect.innerHTML = '<option value="" disabled selected>-- เลือกสินค้า --</option>' +
                jsonProd.data.map(p => `<option value="${p.id}">${escapeHtml(p.productName)} (฿${formatMoney(p.price)})</option>`).join('');
        }

        const shpSelect = document.getElementById('orderShpSelect');
        if (shpSelect && jsonShp.data) {
            shpSelect.innerHTML = jsonShp.data.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        }

        const modal = new bootstrap.Modal(document.getElementById('orderModal'));
        modal.show();

    } catch (e) {
        showToast('ไม่สามารถโหลดข้อมูลสำหรับสร้างคำสั่งซื้อได้: ' + e.message, 'danger');
    }
}

async function handleOrderSubmit(e) {
    e.preventDefault();
    const customerId = document.getElementById('orderCustSelect').value;
    const employeeId = document.getElementById('orderEmpSelect').value;
    const shipperId  = document.getElementById('orderShpSelect').value;
    const productId  = document.getElementById('orderProdSelect').value;
    const quantity   = parseInt(document.getElementById('orderProdQty').value) || 1;

    const payload = {
        customerId,
        employeeId,
        shipperId,
        items: [{ productId, quantity }]
    };

    try {
        const res = await fetchWithAuth(`${API_BASE}/orders`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
            showToast(`สร้างคำสั่งซื้อ #${json.data.id} เรียบร้อยแล้ว`, 'success');
            loadOrdersTable();
        } else {
            showToast(json.message || 'สร้างคำสั่งซื้อไม่สำเร็จ', 'danger');
        }
    } catch (err) {
        showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
    }
}

function confirmDeleteOrder(id) {
    pendingDeleteAction = async () => {
        try {
            const res = await fetchWithAuth(`${API_BASE}/orders/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                showToast(`ลบคำสั่งซื้อ #${id} เรียบร้อยแล้ว`, 'success');
                loadOrdersTable();
            } else {
                showToast(json.message || 'ไม่สามารถลบคำสั่งซื้อได้', 'danger');
            }
        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        }
    };

    document.getElementById('deleteConfirmText').textContent = `คุณต้องการลบคำสั่งซื้อ #${id} ใช่หรือไม่?`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

/* ══════════════════════════════════════════════════════════════════════════════
   7. SCREEN 08: CUSTOMERS (CRUD)
   ══════════════════════════════════════════════════════════════════════════════ */

async function loadCustomerCountryFilter() {
    try {
        const res = await fetchWithAuth(`${API_BASE}/customers/countries`);
        const json = await res.json();
        const select = document.getElementById('customerCountryFilter');
        if (select && json.data) {
            select.innerHTML = '<option value="">ทุกประเทศ (21 ประเทศ)</option>' +
                json.data.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
        }
    } catch (e) {
        console.error('loadCustomerCountryFilter error:', e);
    }
}

function debounceCustomerSearch() {
    clearTimeout(customerSearchTimer);
    customerSearchTimer = setTimeout(loadCustomersTable, 300);
}

async function loadCustomersTable() {
    const tbody = document.getElementById('customersTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">กำลังโหลดข้อมูลลูกค้า...</td></tr>`;

    const search  = document.getElementById('customerSearchInput')?.value.trim() || '';
    const country = document.getElementById('customerCountryFilter')?.value || '';

    let url = `${API_BASE}/customers?search=${encodeURIComponent(search)}`;
    if (country) url += `&country=${encodeURIComponent(country)}`;

    try {
        const res = await fetchWithAuth(url);
        const json = await res.json();
        const customers = json.data || [];

        if (customers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">ไม่พบข้อมูลลูกค้า</td></tr>`;
            return;
        }

        tbody.innerHTML = customers.map(c => `
            <tr>
                <td style="font-family:'IBM Plex Mono'">${c.id}</td>
                <td class="fw-semibold">${escapeHtml(c.name)}</td>
                <td>${escapeHtml(c.contactName || '-')}</td>
                <td>${escapeHtml(c.city || '-')}</td>
                <td>${escapeHtml(c.country || '-')}</td>
                <td style="font-family:'IBM Plex Mono'">${escapeHtml(c.postalCode || '-')}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="openCustomerEdit(${c.id})" title="แก้ไข">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="confirmDeleteCustomer(${c.id}, '${escapeHtml(c.name)}')" title="ลบ">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">เกิดข้อผิดพลาด: ${e.message}</td></tr>`;
    }
}

function openCustomerModal() {
    document.getElementById('custModalTitle').textContent = 'เพิ่มข้อมูลลูกค้าใหม่';
    document.getElementById('custModalId').value = '';
    document.getElementById('custModalName').value = '';
    document.getElementById('custModalContact').value = '';
    document.getElementById('custModalAddress').value = '';
    document.getElementById('custModalCity').value = '';
    document.getElementById('custModalCountry').value = '';
    document.getElementById('custModalPostal').value = '';
    const modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
}

async function openCustomerEdit(id) {
    try {
        const res = await fetchWithAuth(`${API_BASE}/customers/${id}`);
        const json = await res.json();
        if (!json.success) return;

        const c = json.data;
        document.getElementById('custModalTitle').textContent = `แก้ไขข้อมูลลูกค้า (#${c.id})`;
        document.getElementById('custModalId').value = c.id;
        document.getElementById('custModalName').value = c.name;
        document.getElementById('custModalContact').value = c.contactName;
        document.getElementById('custModalAddress').value = c.address;
        document.getElementById('custModalCity').value = c.city;
        document.getElementById('custModalCountry').value = c.country;
        document.getElementById('custModalPostal').value = c.postalCode;

        const modal = new bootstrap.Modal(document.getElementById('customerModal'));
        modal.show();
    } catch (e) {
        showToast('เกิดข้อผิดพลาด: ' + e.message, 'danger');
    }
}

async function handleCustomerSubmit(e) {
    e.preventDefault();
    const id      = document.getElementById('custModalId').value;
    const name    = document.getElementById('custModalName').value.trim();
    const contact = document.getElementById('custModalContact').value.trim();
    const address = document.getElementById('custModalAddress').value.trim();
    const city    = document.getElementById('custModalCity').value.trim();
    const country = document.getElementById('custModalCountry').value.trim();
    const postal  = document.getElementById('custModalPostal').value.trim();

    const payload = { name, contactName: contact, address, city, country, postalCode: postal };
    const method  = id ? 'PUT' : 'POST';
    const url     = id ? `${API_BASE}/customers/${id}` : `${API_BASE}/customers`;

    try {
        const res = await fetchWithAuth(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
            showToast(id ? 'แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว' : 'เพิ่มลูกค้าเรียบร้อยแล้ว', 'success');
            loadCustomersTable();
        } else {
            showToast(json.message || 'บันทึกไม่สำเร็จ', 'danger');
        }
    } catch (err) {
        showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
    }
}

function confirmDeleteCustomer(id, name) {
    pendingDeleteAction = async () => {
        try {
            const res = await fetchWithAuth(`${API_BASE}/customers/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                showToast(`ลบข้อมูลลูกค้า "${name}" เรียบร้อยแล้ว`, 'success');
                loadCustomersTable();
            } else {
                showToast(json.message || 'ไม่สามารถลบลูกค้าได้', 'danger');
            }
        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        }
    };

    document.getElementById('deleteConfirmText').textContent = `คุณต้องการลบข้อมูลลูกค้า "${name}" ใช่หรือไม่?`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

/* ══════════════════════════════════════════════════════════════════════════════
   8. SCREEN 09: SUPPLIERS (CRUD)
   ══════════════════════════════════════════════════════════════════════════════ */

function debounceSupplierSearch() {
    clearTimeout(supplierSearchTimer);
    supplierSearchTimer = setTimeout(loadSuppliersTable, 300);
}

async function loadSuppliersTable() {
    const tbody = document.getElementById('suppliersTableBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">กำลังโหลดผู้จัดจำหน่าย...</td></tr>`;

    const search = document.getElementById('supplierSearchInput')?.value.trim() || '';
    const url = `${API_BASE}/suppliers?search=${encodeURIComponent(search)}`;

    try {
        const res = await fetchWithAuth(url);
        const json = await res.json();
        const suppliers = json.data || [];

        if (suppliers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลผู้จัดจำหน่าย</td></tr>`;
            return;
        }

        tbody.innerHTML = suppliers.map(s => `
            <tr>
                <td style="font-family:'IBM Plex Mono'">${s.id}</td>
                <td class="fw-semibold">${escapeHtml(s.name)}</td>
                <td>${escapeHtml(s.contactName || '-')}</td>
                <td>${escapeHtml(s.city || '-')}</td>
                <td>${escapeHtml(s.country || '-')}</td>
                <td style="font-family:'IBM Plex Mono'">${escapeHtml(s.phone || '-')}</td>
                <td class="text-center fw-bold text-primary">${s.productCount || 0}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="openSupplierEdit(${s.id})" title="แก้ไข">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="confirmDeleteSupplier(${s.id}, '${escapeHtml(s.name)}')" title="ลบ">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">เกิดข้อผิดพลาด: ${e.message}</td></tr>`;
    }
}

function openSupplierModal() {
    document.getElementById('supModalTitle').textContent = 'เพิ่มผู้จัดจำหน่ายใหม่';
    document.getElementById('supModalId').value = '';
    document.getElementById('supModalName').value = '';
    document.getElementById('supModalContact').value = '';
    document.getElementById('supModalPhone').value = '';
    document.getElementById('supModalCity').value = '';
    document.getElementById('supModalCountry').value = '';
    const modal = new bootstrap.Modal(document.getElementById('supplierModal'));
    modal.show();
}

async function openSupplierEdit(id) {
    try {
        const res = await fetchWithAuth(`${API_BASE}/suppliers/${id}`);
        const json = await res.json();
        if (!json.success) return;

        const s = json.data;
        document.getElementById('supModalTitle').textContent = `แก้ไขผู้จัดจำหน่าย (#${s.id})`;
        document.getElementById('supModalId').value = s.id;
        document.getElementById('supModalName').value = s.name;
        document.getElementById('supModalContact').value = s.contactName;
        document.getElementById('supModalPhone').value = s.phone;
        document.getElementById('supModalCity').value = s.city;
        document.getElementById('supModalCountry').value = s.country;

        const modal = new bootstrap.Modal(document.getElementById('supplierModal'));
        modal.show();
    } catch (e) {
        showToast('เกิดข้อผิดพลาด: ' + e.message, 'danger');
    }
}

async function handleSupplierSubmit(e) {
    e.preventDefault();
    const id      = document.getElementById('supModalId').value;
    const name    = document.getElementById('supModalName').value.trim();
    const contact = document.getElementById('supModalContact').value.trim();
    const phone   = document.getElementById('supModalPhone').value.trim();
    const city    = document.getElementById('supModalCity').value.trim();
    const country = document.getElementById('supModalCountry').value.trim();

    const payload = { name, contactName: contact, phone, city, country };
    const method  = id ? 'PUT' : 'POST';
    const url     = id ? `${API_BASE}/suppliers/${id}` : `${API_BASE}/suppliers`;

    try {
        const res = await fetchWithAuth(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
            showToast(id ? 'แก้ไขผู้จัดจำหน่ายเรียบร้อยแล้ว' : 'เพิ่มผู้จัดจำหน่ายเรียบร้อยแล้ว', 'success');
            loadSuppliersTable();
        } else {
            showToast(json.message || 'บันทึกไม่สำเร็จ', 'danger');
        }
    } catch (err) {
        showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
    }
}

function confirmDeleteSupplier(id, name) {
    pendingDeleteAction = async () => {
        try {
            const res = await fetchWithAuth(`${API_BASE}/suppliers/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                showToast(`ลบผู้จัดจำหน่าย "${name}" เรียบร้อยแล้ว`, 'success');
                loadSuppliersTable();
            } else {
                showToast(json.message || 'ไม่สามารถลบผู้จัดจำหน่ายได้', 'danger');
            }
        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        }
    };

    document.getElementById('deleteConfirmText').textContent = `คุณต้องการลบผู้จัดจำหน่าย "${name}" ใช่หรือไม่?`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

/* ══════════════════════════════════════════════════════════════════════════════
   9. SCREEN 10: EMPLOYEES (CRUD)
   ══════════════════════════════════════════════════════════════════════════════ */

const avatarColors = [
    '#2563EB', '#7C3AED', '#059669', '#D97706', '#DC2626', '#0891B2', '#65A30D', '#C026D3', '#9333EA', '#4F46E5'
];

async function loadEmployeesData() {
    const grid = document.getElementById('employeesGrid');
    if (!grid) return;

    grid.innerHTML = `<div class="col-12 text-center py-4 text-muted">กำลังโหลดรายชื่อพนักงาน...</div>`;

    try {
        const res = await fetchWithAuth(`${API_BASE}/employees`);
        const json = await res.json();
        const employees = json.data || [];

        grid.innerHTML = employees.map((emp, i) => {
            const color = avatarColors[i % avatarColors.length];
            return `
                <div class="item-card text-center position-relative">
                    <div class="position-absolute top-0 end-0 p-3">
                        <button class="btn btn-sm btn-outline-primary py-0 px-1 me-1" onclick="openEmployeeEdit(${emp.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="confirmDeleteEmployee(${emp.id}, '${escapeHtml(emp.fullName)}')"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="avatar mx-auto mb-3" style="width:56px;height:56px;font-size:20px;background:${color}">
                        ${emp.initials || 'EM'}
                    </div>
                    <div class="fw-bold fs-6 text-dark">${escapeHtml(emp.fullName)}</div>
                    <div class="text-primary small fw-semibold mt-1">${escapeHtml(emp.title || 'พนักงานขาย')}</div>
                    <div class="text-muted small mt-2">เกิด: ${escapeHtml(emp.birthDate || '-')}</div>
                    <div class="text-secondary small mt-2 p-2 bg-light rounded text-start" style="font-size:11.5px;max-height:60px;overflow:hidden">
                        ${escapeHtml(emp.notes || '-')}
                    </div>
                </div>
            `;
        }).join('');

    } catch (e) {
        grid.innerHTML = `<div class="col-12 text-center text-danger py-4">เกิดข้อผิดพลาด: ${e.message}</div>`;
    }
}

function openEmployeeModal() {
    document.getElementById('empModalTitle').textContent = 'เพิ่มพนักงานใหม่';
    document.getElementById('empModalId').value = '';
    document.getElementById('empModalFirstName').value = '';
    document.getElementById('empModalLastName').value = '';
    document.getElementById('empModalBirthDate').value = '';
    document.getElementById('empModalNotes').value = '';
    const modal = new bootstrap.Modal(document.getElementById('employeeModal'));
    modal.show();
}

async function openEmployeeEdit(id) {
    try {
        const res = await fetchWithAuth(`${API_BASE}/employees/${id}`);
        const json = await res.json();
        if (!json.success) return;

        const e = json.data;
        document.getElementById('empModalTitle').textContent = `แก้ไขข้อมูลพนักงาน (#${e.id})`;
        document.getElementById('empModalId').value = e.id;
        document.getElementById('empModalFirstName').value = e.firstName;
        document.getElementById('empModalLastName').value = e.lastName;
        document.getElementById('empModalBirthDate').value = e.birthDate;
        document.getElementById('empModalNotes').value = e.notes;

        const modal = new bootstrap.Modal(document.getElementById('employeeModal'));
        modal.show();
    } catch (e) {
        showToast('เกิดข้อผิดพลาด: ' + e.message, 'danger');
    }
}

async function handleEmployeeSubmit(e) {
    e.preventDefault();
    const id        = document.getElementById('empModalId').value;
    const firstName = document.getElementById('empModalFirstName').value.trim();
    const lastName  = document.getElementById('empModalLastName').value.trim();
    const birthDate = document.getElementById('empModalBirthDate').value.trim();
    const notes     = document.getElementById('empModalNotes').value.trim();

    const payload = { firstName, lastName, birthDate, notes };
    const method  = id ? 'PUT' : 'POST';
    const url     = id ? `${API_BASE}/employees/${id}` : `${API_BASE}/employees`;

    try {
        const res = await fetchWithAuth(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();
            showToast(id ? 'แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว' : 'เพิ่มพนักงานเรียบร้อยแล้ว', 'success');
            loadEmployeesData();
        } else {
            showToast(json.message || 'บันทึกไม่สำเร็จ', 'danger');
        }
    } catch (err) {
        showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
    }
}

function confirmDeleteEmployee(id, name) {
    pendingDeleteAction = async () => {
        try {
            const res = await fetchWithAuth(`${API_BASE}/employees/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                showToast(`ลบข้อมูลพนักงาน "${name}" เรียบร้อยแล้ว`, 'success');
                loadEmployeesData();
            } else {
                showToast(json.message || 'ไม่สามารถลบข้อมูลได้', 'danger');
            }
        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        }
    };

    document.getElementById('deleteConfirmText').textContent = `คุณต้องการลบพนักงาน "${name}" ใช่หรือไม่?`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

/* ══════════════════════════════════════════════════════════════════════════════
   10. SCREEN 11: SHIPPERS (CRUD)
   ══════════════════════════════════════════════════════════════════════════════ */

async function loadShippersData() {
    const grid = document.getElementById('shippersGrid');
    if (!grid) return;

    grid.innerHTML = `<div class="col-12 text-center py-4 text-muted">กำลังโหลดบริษัทขนส่ง...</div>`;

    try {
        const res = await fetchWithAuth(`${API_BASE}/shippers`);
        const json = await res.json();
        const shippers = json.data || [];

        grid.innerHTML = shippers.map(s => `
            <div class="item-card">
                <div class="fs-1 mb-2">${s.icon || '🚚'}</div>
                <div class="fw-bold fs-6 text-dark">${escapeHtml(s.name)}</div>
                <div class="text-muted small mt-1"><i class="bi bi-telephone me-1"></i>${escapeHtml(s.phone || '-')}</div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                    <span class="text-secondary small">จัดส่งแล้ว ${s.shipmentCount || 0} ครั้ง</span>
                    <span class="badge-status badge-green">${s.status || 'พร้อมให้บริการ'}</span>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="openShipperEdit(${s.id}, '${escapeHtml(s.name)}', '${escapeHtml(s.phone)}')">
                        <i class="bi bi-pencil me-1"></i> แก้ไข
                    </button>
                    <button class="btn btn-sm btn-outline-danger px-3" onclick="confirmDeleteShipper(${s.id}, '${escapeHtml(s.name)}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

    } catch (e) {
        grid.innerHTML = `<div class="col-12 text-center text-danger py-4">เกิดข้อผิดพลาด: ${e.message}</div>`;
    }
}

function openShipperModal() {
    document.getElementById('shpModalTitle').textContent = 'เพิ่มบริษัทขนส่งใหม่';
    document.getElementById('shpModalId').value = '';
    document.getElementById('shpModalName').value = '';
    document.getElementById('shpModalPhone').value = '';
    const modal = new bootstrap.Modal(document.getElementById('shipperModal'));
    modal.show();
}

function openShipperEdit(id, name, phone) {
    document.getElementById('shpModalTitle').textContent = 'แก้ไขบริษัทขนส่ง';
    document.getElementById('shpModalId').value = id;
    document.getElementById('shpModalName').value = name;
    document.getElementById('shpModalPhone').value = phone;
    const modal = new bootstrap.Modal(document.getElementById('shipperModal'));
    modal.show();
}

async function handleShipperSubmit(e) {
    e.preventDefault();
    const id    = document.getElementById('shpModalId').value;
    const name  = document.getElementById('shpModalName').value.trim();
    const phone = document.getElementById('shpModalPhone').value.trim();

    const payload = { name, phone };
    const method  = id ? 'PUT' : 'POST';
    const url     = id ? `${API_BASE}/shippers/${id}` : `${API_BASE}/shippers`;

    try {
        const res = await fetchWithAuth(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('shipperModal')).hide();
            showToast(id ? 'แก้ไขข้อมูลบริษัทขนส่งเรียบร้อยแล้ว' : 'เพิ่มบริษัทขนส่งเรียบร้อยแล้ว', 'success');
            loadShippersData();
        } else {
            showToast(json.message || 'บันทึกไม่สำเร็จ', 'danger');
        }
    } catch (err) {
        showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
    }
}

function confirmDeleteShipper(id, name) {
    pendingDeleteAction = async () => {
        try {
            const res = await fetchWithAuth(`${API_BASE}/shippers/${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (json.success) {
                showToast(`ลบบริษัทขนส่ง "${name}" เรียบร้อยแล้ว`, 'success');
                loadShippersData();
            } else {
                showToast(json.message || 'ไม่สามารถลบข้อมูลได้', 'danger');
            }
        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
        }
    };

    document.getElementById('deleteConfirmText').textContent = `คุณต้องการลบบริษัทขนส่ง "${name}" ใช่หรือไม่?`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

/* ══════════════════════════════════════════════════════════════════════════════
   11. SCREEN 12: REPORTS & ANALYTICS
   ══════════════════════════════════════════════════════════════════════════════ */

async function loadReportsData() {
    try {
        const res = await fetchWithAuth(`${API_BASE}/reports/summary`);
        const json = await res.json();
        if (!json.success) return;

        const data = json.data;

        // KPIs
        document.getElementById('reportRevenue').textContent = `฿${formatMoney(data.kpis.totalRevenue)}`;
        document.getElementById('reportOrders').textContent  = data.kpis.totalOrders;
        document.getElementById('reportAvgOrder').textContent= `฿${formatMoney(data.kpis.avgPerOrder)}`;
        document.getElementById('reportTopShipper').textContent = data.kpis.topShipper;
        document.getElementById('reportTopShipperCount').textContent = `${data.kpis.topShipperCount} ครั้ง`;

        // Monthly Trends
        const chartContainer = document.getElementById('reportMonthlyChart');
        if (chartContainer && data.monthlyTrends) {
            chartContainer.innerHTML = data.monthlyTrends.map(m => {
                const heightPx = Math.max(15, Math.min(85, (m.revenue / 500000) * 85));
                return `
                    <div class="bar-wrap">
                        <div class="bar-col" style="height:${heightPx}px" title="${m.month}: ฿${formatMoney(m.revenue)}"></div>
                        <div class="bar-lbl">${m.month}</div>
                    </div>`;
            }).join('');
        }

        // Top 5 Products Table
        const prodTbody = document.getElementById('reportTopProductsBody');
        if (prodTbody && data.topProducts) {
            prodTbody.innerHTML = data.topProducts.map(p => `
                <tr>
                    <td class="fw-semibold">${escapeHtml(p.name)}</td>
                    <td><span class="badge-status badge-blue">${escapeHtml(p.categoryName || '-')}</span></td>
                    <td class="text-center fw-bold" style="font-family:'IBM Plex Mono'">${p.totalSold} หน่วย</td>
                    <td class="text-end fw-bold text-success" style="font-family:'IBM Plex Mono'">฿${formatMoney(p.totalSales)}</td>
                </tr>
            `).join('');
        }

        // Top 5 Customers Table
        const custTbody = document.getElementById('reportTopCustomersBody');
        if (custTbody && data.topCustomers) {
            custTbody.innerHTML = data.topCustomers.map((c, i) => {
                const medal = i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : `${i + 1}`));
                return `
                    <tr>
                        <td class="fs-5">${medal}</td>
                        <td class="fw-bold">${escapeHtml(c.name)}</td>
                        <td>${escapeHtml(c.country || '-')}</td>
                        <td class="text-center fw-bold">${c.orderCount}</td>
                        <td class="text-end fw-bold text-primary" style="font-family:'IBM Plex Mono'">฿${formatMoney(c.totalSpent)}</td>
                    </tr>
                `;
            }).join('');
        }

    } catch (e) {
        console.error('loadReportsData error:', e);
    }
}

function exportReportCSV() {
    window.open(`${API_BASE}/reports/summary`, '_blank');
}

/* ══════════════════════════════════════════════════════════════════════════════
   12. UTILITY HELPERS (TOAST, CONFIRM MODAL, FORMATTERS)
   ══════════════════════════════════════════════════════════════════════════════ */

function showToast(message, type = 'info') {
    const toastEl = document.getElementById('liveToast');
    const toastBody = document.getElementById('toastMessage');
    if (!toastEl || !toastBody) return;

    toastBody.textContent = message;
    toastEl.className = `toast align-items-center text-white border-0 bg-${type === 'danger' ? 'danger' : (type === 'success' ? 'success' : 'primary')}`;

    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}

function formatMoney(amount) {
    return parseFloat(amount || 0).toLocaleString('th-TH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Bind Universal Delete Confirmation Button
document.addEventListener('DOMContentLoaded', () => {
    const btnConfirm = document.getElementById('btnConfirmDeleteAction');
    if (btnConfirm) {
        btnConfirm.addEventListener('click', async () => {
            if (typeof pendingDeleteAction === 'function') {
                const action = pendingDeleteAction;
                pendingDeleteAction = null;
                bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
                await action();
            }
        });
    }

    // Auto-check session on boot
    if (authToken && currentUser) {
        updateUserUI();
        switchScreen('dashboard');
    } else {
        switchScreen('login');
    }
});
