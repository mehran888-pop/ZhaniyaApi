<?php
/**
 * Zhaniya Cloud — ویزارد نصب تحت وب (Setup Wizard)
 * ------------------------------------------------
 * بعد از آپلود فایل‌ها، کافی است این صفحه را در مرورگر باز کنید:
 *   http://YOUR-SERVER/setup.php
 *
 * این صفحه فقط تا قبل از نصب کامل در دسترس است؛ پس از نصب،
 * فایل data/install.lock ساخته می‌شود و دیگر اجرا نمی‌شود.
 */
declare(strict_types=1);

$CLOUD_DIR = dirname(__DIR__);
$LOCK = $CLOUD_DIR . '/data/install.lock';

// ------------------------------------------------------------- کمکی‌ها
function s_e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function check_requirements(): array
{
    global $CLOUD_DIR;
    $checks = [];
    $checks[] = ['PHP 8.0+', version_compare(PHP_VERSION, '8.0.0', '>='), 'نسخه‌ی فعلی: ' . PHP_VERSION];
    $checks[] = ['اکستنشن PDO', extension_loaded('pdo'), ''];
    $checks[] = ['اکستنشن SQLite (pdo_sqlite یا sqlite3)', extension_loaded('pdo_sqlite') || extension_loaded('sqlite3'), 'برای نصب آسان بدون دیتابیس'];
    $checks[] = ['اکستنشن MySQL (pdo_mysql)', extension_loaded('pdo_mysql'), 'فقط برای حالت MySQL لازم است'];
    $checks[] = ['اکستنشن mbstring', extension_loaded('mbstring'), ''];
    $checks[] = ['اکستنشن OpenSSL', extension_loaded('openssl'), 'برای هش/توکن‌ها'];
    $writableData = is_dir("$CLOUD_DIR/data") ? is_writable("$CLOUD_DIR/data") : is_writable($CLOUD_DIR);
    $checks[] = ['مجوز نوشتن پوشه‌ی data/', $writableData, $CLOUD_DIR . '/data'];
    $checks[] = ['مجوز نوشتن config.php', is_writable("$CLOUD_DIR/config.php") || is_writable($CLOUD_DIR), $CLOUD_DIR . '/config.php'];
    return $checks;
}

function test_db_connection(array $post): array
{
    // [bool ok, string message]
    $driver = ($post['db_driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    if ($driver === 'sqlite') {
        $path = ($post['db_path'] ?? '') !== '' ? $post['db_path'] : (dirname(__DIR__) . '/data/zhaniya_cloud.sqlite');
        $path = str_replace(['{APP}', '{app}'], dirname(__DIR__), $path);
        try {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path);
            $pdo->query('SELECT 1');
            return [true, 'اتصال SQLite برقرار شد: ' . $path];
        } catch (Throwable $ex) {
            return [false, 'خطای SQLite: ' . $ex->getMessage()];
        }
    }
    // MySQL
    $host = trim((string)($post['mysql_host'] ?? '127.0.0.1'));
    $port = (int)($post['mysql_port'] ?? 3306);
    $name = trim((string)($post['mysql_name'] ?? 'zhaniya_cloud'));
    $user = trim((string)($post['mysql_user'] ?? ''));
    $pass = (string)($post['mysql_pass'] ?? '');
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4");
        $pdo->exec("USE `$name`");
        return [true, "اتصال MySQL برقرار و دیتابیس `$name` آماده شد."];
    } catch (Throwable $ex) {
        return [false, 'خطای MySQL: ' . $ex->getMessage()];
    }
}

