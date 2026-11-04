<?php
require_once __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/src/repo.php';

auth_require();

$devices = rep_devices();
$logs = db()->query(
    'SELECT s.received_at, s.batch_id, s.invoice_count, s.item_count, d.name AS device_name
     FROM sync_log s JOIN devices d ON d.id = s.device_id
     ORDER BY s.id DESC LIMIT 30'
)->fetchAll();

page_header('دستگاه‌ها و وضعیت اتصال', 'devices');
?>
<div class="alert info">
  هر دستگاه (کانکتور فروشگاه) با کلید و امضای HMAC اختصاصی به این سرور متصل می‌شود؛
  اتصال از سمت فروشگاه به‌صورت <b>outbound</b> است و به IP ثابت نیاز ندارد.
</div>

<div class="panel">
  <h2>دستگاه‌های ثبت‌شده</h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>نام دستگاه</th><th>فروشگاه</th><th>وضعیت</th><th>آخرین ارتباط</th>
        <th>آخرین دسته‌داده</th><th>تعداد فاکتور</th><th>آخرین تاریخ فاکتور</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($devices as $d): ?>
      <?php $fresh = $d['last_seen_at'] && (time() - strtotime($d['last_seen_at'] . ' UTC') < 300); ?>
      <tr>
        <td><b><?= e($d['name']) ?></b></td>
        <td><?= e($d['store_name']) ?></td>
        <td><span class="badge <?= $fresh ? 'ok' : 'off' ?>"><?= $fresh ? 'متصل (فعال)' : 'غیرفعال/آفلاین' ?></span></td>
        <td><?= e($d['last_seen_at'] ?: '—') ?></td>
        <td class="num"><?= e($d['last_batch_id'] ? mb_substr($d['last_batch_id'], 0, 8) : '—') ?></td>
        <td class="num"><?= money($d['invoice_count']) ?></td>
        <td><?= e($d['last_invoice_date'] ? jalali_date($d['last_invoice_date']) : '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$devices): ?>
      <tr><td colspan="7" class="muted">هنوز دستگاهی ثبت نشده است. برای افزودن، ردیف جدول <code>devices</code> را اضافه کنید.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="panel mt">
  <h2>آخرین دسته‌های دریافتی (Sync Log)</h2>
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
