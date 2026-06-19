<?php
/** users.php — ?action=list|update|delete  (เฉพาะ admin) */
declare(strict_types=1);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/jwt.php';

switch ($_GET['action'] ?? '') {
    case 'list':   users_list();   break;
    case 'update': users_update(); break;
    case 'delete': users_delete(); break;
    default: json_response(['success'=>false,'message'=>'ไม่พบ action ที่ร้องขอ'],404);
}

function users_list(): void {
    require_admin();
    $rows = db()->query("SELECT id, name, email, role, created_at FROM users ORDER BY role DESC, id ASC")->fetchAll();
    json_response(['success'=>true,'data'=>$rows]);
}

function users_update(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องใช้ POST'],405);
    require_admin();
    $b = get_json_body();
    $id = (int)($b['id'] ?? 0);
    $name = trim($b['name'] ?? '');
    $email = trim($b['email'] ?? '');
    $role = ($b['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    if ($id <= 0 || $name === '' || $email === '') json_response(['success'=>false,'message'=>'ข้อมูลไม่ครบ'],400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(['success'=>false,'message'=>'อีเมลไม่ถูกต้อง'],400);
    try {
        db()->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?")->execute([$name, $email, $role, $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') json_response(['success'=>false,'message'=>'อีเมลนี้ถูกใช้แล้ว'],409);
        json_response(['success'=>false,'message'=>'เกิดข้อผิดพลาด'],500);
    }
    json_response(['success'=>true,'message'=>'แก้ไขข้อมูลสมาชิกสำเร็จ']);
}

function users_delete(): void {
    $admin = require_admin();
    $id = (int)($_GET['id'] ?? (get_json_body()['id'] ?? 0));
    if ($id <= 0) json_response(['success'=>false,'message'=>'ไม่พบรหัสสมาชิก'],400);
    if ($id === (int)$admin['sub']) json_response(['success'=>false,'message'=>'ลบบัญชีตัวเองที่กำลังใช้งานไม่ได้'],400);
    $chk = db()->prepare("SELECT COUNT(*) FROM issues WHERE user_id=?");
    $chk->execute([$id]);
    if ((int)$chk->fetchColumn() > 0) json_response(['success'=>false,'message'=>'ลบไม่ได้ สมาชิกนี้มีประวัติการเช่า'],409);
    db()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    json_response(['success'=>true,'message'=>'ลบสมาชิกเรียบร้อย']);
}