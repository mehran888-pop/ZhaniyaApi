<?php
/** توابع عمومی */

function config(?string $key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require dirname(__DIR__) . '/config.php';
    }
    if ($key === null) {
        return $cfg;
    }
    $parts = explode('.', $key);
    $val = $cfg;
    foreach ($parts as $p) {
        if (is_array($val) && array_key_exists($p, $val)) {
            $val = $val[$p];
        } else {
            return $default;
        }
    }
    return $val;
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail(string $msg, int $status = 400, array $extra = []): void
{
    json_out(array_merge(['ok' => false, 'error' => $msg], $extra), $status);
}

function num($v, float $def = 0.0): float
{
    return is_numeric($v) ? (float)$v : $def;
}

function money($v): string
{
    return number_format((float)$v, 0, '.', ',');
}

/** تاریخ امروز میلادی YYYY-MM-DD */
function today_g(): string
{
    return (new DateTime('now', new DateTimeZone(config('timezone'))))->format('Y-m-d');
}

/**
 * تبدیل تاریخ ورودی کاربر به میلادی YYYY-MM-DD.
 * هر دو قالب جلالی (1405/08/13) و میلادی (2026-11-04) پذیرفته می‌شود.
 */
function parse_date(string $in, ?string $fallback = null): ?string
{
    $in = trim(str_replace(['-', '/'], '/', $in));
    if ($in === '') {
        return $fallback;
    }
    if (preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $in, $m)) {
        $y = (int)$m[1];
        if ($y > 1700) { // میلادی
            return sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[3]);
        }
        if ($y >= 1200 && $y <= 1600) { // جلالی
            require_once __DIR__ . '/jalali.php';
            [$gy, $gm, $gd] = jalali_to_gregorian($y, (int)$m[2], (int)$m[3]);
            return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        }
    }
    $ts = strtotime($in);
    return $ts ? date('Y-m-d', $ts) : $fallback;
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), (string)$sent)) {
        json_fail('CSRF نامعتبر', 419);
    }
}
