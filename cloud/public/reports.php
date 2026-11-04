<?php
require_once __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/src/repo.php';

auth_require();

$from = parse_date($_GET['from'] ?? '', date('Y-m-d', strtotime('-29 days')));
$to   = parse_date($_GET['to'] ?? '', today_g());

// خروجی CSV
if (($_GET['export'] ?? '') === 'csv') {
    $rows = rep_sales_invoices($from, $to, 1000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-' . $from . '_' . $to . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM برای اکسل
    $out = fopen('php://output', 'w');
    fputcsv($out, ['شماره فاکتور', 'تاریخ', 'ساعت', 'مشتری', 'میز', 'کاربر', 'نوع پرداخت', 'جمع', 'تخفیف', 'قابل پرداخت', 'پرداختی', 'وضعیت']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['invoice_number'], jalali_date($r['invoice_date']), substr($r['invoice_time'], 0, 5),
            $r['person_name'], $r['desk'], $r['user_name'], $r['pay_type'],
            $r['total'], $r['discount'], $r['payable'], $r['paid'], $r['status'],
        ]);
    }
    fclose($out);
    exit;
}

$summary = rep_summary($from, $to);
$range   = rep_sales_range($from, $to);
$top     = rep_top_items($from, $to, 15);
$detail  = rep_items_detail($from, $to, 120);
$invoices = rep_sales_invoices($from, $to, 120);

$payLabel = ['cash' => 'نقدی', 'credit' => 'اعتباری', 'card' => 'کارت', 'mixed' => 'ترکیبی'];

page_header('گزارش فروش و اقلام فروش رفته', 'reports');
?>
<form class="filters" method="get" action="reports.php">
  <div class="field">
    <label>از تاریخ</label>
    <input name="from" value="<?= e(jalali_date($from)) ?>" placeholder="1405/08/01">
  </div>
  <div class="field">
    <label>تا تاریخ</label>
    <input name="to" value="<?= e(jalali_date($to)) ?>" placeholder="1405/08/13">
  </div>
  <button class="btn" type="submit">نمایش گزارش</button>
  <a class="btn secondary" href="reports.php?export=csv&from=<?= e(jalali_date($from)) ?>&to=<?= e(jalali_date($to)) ?>">خروجی CSV</a>
</form>

<div class="kpis">
  <div class="kpi"><div class="k-label">جمع فروش</div><div class="k-value"><?= money($summary['payable']) ?></div>
    <div class="k-sub">قبل از تخفیف: <?= money($summary['total']) ?></div></div>
  <div class="kpi indigo"><div class="k-label">تعداد فاکتور</div><div class="k-value"><?= money($summary['invoice_count']) ?></div>
    <div class="k-sub">میانگین سبد: <?= money($summary['avg_basket']) ?></div></div>
  <div class="kpi green"><div class="k-label">پرداخت نقدی</div><div class="k-value"><?= money($summary['cash_sale']) ?></div>
    <div class="k-sub">کارت: <?= money($summary['card_sale']) ?></div></div>
  <div class="kpi amber"><div class="k-label">فروش اعتباری</div><div class="k-value"><?= money($summary['credit_sale']) ?></div>
    <div class="k-sub">پرداختی ثبت‌شده: <?= money($summary['paid']) ?></div></div>
</div>

<div class="grid grid-2">
  <div class="panel">
    <h2>نمودار فروش روزانه <span class="hint"><?= e(jalali_date($from)) ?> تا <?= e(jalali_date($to)) ?></span></h2>
    <div class="chart-box"><canvas id="repSales"></canvas></div>
  </div>
  <div class="panel">
    <h2>اقلام فروش رفته — بر اساس مبلغ</h2>
    <div class="chart-box"><canvas id="repItems"></canvas></div>
  </div>
</div>

<div class="panel mt">
  <h2>گزارش اقلام فروش رفته <span class="hint">۱۲۰ ردیف آخر</span></h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>تاریخ</th><th>ساعت</th><th>فاکتور</th><th>کالا</th>
        <th>تعداد</th><th>قیمت واحد</th><th>تخفیف</th><th>مبلغ</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($detail as $r): ?>
      <tr>
        <td><?= e(jalali_date($r['d'])) ?></td>
        <td><?= e(substr($r['t'], 0, 5)) ?></td>
        <td><?= e($r['num']) ?></td>
        <td><?= e($r['name']) ?></td>
        <td class="num"><?= e(rtrim(rtrim(number_format((float)$r['qty'], 2, '.', ','), '0'), '.')) ?></td>
        <td class="num"><?= money($r['price']) ?></td>
        <td class="num"><?= money($r['discount']) ?></td>
        <td class="num"><b><?= money($r['amount']) ?></b></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$detail): ?>
      <tr><td colspan="8" class="muted">داده‌ای در این بازه یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="panel mt">
  <h2>گزارش فروش — فاکتورها <span class="hint">از تاریخ تا تاریخ</span></h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>شماره فاکتور</th><th>تاریخ</th><th>ساعت</th><th>مشتری</th><th>میز</th>
        <th>نوع پرداخت</th><th>جمع</th><th>تخفیف</th><th>قابل پرداخت</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $r): ?>
      <tr>
        <td><?= e($r['invoice_number']) ?></td>
        <td><?= e(jalali_date($r['invoice_date'])) ?></td>
        <td><?= e(substr($r['invoice_time'], 0, 5)) ?></td>
        <td><?= e($r['person_name'] ?: '—') ?></td>
        <td><?= e($r['desk'] ?: '—') ?></td>
        <td><span class="badge <?= e($r['pay_type']) ?>"><?= e($payLabel[$r['pay_type']] ?? $r['pay_type']) ?></span></td>
        <td class="num"><?= money($r['total']) ?></td>
        <td class="num"><?= money($r['discount']) ?></td>
        <td class="num"><b><?= money($r['payable']) ?></b></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?>
      <tr><td colspan="9" class="muted">داده‌ای در این بازه یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
new Chart(document.getElementById('repSales'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($r) => jalali_date($r['d']), $range), JSON_UNESCAPED_UNICODE) ?>,
    datasets: [
      { label: 'فروش', data: <?= json_encode(array_map(fn($r) => (float)$r['payable'], $range)) ?>, backgroundColor: '#0ea5a4', borderRadius: 5 },
      { label: 'تخفیف', data: <?= json_encode(array_map(fn($r) => (float)$r['discount'], $range)) ?>, backgroundColor: '#f59e0b', borderRadius: 5 },
      { label: 'اعتباری', data: <?= json_encode(array_map(fn($r) => (float)$r['credit_sale'], $range)) ?>, backgroundColor: '#ef4444', borderRadius: 5 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    scales: { y: { beginAtZero: true }, x: { reverse: true } },
    plugins: { legend: { position: 'bottom' } }
  }
});

new Chart(document.getElementById('repItems'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($r) => $r['name'], $top), JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      label: 'مبلغ فروش',
      data: <?= json_encode(array_map(fn($r) => (float)$r['amount'], $top)) ?>,
      backgroundColor: '#6366f1',
      borderRadius: 5
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
    scales: { x: { beginAtZero: true }, y: { reverse: true } },
    plugins: { legend: { display: false } }
  }
});
</script>
<?php page_footer(); ?>
