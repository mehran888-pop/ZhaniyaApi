-- Zhaniya Cloud — اسکیمای MySQL (تولید)
-- اجرا: mysql -u root -p zhaniya_cloud < schema.mysql.sql

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

-- کاربر مدیر اولیه را با seed_demo.php بسازید یا دستی:
-- INSERT INTO users (username, password_hash, full_name, role)
-- VALUES ('admin', '$2y$10$...', 'مدیر سیستم', 'admin');
