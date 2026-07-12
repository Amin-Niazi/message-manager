<?php
// ============================================
// API مدیریت دسته‌بندی‌ها
// فایل: api/categories.php
// آدرس: /api/categories.php
// ============================================

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

try {
    $db = getDB(); // 🔌 اتصال به دیتابیس از همین‌جا گرفته می‌شود (تنظیمات آن در config/db.php است)

    if ($method === 'GET') {
        // ─────────────────────────────────────
        // GET /api/categories.php
        // گرفتن تمام دسته‌ها به همراه تعداد پیام هر دسته
        // (این بخش برای کاربر عادی هم آزاد است تا همه دسته‌ها را ببیند)
        // ─────────────────────────────────────
        $stmt = $db->query(
            'SELECT c.id, c.name, c.created_at,
                    COUNT(m.id) AS message_count
             FROM categories c
             LEFT JOIN messages m ON m.category_id = c.id
             GROUP BY c.id, c.name, c.created_at
             ORDER BY c.name ASC'
        );
        $categories = $stmt->fetchAll();

        respond(200, [
            'success'    => true,
            'count'      => count($categories),
            'categories' => $categories,
        ]);

    } elseif ($method === 'POST') {
        // ─────────────────────────────────────
        // POST /api/categories.php
        // ساخت دسته‌بندی جدید (فقط ادمین)
        // ─────────────────────────────────────
        requireAdmin();

        $body = json_decode(file_get_contents('php://input'), true);
        $name = trim($body['name'] ?? '');

        if ($name === '') {
            respond(400, [
                'success' => false,
                'error'   => 'نام دسته‌بندی نمی‌تواند خالی باشد.',
            ]);
        }

        // بررسی تکراری نبودن نام دسته
        $check = $db->prepare('SELECT id FROM categories WHERE name = ?');
        $check->execute([$name]);
        if ($check->fetch()) {
            respond(409, [
                'success' => false,
                'error'   => 'این دسته‌بندی از قبل وجود دارد.',
            ]);
        }

        $stmt = $db->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$name]);
        $newId = (int) $db->lastInsertId();

        $stmt = $db->prepare('SELECT id, name, created_at FROM categories WHERE id = ?');
        $stmt->execute([$newId]);
        $newCategory = $stmt->fetch();

        respond(201, [
            'success' => true,
            'message' => 'دسته‌بندی با موفقیت ایجاد شد.',
            'data'    => $newCategory,
        ]);

    } elseif ($method === 'DELETE') {
        // ─────────────────────────────────────
        // DELETE /api/categories.php?id=X
        // حذف دسته‌بندی (فقط ادمین)
        // پیام‌های مرتبط، دسته‌بندی‌شان NULL می‌شود (نه حذف)
        // ─────────────────────────────────────
        requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            respond(400, [
                'success' => false,
                'error'   => 'شناسه دسته‌بندی نامعتبر است.',
            ]);
        }

        $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        respond(200, [
            'success' => true,
            'message' => 'دسته‌بندی حذف شد.',
        ]);

    } else {
        respond(405, [
            'success' => false,
            'error'   => 'متد درخواست پشتیبانی نمی‌شود.',
        ]);
    }

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
