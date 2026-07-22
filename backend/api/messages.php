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

/**
 * پیدا کردن دسته‌بندی «عمومی»؛ اگر وجود نداشت، خودش می‌سازدش.
 * این تابع تضمین می‌کند وقتی ادمین دسته‌بندی انتخاب نکند،
 * پیام همیشه در دسته «عمومی» ذخیره شود (نه بدون دسته).
 */
function getOrCreateDefaultCategory(PDO $db): int {
    $defaultName = 'عمومی';

    $stmt = $db->prepare('SELECT id FROM categories WHERE name = ?');
    $stmt->execute([$defaultName]);
    $row = $stmt->fetch();

    if ($row) {
        return (int) $row['id'];
    }

    // دسته‌بندی «عمومی» وجود ندارد؛ می‌سازیمش
    $insert = $db->prepare('INSERT INTO categories (name) VALUES (?)');
    $insert->execute([$defaultName]);

    return (int) $db->lastInsertId();
}

/**
 * آپلود عکس ضمیمه پیام
 * - فقط jpg/jpeg/png/gif/webp قبول می‌شود
 * - حداکثر حجم: ۵ مگابایت
 * - نام فایل به‌صورت رندوم ساخته می‌شود تا تداخل/بازنویسی رخ ندهد
 *
 * خروجی: مسیر نسبی فایل (مثلاً "uploads/64f1a2b3c4d5e.jpg") برای ذخیره در دیتابیس
 */
