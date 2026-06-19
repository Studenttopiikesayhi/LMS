<?php
/** config.php — โหลดค่าลับจาก secret.php + ตั้งค่า CORS/JSON + เชื่อม DB + ฟังก์ชันช่วย */
declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');

header('Access-Control-Allow-Origin: *'); // โปรเจกต์จริงควรระบุโดเมน Frontend แทน *
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ---------- โหลดค่าลับจาก secret.php ----------
$secretFile = __DIR__ . '/secret.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'ไม่พบไฟล์ secret.php (คัดลอกจาก secret.example.php แล้วใส่ค่าจริง)'], JSON_UNESCAPED_UNICODE);
    exit;
}
$cfg = require $secretFile;

define('DB_HOST', $cfg['DB_HOST']);
define('DB_NAME', $cfg['DB_NAME']);
define('DB_USER', $cfg['DB_USER']);
define('DB_PASS', $cfg['DB_PASS']);
define('JWT_SECRET', $cfg['JWT_SECRET']);
const JWT_EXPIRE_SECONDS = 3600; // อายุ token 1 ชั่วโมง

// ---------- เชื่อมต่อฐานข้อมูล (PDO) ----------
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            json_response(['success' => false, 'message' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ'], 500);
        }
    }
    return $pdo;
}

// ---------- ตอบกลับ JSON แล้วจบการทำงาน ----------
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- อ่าน JSON Body จาก Request ----------
function get_json_body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

// ---------- อ่าน Bearer Token จาก Header Authorization ----------
function get_bearer_token(): ?string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($auth === '' && function_exists('apache_request_headers')) {
        $all = apache_request_headers();
        $auth = $all['Authorization'] ?? $all['authorization'] ?? '';
    }
    return preg_match('/Bearer\s+(.+)/i', $auth, $m) ? trim($m[1]) : null;
}