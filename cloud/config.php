<?php
/**
 * Zhaniya Cloud — مدیریت و گزارش‌گیری متمرکز فروش
 * تنظیمات برنامه. برای استقرار واقعی مقادیر را تغییر دهید.
 */
return [
    // دیتابیس: 'sqlite' (پیش‌فرض، مناسب دمو) یا 'mysql' (تولید)
    'db_driver' => getenv('ZH_DB_DRIVER') ?: 'sqlite',
    'db_path'   => getenv('ZH_DB_PATH') ?: __DIR__ . '/data/zhaniya_cloud.sqlite',
    'mysql'     => [
        'host'     => getenv('ZH_DB_HOST') ?: '127.0.0.1',
        'port'     => (int)(getenv('ZH_DB_PORT') ?: 3306),
        'dbname'   => getenv('ZH_DB_NAME') ?: 'zhaniya_cloud',
        'user'     => getenv('ZH_DB_USER') ?: 'zhaniya',
        'password' => getenv('ZH_DB_PASS') ?: 'change-me',
        'charset'  => 'utf8mb4',
    ],

    // نشست و امنیت وب
    'app_name'     => 'داشبورد مدیریت ژانیا',
    'session_name' => 'ZHANIYA_SESS',
    // کلید امضای CSRF و توکن‌ها — در تولید حتماً تغییر کنید
    'app_secret'   => getenv('ZH_APP_SECRET') ?: 'zh-demo-secret-change-in-production',

    // اتصال دستگاه‌ها (کانکتور فروشگاه)
    'ingest' => [
        // اختلاف مجاز ساعت برای جلوگیری از replay (ثانیه)
        'max_clock_skew' => 300,
    ],

    // واحد پول نمایش داده‌شده
    'currency' => 'تومان',

    // منطقه زمانی
    'timezone' => 'Asia/Tehran',
];
