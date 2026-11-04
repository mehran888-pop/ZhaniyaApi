<?php
require_once __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/src/repo.php';

auth_require();

$flash = $_SESSION['flash_device'] ?? null;
unset($_SESSION['flash_device']);

// ------------------------------------------------------------- اکشن‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_device') {
        $name = trim((string)($_POST['name'] ?? ''));
        $store = trim((string)($_POST['store_name'] ?? ''));
        if ($name === '') {
            $err = 'نام دستگاه را وارد کنید.';
        } else {
            $key = 'dev-' . bin2hex(random_bytes(6));
            $secret = bin2hex(random_bytes(24));
            db()->prepare('INSERT INTO devices (device_key, secret, name, store_name, status) VALUES (?,?,?,?,?)')
                ->execute([$key, $secret, $name, $store, 'active']);
            $_SESSION['flash_device'] = [
                'id' => (int)db()->lastInsertId(),
                'key' => $key,
                'secret' => $secret,
                'name' => $name,
            ];
            header('Location: devices.php');
            exit;
        }
    }

    if ($action === 'rotate_secret') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->prepare('SELECT * FROM devices WHERE id = ?');
        $st->execute([$id]);
        $dev = $st->fetch();
        if ($dev) {
            $secret = bin2hex(random_bytes(24));
            db()->prepare('UPDATE devices SET secret = ? WHERE id = ?')->execute([$secret, $id]);
            $_SESSION['flash_device'] = [
                'id' => $id, 'key' => $dev['device_key'], 'secret' => $secret,
                'name' => $dev['name'], 'rotated' => true,
            ];
        }
        header('Location: devices.php');
        exit;
    }

    if ($action === 'download_config') {
        $id = (int)($_POST['device_id'] ?? 0);
        $st = db()->prepare('SELECT * FROM devices WHERE id = ?');
        $st->execute([$id]);
        $dev = $st->fetch();
        if (!$dev) {
            json_fail('دستگاه یافت نشد', 404);
        }
        $mode = in_array($_POST['conn_mode'] ?? '', ['api', 'sqlserver', 'demo'], true) ? $_POST['conn_mode'] : 'api';

        // آدرس پایه‌ی همین داشبورد (جایی که api/ingest.php روی آن است)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $cloudUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base;

        $cfg = [
            'mode' => $mode,
            'cloud_url' => $cloudUrl,
            'ingest_path' => 'api/ingest.php',
            'health_path' => 'api/health.php',
            'device_key' => $dev['device_key'],
            'device_secret' => $dev['secret'],
            'sync_interval_sec' => 20,
            'heartbeat_sec' => 120,
            'http_timeout' => 30,
            'verify_tls' => true,
            'api' => [
                'base_url' => trim((string)($_POST['api_url'] ?? '')) ?: 'http://127.0.0.1/ZhaniyaApi',
                'username' => (string)($_POST['api_user'] ?? ''),
                'password' => (string)($_POST['api_pass'] ?? ''),
                'agency' => (string)($_POST['api_agency'] ?? ''),
                'login_path' => 'api/User/LoginWithAgency',
                'invoices_path' => 'api/Invoice/GetAll',
                'timeout' => 30,
            ],
            'sql' => [
                'connection_string' => (string)($_POST['sql_conn'] ?? '')
                    ?: 'DRIVER={ODBC Driver 17 for SQL Server};SERVER=.\\SQLEXPRESS;DATABASE=ZhaniyaDB;UID=sa;PWD=CHANGE_ME',
            ],
        ];

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="config.json"');
        echo json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$devices = rep_devices();

page_header('دستگاه‌ها و وضعیت اتصال', 'devices');
?>
<?php if (!empty($err)): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<?php if ($flash): ?>
<div class="alert info">
  ✅ دستگاه «<?= e($flash['name']) ?>» <?= !empty($flash['rotated']) ? 'به‌روزرسانی شد' : 'ساخته شد' ?>.
  این مقادیر را همین حالا در config.json کانکتور وارد کنید:
  <div style="margin-top:10px; direction:ltr; text-align:left; background:#0f172a; color:#a5f3fc; padding:10px 12px; border-radius:8px; font-size:12px">
    "device_key": "<b><?= e($flash['key']) ?></b>",<br>
    "device_secret": "<b><?= e($flash['secret']) ?></b>"
  </div>
  <form method="post" action="devices.php" style="margin-top:10px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="download_config">
    <input type="hidden" name="device_id" value="<?= (int)$flash['id'] ?>">
    <input type="hidden" name="conn_mode" value="api">
    <button class="btn" type="submit">⬇ دانلود config.json آماده</button>
  </form>
</div>
<?php endif; ?>

<div class="alert info">
  اتصال از سمت فروشگاه به‌صورت <b>outbound</b> است و به IP ثابت نیاز ندارد.
  برای هر فروشگاه یک دستگاه بسازید و فایل <code>config.json</code> را روی سیستم
  فروشگاه (کنار <code>zhaniya_connector.py</code>) قرار دهید.
</div>

<div class="panel">
  <h2>افزودن دستگاه (کانکتور) جدید</h2>
  <form method="post" action="devices.php" class="filters" style="border:0;box-shadow:none;padding:0">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_device">
    <div class="field">
      <label>نام دستگاه</label>
      <input name="name" placeholder="مثلاً: کانکتور شعبه ۱" required>
    </div>
    <div class="field">
      <label>نام فروشگاه/شعبه</label>
      <input name="store_name" placeholder="مثلاً: شعبه مرکزی">
    </div>
    <button class="btn" type="submit">＋ ساخت دستگاه</button>
  </form>
</div>

<div class="panel mt">
  <h2>ساخت config.json کانکتور <span class="hint">برای اتصال به ژانیا از طریق API</span></h2>
  <form method="post" action="devices.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="download_config">
    <div class="filters" style="border:0;box-shadow:none;padding:0">
      <div class="field">
        <label>دستگاه</label>
        <select name="device_id" required>
          <?php foreach ($devices as $d): ?>
            <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['store_name'] ?: '—') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>نحوه‌ی اتصال به ژانیا</label>
        <select name="conn_mode" id="connMode">
          <option value="api">API نرم‌افزار ژانیا (روی همان سیستم) — توصیه‌شده</option>
          <option value="sqlserver">دیتابیس مستقیم SQL Server</option>
          <option value="demo">داده‌ی آزمایشی (تست)</option>
        </select>
      </div>
      <div class="field">
        <label>آدرس ZhaniyaApi روی سیستم فروشگاه</label>
        <input name="api_url" value="http://127.0.0.1/ZhaniyaApi" dir="ltr">
      </div>
      <div class="field">
        <label>کاربر ژانیا</label>
        <input name="api_user" autocomplete="off">
      </div>
      <div class="field">
        <label>رمز ژانیا</label>
        <input name="api_pass" type="password" autocomplete="new-password">
      </div>
      <div class="field">
        <label>آژانس/شعبه (اگر ژانیا می‌خواهد — معمولاً خالی)</label>
        <input name="api_agency">
      </div>
      <button class="btn" type="submit">⬇ ساخت و دانلود config.json</button>
    </div>
  </form>
  <p class="muted small" style="margin-top:10px">
    فایل دانلودشده را کنار <code>zhaniya_connector.py</code> روی سیستم فروشگاه بگذارید و اجرا کنید.
  </p>
