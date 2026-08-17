/* api.js — ตั้งค่ากลาง + ฟังก์ชันช่วยทุกหน้า */

/* API_BASE อ้างอิงโฮสต์ที่กำลังเปิดหน้าเว็บอยู่จริง
   -> เปิดจากเครื่องอื่นในวง LAN (เช่น http://192.168.1.20/...) ก็ยังเรียก API ได้
   -> ถ้าเปิดไฟล์ตรง ๆ ด้วย file:// จะย้อนกลับไปใช้ localhost */
const API_BASE = (location.protocol === 'http:' || location.protocol === 'https:')
    ? location.origin + '/lms_project/lms-backend/api'
    : 'http://localhost/lms_project/lms-backend/api';

/* ---------- จัดการ token / user (เก็บใน localStorage) ---------- */
function setToken(t) { localStorage.setItem('lms_token', t); }
function getToken() { return localStorage.getItem('lms_token'); }
function setUser(u) { localStorage.setItem('lms_user', JSON.stringify(u)); }
function getUser() { const r = localStorage.getItem('lms_user'); return r ? JSON.parse(r) : null; }
function clearAuth() { localStorage.removeItem('lms_token'); localStorage.removeItem('lms_user'); }

/* ---------- เรียก API (แนบ Bearer token อัตโนมัติ) ---------- */
async function apiFetch(endpoint, { method = 'GET', body = null } = {}) {
    const headers = { 'Content-Type': 'application/json' };
    const token = getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;
    let res, data;
    try {
        res = await fetch(API_BASE + endpoint, { method, headers, body: body ? JSON.stringify(body) : null });
        data = await res.json().catch(() => ({}));
    } catch (e) {
        return { status: 0, data: { success: false, message: 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ (ตรวจว่าเปิด Apache/MySQL แล้ว)' } };
    }
    if (res.status === 401) clearAuth();
    return { status: res.status, data };
}

/* ---------- ป้องกันหน้า ---------- */
function pathPrefix() { return location.pathname.includes('/admin/') ? '../' : ''; }
function requireLogin() { if (!getToken()) { location.href = pathPrefix() + 'login.html'; return false; } return true; }
function requireAdmin() {
    const u = getUser();
    if (!getToken() || !u || u.role !== 'admin') { location.href = pathPrefix() + 'dashboard.html'; return false; }
    return true;
}

/* ---------- แถบเมนูบน ---------- */
function renderNavbar() {
    const el = document.getElementById('navbar');
    const u = getUser();
    if (!el || !u) return;
    const badge = u.role === 'admin' ? 'danger' : 'primary';
    el.innerHTML =
        `<nav class="navbar navbar-dark bg-dark mb-4 shadow-sm">
       <div class="container">
         <a class="navbar-brand" href="${pathPrefix()}dashboard.html">📚 ระบบร้านเช่าหนังสือ</a>
         <div class="d-flex align-items-center">
           <span class="text-white me-3">สวัสดี, ${escapeHtml(u.name)}
             <span class="badge bg-${badge}">${escapeHtml(String(u.role).toUpperCase())}</span></span>
           <button class="btn btn-outline-light btn-sm" onclick="logout()">ออกจากระบบ</button>
         </div>
       </div>
     </nav>`;
}
function logout() { clearAuth(); setFlash('success', 'ออกจากระบบเรียบร้อยแล้ว'); location.href = pathPrefix() + 'login.html'; }

/* ---------- แจ้งเตือน (flash ข้ามหน้า + alert ในหน้า) ---------- */
function setFlash(type, message) { sessionStorage.setItem('lms_flash', JSON.stringify({ type, message })); }
function showFlash(containerId = 'alertBox') {
    const r = sessionStorage.getItem('lms_flash');
    if (!r) return;
    sessionStorage.removeItem('lms_flash');
    const { type, message } = JSON.parse(r);
    showAlert(message, type, containerId);
}
function showAlert(message, type = 'info', containerId = 'alertBox') {
    const box = document.getElementById(containerId);
    if (!box) { alert(message); return; }
    box.innerHTML =
        `<div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
       ${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
     </div>`;
}

/* ---------- helper ---------- */
function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function fmtDate(d) { if (!d) return '-'; const p = String(d).split('-'); return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d; }
function fmtMoney(v) { return Number(v ?? 0).toFixed(2); }
function statusBadge(r) {
    const today = new Date().toISOString().slice(0, 10);
    if (r.status === 'returned') return '<span class="badge bg-success">คืนแล้ว</span>';
    if (r.status === 'active') return r.due_date < today ? '<span class="badge bg-danger">เกินกำหนด</span>' : '<span class="badge bg-primary">กำลังเช่า</span>';
    if (r.status === 'reserved') return '<span class="badge bg-warning text-dark">รอรับของ</span>';
    return '<span class="badge bg-secondary">ยกเลิก</span>';
}

/* ---------- รูปหน้าปกหนังสือ ----------
   - ยอมรับเฉพาะ http(s):// หรือ path สัมพัทธ์ (กัน javascript:/data: ที่เป็นช่องโหว่ XSS)
   - ถ้าไม่มีรูป หรือรูปโหลดไม่ขึ้น จะแสดงกรอบ 📖 แทน (onerror ลบ <img> ทิ้ง เหลือ placeholder) */
function isSafeCoverUrl(u) {
    const s = String(u ?? '').trim();
    if (!s) return false;
    if (/^https?:\/\//i.test(s)) return true;
    return /^[A-Za-z0-9_\-./]+$/.test(s) && !s.includes('..');
}
/* path สัมพัทธ์จากหน้าเว็บกลับไปที่รากของ lms-frontend/
   - pages/xxx.html        -> ../
   - pages/admin/xxx.html  -> ../../   */
function assetPrefix() { return location.pathname.includes('/admin/') ? '../../' : '../'; }

function coverImg(url, title = '', h = 56) {
    const box = `<span class="cover-box" style="height:${h}px;width:${Math.round(h * 0.7)}px">`;
    if (!isSafeCoverUrl(url)) return box + '📖</span>';
    const raw = String(url).trim();
    const src = /^https?:\/\//i.test(raw) ? raw : assetPrefix() + raw;
    return box + `📖<img src="${escapeHtml(src)}" alt="${escapeHtml(title)}" loading="lazy" onerror="this.remove()"></span>`;
}