function render_config_php(array $v): string
{
    $export = fn($x) => var_export($x, true);
    // جایگزینی نشانگر {APP} با مسیر واقعی برنامه
    $dbPath = str_replace(['{APP}', '{app}'], dirname(__DIR__), (string)$v['db_path']);
    return "<?php
/**
 * Zhaniya Cloud — تنظیمات (ساخته‌شده توسط ویزارد نصب)
 * تاریخ نصب: " . date('Y-m-d H:i') . "
 */
return [
    'db_driver' => " . $export($v['db_driver']) . ",
    'db_path'   => " . $export($dbPath) . ",
    'mysql'     => [
        'host'     => " . $export($v['mysql_host']) . ",
        'port'     => " . $export((int)$v['mysql_port']) . ",
        'dbname'   => " . $export($v['mysql_name']) . ",
        'user'     => " . $export($v['mysql_user']) . ",
        'password' => " . $export($v['mysql_pass']) . ",
        'charset'  => 'utf8mb4',
    ],
    'app_name'     => 'داشبورد مدیریت ژانیا',
    'session_name' => 'ZHANIYA_SESS',
    'app_secret'   => " . $export($v['app_secret']) . ",
    'ingest' => ['max_clock_skew' => 300],
    'currency' => 'تومان',
    'timezone' => 'Asia/Tehran',
];
";
}

// ------------------------------------------------------------- نصب انجام‌شده؟
if (file_exists($LOCK)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>نصب انجام شده</title>
    <link rel="stylesheet" href="assets/app.css"></head><body><div class="login-wrap"><div class="login-card" style="text-align:center">
    <div class="login-logo">✓</div><h1>نصب قبلاً انجام شده است</h1>
    <p class="sub">سیستم آماده‌ی استفاده است. برای نصب مجدد، فایل <code>data/install.lock</code> را حذف کنید.</p>
    <a class="btn block" href="login.php">ورود به داشبورد</a>
    </div></div></body></html>';
    exit;
}

// ------------------------------------------------------------- پردازش POST
$step = (string)($_GET['step'] ?? 'check');
$action = (string)($_POST['action'] ?? '');
$errors = [];

$V = [
    'db_driver' => $_POST['db_driver'] ?? 'sqlite',
    'db_path' => $_POST['db_path'] ?? '{APP}/data/zhaniya_cloud.sqlite',
    'mysql_host' => $_POST['mysql_host'] ?? '127.0.0.1',
    'mysql_port' => $_POST['mysql_port'] ?? '3306',
    'mysql_name' => $_POST['mysql_name'] ?? 'zhaniya_cloud',
    'mysql_user' => $_POST['mysql_user'] ?? 'zhaniya',
    'mysql_pass' => $_POST['mysql_pass'] ?? '',
    'admin_user' => $_POST['admin_user'] ?? 'admin',
    'admin_pass' => $_POST['admin_pass'] ?? '',
    'admin_pass2' => $_POST['admin_pass2'] ?? '',
    'admin_name' => $_POST['admin_name'] ?? 'مدیر سیستم',
    'device_name' => $_POST['device_name'] ?? 'کانکتور شعبه مرکزی',
    'store_name' => $_POST['store_name'] ?? 'فروشگاه مرکزی',
    'device_key' => $_POST['device_key'] ?? ('dev-' . bin2hex(random_bytes(6))),
    'device_secret' => $_POST['device_secret'] ?? bin2hex(random_bytes(24)),
    'seed_demo' => ($_POST['seed_demo'] ?? '') === '1' ? '1' : '0',
    'app_secret' => $_POST['app_secret'] ?? bin2hex(random_bytes(16)),
];

if ($action === 'test_db') {
    [$ok, $msg] = test_db_connection($V);
    $dbTestOk = $ok;
    $dbTestMsg = $msg;
    $step = 'db';
}

