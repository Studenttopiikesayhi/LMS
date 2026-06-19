<?php
/** issues.php — ?action=reserve|return|my_current|history|reservations|approve|cancel|report */
declare(strict_types=1);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/jwt.php';

switch ($_GET['action'] ?? '') {
    case 'reserve':      issues_reserve();      break;
    case 'return':       issues_return();       break;
    case 'my_current':   issues_my_current();   break;
    case 'history':      issues_history();      break;
    case 'reservations': issues_reservations(); break;
    case 'approve':      issues_approve();      break;
    case 'cancel':       issues_cancel();       break;
    case 'report':       issues_report();       break;
    default: json_response(['success'=>false,'message'=>'ไม่พบ action ที่ร้องขอ'],404);
}

// จองหนังสือ (สมาชิก) — ตัดสต็อกทันที
function issues_reserve(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องใช้ POST'],405);
    $user = require_auth();
    $book_id = (int)(get_json_body()['book_id'] ?? 0);
    if ($book_id <= 0) json_response(['success'=>false,'message'=>'ไม่พบรหัสหนังสือ'],400);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT copies, price FROM books WHERE id = ? FOR UPDATE");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        if (!$book) throw new RuntimeException('ไม่พบข้อมูลหนังสือ');
        if ((int)$book['copies'] <= 0) throw new RuntimeException('ขออภัย หนังสือหมดแล้ว');

        $pdo->prepare("UPDATE books SET copies = copies - 1 WHERE id = ?")->execute([$book_id]);
        $issue_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+7 days'));
        $pdo->prepare("INSERT INTO issues (user_id, book_id, issue_date, due_date, status) VALUES (?,?,?,?, 'reserved')")
            ->execute([(int)$user['sub'], $book_id, $issue_date, $due_date]);
        $pdo->commit();

        $fee = round((float)$book['price'] * 0.10, 2);
        json_response(['success'=>true,'message'=>"จองสำเร็จ (ค่าเช่า $fee บาท ชำระที่เคาน์เตอร์)",'rental_fee'=>$fee],201);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['success'=>false,'message'=>$e->getMessage()],400);
    }
}

// คืนหนังสือ (สมาชิก) — คิดค่าปรับ 10 บาท/วันเกินกำหนด
function issues_return(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องใช้ POST'],405);
    $user = require_auth();
    $issue_id = (int)(get_json_body()['issue_id'] ?? 0);
    if ($issue_id <= 0) json_response(['success'=>false,'message'=>'ไม่พบรายการ'],400);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM issues WHERE id = ? AND user_id = ? FOR UPDATE");
        $stmt->execute([$issue_id, (int)$user['sub']]);
        $issue = $stmt->fetch();
        if (!$issue) throw new RuntimeException('ไม่พบรายการนี้');
        if ($issue['status'] !== 'active') throw new RuntimeException('รายการนี้ยังไม่อยู่ในสถานะกำลังเช่า');

        $return_date = date('Y-m-d');
        $days = (int)ceil((strtotime($return_date) - strtotime($issue['due_date'])) / 86400);
        $fine = $days > 0 ? $days * 10 : 0;

        $pdo->prepare("UPDATE issues SET return_date=?, fine=?, status='returned' WHERE id=?")
            ->execute([$return_date, $fine, $issue_id]);
        $pdo->prepare("UPDATE books SET copies = copies + 1 WHERE id=?")->execute([(int)$issue['book_id']]);
        $pdo->commit();

        json_response(['success'=>true,'message'=>"คืนสำเร็จ ค่าปรับ $fine บาท",'fine'=>$fine]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['success'=>false,'message'=>$e->getMessage()],400);
    }
}

// หนังสือที่กำลังเช่าของฉัน (active) ไว้เลือกคืน
function issues_my_current(): void {
    $user = require_auth();
    $stmt = db()->prepare(
        "SELECT i.id, b.title, i.due_date FROM issues i JOIN books b ON b.id=i.book_id
         WHERE i.user_id=? AND i.status='active' AND i.return_date IS NULL ORDER BY i.due_date ASC"
    );
    $stmt->execute([(int)$user['sub']]);
    json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
}

// ประวัติของฉัน
function issues_history(): void {
    $user = require_auth();
    $stmt = db()->prepare(
        "SELECT i.*, b.title, b.price FROM issues i JOIN books b ON b.id=i.book_id
         WHERE i.user_id=? ORDER BY i.issue_date DESC, i.id DESC"
    );
    $stmt->execute([(int)$user['sub']]);
    json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
}

// รายการจองที่รออนุมัติ (admin)
function issues_reservations(): void {
    require_admin();
    $stmt = db()->query(
        "SELECT i.id, u.name AS user_name, b.title, i.issue_date
         FROM issues i JOIN users u ON u.id=i.user_id JOIN books b ON b.id=i.book_id
         WHERE i.status='reserved' ORDER BY i.issue_date ASC, i.id ASC"
    );
    json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
}

// อนุมัติรับของ (admin) → active เริ่มนับวันเช่าวันนี้
function issues_approve(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องใช้ POST'],405);
    require_admin();
    $id = (int)(get_json_body()['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ไม่พบรายการ'],400);
    $now = date('Y-m-d'); $due = date('Y-m-d', strtotime('+7 days'));
    $stmt = db()->prepare("UPDATE issues SET status='active', issue_date=?, due_date=? WHERE id=? AND status='reserved'");
    $stmt->execute([$now, $due, $id]);
    if ($stmt->rowCount() === 0) json_response(['success'=>false,'message'=>'ไม่พบรายการจองที่รออนุมัติ'],404);
    json_response(['success'=>true,'message'=>'อนุมัติรับหนังสือเรียบร้อย (เริ่มนับวันเช่าวันนี้)']);
}

// ยกเลิกการจอง (admin) → คืนสต็อก
function issues_cancel(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องใช้ POST'],405);
    require_admin();
    $id = (int)(get_json_body()['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ไม่พบรายการ'],400);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT book_id, status FROM issues WHERE id=? FOR UPDATE");
        $stmt->execute([$id]);
        $issue = $stmt->fetch();
        if (!$issue) throw new RuntimeException('ไม่พบรายการ');
        if ($issue['status'] !== 'reserved') throw new RuntimeException('ยกเลิกได้เฉพาะรายการที่รอรับของ');
        $pdo->prepare("UPDATE books SET copies = copies + 1 WHERE id=?")->execute([(int)$issue['book_id']]);
        $pdo->prepare("UPDATE issues SET status='cancelled' WHERE id=?")->execute([$id]);
        $pdo->commit();
        json_response(['success'=>true,'message'=>'ยกเลิกการจองและคืนสต็อกเรียบร้อย']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['success'=>false,'message'=>$e->getMessage()],400);
    }
}

// รายงานสรุป + ยอดค่าปรับรวม (admin)
function issues_report(): void {
    require_admin();
    $rows = db()->query(
        "SELECT i.*, u.name AS user_name, b.title FROM issues i
         JOIN users u ON u.id=i.user_id JOIN books b ON b.id=i.book_id ORDER BY i.id DESC"
    )->fetchAll();
    $total_fine = (float)db()->query("SELECT COALESCE(SUM(fine),0) FROM issues")->fetchColumn();
    json_response(['success'=>true,'data'=>$rows,'total_fine'=>$total_fine]);
}