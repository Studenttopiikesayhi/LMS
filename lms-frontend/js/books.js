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
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">ไม่พบหนังสือที่ค้นหา</td></tr>';
        return;
    }
    const loggedIn = !!getToken();
    tbody.innerHTML = data.data.map(b => {
        const avail = b.copies > 0
            ? `<span class="badge bg-success">ว่าง (${b.copies})</span>`
            : '<span class="badge bg-danger">หมด</span>';
        const action = (loggedIn && b.copies > 0)
            ? `<button class="btn btn-sm btn-success" onclick="openReserve(${b.id})">จอง</button>` : '-';
        const fee = (Number(b.price) * 0.10).toFixed(2);
        return `<tr>
      <td>${coverImg(b.cover_url, b.title)}</td>
      <td>${escapeHtml(b.title)}</td><td>${escapeHtml(b.author || '-')}</td>
      <td>${escapeHtml(b.category || '-')}</td>
      <td>${fmtMoney(b.price)}<br><small class="text-muted">ค่าเช่า ${fee} บ.</small></td>
      <td>${avail}</td><td>${action}</td></tr>`;
    }).join('');
    window.__books = data.data;   // เก็บไว้ให้ modal จองใช้แสดงรายละเอียด
}

/* ---------- เปิดกล่องยืนยันการจอง (มีช่องหมายเหตุ) ---------- */
function openReserve(bookId) {
    const b = (window.__books || []).find(x => Number(x.id) === Number(bookId));
    if (!b) return;
    document.getElementById('rv_book_id').value = b.id;
    document.getElementById('rv_cover').innerHTML = coverImg(b.cover_url, b.title, 120);
    document.getElementById('rv_title').textContent = b.title;
    document.getElementById('rv_author').textContent = b.author || '-';
    document.getElementById('rv_fee').textContent = (Number(b.price) * 0.10).toFixed(2);
    document.getElementById('rv_note').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('reserveModal')).show();
}

async function confirmReserve() {
    const bookId = Number(document.getElementById('rv_book_id').value);
    const note = document.getElementById('rv_note').value.trim().slice(0, 255);
    const { status, data } = await apiFetch('/issues.php?action=reserve', {
        method: 'POST',
        body: { book_id: bookId, description: note }
    });
    if (status === 201 && data.success) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('reserveModal')).hide();
        setFlash('success', data.message);
        location.href = 'my_history.html';
    } else {
        showAlert(data.message || 'จองไม่สำเร็จ', 'danger');
    }
}
