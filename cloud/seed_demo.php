<?php
/**
 * ساخت کاربر مدیر + دستگاه دمو + داده‌ی نمونه برای مشاهده‌ی داشبورد
 * اجرا: php cloud/seed_demo.php
 */
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/db.php';

$pdo = db();

// کاربر مدیر
$st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$st->execute(['admin']);
if ((int)$st->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)')
        ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'مدیر سیستم', 'admin']);
    echo "user admin / admin123 ساخته شد (در تولید حتماً رمز را عوض کنید)\n";
}

// دستگاه دمو
$st = $pdo->prepare('SELECT COUNT(*) FROM devices WHERE device_key = ?');
$st->execute(['demo-device-key']);
if ((int)$st->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO devices (device_key, secret, name, store_name, status) VALUES (?,?,?,?,?)')
        ->execute(['demo-device-key', 'demo-device-secret', 'کانکتور شعبه مرکزی', 'فروشگاه مرکزی', 'active']);
    echo "device demo-device-key / demo-device-secret ساخته شد\n";
}
$deviceId = (int)$pdo->query('SELECT id FROM devices WHERE device_key = \'demo-device-key\'')->fetchColumn();

// داده‌ی نمونه (در صورت خالی‌بودن)
$cnt = (int)$pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
if ($cnt > 0) {
    echo "داده‌ی فاکتور از قبل موجود است ($cnt). رد شد.\n";
    exit;
}

mt_srand(42);
$products = [
    ['P-100', 'چلوکباب کوبیده', 'غذای اصلی', 980000],
    ['P-101', 'چلوجوجه کباب', 'غذای اصلی', 860000],
    ['P-102', 'قورمه سبزی', 'غذای اصلی', 720000],
    ['P-103', 'زرشک پلو با مرغ', 'غذای اصلی', 790000],
    ['P-104', 'پیتزا مخصوص', 'فست فود', 650000],
    ['P-105', 'ساندویچ مرغ', 'فست فود', 320000],
    ['P-106', 'سالاد فصل', 'پیش غذا', 180000],
    ['P-107', 'نوشابه خانواده', 'نوشیدنی', 90000],
    ['P-108', 'دلستر', 'نوشیدنی', 75000],
    ['P-109', 'چای و دمنوش', 'نوشیدنی', 60000],
    ['P-110', 'بستنی سنتی', 'دسر', 120000],
    ['P-111', 'کیک شکلاتی', 'دسر', 150000],
];
foreach ($products as $i => $p) {
    upsert($pdo, 'products', [
        'device_id' => $deviceId, 'source_id' => $p[0], 'code' => $p[0],
        'name' => $p[1], 'group_name' => $p[2], 'brand' => '', 'price' => $p[3],
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ], ['device_id', 'source_id'], ['name', 'price', 'updated_at']);
}

$persons = [
    ['C-1', 'علی رضایی', '09121112233', 5000000],
    ['C-2', 'مریم احمدی', '09353334455', 3000000],
    ['C-3', 'حسین محمدی', '09125556677', 8000000],
    ['C-4', 'زهرا کریمی', '09197778899', 2000000],
    ['C-5', 'رضا قاسمی', '09369990011', 4500000],
];
foreach ($persons as $p) {
    upsert($pdo, 'persons', [
        'device_id' => $deviceId, 'source_id' => $p[0], 'name' => $p[1],
        'phone' => $p[2], 'credit_limit' => $p[3], 'updated_at' => gmdate('Y-m-d H:i:s'),
    ], ['device_id', 'source_id'], ['name', 'phone', 'credit_limit', 'updated_at']);
}

$desks = ['سالن ۱', 'سالن ۲', 'تراس', 'VIP', 'بیرون‌بر'];
$users = ['کاربر صندوق ۱', 'کاربر صندوق ۲'];
$payTypes = ['cash', 'cash', 'cash', 'card', 'card', 'credit', 'mixed'];