if ($action === 'install') {
    // اعتبارسنجی
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', (string)$V['admin_user'])) {
        $errors[] = 'نام کاربری باید ۳ تا ۳۲ کاراکتر انگلیسی باشد.';
    }
    if (strlen((string)$V['admin_pass']) < 8) {
        $errors[] = 'رمز عبور مدیر باید حداقل ۸ کاراکتر باشد.';
    }
    if ($V['admin_pass'] !== $V['admin_pass2']) {
        $errors[] = 'تکرار رمز عبور مطابقت ندارد.';
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]{4,64}$/', (string)$V['device_key'])) {
        $errors[] = 'کلید دستگاه باید ۴ تا ۶۴ کاراکتر انگلیسی/عدد باشد.';
    }
    if (strlen((string)$V['device_secret']) < 12) {
        $errors[] = 'رمز دستگاه باید حداقل ۱۲ کاراکتر باشد.';
    }

    if (!$errors) {
        [$ok, $msg] = test_db_connection($V);
        if (!$ok) {
            $errors[] = $msg;
        }
    }

    if (!$errors) {
        $stage = 'آماده‌سازی';
        try {
            if (!is_file($CLOUD_DIR . '/src/schema.php')) {
                throw new RuntimeException(
                    'فایل src/schema.php یافت نشد. مطمئن شوید کل پوشه‌ی cloud (شامل زیرپوشه‌های public و src) را آپلود کرده‌اید، نه فقط public را.'
                );
            }
            require_once $CLOUD_DIR . '/src/schema.php';

            // ۱) نوشتن config.php
            $stage = 'نوشتن فایل config.php';
            $cfgPhp = render_config_php($V);
            if (file_put_contents($CLOUD_DIR . '/config.php', $cfgPhp) === false) {
                throw new RuntimeException('نوشتن config.php ممکن نشد (مجوز نوشتن را بررسی کنید)');
            }

            // ۲) اتصال به پایگاه‌داده
            $stage = 'اتصال به پایگاه‌داده';
            if ($V['db_driver'] === 'mysql') {
                $pdo = new PDO(
                    "mysql:host={$V['mysql_host']};port={$V['mysql_port']};dbname={$V['mysql_name']};charset=utf8mb4",
                    $V['mysql_user'], $V['mysql_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } else {
                $path = str_replace(['{APP}', '{app}'], $CLOUD_DIR, (string)$V['db_path']);
                if (trim($path) === '') {
                    $path = $CLOUD_DIR . '/data/zhaniya_cloud.sqlite';
                }
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec('PRAGMA journal_mode=WAL');
            }

            // ۳) ساخت جداول (از src/schema.php — بدون وابستگی به فایل .sql)
            $stage = 'ساخت جداول پایگاه‌داده';
            zh_apply_schema($pdo, (string)$V['db_driver']);

            // ۴) ساخت کاربر مدیر
            $stage = 'ساخت کاربر مدیر';
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $st->execute([(string)$V['admin_user']]);
            if ((int)$st->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)')
                    ->execute([
                        (string)$V['admin_user'],
                        password_hash((string)$V['admin_pass'], PASSWORD_DEFAULT),
                        (string)$V['admin_name'],
                        'admin',
                    ]);
            }

            // ۵) ثبت دستگاه (کانکتور فروشگاه)
            $stage = 'ثبت دستگاه فروشگاه';
            $st = $pdo->prepare('SELECT COUNT(*) FROM devices WHERE device_key = ?');
            $st->execute([(string)$V['device_key']]);
            if ((int)$st->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO devices (device_key, secret, name, store_name, status) VALUES (?,?,?,?,?)')
                    ->execute([
                        (string)$V['device_key'], (string)$V['device_secret'],
                        (string)$V['device_name'], (string)$V['store_name'], 'active',
                    ]);
            }

            // ۶) داده‌ی نمونه (اختیاری — خطا در این مرحله نصب را خراب نمی‌کند)
            $seedLog = '';
            if ($V['seed_demo'] === '1' && $V['db_driver'] === 'sqlite') {
                try {
                    $stage = 'افزودن داده‌ی نمونه';
                    ob_start();
                    include $CLOUD_DIR . '/seed_demo.php';
                    $seedLog = trim(ob_get_clean());
                } catch (Throwable $seedEx) {
                    ob_end_clean();
                    $seedLog = 'نصب انجام شد؛ ولی افزودن داده‌ی نمونه ناموفق بود: ' . $seedEx->getMessage();
                }
            } elseif ($V['seed_demo'] === '1') {
                $seedLog = 'داده‌ی نمونه فقط برای SQLite اضافه می‌شود (برای MySQL از php cloud/seed_demo.php استفاده کنید).';
            }

            // ۷) قفل نصب
            $stage = 'نهایی‌سازی نصب';
            if (!is_dir($CLOUD_DIR . '/data')) {
                mkdir($CLOUD_DIR . '/data', 0775, true);
            }
            file_put_contents($LOCK, date('c') . "\n");

            // آماده‌سازی config کانکتور برای نمایش
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $connectorCfg = [
                'mode' => 'demo',
                'cloud_url' => $scheme . '://' . $host,
                'ingest_path' => 'api/ingest.php',
                'health_path' => 'api/health.php',
                'device_key' => $V['device_key'],
                'device_secret' => $V['device_secret'],
                'sync_interval_sec' => 20,
                'heartbeat_sec' => 120,
                'http_timeout' => 30,
                'verify_tls' => true,
            ];
            $step = 'done';
        } catch (Throwable $ex) {
            $errors[] = 'خطای نصب (مرحله‌ی ' . $stage . '): ' . $ex->getMessage();
            $step = 'admin';
        }
    } else {
        $step = 'admin';
    }
}

