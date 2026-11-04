<?php
require_once __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/src/repo.php';

auth_require();

$from = parse_date($_GET['from'] ?? '', date('Y-m-d', strtotime('-6 days')));
$to   = parse_date($_GET['to'] ?? '', today_g());

$summary = rep_summary($from, $to);
$today   = rep_today();
$range   = rep_sales_range($from, $to);
$mix     = rep_payment_mix($from, $to);
$top     = rep_top_items($from, $to, 8);
$live    = rep_realtime(0, 8);

page_header('داشبورد مدیریت', 'dashboard');
?>
<form class="filters" method="get" action="dashboard.php">
  <div class="field">
    <label>از تاریخ (جلالی یا میلادی)</label>
    <input name="from" value="<?= e(jalali_date($from)) ?>" placeholder="1405/08/01">
  </div>
  <div class="field">
    <label>تا تاریخ</label>
    <input name="to" value="<?= e(jalali_date($to)) ?>" placeholder="1405/08/13">
  </div>
  <button class="btn" type="submit">اعمال بازه</button>
  <a class="btn secondary" href="dashboard.php">۷ روز اخیر</a>
</form>

<div class="kpis">
  <div class="kpi">
    <div class="k-label">فروش امروز</div>
    <div class="k-value"><?= money($today['payable']) ?> <span class="small muted"><?= e(config('currency')) ?></span></div>
    <div class="k-sub"><?= e(jalali_date(today_g())) ?> — <?= (int)$today['invoice_count'] ?> فاکتور</div>
  </div>
  <div class="kpi indigo">
    <div class="k-label">فروش بازه‌ی انتخابی</div>
    <div class="k-value"><?= money($summary['payable']) ?> <span class="small muted"><?= e(config('currency')) ?></span></div>
    <div class="k-sub"><?= e(jalali_date($from)) ?> تا <?= e(jalali_date($to)) ?></div>
  </div>
  <div class="kpi green">
    <div class="k-label">تعداد فاکتور (بازه)</div>
    <div class="k-value"><?= money($summary['invoice_count']) ?></div>
    <div class="k-sub">میانگین سبد خرید: <?= money($summary['avg_basket']) ?></div>
  </div>
  <div class="kpi amber">
    <div class="k-label">فروش اعتباری (بازه)</div>
    <div class="k-value"><?= money($summary['credit_sale']) ?></div>
    <div class="k-sub">نقدی: <?= money($summary['cash_sale']) ?> — کارت: <?= money($summary['card_sale']) ?></div>
  </div>
  <div class="kpi red">
    <div class="k-label">تخفیف‌های اعطاشده</div>
    <div class="k-value"><?= money($summary['discount']) ?></div>
    <div class="k-sub">در بازه‌ی انتخابی</div>
  </div>
</div>

<div class="grid grid-2">
  <div class="panel">
    <h2>روند فروش روزانه <span class="hint">از تاریخ تا تاریخ</span></h2>
    <div class="chart-box"><canvas id="salesChart"></canvas></div>
  </div>
  <div class="panel">
    <h2>ترکیب پرداخت</h2>
    <div class="chart-box"><canvas id="mixChart"></canvas></div>
  </div>
</div>

<div class="grid grid-2 mt">
  <div class="panel">
    <h2>پرفروش‌ترین اقلام فروش رفته</h2>
    <div class="chart-box sm"><canvas id="itemsChart"></canvas></div>
  </div>
  <div class="panel">
    <h2><span class="pulse"></span>آخرین فاکتورهای لحظه‌ای <span class="hint">به‌روزرسانی خودکار</span></h2>
    <div class="feed" id="liveFeed">
      <?php foreach ($live['invoices'] as $inv): ?>
        <div class="feed-item">
          <div>
            <div class="f-title">فاکتور <?= e($inv['invoice_number'] ?: '#' . $inv['id']) ?> — <?= e($inv['person_name'] ?: 'مشتری نقدی') ?></div>
            <div class="f-meta"><?= e(jalali_date($inv['invoice_date'])) ?> ساعت <?= e(substr($inv['invoice_time'], 0, 5)) ?> — میز <?= e($inv['desk'] ?: '—') ?></div>
          </div>
          <div style="text-align:left">
            <div class="f-title"><?= money($inv['payable']) ?></div>
            <span class="badge <?= e($inv['pay_type']) ?>"><?= e($inv['pay_type'] === 'cash' ? 'نقدی' : ($inv['pay_type'] === 'credit' ? 'اعتباری' : ($inv['pay_type'] === 'card' ? 'کارت' : 'ترکیبی'))) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<footer class="app-foot">Zhaniya Cloud — داشبورد مدیریت فروش | داده‌ها از کانکتور فروشگاه به‌روز می‌شوند</footer>