</div>

<div class="panel mt">
  <h2>دستگاه‌های ثبت‌شده</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>نام دستگاه</th><th>فروشگاه</th><th>وضعیت</th><th>آخرین ارتباط</th>
        <th>تعداد فاکتور</th><th>آخرین تاریخ فاکتور</th><th>عملیات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($devices as $d): ?>
      <?php $fresh = $d['last_seen_at'] && (time() - strtotime($d['last_seen_at'] . ' UTC') < 300); ?>
      <tr>
        <td><b><?= e($d['name']) ?></b><div class="muted small" dir="ltr"><?= e($d['device_key']) ?></div></td>
        <td><?= e($d['store_name']) ?></td>
        <td><span class="badge <?= $fresh ? 'ok' : 'off' ?>"><?= $fresh ? 'متصل (فعال)' : 'در انتظار اتصال' ?></span></td>
        <td><?= e($d['last_seen_at'] ?: '—') ?></td>
        <td class="num"><?= money($d['invoice_count']) ?></td>
        <td><?= e($d['last_invoice_date'] ? jalali_date($d['last_invoice_date']) : '—') ?></td>
        <td>
          <form method="post" action="devices.php" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="rotate_secret">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="btn secondary" type="submit" title="تولید رمز جدید (config قبلی باطل می‌شود)">چرخش رمز</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$devices): ?>
      <tr><td colspan="7" class="muted">هنوز دستگاهی نساخته‌اید — از فرم بالا یک دستگاه بسازید.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="panel mt">
  <h2>آخرین دسته‌های دریافتی (Sync Log)</h2>
  <?php
    $logs = db()->query(
        'SELECT s.received_at, s.batch_id, s.invoice_count, s.item_count, d.name AS device_name
         FROM sync_log s JOIN devices d ON d.id = s.device_id
         ORDER BY s.id DESC LIMIT 30'
    )->fetchAll();
  ?>
  <table class="tbl">
    <thead><tr><th>زمان دریافت (UTC)</th><th>دستگاه</th><th>شناسه دسته</th><th>فاکتور</th><th>قلم</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= e($l['received_at']) ?></td>
        <td><?= e($l['device_name']) ?></td>
        <td class="num"><?= e($l['batch_id']) ?></td>
        <td class="num"><?= money($l['invoice_count']) ?></td>
        <td class="num"><?= money($l['item_count']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?>
      <tr><td colspan="5" class="muted">هنوز دسته‌ای دریافت نشده است.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php page_footer(); ?>
