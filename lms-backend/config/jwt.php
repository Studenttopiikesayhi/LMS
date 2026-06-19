<?php
/** jwt.php — สร้าง/ตรวจ JWT (HS256) + ฟังก์ชันตรวจสิทธิ์ (middleware) */
declare(strict_types=1);

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_encode(array $payload, string $secret): string {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $h = base64url_encode(json_encode($header, JSON_UNESCAPED_UNICODE));
    $p = base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    $signature = hash_hmac('sha256', "$h.$p", $secret, true);
    return "$h.$p." . base64url_encode($signature);
}

function jwt_decode(string $jwt, string $secret): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    [$h, $p, $sig] = $parts;

    $header = json_decode(base64url_decode($h), true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') return null;

    $expected = hash_hmac('sha256', "$h.$p", $secret, true);
    if (!hash_equals($expected, base64url_decode($sig))) return null;

    $payload = json_decode(base64url_decode($p), true);
    if (!is_array($payload)) return null;
    if (isset($payload['exp']) && time() >= (int)$payload['exp']) return null;
    return $payload;
}

// ---------- ตัวตรวจสิทธิ์ (ใช้ร่วมในทุก api) ----------
function require_auth(): array {
    $token = get_bearer_token();
    if ($token === null) json_response(['success'=>false,'message'=>'ต้องเข้าสู่ระบบก่อน (ไม่พบ token)'], 401);
    $payload = jwt_decode($token, JWT_SECRET);
    if ($payload === null) json_response(['success'=>false,'message'=>'token ไม่ถูกต้องหรือหมดอายุ'], 401);
    return $payload;
}

function require_admin(): array {
    $payload = require_auth();
    if (($payload['role'] ?? '') !== 'admin') {
        json_response(['success'=>false,'message'=>'เฉพาะผู้ดูแลระบบเท่านั้น'], 403);
    }
    return $payload;
}