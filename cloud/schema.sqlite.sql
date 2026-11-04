-- Zhaniya Cloud — اسکیمای SQLite (دمو/تک‌کاربره)
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
