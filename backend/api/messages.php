<?php
// ============================================
// API اصلی برای مدیریت پیام‌ها
// فایل: api/messages.php
// آدرس: /api/messages.php
// ============================================

// تنظیم هدرها: JSON و CORS برای فرانت
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// پاسخ به درخواست‌های OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';

/**
 * ارسال پاسخ JSON به فرانت
 */
function respond(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// مسیریابی براساس متد HTTP
// ============================================

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    if ($method === 'GET') {
        // ─────────────────────────────────────
        // GET /api/messages.php
        // گرفتن تمام پیام‌ها، جدیدترین اول
        // ─────────────────────────────────────

        $stmt = $db->query(
            'SELECT id, text, created_at FROM messages ORDER BY created_at DESC'
        );
        $messages = $stmt->fetchAll();

        respond(200, [
            'success'  => true,
            'count'    => count($messages),
            'messages' => $messages,
        ]);

    } elseif ($method === 'POST') {
        // ─────────────────────────────────────
        // POST /api/messages.php
        // ذخیره پیام جدید در دیتابیس
        // ─────────────────────────────────────

        // خواندن body درخواست (JSON)
        $body = json_decode(file_get_contents('php://input'), true);

        // اعتبارسنجی ورودی
        if (empty($body['text']) || trim($body['text']) === '') {
            respond(400, [
                'success' => false,
                'error'   => 'متن پیام نمی‌تواند خالی باشد.',
            ]);
        }

        $text = trim($body['text']);

        // ذخیره در دیتابیس
        $stmt = $db->prepare(
            'INSERT INTO messages (text) VALUES (?)'
        );
        $stmt->execute([$text]);
        $newId = (int) $db->lastInsertId();

        // گرفتن رکورد ذخیره‌شده برای ارسال به فرانت
        $stmt = $db->prepare(
            'SELECT id, text, created_at FROM messages WHERE id = ?'
        );
        $stmt->execute([$newId]);
        $newMessage = $stmt->fetch();

        respond(201, [
            'success' => true,
            'message' => 'پیام با موفقیت ذخیره شد.',
            'data'    => $newMessage,
        ]);

    } else {
        // متد نامعتبر
        respond(405, [
            'success' => false,
            'error'   => 'متد درخواست پشتیبانی نمی‌شود.',
        ]);
    }

} catch (PDOException $e) {
    // خطای دیتابیس
    respond(500, [
        'success' => false,
        'error'   => 'خطای دیتابیس: ' . $e->getMessage(),
    ]);
} catch (Exception $e) {
    // سایر خطاها
    respond(500, [
        'success' => false,
        'error'   => 'خطای سرور: ' . $e->getMessage(),
    ]);
}
