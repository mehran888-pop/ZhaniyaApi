<?php
/**
 * دریافت داده از کانکتور فروشگاه (outbound push)
 *
 * هدرها:
 *   X-Device-Key : کلید عمومی دستگاه
 *   X-Timestamp  : UNIX time (UTC)
 *   X-Signature  : HMAC-SHA256(secret, timestamp + '.' + raw_body) به صورت hex
 *
 * بدنه (JSON): products[], persons[], invoices[] (هر فاکتور با items[]), credit_transactions[]
 */
require_once dirname(__DIR__, 2) . '/src/helpers.php';
require_once dirname(__DIR__, 2) . '/src/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('فقط POST پذیرفته می‌شود', 405);
}

$raw = file_get_contents('php://input');
$deviceKey = (string)($_SERVER['HTTP_X_DEVICE_KEY'] ?? '');
$ts = (int)($_SERVER['HTTP_X_TIMESTAMP'] ?? 0);
$sig = strtolower((string)($_SERVER['HTTP_X_SIGNATURE'] ?? ''));

if ($deviceKey === '' || $ts <= 0 || $sig === '') {
    json_fail('هدرهای احراز هویت ناقص است', 401);
}

$st = db()->prepare('SELECT * FROM devices WHERE device_key = ?');
$st->execute([$deviceKey]);
$device = $st->fetch();
if (!$device || $device['status'] !== 'active') {
    json_fail('دستگاه ناشناخته یا غیرفعال', 403);
}

// جلوگیری از replay
$skew = abs(time() - $ts);
if ($skew > (int)config('ingest.max_clock_skew', 300)) {
    json_fail('اختلاف ساعت بیش از حد مجاز است', 408);
}

$expected = hash_hmac('sha256', $ts . '.' . $raw, $device['secret']);
if (!hash_equals($expected, $sig)) {
    json_fail('امضای نامعتبر', 401);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_fail('JSON نامعتبر', 400);
}

