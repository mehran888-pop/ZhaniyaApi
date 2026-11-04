<?php
/**
 * اسکیمای پایگاه‌داده — منبع واحد ساخت جداول (SQLite و MySQL)
 *
 * دلیل جاسازی در PHP: برخی ابزارهای آپلود/هاست‌ها فایل‌های .sql را رد یا
 * مسدود می‌کنند و نصب با خطای «no such table» شکست می‌خورد. این فایل
 * همیشه همراه برنامه است و نصب را تضمین می‌کند.
 *
 * فایل‌های schema.sqlite.sql و schema.mysql.sql نسخه‌های کمکی برای نصب
 * دستی‌اند و باید با همین محتوا همگام بمانند.
 */

function zh_schema_sql(string $driver): string
{
    if ($driver === 'mysql') {
        return <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(128) NOT NULL DEFAULT '',
    role VARCHAR(32) NOT NULL DEFAULT 'manager',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_key VARCHAR(64) NOT NULL UNIQUE,
    secret VARCHAR(128) NOT NULL,
    name VARCHAR(128) NOT NULL,
    store_name VARCHAR(128) NOT NULL DEFAULT '',
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    last_seen_at TIMESTAMP NULL,
    last_batch_id VARCHAR(64) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    code VARCHAR(64) NOT NULL DEFAULT '',
    name VARCHAR(255) NOT NULL,
    group_name VARCHAR(128) NOT NULL DEFAULT '',
    brand VARCHAR(128) NOT NULL DEFAULT '',
    price DOUBLE NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product (device_id, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS persons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(32) NOT NULL DEFAULT '',
    credit_limit DOUBLE NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_person (device_id, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    invoice_number VARCHAR(64) NOT NULL DEFAULT '',
    invoice_date DATE NOT NULL,
    invoice_time TIME NOT NULL DEFAULT '00:00:00',
    person_source_id VARCHAR(64) NOT NULL DEFAULT '',
    person_name VARCHAR(255) NOT NULL DEFAULT '',
    desk VARCHAR(64) NOT NULL DEFAULT '',
    user_name VARCHAR(64) NOT NULL DEFAULT '',
    pay_type VARCHAR(16) NOT NULL DEFAULT 'cash',
    total DOUBLE NOT NULL DEFAULT 0,
    discount DOUBLE NOT NULL DEFAULT 0,
    payable DOUBLE NOT NULL DEFAULT 0,
    paid DOUBLE NOT NULL DEFAULT 0,
    status VARCHAR(16) NOT NULL DEFAULT 'done',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice (device_id, source_id),
    KEY idx_inv_date (invoice_date, invoice_time),
    KEY idx_inv_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    invoice_id INT NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    product_source_id VARCHAR(64) NOT NULL DEFAULT '',
    product_name VARCHAR(255) NOT NULL,
    quantity DOUBLE NOT NULL DEFAULT 1,
    unit_price DOUBLE NOT NULL DEFAULT 0,
    discount DOUBLE NOT NULL DEFAULT 0,
    total DOUBLE NOT NULL DEFAULT 0,
    UNIQUE KEY uq_item (device_id, source_id),
    KEY idx_item_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    person_source_id VARCHAR(64) NOT NULL DEFAULT '',
    person_name VARCHAR(255) NOT NULL DEFAULT '',
    invoice_source_id VARCHAR(64) NOT NULL DEFAULT '',
    type VARCHAR(16) NOT NULL DEFAULT 'sale',
    amount DOUBLE NOT NULL DEFAULT 0,
    txn_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_credit (device_id, source_id),
    KEY idx_credit_date (txn_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    batch_id VARCHAR(64) NOT NULL DEFAULT '',
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    invoice_count INT NOT NULL DEFAULT 0,
    item_count INT NOT NULL DEFAULT 0,
    note VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(64) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    }

    return <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT 'manager',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_key TEXT NOT NULL UNIQUE,
    secret TEXT NOT NULL,
    name TEXT NOT NULL,
    store_name TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'active',
    last_seen_at TEXT,
    last_batch_id TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id INTEGER NOT NULL,
    source_id TEXT NOT NULL,
    code TEXT NOT NULL DEFAULT '',
    name TEXT NOT NULL,
    group_name TEXT NOT NULL DEFAULT '',
    brand TEXT NOT NULL DEFAULT '',
    price REAL NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (device_id, source_id)
);
CREATE TABLE IF NOT EXISTS persons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id INTEGER NOT NULL,
    source_id TEXT NOT NULL,
    name TEXT NOT NULL,
    phone TEXT NOT NULL DEFAULT '',
    credit_limit REAL NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (device_id, source_id)
);
CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id INTEGER NOT NULL,
    source_id TEXT NOT NULL,
    invoice_number TEXT NOT NULL DEFAULT '',
    invoice_date TEXT NOT NULL,
    invoice_time TEXT NOT NULL DEFAULT '',
    person_source_id TEXT NOT NULL DEFAULT '',
    person_name TEXT NOT NULL DEFAULT '',
    desk TEXT NOT NULL DEFAULT '',
    user_name TEXT NOT NULL DEFAULT '',
    pay_type TEXT NOT NULL DEFAULT 'cash',
    total REAL NOT NULL DEFAULT 0,
    discount REAL NOT NULL DEFAULT 0,
    payable REAL NOT NULL DEFAULT 0,
    paid REAL NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'done',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (device_id, source_id)
);
CREATE TABLE IF NOT EXISTS invoice_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id INTEGER NOT NULL,
    invoice_id INTEGER NOT NULL,
    source_id TEXT NOT NULL,
    product_source_id TEXT NOT NULL DEFAULT '',
    product_name TEXT NOT NULL,
    quantity REAL NOT NULL DEFAULT 1,
    unit_price REAL NOT NULL DEFAULT 0,
    discount REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL DEFAULT 0,
    UNIQUE (device_id, source_id)
);
CREATE INDEX IF NOT EXISTS idx_items_invoice ON invoice_items (invoice_id);
CREATE INDEX IF NOT EXISTS idx_invoices_date ON invoices (invoice_date, invoice_time);
CREATE INDEX IF NOT EXISTS idx_invoices_device ON invoices (device_id);
CREATE TABLE IF NOT EXISTS credit_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id INTEGER NOT NULL,
    source_id TEXT NOT NULL,
    person_source_id TEXT NOT NULL DEFAULT '',
    person_name TEXT NOT NULL DEFAULT '',
    invoice_source_id TEXT NOT NULL DEFAULT '',
    type TEXT NOT NULL DEFAULT 'sale',
    amount REAL NOT NULL DEFAULT 0,
    txn_date TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (device_id, source_id)
);
CREATE INDEX IF NOT EXISTS idx_credit_date ON credit_transactions (txn_date);
CREATE TABLE IF NOT EXISTS sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id INTEGER NOT NULL,
    batch_id TEXT NOT NULL DEFAULT '',
    received_at TEXT NOT NULL DEFAULT (datetime('now')),
    invoice_count INTEGER NOT NULL DEFAULT 0,
    item_count INTEGER NOT NULL DEFAULT 0,
    note TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
