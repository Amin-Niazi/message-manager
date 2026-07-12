<?php
// ============================================
// API اصلی برای مدیریت پیام‌ها
// فایل: api/messages.php
// آدرس: /api/messages.php
// ============================================

require_once __DIR__ . '/../config/session.php';

// تنظیم هدرها: JSON و CORS برای فرانت
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
    $db = getDB(); // 🔌 اتصال به دیتابیس از همین‌جا گرفته می‌شود (تنظیمات آن در config/db.php است)

    if ($method === 'GET') {
        // ─────────────────────────────────────
        // GET /api/messages.php
        // GET /api/messages.php?category_id=X   (فیلتر بر اساس دسته‌بندی)
        // گرفتن پیام‌ها، جدیدترین اول، به همراه نام دسته‌بندی
        // ─────────────────────────────────────

        $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;

        $sql = 'SELECT m.id, m.text, m.created_at, m.category_id,
                       c.name AS category_name
                FROM messages m
                LEFT JOIN categories c ON c.id = m.category_id';

        $params = [];
        if ($categoryId) {
            $sql .= ' WHERE m.category_id = ?';
            $params[] = $categoryId;
        }
        $sql .= ' ORDER BY m.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();

        respond(200, [
            'success'  => true,
            'count'    => count($messages),
            'messages' => $messages,
        ]);

    } elseif ($method === 'POST') {
        // ─────────────────────────────────────
        // POST /api/messages.php
        // ذخیره پیام جدید در دیتابیس (فقط ادمین)
        // بدنه: { text, category_id }
        // ─────────────────────────────────────
        requireAdmin();

        // خواندن body درخواست (JSON)
        $body = json_decode(file_get_contents('php://input'), true);

        // اعتبارسنجی متن پیام
        if (empty($body['text']) || trim($body['text']) === '') {
            respond(400, [
                'success' => false,
                'error'   => 'متن پیام نمی‌تواند خالی باشد.',
            ]);
        }

        $text = trim($body['text']);

        // اعتبارسنجی دسته‌بندی (الزامی است)
        $categoryId = isset($body['category_id']) ? (int) $body['category_id'] : 0;
        if ($categoryId <= 0) {
            respond(400, [
                'success' => false,
                'error'   => 'انتخاب دسته‌بندی برای پیام الزامی است.',
            ]);
        }

        $checkCat = $db->prepare('SELECT id FROM categories WHERE id = ?');
        $checkCat->execute([$categoryId]);
        if (!$checkCat->fetch()) {
            respond(404, [
                'success' => false,
                'error'   => 'دسته‌بندی انتخاب‌شده وجود ندارد.',
            ]);
        }

        // ذخیره در دیتابیس
        $stmt = $db->prepare(
            'INSERT INTO messages (text, category_id) VALUES (?, ?)'
        );
        $stmt->execute([$text, $categoryId]);
        $newId = (int) $db->lastInsertId();

        // گرفتن رکورد ذخیره‌شده برای ارسال به فرانت
        $stmt = $db->prepare(
            'SELECT m.id, m.text, m.created_at, m.category_id, c.name AS category_name
             FROM messages m
             LEFT JOIN categories c ON c.id = m.category_id
             WHERE m.id = ?'
        );
        $stmt->execute([$newId]);
        $newMessage = $stmt->fetch();

        respond(201, [
            'success' => true,
            'message' => 'پیام با موفقیت ذخیره شد.',
            'data'    => $newMessage,
        ]);

    } elseif ($method === 'DELETE') {
        // ─────────────────────────────────────
        // DELETE /api/messages.php?id=X
        // حذف پیام (فقط ادمین)
        // ─────────────────────────────────────
        requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            respond(400, [
                'success' => false,
                'error'   => 'شناسه پیام نامعتبر است.',
            ]);
        }

        $stmt = $db->prepare('DELETE FROM messages WHERE id = ?');
        $stmt->execute([$id]);

        respond(200, [
            'success' => true,
            'message' => 'پیام حذف شد.',
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
