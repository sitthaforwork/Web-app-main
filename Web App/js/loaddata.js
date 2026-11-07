/**
 * loaddata.js
 * โหลดข้อมูล Dropdown (Categories / Suppliers) จาก API
 */
async function loadSelectOptions(url, selectId, placeholderText) {
    const selectEl = document.getElementById(selectId);
    if (!selectEl) return;

    try {
        const response = await fetch(url);
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || `HTTP ${response.status}`);
        }

        selectEl.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.disabled = true;
        defaultOption.selected = true;
        defaultOption.textContent = placeholderText;
        selectEl.appendChild(defaultOption);

        result.data.forEach((item) => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name;
            selectEl.appendChild(opt);
        });

    } catch (error) {
        selectEl.innerHTML = '';
        const errOption = document.createElement('option');
        errOption.value = '';
        errOption.disabled = true;
        errOption.selected = true;
        errOption.textContent = 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่';
        selectEl.appendChild(errOption);
        console.error(`โหลดข้อมูลจาก ${url} ไม่สำเร็จ:`, error);
    }
}
