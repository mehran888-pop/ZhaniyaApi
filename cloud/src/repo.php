<?php
/** کوئری‌های گزارش‌گیری — قابل اجرا روی SQLite و MySQL */

function rep_sales_range(string $from, string $to): array
{
    $st = db()->prepare(
        'SELECT invoice_date AS d,
                COUNT(*) AS invoice_count,
                SUM(payable) AS payable,
                SUM(total) AS total,
                SUM(discount) AS discount,
                SUM(CASE WHEN pay_type = "credit" THEN payable ELSE 0 END) AS credit_sale
         FROM invoices
         WHERE invoice_date BETWEEN ? AND ?
         GROUP BY invoice_date
         ORDER BY invoice_date'
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function rep_summary(string $from, string $to): array
{
    $st = db()->prepare(
        'SELECT COUNT(*) AS invoice_count,
                COALESCE(SUM(payable),0) AS payable,
                COALESCE(SUM(total),0) AS total,
                COALESCE(SUM(discount),0) AS discount,
                COALESCE(SUM(paid),0) AS paid,
                COALESCE(SUM(CASE WHEN pay_type = "credit" THEN payable ELSE 0 END),0) AS credit_sale,
                COALESCE(SUM(CASE WHEN pay_type = "cash"  THEN payable ELSE 0 END),0) AS cash_sale,
                COALESCE(SUM(CASE WHEN pay_type = "card"  THEN payable ELSE 0 END),0) AS card_sale,
                COALESCE(SUM(CASE WHEN pay_type = "mixed" THEN payable ELSE 0 END),0) AS mixed_sale
         FROM invoices
         WHERE invoice_date BETWEEN ? AND ?'
    );
    $st->execute([$from, $to]);
    $row = $st->fetch() ?: [];
    $row['avg_basket'] = ((int)($row['invoice_count'] ?? 0) > 0)
        ? (float)($row['payable'] ?? 0) / (int)$row['invoice_count']
        : 0.0;
    return $row;
}

function rep_top_items(string $from, string $to, int $limit = 15): array
{
    $limit = max(1, min(100, $limit));
    $st = db()->prepare(
        "SELECT ii.product_name AS name,
                SUM(ii.quantity) AS qty,
                SUM(ii.total) AS amount,
                COUNT(DISTINCT ii.invoice_id) AS invoice_count
         FROM invoice_items ii
         JOIN invoices i ON i.id = ii.invoice_id
         WHERE i.invoice_date BETWEEN ? AND ?
         GROUP BY ii.product_name
         ORDER BY amount DESC
         LIMIT $limit"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function rep_items_detail(string $from, string $to, int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));
    $st = db()->prepare(
        "SELECT i.invoice_date AS d, i.invoice_time AS t, i.invoice_number AS num,
                ii.product_name AS name, ii.quantity AS qty, ii.unit_price AS price,
                ii.discount AS discount, ii.total AS amount
         FROM invoice_items ii
         JOIN invoices i ON i.id = ii.invoice_id
         WHERE i.invoice_date BETWEEN ? AND ?
         ORDER BY i.invoice_date DESC, i.invoice_time DESC
         LIMIT $limit"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function rep_payment_mix(string $from, string $to): array
{
    $st = db()->prepare(
        'SELECT pay_type, COALESCE(SUM(payable),0) AS amount, COUNT(*) AS cnt
         FROM invoices
         WHERE invoice_date BETWEEN ? AND ?
         GROUP BY pay_type'
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function rep_hourly(string $date): array
{
    $st = db()->prepare(
        "SELECT substr(invoice_time, 1, 2) AS h,
                COUNT(*) AS invoice_count,
                COALESCE(SUM(payable),0) AS payable
         FROM invoices
         WHERE invoice_date = ? AND invoice_time <> ''
         GROUP BY substr(invoice_time, 1, 2)
         ORDER BY h"
    );
    $st->execute([$date]);
    return $st->fetchAll();
}

function rep_credit_summary(string $from, string $to): array
{
    $st = db()->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'sale' THEN amount END),0) AS credit_sales,
            COALESCE(SUM(CASE WHEN type = 'payment' THEN amount END),0) AS payments,
            COALESCE(SUM(CASE WHEN type = 'adjustment' THEN amount END),0) AS adjustments,
            COUNT(*) AS txn_count
         FROM credit_transactions
         WHERE txn_date BETWEEN ? AND ?"
    );
    $st->execute([$from, $to]);
    return $st->fetch() ?: [];
}

function rep_credit_persons(string $from, string $to, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $st = db()->prepare(
        "SELECT person_name,
                COALESCE(SUM(CASE WHEN type = 'sale' THEN amount END),0) AS credit_sales,
                COALESCE(SUM(CASE WHEN type = 'payment' THEN amount END),0) AS payments,
                COALESCE(SUM(CASE WHEN type = 'adjustment' THEN amount END),0) AS adjustments,
                COALESCE(SUM(CASE WHEN type = 'sale' THEN amount ELSE -amount END),0) AS balance,
                COUNT(*) AS txn_count,
                MAX(txn_date) AS last_txn
         FROM credit_transactions
         WHERE txn_date BETWEEN ? AND ? AND person_name <> ''
         GROUP BY person_name
         ORDER BY balance DESC
         LIMIT $limit"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function rep_credit_transactions(string $from, string $to, int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));
    $st = db()->prepare(
        "SELECT txn_date, person_name, type, amount, description
         FROM credit_transactions
         WHERE txn_date BETWEEN ? AND ?
         ORDER BY txn_date DESC
         LIMIT $limit"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function rep_realtime(int $sinceId = 0, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    if ($sinceId > 0) {
        $st = db()->prepare(
            "SELECT id, invoice_number, invoice_date, invoice_time, person_name, desk,
                    pay_type, payable, status
             FROM invoices WHERE id > ? ORDER BY id ASC LIMIT $limit"
        );
        $st->execute([$sinceId]);
    } else {
        $st = db()->query(
            "SELECT id, invoice_number, invoice_date, invoice_time, person_name, desk,
                    pay_type, payable, status
             FROM invoices ORDER BY id DESC LIMIT $limit"
        );
    }
    $rows = $st->fetchAll();
    $maxId = 0;
    foreach ($rows as $r) {
        $maxId = max($maxId, (int)$r['id']);
    }
    return ['invoices' => $rows, 'max_id' => $maxId];
}

function rep_today(): array
{
    return rep_summary(today_g(), today_g());
}

function rep_devices(): array
{
    return db()->query(
        'SELECT d.id, d.name, d.store_name, d.status, d.last_seen_at, d.last_batch_id,
                (SELECT COUNT(*) FROM invoices i WHERE i.device_id = d.id) AS invoice_count,
                (SELECT MAX(invoice_date) FROM invoices i WHERE i.device_id = d.id) AS last_invoice_date
         FROM devices d
         ORDER BY d.id'
    )->fetchAll();
}

function rep_sales_invoices(string $from, string $to, int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));
    $st = db()->prepare(
        "SELECT invoice_number, invoice_date, invoice_time, person_name, desk, user_name,
                pay_type, total, discount, payable, paid, status
         FROM invoices
         WHERE invoice_date BETWEEN ? AND ?
         ORDER BY invoice_date DESC, invoice_time DESC
         LIMIT $limit"
    );
    $st->execute([$from, $to]);
    return $st->fetchAll();
}
