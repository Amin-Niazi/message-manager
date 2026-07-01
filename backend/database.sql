-- ============================================
-- ساخت دیتابیس و جداول سیستم پیام‌رسانی
-- ============================================

-- ساخت دیتابیس
CREATE DATABASE IF NOT EXISTS messages_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_persian_ci;

USE messages_db;

-- ساخت جدول پیام‌ها
CREATE TABLE IF NOT EXISTS messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text        TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_persian_ci;

-- داده‌های نمونه برای تست
INSERT INTO messages (text) VALUES
    ('سلام! این اولین پیام سیستم است.'),
    ('این سیستم به شما امکان می‌دهد پیام‌ها را از پنل ادمین مدیریت کنید.'),
    ('پیام‌ها به ترتیب جدیدترین نمایش داده می‌شوند.');
