-- =========================================================================
-- 1. إنشاء قاعدة البيانات الموحدة للمشروع
-- =========================================================================
CREATE DATABASE IF NOT EXISTS `internship_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `internship_db`;

-- =========================================================================
-- 2. جدول العملاء (Customers Table) - Task 1
-- يتولى تخزين البيانات الأساسية للعملاء (الاسم، الهاتف، الإيميل، الحالة)
-- =========================================================================
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'المعرف الفريد للعميل',
  `name` VARCHAR(255) NOT NULL COMMENT 'اسم العميل الكامل',
  `phone` VARCHAR(20) NOT NULL UNIQUE COMMENT 'رقم الهاتف الفريد (يمنع التكرار)',
  `email` VARCHAR(255) DEFAULT NULL COMMENT 'البريد الإلكتروني للعميل (اختياري)',
  `status` ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'حالة العميل (نشط / غير نشط)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ ووقت إنشاء السجل',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ ووقت أحدث تعديل'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- 3. جدول سجلات الـ Webhook و الـ API (Webhook Logs Table) - Task 2 & Task 3
-- ينظم السجلات الخاصة بعمليات ربط البيانات ومراقبة الاستجابات وإعادة المحاولة
-- =========================================================================
CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'المعرف الفريد لسجل العملية',
  `customer_id` INT NOT NULL COMMENT 'معرف العميل المرتبط بالسجل',
  `status` ENUM('pending', 'success', 'failed') DEFAULT 'pending' COMMENT 'حالة معالجة الـ Webhook',
  `retry_count` INT DEFAULT 0 COMMENT 'عدد محاولات إعادة الإرسال عند الفشل',
  `api_response` TEXT DEFAULT NULL COMMENT 'نص استجابة الـ API الخارجي بصيغة JSON',
  `last_attempt_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'تاريخ ووقت آخر محاولة تنفيذ',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ ووقت استلام الـ Webhook',
  
  -- ربط المفتاح الأجنبي لجدول العملاء مع خاصية الحذف التلقائي للسجلات المرتبطة عند حذف العميل
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;