<?php
/** auth.php — ?action=register | login | me */
declare(strict_types=1);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/jwt.php';

switch ($_GET['action'] ?? '') {
    case 'register': handle_register(); break;
    case 'login':    handle_login();    break;
    case 'me':       handle_me();       break;
    default: json_response(['success'=>false,'message'=>'ไม่พบ action ที่ร้องขอ'], 404);
}

function handle_register(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องเรียกด้วยเมธอด POST'],405);
    $body = get_json_body();
    $name = trim($body['name'] ?? '');
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if ($name === '' || $email === '' || $password === '')
        json_response(['success'=>false,'message'=>'กรุณากรอกชื่อ อีเมล และรหัสผ่านให้ครบ'],400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        json_response(['success'=>false,'message'=>'รูปแบบอีเมลไม่ถูกต้อง'],400);
    if (strlen($password) < 4)
        json_response(['success'=>false,'message'=>'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร'],400);

    $hash = password_hash($password, PASSWORD_BCRYPT);
    try {
        db()->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)')
            ->execute([$name, $email, $hash]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') json_response(['success'=>false,'message'=>'อีเมลนี้ถูกใช้งานแล้ว'],409);
        json_response(['success'=>false,'message'=>'เกิดข้อผิดพลาดในการสมัครสมาชิก'],500);
    }
    json_response(['success'=>true,'message'=>'สมัครสมาชิกสำเร็จ'],201);
}

function handle_login(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'ต้องเรียกด้วยเมธอด POST'],405);
    $body = get_json_body();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if ($email === '' || $password === '') json_response(['success'=>false,'message'=>'กรุณากรอกอีเมลและรหัสผ่าน'],400);

    $stmt = db()->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password']))
        json_response(['success'=>false,'message'=>'อีเมลหรือรหัสผ่านไม่ถูกต้อง'],401);

    $now = time();
    $token = jwt_encode([
        'sub'  => (int)$user['id'],
        'name' => $user['name'],
        'role' => $user['role'],
        'iat'  => $now,
        'exp'  => $now + JWT_EXPIRE_SECONDS,
    ], JWT_SECRET);

    json_response([
        'success'=>true, 'message'=>'เข้าสู่ระบบสำเร็จ', 'token'=>$token,
        'user'=>['id'=>(int)$user['id'],'name'=>$user['name'],'role'=>$user['role']],
    ]);
}

function handle_me(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['success'=>false,'message'=>'ต้องเรียกด้วยเมธอด GET'],405);
    $payload = require_auth();
    json_response(['success'=>true,'user'=>[
        'id'=>$payload['sub']??null, 'name'=>$payload['name']??null, 'role'=>$payload['role']??null,
    ]]);
}