$pdo = db();
$pdo->beginTransaction();
try {
    // محصولات
    $nProducts = 0;
    foreach (($payload['products'] ?? []) as $p) {
        $sid = (string)($p['source_id'] ?? '');
        if ($sid === '') {
            continue;
        }
        upsert($pdo, 'products', [
            'device_id' => (int)$device['id'],
            'source_id' => $sid,
            'code' => (string)($p['code'] ?? ''),
            'name' => (string)($p['name'] ?? '—'),
            'group_name' => (string)($p['group_name'] ?? ''),
            'brand' => (string)($p['brand'] ?? ''),
            'price' => num($p['price'] ?? 0),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['device_id', 'source_id'], ['code', 'name', 'group_name', 'brand', 'price', 'updated_at']);
        $nProducts++;
    }

    // اشخاص
    $nPersons = 0;
    foreach (($payload['persons'] ?? []) as $p) {
        $sid = (string)($p['source_id'] ?? '');
        if ($sid === '') {
            continue;
        }
        upsert($pdo, 'persons', [
            'device_id' => (int)$device['id'],
            'source_id' => $sid,
            'name' => (string)($p['name'] ?? '—'),
            'phone' => (string)($p['phone'] ?? ''),
            'credit_limit' => num($p['credit_limit'] ?? 0),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['device_id', 'source_id'], ['name', 'phone', 'credit_limit', 'updated_at']);
        $nPersons++;
    }

    // فاکتورها + اقلام
    $nInvoices = 0;
    $nItems = 0;
    $invStmt = $pdo->prepare('SELECT id FROM invoices WHERE device_id = ? AND source_id = ?');
    foreach (($payload['invoices'] ?? []) as $inv) {
        $sid = (string)($inv['source_id'] ?? '');
        if ($sid === '') {
            continue;
        }
        $idate = (string)($inv['invoice_date'] ?? '');
        if (!preg_match('#^\d{4}-\d{2}-\d{2}$#', $idate)) {
            $idate = parse_date($idate, gmdate('Y-m-d'));
        }
        upsert($pdo, 'invoices', [
            'device_id' => (int)$device['id'],
            'source_id' => $sid,
            'invoice_number' => (string)($inv['invoice_number'] ?? ''),
            'invoice_date' => $idate,
            'invoice_time' => (string)($inv['invoice_time'] ?? '00:00:00'),
            'person_source_id' => (string)($inv['person_source_id'] ?? ''),
            'person_name' => (string)($inv['person_name'] ?? ''),
            'desk' => (string)($inv['desk'] ?? ''),
            'user_name' => (string)($inv['user_name'] ?? ''),
            'pay_type' => in_array($inv['pay_type'] ?? '', ['cash', 'credit', 'card', 'mixed'], true) ? $inv['pay_type'] : 'cash',
            'total' => num($inv['total'] ?? 0),
            'discount' => num($inv['discount'] ?? 0),
            'payable' => num($inv['payable'] ?? 0),
            'paid' => num($inv['paid'] ?? 0),
            'status' => (string)($inv['status'] ?? 'done'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['device_id', 'source_id'], [
            'invoice_number', 'invoice_date', 'invoice_time', 'person_source_id', 'person_name',
            'desk', 'user_name', 'pay_type', 'total', 'discount', 'payable', 'paid', 'status', 'updated_at',
        ]);
        $nInvoices++;

        $invStmt->execute([(int)$device['id'], $sid]);
        $invoiceId = (int)$invStmt->fetchColumn();

        foreach (($inv['items'] ?? []) as $it) {
            $isid = (string)($it['source_id'] ?? ($sid . '-' . $nItems));
            upsert($pdo, 'invoice_items', [
                'device_id' => (int)$device['id'],
                'invoice_id' => $invoiceId,
                'source_id' => $isid,
                'product_source_id' => (string)($it['product_source_id'] ?? ''),
                'product_name' => (string)($it['product_name'] ?? '—'),
                'quantity' => num($it['quantity'] ?? 1),
                'unit_price' => num($it['unit_price'] ?? 0),
                'discount' => num($it['discount'] ?? 0),
                'total' => num($it['total'] ?? 0),
            ], ['device_id', 'source_id'], [
                'invoice_id', 'product_source_id', 'product_name', 'quantity', 'unit_price', 'discount', 'total',
            ]);
            $nItems++;
        }
    }

    // تراکنش‌های اعتباری
    $nCredits = 0;
    foreach (($payload['credit_transactions'] ?? []) as $c) {
        $sid = (string)($c['source_id'] ?? '');
        if ($sid === '') {
            continue;
        }
        $tdate = (string)($c['txn_date'] ?? '');
        if (!preg_match('#^\d{4}-\d{2}-\d{2}$#', $tdate)) {
            $tdate = parse_date($tdate, gmdate('Y-m-d'));
        }
        $type = in_array($c['type'] ?? '', ['sale', 'payment', 'adjustment'], true) ? $c['type'] : 'sale';
        upsert($pdo, 'credit_transactions', [
            'device_id' => (int)$device['id'],
            'source_id' => $sid,
            'person_source_id' => (string)($c['person_source_id'] ?? ''),
            'person_name' => (string)($c['person_name'] ?? ''),
            'invoice_source_id' => (string)($c['invoice_source_id'] ?? ''),
            'type' => $type,
            'amount' => num($c['amount'] ?? 0),
            'txn_date' => $tdate,
            'description' => (string)($c['description'] ?? ''),
        ], ['device_id', 'source_id'], [
            'person_source_id', 'person_name', 'invoice_source_id', 'type', 'amount', 'txn_date', 'description',
        ]);
        $nCredits++;
    }

    // ثبت sync و به‌روزرسانی وضعیت دستگاه
    $batchId = (string)($payload['batch_id'] ?? '');
    $pdo->prepare('INSERT INTO sync_log (device_id, batch_id, invoice_count, item_count, note) VALUES (?,?,?,?,?)')
        ->execute([(int)$device['id'], $batchId, $nInvoices, $nItems, (string)($payload['note'] ?? '')]);
    $pdo->prepare('UPDATE devices SET last_seen_at = ?, last_batch_id = ? WHERE id = ?')
        ->execute([gmdate('Y-m-d H:i:s'), $batchId, (int)$device['id']]);

    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    json_fail('خطای ذخیره‌سازی: ' . $ex->getMessage(), 500);
}

json_out([
    'ok' => true,
    'received' => [
        'products' => $nProducts,
        'persons' => $nPersons,
        'invoices' => $nInvoices,
        'items' => $nItems,
        'credit_transactions' => $nCredits,
    ],
    'server_time' => gmdate('c'),
]);