function handleImageUpload(array $file): string {
    // بررسی خطای آپلود
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(400, [
            'success' => false,
            'error'   => 'خطا در آپلود عکس (کد خطا: ' . $file['error'] . ')',
        ]);
    }

    // بررسی حجم فایل (حداکثر ۵ مگابایت)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        respond(400, [
            'success' => false,
            'error'   => 'حجم عکس نباید بیشتر از ۵ مگابایت باشد.',
        ]);
    }

    // بررسی نوع واقعی فایل (نه فقط پسوند) با استفاده از finfo برای امنیت بیشتر
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimes[$mimeType])) {
        respond(400, [
            'success' => false,
            'error'   => 'فرمت عکس مجاز نیست. فقط JPG، PNG، GIF و WebP قابل قبول است.',
        ]);
    }

    $extension = $allowedMimes[$mimeType];
    $fileName  = bin2hex(random_bytes(16)) . '.' . $extension;
    $uploadDir = __DIR__ . '/../uploads/';
    $destPath  = $uploadDir . $fileName;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        respond(500, [
            'success' => false,
            'error'   => 'ذخیره فایل عکس روی سرور با خطا مواجه شد.',
        ]);
    }

    return 'uploads/' . $fileName;
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
        // GET /api/messages.php?category_id=X        (فیلتر بر اساس دسته‌بندی)
        // GET /api/messages.php?page=2&limit=3        (صفحه‌بندی - پیش‌فرض ۳ پیام در هر صفحه)
        // گرفتن پیام‌ها، جدیدترین اول، به همراه نام دسته‌بندی
        // ─────────────────────────────────────

        $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;

        // شماره صفحه (حداقل ۱) و تعداد آیتم در هر صفحه (پیش‌فرض ۳، حداکثر ۵۰ برای امنیت)
        $page  = isset($_GET['page'])  ? max(1, (int) $_GET['page'])  : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 3;
        if ($limit < 1)  $limit = 3;
        if ($limit > 50) $limit = 50;
        $offset = ($page - 1) * $limit;

        $whereSql = '';
        $params   = [];
        if ($categoryId) {
            $whereSql = ' WHERE m.category_id = ?';
            $params[] = $categoryId;
        }

        // ابتدا تعداد کل پیام‌ها (برای محاسبه تعداد صفحات) گرفته می‌شود
        $countSql  = 'SELECT COUNT(*) AS total FROM messages m' . $whereSql;
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];
        $totalPages = max(1, (int) ceil($total / $limit));

        // سپس خودِ پیام‌ها با LIMIT/OFFSET گرفته می‌شوند
        $sql = 'SELECT m.id, m.text, m.image_path, m.image_width, m.created_at, m.category_id,
               c.name AS category_name
        FROM messages m
        LEFT JOIN categories c ON c.id = m.category_id' . $whereSql . '
        ORDER BY m.created_at DESC
        LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();

        respond(200, [
            'success'     => true,
            'count'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $totalPages,
            'messages'    => $messages,
        ]);

    } elseif ($method === 'POST') {
        // ─────────────────────────────────────
        // POST /api/messages.php
        // ذخیره پیام جدید در دیتابیس (فقط ادمین)
        //
        // دو حالت پشتیبانی می‌شود:
        //  ۱) multipart/form-data  → وقتی عکس ضمیمه است (فیلدها: text, category_id, image_width, image)
        //  ۲) application/json     → حالت قدیمی بدون عکس (فیلدها: text, category_id)
        // ─────────────────────────────────────
        requireAdmin();

        $contentType  = $_SERVER['CONTENT_TYPE'] ?? '';
        $isMultipart  = stripos($contentType, 'multipart/form-data') !== false;

        if ($isMultipart) {
            $text        = trim($_POST['text'] ?? '');
            $categoryId  = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            $imageWidth  = isset($_POST['image_width']) ? (int) $_POST['image_width'] : 100;
        } else {
            $body        = json_decode(file_get_contents('php://input'), true) ?? [];
            $text        = trim($body['text'] ?? '');
            $categoryId  = isset($body['category_id']) ? (int) $body['category_id'] : 0;
            $imageWidth  = isset($body['image_width']) ? (int) $body['image_width'] : 100;
        }

        // اعتبارسنجی متن پیام
        if ($text === '') {
            respond(400, [
                'success' => false,
                'error'   => 'متن پیام نمی‌تواند خالی باشد.',
            ]);
        }

        // ─────────────────────────────────────
        // دسته‌بندی: اگر انتخاب نشده باشد، به‌صورت خودکار روی «عمومی» تنظیم می‌شود
        // (دیگر انتخاب دسته‌بندی الزامی نیست)
        // ─────────────────────────────────────
        if ($categoryId > 0) {
            $checkCat = $db->prepare('SELECT id FROM categories WHERE id = ?');
            $checkCat->execute([$categoryId]);
            if (!$checkCat->fetch()) {
                respond(404, [
                    'success' => false,
                    'error'   => 'دسته‌بندی انتخاب‌شده وجود ندارد.',
                ]);
            }
        } else {
            $categoryId = getOrCreateDefaultCategory($db);
        }

        // ─────────────────────────────────────
        // آپلود عکس (اختیاری)
        // ─────────────────────────────────────
        $imagePath = null;

        // عرض نمایش عکس فقط باید یکی از این مقادیر باشد (برای جلوگیری از مقادیر عجیب)
        $allowedWidths = [25, 50, 75, 100];
        if (!in_array($imageWidth, $allowedWidths, true)) {
            $imageWidth = 100;
        }

        if ($isMultipart && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = handleImageUpload($_FILES['image']);
            // handleImageUpload خودش در صورت خطا respond(400,...) را صدا می‌زند و برنامه را متوقف می‌کند
        }

        // ذخیره در دیتابیس
        $stmt = $db->prepare(
            'INSERT INTO messages (text, category_id, image_path, image_width) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$text, $categoryId, $imagePath, $imageWidth]);
        $newId = (int) $db->lastInsertId();

        // گرفتن رکورد ذخیره‌شده برای ارسال به فرانت
        $stmt = $db->prepare(
            'SELECT m.id, m.text, m.image_path, m.image_width, m.created_at, m.category_id, c.name AS category_name
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

        // اگر پیام عکس داشت، قبل از حذف رکورد، فایل عکس را هم از روی دیسک پاک می‌کنیم
        $imgStmt = $db->prepare('SELECT image_path FROM messages WHERE id = ?');
        $imgStmt->execute([$id]);
        $existing = $imgStmt->fetch();
        if ($existing && !empty($existing['image_path'])) {
            $filePath = __DIR__ . '/../' . $existing['image_path'];
            if (is_file($filePath)) {
                @unlink($filePath);
            }
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
