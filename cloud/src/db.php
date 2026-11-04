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
    require_once dirname(__DIR__) . '/src/schema.php';
    zh_apply_schema($pdo, 'sqlite');
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
