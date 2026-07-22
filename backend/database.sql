-- ============================================
-- ساخت دیتابیس و جداول سیستم پیام‌رسانی
-- (نسخه با دسته‌بندی پیام‌ها و ورود ادمین)
-- ============================================

-- ساخت دیتابیس
CREATE DATABASE IF NOT EXISTS messages_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_persian_ci;

USE messages_db;

-- ============================================
-- جدول دسته‌بندی‌ها
-- ============================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_category_name (name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_persian_ci;

-- ============================================
-- جدول پیام‌ها (با ارتباط به دسته‌بندی)
-- ============================================
CREATE TABLE IF NOT EXISTS messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    text        TEXT         NOT NULL,
    image_path  VARCHAR(255) NULL,               -- مسیر عکس ضمیمه پیام (نسبت به پوشه backend) - می‌تواند خالی باشد
    image_width TINYINT UNSIGNED NULL DEFAULT 100, -- درصد عرض نمایش عکس نزد کاربر (25/50/75/100)
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at DESC),
    INDEX idx_category_id (category_id),
    CONSTRAINT fk_messages_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_persian_ci;

-- ============================================
-- جدول ادمین‌ها (برای صفحه ورود پنل ادمین)
-- ============================================
CREATE TABLE IF NOT EXISTS admins (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_admin_username (username)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_persian_ci;

-- ============================================
-- داده‌های نمونه برای تست
-- ============================================

-- دسته‌بندی‌های نمونه
INSERT INTO categories (name) VALUES
    ('عمومی'),
    ('خبری'),
    ('مهم / فوری'),
    ('اطلاعیه');

-- پیام‌های نمونه (متصل به دسته‌بندی)
INSERT INTO messages (category_id, text) VALUES
    (1, 'سلام! این اولین پیام سیستم است.'),
    (2, 'این سیستم به شما امکان می‌دهد پیام‌ها را از پنل ادمین مدیریت کنید.'),
    (3, 'پیام‌های مهم و فوری در این دسته نمایش داده می‌شوند.');

-- ============================================
-- 👤 کاربر ادمین پیش‌فرض پنل مدیریت (نام کاربری/رمز ورود به سایت)
-- نام کاربری: admin
-- رمز عبور:   admin123
--
-- ⚠️ این رمز، همان رمزی است که در صفحه login.html وارد می‌کنید؛
--    با «رمز دیتابیس MySQL» در فایل backend/config/db.php فرق دارد.
--
-- برای تغییر نام کاربری/رمز عبور پنل ادمین، دو راه دارید:
--   1) ساده‌ترین راه: یک رکورد جدید با رمز دلخواه در جدول admins بسازید
--      (رمز را از قبل با تابع password_hash فرم گرفته باشید، نه متن ساده)
--   2) یا مستقیم در phpMyAdmin مقدار ستون username و password_hash
--      این ردیف را ویرایش کنید. password_hash باید خروجی
--      تابع PHP: password_hash('رمز-جدید-شما', PASSWORD_BCRYPT) باشد،
--      هرگز رمز را به صورت متن ساده (plain text) در این ستون ننویسید.
-- توضیح کامل با مثال در فایل «راهنما_فارسی.txt» آمده است.
-- ============================================
INSERT INTO admins (username, password_hash) VALUES
    ('admin', '$2b$10$d42UmcQXvV04Q7BgXgcCwec08TkPZu8g10L53QHEv8Wc0/T5nvdUy');
