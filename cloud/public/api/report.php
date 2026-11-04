<?php
/**
 * API گزارش‌ها — مصرف داخلی داشبورد (نیازمند نشست کاربر)
 * GET /api/report.php?type=...&from=...&to=...
 */
require_once dirname(__DIR__, 2) . '/src/helpers.php';
require_once dirname(__DIR__, 2) . '/src/db.php';
require_once dirname(__DIR__, 2) . '/src/auth.php';
require_once dirname(__DIR__, 2) . '/src/repo.php';

auth_require();

$type = (string)($_GET['type'] ?? '');
$from = parse_date($_GET['from'] ?? '', date('Y-m-d', strtotime('-29 days')));
$to   = parse_date($_GET['to'] ?? '', today_g());

switch ($type) {
    case 'summary':
        json_out(['ok' => true] + rep_summary($from, $to));

    case 'sales_range':
        json_out(['ok' => true, 'from' => $from, 'to' => $to, 'rows' => rep_sales_range($from, $to)]);

    case 'items':
        json_out(['ok' => true, 'rows' => rep_top_items($from, $to, (int)($_GET['limit'] ?? 15))]);

    case 'payment_mix':
        json_out(['ok' => true, 'rows' => rep_payment_mix($from, $to)]);

    case 'hourly':
        json_out(['ok' => true, 'rows' => rep_hourly($from)]);

    case 'credits':
        json_out([
            'ok' => true,
            'summary' => rep_credit_summary($from, $to),
            'persons' => rep_credit_persons($from, $to, (int)($_GET['limit'] ?? 50)),
        ]);

    case 'credits_daily':
        $st = db()->prepare(
            "SELECT txn_date AS d,
                    COALESCE(SUM(CASE WHEN type = 'sale' THEN amount END),0) AS credit_sales,
                    COALESCE(SUM(CASE WHEN type = 'payment' THEN amount END),0) AS payments
             FROM credit_transactions
             WHERE txn_date BETWEEN ? AND ?
             GROUP BY txn_date
             ORDER BY txn_date"
        );
        $st->execute([$from, $to]);
        json_out(['ok' => true, 'rows' => $st->fetchAll()]);

    case 'realtime':
        $res = rep_realtime((int)($_GET['since_id'] ?? 0), (int)($_GET['limit'] ?? 20));
        json_out(['ok' => true, 'today' => rep_today()] + $res);

    case 'today':
        json_out(['ok' => true] + rep_today());

    case 'devices':
        json_out(['ok' => true, 'rows' => rep_devices()]);

    default:
        json_fail('نوع گزارش نامعتبر است. مقادیر مجاز: summary, sales_range, items, payment_mix, hourly, credits, credits_daily, realtime, today, devices', 400);
}
