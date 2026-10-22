-- ==============================================================================
-- 1. إنشاء وتهيئة قاعدة البيانات (Database Initialization)
-- ==============================================================================

-- إنشاء قاعدة البيانات إذا لم تكن موجودة مسبقاً مع دعم الترميز الموحد utf8mb4 لدعم اللغة العربية والرموز التعبيرية
CREATE DATABASE IF NOT EXISTS `webhook_db` 
  DEFAULT CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

-- تعيين قاعدة البيانات كقاعدة بيانات افتراضية لتنفيذ الأوامر التالية عليها
USE `webhook_db`;

-- ==============================================================================
-- 2. جدول العملاء (Customers Table)
-- ==============================================================================

-- جدول مخصص لتخزين بيانات العملاء الأساسية مع منع تكرار أرقام الهواتف
CREATE TABLE IF NOT EXISTS `customers` (
  `id`         INT(11)                               NOT NULL AUTO_INCREMENT,          -- المعرف الفريد للعميل (مفتاح أساسي)
  `name`       VARCHAR(100)                          NOT NULL,                         -- اسم العميل الكامل
  `phone`      VARCHAR(20)                           NOT NULL,                         -- رقم الجوال الخاص بالعميل
  `email`      VARCHAR(100)                          DEFAULT NULL,                     -- البريد الإلكتروني (حقل اختياري)
  `status`     ENUM('active', 'inactive')            NOT NULL DEFAULT 'active',        -- حالة الحساب (نشط أو غير نشط)
  `created_at` TIMESTAMP                             NOT NULL DEFAULT CURRENT_TIMESTAMP, -- تاريخ ووقت تسجيل العميل تلقائياً

  PRIMARY KEY (`id`),                                                                  -- تعيين المعرف كمفتاح أساسي
  UNIQUE KEY `unique_phone` (`phone`)                                                  -- منع تكرار رقم الجوال لضمان فرادته
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================================================================
-- 3. جدول سجلات التكامل والربط الخارجي (Integration Logs Table)
-- ==============================================================================

-- جدول تتبع ومراقبة عمليات المزامنة مع الـ External API لكل عميل
CREATE TABLE IF NOT EXISTS `integration_logs` (
  `id`            INT(11)                            NOT NULL AUTO_INCREMENT,          -- المعرف الفريد لسجل المزامنة
  `customer_id`   INT(11)                            NOT NULL,                         -- معرف العميل المرتبط بهذه العملية
  `status`        ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',     -- حالة المزامنة الحالية
  `attempts`      INT(11)                            NOT NULL DEFAULT 1,               -- عدد محاولات الإرسال المنفذة
  `response_code` INT(11)                            DEFAULT NULL,                     -- رمز استجابة HTTP الوارد من السيرفر الخارجي
  `response_body` TEXT                               DEFAULT NULL,                     -- نص الاستجابة أو تفاصيل الخطأ المسجل
  `created_at`    TIMESTAMP                          NOT NULL DEFAULT CURRENT_TIMESTAMP, -- تاريخ إنشاء السجل وبدء العملية
  `updated_at`    TIMESTAMP                          NOT NULL DEFAULT CURRENT_TIMESTAMP 
                                                     ON UPDATE CURRENT_TIMESTAMP,      -- تحديث التوقيت تلقائياً عند أي تعديل

  PRIMARY KEY (`id`),                                                                  -- تعيين معرف السجل كمفتاح أساسي
  KEY `customer_id` (`customer_id`)                                                    -- إنشاء فهرس لتسريع الاستعلام والربط بالعميل
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;