<script>
const salesLabels = <?= json_encode(array_map(fn($r) => jalali_date($r['d']), $range), JSON_UNESCAPED_UNICODE) ?>;
const salesData = <?= json_encode(array_map(fn($r) => (float)$r['payable'], $range)) ?>;
const salesCount = <?= json_encode(array_map(fn($r) => (int)$r['invoice_count'], $range)) ?>;

new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: {
    labels: salesLabels,
    datasets: [
      {
        label: 'فروش (<?= e(config('currency')) ?>)',
        data: salesData,
        borderColor: '#0ea5a4',
        backgroundColor: 'rgba(14,165,164,.15)',
        fill: true,
        tension: .35,
        yAxisID: 'y'
      },
      {
        label: 'تعداد فاکتور',
        data: salesCount,
        borderColor: '#6366f1',
        backgroundColor: '#6366f1',
        type: 'bar',
        yAxisID: 'y1'
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    scales: {
      y: { position: 'right', beginAtZero: true },
      y1: { position: 'left', beginAtZero: true, grid: { drawOnChartArea: false } },
      x: { reverse: true }
    },
    plugins: { legend: { position: 'bottom' } }
  }
});

const mixLabels = <?= json_encode(array_map(function ($r) {
    $map = ['cash' => 'نقدی', 'credit' => 'اعتباری', 'card' => 'کارت', 'mixed' => 'ترکیبی'];
    return $map[$r['pay_type']] ?? $r['pay_type'];
}, $mix), JSON_UNESCAPED_UNICODE) ?>;
const mixData = <?= json_encode(array_map(fn($r) => (float)$r['amount'], $mix)) ?>;

new Chart(document.getElementById('mixChart'), {
  type: 'doughnut',
  data: {
    labels: mixLabels,
    datasets: [{
      data: mixData,
      backgroundColor: ['#22c55e', '#ef4444', '#6366f1', '#f59e0b'],
      borderWidth: 0
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } }
  }
});

const topLabels = <?= json_encode(array_map(fn($r) => $r['name'], $top), JSON_UNESCAPED_UNICODE) ?>;
const topData = <?= json_encode(array_map(fn($r) => (float)$r['amount'], $top)) ?>;

new Chart(document.getElementById('itemsChart'), {
  type: 'bar',
  data: {
    labels: topLabels,
    datasets: [{ label: 'مبلغ فروش', data: topData, backgroundColor: '#6366f1', borderRadius: 6 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
    scales: { x: { beginAtZero: true }, y: { reverse: true } },
    plugins: { legend: { display: false } }
  }
});

// به‌روزرسانی لحظه‌ای فید
let lastId = <?= (int)$live['max_id'] ?>;
async function refreshFeed() {
  try {
    const res = await ZH.api('api/report.php?type=realtime&since_id=' + lastId);
    if (!res.ok || !res.invoices.length) return;
    lastId = res.max_id || lastId;
    const feed = document.getElementById('liveFeed');
    const label = { cash: 'نقدی', credit: 'اعتباری', card: 'کارت', mixed: 'ترکیبی' };
    for (const inv of res.invoices.reverse()) {
      const div = document.createElement('div');
      div.className = 'feed-item';
      div.innerHTML = `
        <div>
          <div class="f-title">فاکتور ${inv.invoice_number || '#' + inv.id} — ${inv.person_name || 'مشتری نقدی'}</div>
          <div class="f-meta">${inv.invoice_date} ساعت ${(inv.invoice_time || '').slice(0, 5)} — میز ${inv.desk || '—'}</div>
        </div>
        <div style="text-align:left">
          <div class="f-title">${ZH.fmt(inv.payable)}</div>
          <span class="badge ${inv.pay_type}">${label[inv.pay_type] || inv.pay_type}</span>
        </div>`;
      feed.prepend(div);
    }
    while (feed.children.length > 15) feed.removeChild(feed.lastChild);
  } catch (e) { /* آفلاین — در نوبت بعد */ }
}
setInterval(refreshFeed, 10000);
</script>
<?php page_footer(); ?>
