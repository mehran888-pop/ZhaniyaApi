<?php
require_once __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/src/repo.php';

auth_require();

$date = parse_date($_GET['date'] ?? '', today_g());
$today = rep_today();
$hourly = rep_hourly($date);
$live = rep_realtime(0, 25);
$devices = rep_devices();

$payLabel = ['cash' => 'نقدی', 'credit' => 'اعتباری', 'card' => 'کارت', 'mixed' => 'ترکیبی'];

page_header('گزارش لحظه‌ای فروش', 'realtime');
?>
<form class="filters" method="get" action="realtime.php">
  <div class="field">
    <label>تاریخ (برای نمودار ساعتی)</label>
    <input name="date" value="<?= e(jalali_date($date)) ?>" placeholder="1405/08/13">
  </div>
  <button class="btn" type="submit">نمایش</button>
  <span class="badge ok" style="align-self:center"><span class="pulse"></span>به‌روزرسانی خودکار هر ۱۰ ثانیه</span>
</form>

<div class="kpis">
  <div class="kpi">
    <div class="k-label">فروش امروز</div>
    <div class="k-value" id="kTodaySales"><?= money($today['payable']) ?></div>
    <div class="k-sub"><?= e(jalali_date(today_g())) ?></div>
  </div>
  <div class="kpi indigo">
    <div class="k-label">فاکتورهای امروز</div>
    <div class="k-value" id="kTodayCount"><?= money($today['invoice_count']) ?></div>
    <div class="k-sub">میانگین سبد: <span id="kTodayAvg"><?= money($today['avg_basket']) ?></span></div>
  </div>
  <div class="kpi green">
    <div class="k-label">نقدی امروز</div>
    <div class="k-value" id="kTodayCash"><?= money($today['cash_sale']) ?></div>
    <div class="k-sub">کارت: <span id="kTodayCard"><?= money($today['card_sale']) ?></span></div>
  </div>
  <div class="kpi amber">
    <div class="k-label">اعتباری امروز</div>
    <div class="k-value" id="kTodayCredit"><?= money($today['credit_sale']) ?></div>
    <div class="k-sub">فروش قسطی/نسیه‌ی امروز</div>
  </div>
</div>

<div class="grid grid-2">
  <div class="panel">
    <h2>فروش ساعتی — <?= e(jalali_date($date)) ?></h2>
    <div class="chart-box"><canvas id="hourlyChart"></canvas></div>
  </div>
  <div class="panel">
    <h2><span class="pulse"></span>جریان زنده‌ی فاکتورها</h2>
    <div class="feed" id="liveFeed">
      <?php foreach ($live['invoices'] as $inv): ?>
        <div class="feed-item">
          <div>
            <div class="f-title">فاکتور <?= e($inv['invoice_number'] ?: '#' . $inv['id']) ?> — <?= e($inv['person_name'] ?: 'مشتری نقدی') ?></div>
            <div class="f-meta"><?= e(jalali_date($inv['invoice_date'])) ?> ساعت <?= e(substr($inv['invoice_time'], 0, 5)) ?> — میز <?= e($inv['desk'] ?: '—') ?></div>
          </div>
          <div style="text-align:left">
            <div class="f-title"><?= money($inv['payable']) ?></div>
            <span class="badge <?= e($inv['pay_type']) ?>"><?= e($payLabel[$inv['pay_type']] ?? $inv['pay_type']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="panel mt">
  <h2>وضعیت اتصال دستگاه‌ها (کانکتور فروشگاه)</h2>
  <table class="tbl">
    <thead><tr><th>دستگاه</th><th>فروشگاه</th><th>وضعیت</th><th>آخرین ارتباط</th><th>آخرین فاکتور</th></tr></thead>
    <tbody>
      <?php foreach ($devices as $d): ?>
      <?php
        $fresh = $d['last_seen_at'] && (time() - strtotime($d['last_seen_at'] . ' UTC') < 300);
      ?>
      <tr>
        <td><b><?= e($d['name']) ?></b></td>
        <td><?= e($d['store_name']) ?></td>
        <td><span class="badge <?= $fresh ? 'ok' : 'off' ?>"><?= $fresh ? 'متصل' : 'آخرین ارتباط قدیمی' ?></span></td>
        <td><?= e($d['last_seen_at'] ?: '—') ?></td>
        <td><?= e($d['last_invoice_date'] ? jalali_date($d['last_invoice_date']) : '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
const hourlyLabels = <?= json_encode(array_map(fn($r) => $r['h'] . ':00', $hourly), JSON_UNESCAPED_UNICODE) ?>;
const hourlySales = <?= json_encode(array_map(fn($r) => (float)$r['payable'], $hourly)) ?>;
const hourlyCount = <?= json_encode(array_map(fn($r) => (int)$r['invoice_count'], $hourly)) ?>;

const hourlyChart = new Chart(document.getElementById('hourlyChart'), {
  type: 'line',
  data: {
    labels: hourlyLabels,
    datasets: [
      {
        label: 'فروش',
        data: hourlySales,
        borderColor: '#0ea5a4',
        backgroundColor: 'rgba(14,165,164,.18)',
        fill: true, tension: .35, yAxisID: 'y'
      },
      {
        label: 'تعداد فاکتور',
        data: hourlyCount,
        type: 'bar',
        backgroundColor: '#6366f1',
        borderRadius: 5, yAxisID: 'y1'
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

let lastId = <?= (int)$live['max_id'] ?>;
const label = { cash: 'نقدی', credit: 'اعتباری', card: 'کارت', mixed: 'ترکیبی' };

async function tick() {
  try {
    // به‌روزرسانی KPIهای امروز
    const sum = await ZH.api('api/report.php?type=summary&from=<?= e(today_g()) ?>&to=<?= e(today_g()) ?>');
    if (sum.ok) {
      document.getElementById('kTodaySales').textContent = ZH.fmt(sum.payable);
      document.getElementById('kTodayCount').textContent = ZH.fmt(sum.invoice_count);
      document.getElementById('kTodayAvg').textContent = ZH.fmt(sum.avg_basket);
      document.getElementById('kTodayCash').textContent = ZH.fmt(sum.cash_sale);
      document.getElementById('kTodayCard').textContent = ZH.fmt(sum.card_sale);
      document.getElementById('kTodayCredit').textContent = ZH.fmt(sum.credit_sale);
    }
    // فاکتورهای جدید
    const res = await ZH.api('api/report.php?type=realtime&since_id=' + lastId);
    if (res.ok && res.invoices.length) {
      lastId = res.max_id || lastId;
      const feed = document.getElementById('liveFeed');
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
        // به‌روزرسانی نمودار ساعتی
        const h = (inv.invoice_time || '').slice(0, 2);
        const idx = hourlyChart.data.labels.indexOf(h + ':00');
        if (idx >= 0) {
          hourlyChart.data.datasets[0].data[idx] += Number(inv.payable);
          hourlyChart.data.datasets[1].data[idx] += 1;
        } else {
          hourlyChart.data.labels.push(h + ':00');
          hourlyChart.data.datasets[0].data.push(Number(inv.payable));
          hourlyChart.data.datasets[1].data.push(1);
        }
        hourlyChart.update('none');
      }
      while (feed.children.length > 30) feed.removeChild(feed.lastChild);
    }
  } catch (e) { /* تلاش بعدی */ }
}
setInterval(tick, 10000);
</script>
<?php page_footer(); ?>
