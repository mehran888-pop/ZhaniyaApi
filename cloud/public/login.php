<?php
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/auth.php';
auth_start();

if (auth_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        $error = 'خطای اعتبارسنجی؛ دوباره تلاش کنید.';
    } else {
        $u = auth_attempt(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''));
        if ($u) {
            header('Location: dashboard.php');
            exit;
        }
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود | <?= e(config('app_name')) ?></title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">ژ</div>
    <h1><?= e(config('app_name')) ?></h1>
    <p class="sub">برای مشاهده داشبورد و گزارش‌ها وارد شوید</p>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="login.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label>نام کاربری</label>
        <input name="username" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label>رمز عبور</label>
        <input type="password" name="password" autocomplete="current-password" required>
      </div>
      <button class="btn block" type="submit">ورود به داشبورد</button>
    </form>
  </div>
</div>
</body>
</html>
