-- ============================================
-- اسکریپت مهاجرت (Migration) برای نسخه ۲
-- فقط در صورتی اجرا کن که دیتابیس را قبلاً ساخته‌ای
-- و نمی‌خواهی از اول با database.sql بسازی.
--
-- این اسکریپت دو ستون جدید به جدول messages اضافه می‌کند:
--   image_path  -> مسیر عکس ضمیمه پیام
--   image_width -> درصد عرض نمایش عکس (25 / 50 / 75 / 100)
--
-- نحوه اجرا: محتوای این فایل را در phpMyAdmin
-- روی دیتابیس messages_db اجرا (Run/Go) کن.
-- ============================================

USE messages_db;

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER text,
    ADD COLUMN IF NOT EXISTS image_width TINYINT UNSIGNED NULL DEFAULT 100 AFTER image_path;
