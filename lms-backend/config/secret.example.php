<?php
/**
 * secret.example.php — แม่แบบไฟล์ตั้งค่าลับ
 * วิธีใช้: คัดลอกไฟล์นี้เป็น secret.php แล้วใส่ค่าจริง
 */
return [
    'DB_HOST'    => 'localhost',
    'DB_NAME'    => 'lms_db',
    'DB_USER'    => 'root',
    'DB_PASS'    => '',
    'JWT_SECRET' => 'ใส่ค่าสุ่มยาว ๆ ตรงนี้ (เช่นจาก: php -r "echo bin2hex(random_bytes(32));")',
];