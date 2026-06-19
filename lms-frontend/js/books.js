/* books.js — ค้นหา + จอง (หน้า search_book) */
document.addEventListener('DOMContentLoaded', () => {
    renderNavbar();
    showFlash();
    loadBooks('');
    const form = document.getElementById('searchForm');
    if (form) form.addEventListener('submit', e => { e.preventDefault(); loadBooks(document.getElementById('q').value.trim()); });
    if (getToken()) document.getElementById('guestHint')?.classList.add('d-none');
});

async function loadBooks(q) {
    const ep = '/books.php?action=list' + (q ? '&q=' + encodeURIComponent(q) : '');
    const { data } = await apiFetch(ep);
    const tbody = document.getElementById('bookRows');
    if (!data.success || !data.data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">ไม่พบหนังสือที่ค้นหา</td></tr>';
        return;
    }
    const loggedIn = !!getToken();
    tbody.innerHTML = data.data.map(b => {
        const avail = b.copies > 0
            ? `<span class="badge bg-success">ว่าง (${b.copies})</span>`
            : '<span class="badge bg-danger">หมด</span>';
        const action = (loggedIn && b.copies > 0)
            ? `<button class="btn btn-sm btn-success" onclick="reserve(${b.id})">จอง</button>` : '-';
        return `<tr>
      <td>${escapeHtml(b.title)}</td><td>${escapeHtml(b.author || '-')}</td>
      <td>${escapeHtml(b.category || '-')}</td><td>${Number(b.price).toFixed(2)}</td>
      <td>${avail}</td><td>${action}</td></tr>`;
    }).join('');
}

async function reserve(bookId) {
    if (!confirm('ยืนยันการจองหนังสือเล่มนี้?')) return;
    const { status, data } = await apiFetch('/issues.php?action=reserve', { method: 'POST', body: { book_id: bookId } });
    if (status === 201 && data.success) { setFlash('success', data.message); location.href = 'my_history.html'; }
    else showAlert(data.message || 'จองไม่สำเร็จ', 'danger');
}