$steps = ['check' => '۱. بررسی سیستم', 'db' => '۲. پایگاه‌داده', 'admin' => '۳. مدیر و دستگاه', 'done' => '۴. پایان'];
$stepIdx = array_search($step, array_keys($steps), true);
if ($stepIdx === false) {
    $stepIdx = 0;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب Zhaniya Cloud</title>
<link rel="stylesheet" href="assets/app.css">
<style>
.setup-wrap {
  min-height: 100vh; width: 100%; padding: 30px 16px;
  display: flex; align-items: flex-start; justify-content: center;
  background: linear-gradient(135deg, #0b1220 0%, #12263f 55%, #0e3a3a 100%);
}
.setup-card {
  width: 680px; max-width: 100%;
  background: #fff; border-radius: 18px; padding: 30px 32px;
  box-shadow: 0 25px 60px rgba(0,0,0,.4);
}
.steps { display: flex; gap: 6px; margin-bottom: 22px; }
.steps .s {
  flex: 1; text-align: center; font-size: 11.5px; padding: 8px 4px;
  border-radius: 8px; background: #f1f5f9; color: #94a3b8;
}
.steps .s.on { background: #0ea5a4; color: #fff; font-weight: 700; }
.steps .s.past { background: #ccfbf1; color: #0f766e; }
.setup-card h1 { font-size: 20px; margin-bottom: 6px; }
.setup-card p.sub { color: #64748b; font-size: 13px; margin-bottom: 18px; }
.row { display: flex; gap: 12px; flex-wrap: wrap; }
.row .field { flex: 1; min-width: 220px; margin-bottom: 14px; }
.setup-card label { display: block; font-size: 12px; color: #64748b; margin-bottom: 6px; }
.setup-card input[type=text], .setup-card input[type=password], .setup-card input[type=number] {
  width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px;
  font-size: 13.5px; font-family: inherit;
}
.setup-card input:focus { outline: 2px solid rgba(14,165,164,.35); }
.check-list { list-style: none; margin-bottom: 18px; }
.check-list li {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px;
}
.ok-tag { color: #16a34a; font-weight: 700; }
.bad-tag { color: #dc2626; font-weight: 700; }
.note { font-size: 11.5px; color: #94a3b8; }
.radio-row { display: flex; gap: 14px; margin-bottom: 14px; }
.radio-row label { display: flex; gap: 6px; align-items: center; font-size: 13px; color: #0f172a; cursor: pointer; }
pre.cfg {
  direction: ltr; text-align: left; background: #0f172a; color: #a5f3fc;
  padding: 14px; border-radius: 10px; font-size: 11.5px; overflow: auto; max-height: 260px;
}
.actions { display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
.dl-btn { background: #6366f1; }
.alert.err { margin-bottom: 14px; }
.hint-box {
  background: #f0f9ff; border: 1px solid #bae6fd; color: #075985;
  border-radius: 10px; padding: 11px 13px; font-size: 12px; margin-bottom: 14px;
}
</style>
</head>
<body>
<div class="setup-wrap">
  <div class="setup-card">
    <div style="text-align:center;margin-bottom:18px">
      <div class="login-logo" style="margin:0 auto 10px">ژ</div>
      <b style="font-size:16px">نصب آسان Zhaniya Cloud</b>
    </div>

    <div class="steps">
      <?php foreach (array_values($steps) as $i => $label): ?>
        <div class="s <?= $i === $stepIdx ? 'on' : ($i < $stepIdx ? 'past' : '') ?>"><?= s_e($label) ?></div>
      <?php endforeach; ?>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert err"><?= s_e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 'check'): ?>
      <h1>بررسی پیش‌نیازها</h1>
      <p class="sub">از آماده‌بودن سرور مطمئن می‌شویم. همه‌چیز باید سبز باشد ✅</p>
      <ul class="check-list">
        <?php foreach (check_requirements() as [$label, $ok, $note]): ?>
          <li>
            <span><?= s_e($label) ?> <?php if ($note): ?><span class="note">(<?= s_e($note) ?>)</span><?php endif; ?></span>
            <span class="<?= $ok ? 'ok-tag' : 'bad-tag' ?>"><?= $ok ? '✓ آماده' : '✗ نیازمند بررسی' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="actions">
        <form method="get" action="setup.php"><input type="hidden" name="step" value="db">
          <button class="btn" type="submit">ادامه →</button>
        </form>
        <a class="btn secondary" href="setup.php">بررسی دوباره</a>
      </div>

    <?php elseif ($step === 'db'): ?>
      <h1>پایگاه‌داده</h1>
      <p class="sub">برای شروع سریع، SQLite را بگذارید بماند (بدون هیچ پیکربندی!). برای تولید/چندکاربره از MySQL استفاده کنید.</p>
      <form method="post" action="setup.php?step=db">
        <input type="hidden" name="action" value="test_db">
        <div class="radio-row">
          <label><input type="radio" name="db_driver" value="sqlite" <?= $V['db_driver'] !== 'mysql' ? 'checked' : '' ?>> SQLite (آسان — پیشنهادی برای شروع)</label>
          <label><input type="radio" name="db_driver" value="mysql" <?= $V['db_driver'] === 'mysql' ? 'checked' : '' ?>> MySQL / MariaDB (تولید)</label>
        </div>
        <div class="row">
          <div class="field">
            <label>مسیر فایل SQLite (اختیاری)</label>
            <input type="text" name="db_path" value="<?= s_e($V['db_path']) ?>">
          </div>
        </div>
        <div class="row">
          <div class="field"><label>میزبان MySQL</label><input type="text" name="mysql_host" value="<?= s_e($V['mysql_host']) ?>"></div>
          <div class="field"><label>پورت</label><input type="number" name="mysql_port" value="<?= s_e($V['mysql_port']) ?>"></div>
        </div>
        <div class="row">
          <div class="field"><label>نام دیتابیس</label><input type="text" name="mysql_name" value="<?= s_e($V['mysql_name']) ?>"></div>
          <div class="field"><label>کاربر</label><input type="text" name="mysql_user" value="<?= s_e($V['mysql_user']) ?>"></div>
          <div class="field"><label>رمز عبور</label><input type="password" name="mysql_pass" value="<?= s_e($V['mysql_pass']) ?>"></div>
        </div>
        <div class="actions">
          <button class="btn secondary" type="submit">🔌 تست اتصال</button>
        </div>
      </form>

      <?php if (isset($dbTestOk)): ?>
        <div class="alert <?= $dbTestOk ? 'info' : 'err' ?>" style="margin-top:14px">
          <?= $dbTestOk ? '✓' : '✗' ?> <?= s_e($dbTestMsg) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="setup.php?step=admin">
        <?php foreach ($V as $k => $v): ?>
          <?php if (!in_array($k, ['admin_pass', 'admin_pass2'], true)): ?>
            <input type="hidden" name="<?= s_e($k) ?>" value="<?= s_e($v) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <div class="actions">
          <button class="btn" type="submit">ادامه →</button>
        </div>
      </form>

    <?php elseif ($step === 'admin'): ?>
      <h1>حساب مدیر و دستگاه فروشگاه</h1>
      <p class="sub">کاربر داشبورد را بسازید و کلید اتصال کانکتور فروشگاه را تعیین کنید (به‌صورت خودکار تولید شده‌اند).</p>
      <form method="post" action="setup.php?step=install">
        <input type="hidden" name="action" value="install">
        <?php foreach ($V as $k => $v): ?>
          <?php if (!in_array($k, ['admin_user', 'admin_pass', 'admin_pass2', 'admin_name', 'device_name', 'store_name', 'device_key', 'device_secret', 'seed_demo'], true)): ?>
            <input type="hidden" name="<?= s_e($k) ?>" value="<?= s_e($v) ?>">
          <?php endif; ?>
        <?php endforeach; ?>

        <div class="hint-box">👤 حساب مدیر داشبورد</div>
        <div class="row">
          <div class="field"><label>نام کاربری (انگلیسی)</label><input type="text" name="admin_user" value="<?= s_e($V['admin_user']) ?>" required></div>
          <div class="field"><label>نام کامل</label><input type="text" name="admin_name" value="<?= s_e($V['admin_name']) ?>"></div>
        </div>
        <div class="row">
          <div class="field"><label>رمز عبور (حداقل ۸ کاراکتر)</label><input type="password" name="admin_pass" required></div>
          <div class="field"><label>تکرار رمز عبور</label><input type="password" name="admin_pass2" required></div>
        </div>

        <div class="hint-box">🔌 دستگاه (کانکتور) فروشگاه — این مقادیر را در config.json کانکتور وارد می‌کنید</div>
        <div class="row">
          <div class="field"><label>نام دستگاه</label><input type="text" name="device_name" value="<?= s_e($V['device_name']) ?>"></div>
          <div class="field"><label>نام فروشگاه</label><input type="text" name="store_name" value="<?= s_e($V['store_name']) ?>"></div>
        </div>
        <div class="row">
          <div class="field"><label>Device Key</label><input type="text" name="device_key" value="<?= s_e($V['device_key']) ?>"></div>
          <div class="field"><label>Device Secret</label><input type="text" name="device_secret" value="<?= s_e($V['device_secret']) ?>"></div>
        </div>

        <div class="hint-box">
          <label style="display:flex;gap:8px;align-items:center;cursor:pointer">
            <input type="checkbox" name="seed_demo" value="1" <?= $V['seed_demo'] === '1' ? 'checked' : '' ?>>
            افزودن داده‌ی نمونه برای نمایش نمودارها (فقط SQLite)
          </label>
        </div>

        <div class="actions">
          <button class="btn" type="submit">🚀 نصب نهایی</button>
          <a class="btn secondary" href="setup.php?step=db">بازگشت</a>
        </div>
      </form>

    <?php elseif ($step === 'done'): ?>
      <h1>نصب با موفقیت انجام شد! 🎉</h1>
      <p class="sub">سیستم آماده‌ی استفاده است. فایل زیر را در سیستم فروشگاه ذخیره و در پوشه‌ی کانکتور با نام <code>config.json</code> قرار دهید.</p>

      <?php if (!empty($seedLog)): ?>
        <div class="alert info"><?= s_e($seedLog) ?></div>
      <?php endif; ?>

      <pre class="cfg" id="cfgJson"><?= s_e(json_encode($connectorCfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>

      <div class="actions">
        <button class="btn dl-btn" type="button" onclick="downloadCfg()">⬇ دانلود config.json کانکتور</button>
        <a class="btn" href="login.php">ورود به داشبورد →</a>
      </div>
      <p class="note" style="margin-top:14px">
        نکته: اگر دامنه/پورت سرور در نهایت متفاوت است، مقدار <code>cloud_url</code> را در config.json اصلاح کنید.
        برای حالت اتصال واقعی به ژانیا، مقدار <code>mode</code> را به <code>api</code> یا <code>sqlserver</code> تغییر دهید.
      </p>
      <script>
      function downloadCfg() {
        const text = document.getElementById('cfgJson').textContent;
        const blob = new Blob([text], {type: 'application/json;charset=utf-8'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'config.json';
        a.click();
        URL.revokeObjectURL(a.href);
      }
      </script>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
