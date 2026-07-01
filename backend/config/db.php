<?php
// ============================================
// تنظیمات اتصال به دیتابیس
// فایل: config/db.php
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'messages_db');
define('DB_USER', 'root');       // نام کاربری MySQL خود را اینجا وارد کنید
define('DB_PASS', '');           // رمز MySQL خود را اینجا وارد کنید
define('DB_CHARSET', 'utf8mb4');

/**
 * ساخت اتصال PDO به دیتابیس
 * در صورت خطا، Exception پرتاب می‌کند
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
