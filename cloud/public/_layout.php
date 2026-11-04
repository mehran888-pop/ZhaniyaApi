<?php
/** چیدمان مشترک صفحات داشبورد */
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/jalali.php';

function page_header(string $title, string $active = ''): void
{
    $user = auth_user();
    $nav = [
        'dashboard' => ['dashboard.php', 'داشبورد', '📊'],
        'reports'   => ['reports.php', 'گزارش فروش و اقلام', '🧾'],
        'credits'   => ['credits.php', 'گزارش اعتباری‌ها', '💳'],
        'realtime'  => ['realtime.php', 'گزارش لحظه‌ای', '⚡'],
        'devices'   => ['devices.php', 'دستگاه‌ها و اتصال', '🔌'],
    ];
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e(config('app_name')) ?></title>
<link rel="stylesheet" href="assets/app.css">
<script src="assets/vendor/chart.umd.js"></script>
<script>
window.ZH = {
  currency: <?= json_encode(config('currency'), JSON_UNESCAPED_UNICODE) ?>,
  today: <?= json_encode(today_g()) ?>,
  fmt: n => Number(n || 0).toLocaleString('en-US', {maximumFractionDigits: 0}),
  money: n => Number(n || 0).toLocaleString('en-US', {maximumFractionDigits: 0}) + ' <?= e(config('currency')) ?>',
  api: (url) => fetch(url, {credentials: 'same-origin'}).then(r => r.json())
};
Chart.defaults.font.family = 'Tahoma, "Segoe UI", sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = '#475569';
</script>
</head>
<body>
<aside class="sidebar">
  <div class="brand">
    <div class="brand-logo">ژ</div>
    <div>
      <div class="brand-title">ژانیا کلود</div>
      <div class="brand-sub">مدیریت متمرکز فروش</div>
    </div>
  </div>
  <nav>
    <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
      <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>">
        <span class="nav-icon"><?= $icon ?></span><?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <div class="user-chip">
      <div class="avatar"><?= e(mb_substr($user['full_name'] ?: $user['username'], 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= e($user['full_name'] ?: $user['username']) ?></div>
        <div class="user-role"><?= e($user['role'] === 'admin' ? 'مدیر کل' : 'مدیر') ?></div>
      </div>
    </div>
    <a class="logout" href="logout.php">خروج</a>
  </div>
</aside>
<main class="content">
  <header class="topbar">
    <h1><?= e($title) ?></h1>
    <div class="topbar-date">
      <?= e(jalali_date(today_g())) ?> &nbsp;•&nbsp; <?= e(today_g()) ?>
    </div>
  </header>
    <?php
}

function page_footer(): void
{
    ?>
</main>
</body>
</html>
    <?php
}
