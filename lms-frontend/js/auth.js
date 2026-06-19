/* auth.js — login / register */
async function doLogin() {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    if (!email || !password) { showAlert('กรุณากรอกอีเมลและรหัสผ่าน', 'warning'); return; }
    const { status, data } = await apiFetch('/auth.php?action=login', { method: 'POST', body: { email, password } });
    if (status === 200 && data.success) {
        setToken(data.token); setUser(data.user);
        setFlash('success', 'เข้าสู่ระบบสำเร็จ ยินดีต้อนรับ ' + data.user.name);
        location.href = 'dashboard.html';
    } else {
        showAlert(data.message || 'เข้าสู่ระบบไม่สำเร็จ', 'danger');
    }
}

async function doRegister() {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    if (!name || !email || !password) { showAlert('กรุณากรอกข้อมูลให้ครบ', 'warning'); return; }
    const { status, data } = await apiFetch('/auth.php?action=register', { method: 'POST', body: { name, email, password } });
    if (status === 201 && data.success) {
        setFlash('success', 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ');
        location.href = 'login.html';
    } else {
        showAlert(data.message || 'สมัครสมาชิกไม่สำเร็จ', 'danger');
    }
}