SQL;
}

/**
 * ساخت/به‌روزرسانی جداول و راستی‌آزمایی نهایی.
 * در صورت شکست، استثنا با پیام فارسی روشن پرتاب می‌شود.
 */
function zh_apply_schema(PDO $pdo, string $driver): void
{
    $sql = zh_schema_sql($driver === 'mysql' ? 'mysql' : 'sqlite');
    $stmts = [];
    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $stmts[] = $stmt;
        }
    }
    $done = 0;
    foreach ($stmts as $stmt) {
        try {
            $pdo->exec($stmt);
            $done++;
        } catch (Throwable $ex) {
            $head = mb_substr(preg_replace('/\s+/', ' ', $stmt), 0, 70);
            throw new RuntimeException(
                "ساخت جداول ناموفق بود (دستور {$done} از " . count($stmts) . "): «{$head}...» — " . $ex->getMessage()
            );
        }
    }

    // راستی‌آزمایی: جدول users حتماً باید وجود داشته باشد
    try {
        $pdo->query('SELECT COUNT(*) FROM users');
    } catch (Throwable $ex) {
        throw new RuntimeException(
            'جدول‌ها ساخته نشدند (' . $ex->getMessage() . '). لطفاً مجوز نوشتن پایگاه‌داده را بررسی کنید.'
        );
    }
}
