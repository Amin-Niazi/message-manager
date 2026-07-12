<?php
// ============================================
// API ورود / خروج و بررسی وضعیت ادمین
// فایل: api/auth.php
// آدرس: /api/auth.php
// ============================================

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';

function respond(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = getDB(); // 🔌 اتصال به دیتابیس از همین‌جا گرفته می‌شود (تنظیمات آن در config/db.php است)

    // ─────────────────────────────────────
    // بررسی وضعیت ورود (GET ?action=status)
    // ─────────────────────────────────────
    if ($method === 'GET' && $action === 'status') {
        respond(200, [
            'success'       => true,
            'authenticated' => isAdminLoggedIn(),
            'username'      => $_SESSION['admin_username'] ?? null,
        ]);
    }

    // ─────────────────────────────────────
    // ورود (POST ?action=login)
    // ─────────────────────────────────────
    if ($method === 'POST' && $action === 'login') {
        $body = json_decode(file_get_contents('php://input'), true);

        $username = trim($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            respond(400, [
                'success' => false,
                'error'   => 'نام کاربری و رمز عبور الزامی است.',
            ]);
        }

        // 📌 نام کاربری و رمز عبور پنل ادمین از جدول "admins" در دیتابیس خوانده می‌شود
        // (نه از داخل این فایل PHP). برای تغییرشان باید رکورد جدول admins را عوض کنید
        // — توضیح کامل در فایل «راهنما_فارسی.txt» آمده است.
        $stmt = $db->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // password_verify رمز واردشده را با هش ذخیره‌شده در دیتابیس مقایسه می‌کند
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            respond(401, [
                'success' => false,
                'error'   => 'نام کاربری یا رمز عبور اشتباه است.',
            ]);
        }

        // ورود موفق: ساخت session
        session_regenerate_id(true);
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        respond(200, [
            'success'  => true,
            'message'  => 'ورود با موفقیت انجام شد.',
            'username' => $admin['username'],
        ]);
    }

    // ─────────────────────────────────────
    // خروج (POST ?action=logout)
    // ─────────────────────────────────────
    if ($method === 'POST' && $action === 'logout') {
        $_SESSION = [];
        session_destroy();

        respond(200, [
            'success' => true,
            'message' => 'خروج با موفقیت انجام شد.',
        ]);
    }

    respond(404, [
        'success' => false,
        'error'   => 'مسیر درخواستی یافت نشد.',
    ]);

} catch (PDOException $e) {
    respond(500, [
        'success' => false,
        'error'   => 'خطای دیتابیس: ' . $e->getMessage(),
    ]);
} catch (Exception $e) {
    respond(500, [
        'success' => false,
        'error'   => 'خطای سرور: ' . $e->getMessage(),
    ]);
}