$pdo->beginTransaction();
$invNo = 10000;
$creditSeq = 0;
// ۴۵ روز اخیر
for ($day = 44; $day >= 0; $day--) {
    $date = date('Y-m-d', strtotime("-$day days"));
    $isToday = $day === 0;
    $hourStart = $isToday ? (int)date('G') : 22;
    $nInv = $isToday ? max(3, (int)($hourStart * 1.4)) : mt_rand(14, 30);
    for ($k = 0; $k < $nInv; $k++) {
        $invNo++;
        $hour = mt_rand(10, max(11, $hourStart));
        $min = mt_rand(0, 59);
        $sec = mt_rand(0, 59);
        $time = sprintf('%02d:%02d:%02d', $hour, $min, $sec);
        $pay = $payTypes[array_rand($payTypes)];
        $nItems = mt_rand(1, 5);
        $total = 0;
        $items = [];
        for ($j = 0; $j < $nItems; $j++) {
            $pr = $products[array_rand($products)];
            $qty = mt_rand(1, 3);
            $line = $pr[3] * $qty;
            $total += $line;
            $items[] = ['pr' => $pr, 'qty' => $qty, 'line' => $line];
        }
        $discount = (mt_rand(0, 10) === 0) ? (int)($total * 0.05) : 0;
        $payable = $total - $discount;
        $paid = $pay === 'credit' ? 0 : $payable;
        $person = ($pay === 'credit' || mt_rand(0, 4) === 0) ? $persons[array_rand($persons)] : null;

        $srcId = 'INV-' . $invNo;
        $pdo->prepare(
            'INSERT INTO invoices (device_id, source_id, invoice_number, invoice_date, invoice_time,
                person_source_id, person_name, desk, user_name, pay_type,
                total, discount, payable, paid, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $deviceId, $srcId, (string)$invNo, $date, $time,
            $person ? $person[0] : '', $person ? $person[1] : '',
            $desks[array_rand($desks)], $users[array_rand($users)], $pay,
            $total, $discount, $payable, $paid, 'done', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'),
        ]);
        $invoiceId = (int)$pdo->lastInsertId();

        foreach ($items as $j => $it) {
            $pdo->prepare(
                'INSERT INTO invoice_items (device_id, invoice_id, source_id, product_source_id,
                    product_name, quantity, unit_price, discount, total)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $deviceId, $invoiceId, $srcId . '-' . $j, $it['pr'][0],
                $it['pr'][1], $it['qty'], $it['pr'][3], 0, $it['line'],
            ]);
        }

        if ($pay === 'credit' && $person) {
            $creditSeq++;
            $pdo->prepare(
                'INSERT INTO credit_transactions (device_id, source_id, person_source_id, person_name,
                    invoice_source_id, type, amount, txn_date, description)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $deviceId, 'CR-' . $creditSeq, $person[0], $person[1], $srcId,
                'sale', $payable, $date, 'فروش اعتباری فاکتور ' . $invNo,
            ]);
        }
    }
    // پرداخت‌های اعتباری
    if (mt_rand(0, 2) > 0) {
        $person = $persons[array_rand($persons)];
        $creditSeq++;
        $pdo->prepare(
            'INSERT INTO credit_transactions (device_id, source_id, person_source_id, person_name,
                invoice_source_id, type, amount, txn_date, description)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $deviceId, 'CR-' . $creditSeq, $person[0], $person[1], '',
            'payment', mt_rand(1, 20) * 100000, $date, 'دریافت وجه نقدی/کارت',
        ]);
    }
}
$pdo->commit();

$pdo->prepare('UPDATE devices SET last_seen_at = ?, last_batch_id = ? WHERE id = ?')
    ->execute([gmdate('Y-m-d H:i:s'), 'seed-demo', $deviceId]);
$pdo->prepare('INSERT INTO sync_log (device_id, batch_id, invoice_count, item_count, note) VALUES (?,?,?,?,?)')
    ->execute([$deviceId, 'seed-demo', $invNo - 10000, 0, 'داده اولیه دمو']);

echo "داده‌ی دمو ساخته شد: " . ($invNo - 10000) . " فاکتور\n";
