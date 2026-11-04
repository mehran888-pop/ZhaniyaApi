<?php
/** احراز هویت مدیر (نشست وب) */

function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(config('session_name', 'ZHANIYA_SESS'));
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function auth_user(): ?array
{
    auth_start();
    return $_SESSION['user'] ?? null;
}

function auth_require(): array
{
    $u = auth_user();
    if (!$u) {
        $api = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
        if ($api) {
            json_fail('ابتدا وارد شوید', 401);
        }
        header('Location: login.php');
        exit;
    }
    return $u;
}

function auth_attempt(string $username, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return null;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'full_name' => $row['full_name'],
        'role' => $row['role'],
    ];
    return $_SESSION['user'];
}

function auth_logout(): void
{
    auth_start();
    $_SESSION = [];
    session_destroy();
}
