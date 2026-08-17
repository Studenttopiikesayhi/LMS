<?php
/** books.php — ?action=list | get | popular | create | update | delete */
declare(strict_types=1);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/jwt.php';

switch ($_GET['action'] ?? '') {
    case 'list':    books_list();    break;
    case 'get':     books_get();     break;
    case 'popular': books_popular(); break;
    case 'create':  books_create();  break;
    case 'update':  books_update();  break;
    case 'delete':  books_delete();  break;
    default: json_response(['success'=>false,'message'=>'ไม่พบ action ที่ร้องขอ'],404);
}

/**
 * ตรวจและทำความสะอาด URL รูปหน้าปก
 * อนุญาตเฉพาะ http(s):// หรือ path สัมพัทธ์ในโปรเจกต์เท่านั้น
 * เพื่อกัน javascript:/data: ที่อาจกลายเป็นช่องโหว่ XSS เมื่อนำไปใส่ใน <img src>
 * คืนค่า null เมื่อผู้ใช้เว้นว่าง (เก็บเป็น NULL ในฐานข้อมูล)
 */
function clean_cover_url(string $url): ?string {
    $url = trim($url);
    if ($url === '') return null;
    if (mb_strlen($url) > 500)
        json_response(['success'=>false,'message'=>'URL รูปหน้าปกต้องยาวไม่เกิน 500 ตัวอักษร'],400);
    if (preg_match('#^https?://#i', $url)) return $url;                       // URL ภายนอก
    if (preg_match('#^[A-Za-z0-9_\-./]+$#', $url) && strpos($url, '..') === false)
        return $url;                                                          // path ในโปรเจกต์ เช่น img/covers/a.jpg
    json_response(['success'=>false,'message'=>'URL รูปหน้าปกต้องขึ้นต้นด้วย http:// หรือ https:// หรือเป็น path ในโปรเจกต์ (เช่น img/covers/a.jpg)'],400);
}

// รายการ + ค้นหา (สาธารณะ — guest ค้นได้)  [SELECT * จึงได้ cover_url มาด้วยอัตโนมัติ]
function books_list(): void {
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $like = "%$q%";
        $stmt = db()->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR category LIKE ? ORDER BY id DESC");
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = db()->query("SELECT * FROM books ORDER BY id DESC");
    }
    json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
}

function books_get(): void {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$id]);
    $book = $stmt->fetch();
    if (!$book) json_response(['success'=>false,'message'=>'ไม่พบหนังสือ'],404);
    json_response(['success'=>true,'data'=>$book]);
}

// หนังสือยอดนิยม — นับจากจำนวนครั้งที่ถูกยืม (ตามขอบเขตข้อ 7.2)
function books_popular(): void {
    $stmt = db()->query(
        "SELECT b.id, b.title, b.author, b.category, b.cover_url, b.price, b.copies, COUNT(i.id) AS borrow_count
         FROM books b LEFT JOIN issues i ON i.book_id = b.id
         GROUP BY b.id ORDER BY borrow_count DESC, b.title ASC LIMIT 5"
    );
    json_response(['success'=>true,'data'=>$stmt->fetchAll()]);
}

function books_create(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องใช้ POST'],405);
    require_admin();
    $b = get_json_body();
    $title = trim($b['title'] ?? '');
    if ($title === '') json_response(['success'=>false,'message'=>'กรุณากรอกชื่อหนังสือ'],400);
    $cover = clean_cover_url((string)($b['cover_url'] ?? ''));
    db()->prepare("INSERT INTO books (title, author, category, cover_url, price, copies) VALUES (?,?,?,?,?,?)")
        ->execute([$title, trim($b['author'] ?? ''), trim($b['category'] ?? ''), $cover, (float)($b['price'] ?? 0), (int)($b['copies'] ?? 0)]);
    json_response(['success'=>true,'message'=>'เพิ่มหนังสือสำเร็จ','id'=>(int)db()->lastInsertId()],201);
}

function books_update(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องใช้ POST'],405);
    require_admin();
    $b = get_json_body();
    $id = (int)($b['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ไม่พบรหัสหนังสือ'],400);
    $cover = clean_cover_url((string)($b['cover_url'] ?? ''));
    db()->prepare("UPDATE books SET title=?, author=?, category=?, cover_url=?, price=?, copies=? WHERE id=?")
        ->execute([trim($b['title'] ?? ''), trim($b['author'] ?? ''), trim($b['category'] ?? ''), $cover, (float)($b['price'] ?? 0), (int)($b['copies'] ?? 0), $id]);
    json_response(['success'=>true,'message'=>'แก้ไขข้อมูลสำเร็จ']);
}

// ลบ — ห้ามลบถ้ามีประวัติการเช่า
function books_delete(): void {
    require_admin();
    $id = (int)($_GET['id'] ?? (get_json_body()['id'] ?? 0));
    if ($id <= 0) json_response(['success'=>false,'message'=>'ไม่พบรหัสหนังสือ'],400);
    $chk = db()->prepare("SELECT COUNT(*) FROM issues WHERE book_id = ?");
    $chk->execute([$id]);
    if ((int)$chk->fetchColumn() > 0)
        json_response(['success'=>false,'message'=>'ลบไม่ได้ เนื่องจากมีประวัติการเช่าหนังสือเล่มนี้'],409);
    db()->prepare("DELETE FROM books WHERE id = ?")->execute([$id]);
    json_response(['success'=>true,'message'=>'ลบหนังสือสำเร็จ']);
}
