<?php
/** لایه‌ی دسترسی به دیتابیس (SQLite برای دمو / MySQL برای تولید) */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $driver = config('db_driver', 'sqlite');
    if ($driver === 'mysql') {
        $c = config('mysql');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['dbname'], $c['charset']);
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $path = config('db_path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        sqlite_init_schema($pdo);
    }
    return $pdo;
}

function sqlite_init_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
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
SQL);
}

/** درج یا به‌روزرسانی قابل حمل (SQLite / MySQL) */
function upsert(PDO $pdo, string $table, array $data, array $conflictCols, array $updateCols): void
{
    $cols = array_keys($data);
    $sqlCols = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $sqlPlace = implode(', ', array_fill(0, count($cols), '?'));
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sqlUpdate = implode(', ', array_map(
        fn($c) => $driver === 'sqlite' ? "`$c` = excluded.`$c`" : "`$c` = VALUES(`$c`)",
        $updateCols
    ));
    $conflict = implode(', ', array_map(fn($c) => "`$c`", $conflictCols));
    if ($driver === 'sqlite') {
        $sql = "INSERT INTO `$table` ($sqlCols) VALUES ($sqlPlace) ON CONFLICT($conflict) DO UPDATE SET $sqlUpdate";
    } else {
        $sql = "INSERT INTO `$table` ($sqlCols) VALUES ($sqlPlace) ON DUPLICATE KEY UPDATE $sqlUpdate";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
}
