<?php
require_once __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/src/repo.php';

auth_require();

$from = parse_date($_GET['from'] ?? '', date('Y-m-d', strtotime('-29 days')));
$to   = parse_date($_GET['to'] ?? '', today_g());

$sum   = rep_credit_summary($from, $to);
$persons = rep_credit_persons($from, $to, 50);
$txns  = rep_credit_transactions($from, $to, 150);

$typeLabel = ['sale' => 'فروش اعتباری', 'payment' => 'دریافت وجه', 'adjustment' => 'اصلاحیه'];

page_header('گزارش اعتباری‌ها', 'credits');
?>
<form class="filters" method="get" action="credits.php">
  <div class="field">
    <label>از تاریخ</label>
    <input name="from" value="<?= e(jalali_date($from)) ?>" placeholder="1405/08/01">
  </div>
  <div class="field">
    <label>تا تاریخ</label>
    <input name="to" value="<?= e(jalali_date($to)) ?>" placeholder="1405/08/13">
  </div>
  <button class="btn" type="submit">نمایش گزارش</button>
</form>

<div class="kpis">
  <div class="kpi amber">
    <div class="k-label">فروش اعتباری (بازه)</div>
    <div class="k-value"><?= money($sum['credit_sales']) ?> <span class="small muted"><?= e(config('currency')) ?></span></div>
    <div class="k-sub">تعداد تراکنش: <?= money($sum['txn_count']) ?></div>
  </div>
  <div class="kpi green">
    <div class="k-label">وجوه دریافتی (بازه)</div>
    <div class="k-value"><?= money($sum['payments']) ?> <span class="small muted"><?= e(config('currency')) ?></span></div>
    <div class="k-sub">تسویه بدهی مشتریان</div>
  </div>
  <div class="kpi red">
    <div class="k-label">مانده بدهی ایجادشده در بازه</div>
    <div class="k-value"><?= money((float)$sum['credit_sales'] - (float)$sum['payments'] + (float)$sum['adjustments']) ?></div>
    <div class="k-sub">فروش اعتباری منهای دریافتی‌ها</div>
  </div>
</div>

<div class="grid grid-2eq">
  <div class="panel">
    <h2>اعتباری در مقابل دریافتی — روند روزانه</h2>
    <div class="chart-box"><canvas id="creditChart"></canvas></div>
  </div>
  <div class="panel">
    <h2>بیشترین مانده بدهی مشتریان</h2>
    <div class="chart-box"><canvas id="balanceChart"></canvas></div>
  </div>
</div>

<div class="panel mt">
  <h2>کارتکس اعتباری مشتریان <span class="hint">در بازه‌ی انتخابی</span></h2>
  <table class="tbl">
    <thead>
      <tr>
        <th>مشتری</th><th>فروش اعتباری</th><th>دریافتی</th><th>اصلاحیه</th>
        <th>مانده بدهی</th><th>تعداد تراکنش</th><th>آخرین تراکنش</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($persons as $p): ?>
      <tr>
        <td><b><?= e($p['person_name']) ?></b></td>
        <td class="num"><?= money($p['credit_sales']) ?></td>
        <td class="num"><?= money($p['payments']) ?></td>
        <td class="num"><?= money($p['adjustments']) ?></td>
        <td class="num"><b><?= money($p['balance']) ?></b></td>
        <td class="num"><?= money($p['txn_count']) ?></td>
        <td><?= e(jalali_date($p['last_txn'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$persons): ?>
      <tr><td colspan="7" class="muted">تراکنش اعتباری در این بازه ثبت نشده است.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="panel mt">
  <h2>جزئیات تراکنش‌های اعتباری</h2>
  <table class="tbl">
    <thead>
      <tr><th>تاریخ</th><th>مشتری</th><th>نوع</th><th>مبلغ</th><th>شرح</th></tr>
    </thead>
    <tbody>
      <?php foreach ($txns as $t): ?>
      <tr>
        <td><?= e(jalali_date($t['txn_date'])) ?></td>
        <td><?= e($t['person_name'] ?: '—') ?></td>
        <td>
          <span class="badge <?= $t['type'] === 'sale' ? 'credit' : ($t['type'] === 'payment' ? 'cash' : 'mixed') ?>">
            <?= e($typeLabel[$t['type']] ?? $t['type']) ?>
          </span>
        </td>
        <td class="num"><?= money($t['amount']) ?></td>
        <td class="muted"><?= e($t['description']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$txns): ?>
      <tr><td colspan="5" class="muted">موردی یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
// داده‌های روزانه‌ی اعتباری از API
ZH.api('api/report.php?type=credits_daily&from=<?= e($from) ?>&to=<?= e($to) ?>').then(res => {
  const rows = res.ok ? res.rows : [];
  new Chart(document.getElementById('creditChart'), {
    type: 'bar',
    data: {
      labels: rows.map(r => r.d),
      datasets: [
        { label: 'فروش اعتباری', data: rows.map(r => Number(r.credit_sales)), backgroundColor: '#ef4444', borderRadius: 5 },
        { label: 'دریافتی', data: rows.map(r => Number(r.payments)), backgroundColor: '#22c55e', borderRadius: 5 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: { y: { beginAtZero: true }, x: { reverse: true } },
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: c => c.dataset.label + ': ' + ZH.fmt(c.parsed.y) } }
      }
    }
  });
});

const persons = <?= json_encode(array_map(fn($p) => ['name' => $p['person_name'], 'balance' => (float)$p['balance']], array_slice($persons, 0, 10)), JSON_UNESCAPED_UNICODE) ?>;
new Chart(document.getElementById('balanceChart'), {
  type: 'bar',
  data: {
    labels: persons.map(p => p.name),
    datasets: [{ label: 'مانده بدهی', data: persons.map(p => p.balance), backgroundColor: '#f59e0b', borderRadius: 5 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
    scales: { x: { beginAtZero: true }, y: { reverse: true } },
    plugins: { legend: { display: false } }
  }
});
</script>
<?php page_footer(); ?>
