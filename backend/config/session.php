<?php
// ============================================
// مدیریت Session و بررسی احراز هویت ادمین
// فایل: config/session.php
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    // تنظیمات کوکی session قبل از شروع آن
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * آیا کاربر فعلی به عنوان ادمین وارد شده است؟
 */
function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

/**
 * اگر ادمین وارد نشده باشد، پاسخ 401 برمی‌گرداند و اجرای اسکریپت را متوقف می‌کند
 */
function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'برای انجام این عملیات باید وارد پنل ادمین شوید.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
