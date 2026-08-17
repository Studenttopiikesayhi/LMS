/* dashboard.js — เมนูตามสิทธิ์ + หนังสือยอดนิยม */
document.addEventListener('DOMContentLoaded', () => {
    if (!requireLogin()) return;
    renderNavbar();
    showFlash();
    if (getUser()?.role === 'admin') document.getElementById('adminMenu').classList.remove('d-none');
    loadPopular();
});

async function loadPopular() {
    const box = document.getElementById('popularBox');
    if (!box) return;
    const { data } = await apiFetch('/books.php?action=popular');
    if (!data.success || !data.data.length) { box.innerHTML = '<li class="list-group-item text-muted">ยังไม่มีข้อมูลการยืม</li>'; return; }
    box.innerHTML = data.data.map(b =>
        `<li class="list-group-item d-flex justify-content-between align-items-center">
       <span class="d-flex align-items-center gap-2">
         ${coverImg(b.cover_url, b.title, 48)}
         <span>${escapeHtml(b.title)} <small class="text-muted">(${escapeHtml(b.author || '-')})</small></span>
       </span>
       <span class="badge bg-info rounded-pill">ยืม ${b.borrow_count} ครั้ง</span>
     </li>`).join('');
}
