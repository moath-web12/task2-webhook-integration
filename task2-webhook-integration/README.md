# مشروع خدمة الويب هوك والتكامل مع الأنظمة الخارجية

خدمة ويب هوك (Webhook) مبنية بلغة PHP وقاعدة بيانات MySQL عبر مكتبة PDO، تستقبل بيانات العملاء عبر طلبات HTTP POST، وتتحقق من صحتها وعدم تكرارها، ثم تحفظها محلياً وتزامنها تلقائياً مع واجهة برمجية خارجية (External API) عبر مكتبة cURL مع توثيق كامل لحالات المزامنة في قاعدة البيانات وملف السجلات النصي.

## بنية المشروع وآلية العمل

يتكون المشروع من الملفات والمجلدات التالية:

```text
task2-webhook-integration/
├── config/
│   ├── cacert.pem
│   └── database.php
├── logs/
│   └── app.log
├── webhook/
│   └── customer.php
├── database.sql
└── README.md
```

- **`webhook/customer.php`:** نقطة النهاية الخاصة باستقبال طلبات الـ Webhook والتحقق من البيانات وحفظها ومزامنتها مع النظام الخارجي.
- **`config/database.php`:** فئة الاتصال بقاعدة البيانات باستخدام PDO مع دعم الترميز العربي `utf8mb4`.
- **`config/cacert.pem`:** ملف شهادات الجذر الموثوقة (CA Certificates) لتفعيل التحقق الآمن من شهادة SSL عند الاتصال بالـ API الخارجي في بيئة الخادم المحلي.
- **`logs/app.log`:** ملف نصي لتدوين العمليات وحالات المزامنة والأخطاء مع الطابع الزمني.
- **`database.sql`:** ملف SQL لإنشاء جدولي العملاء `customers` وسجلات التكامل `integration_logs`.
- **`README.md`:** دليل التوثيق والتشغيل الشامل للمشروع.

### مسار وآلية عمل النظام

1. **التحقق من طريقة الطلب:** يقبل الملف `webhook/customer.php` طلبات `POST` فقط، ويرفض أي طريقة HTTP أخرى بكود `405 Method Not Allowed`.
2. **فحص صيغة JSON والمدخلات:** يتحقق النظام من عدم فراغ جسم الطلب، وسلامة هيكل JSON، ووجود الحقول الإلزامية (`name` و `phone`)، وصحة صيغة البريد الإلكتروني (`email`) في حال إرساله.
3. **منع تكرار رقم الهاتف:** يستعلم النظام في جدول `customers` للتأكد من عدم تسجيل رقم الهاتف مسبقاً، وفي حال وجوده يُرجع كود `409 Conflict`.
4. **الحفظ المحلي وتهيئة سجل التكامل:** يُدرج العميل في جدول `customers` بحالة `active`، وينشئ مباشرة سجلاً مرتبطاً به في جدول `integration_logs` بحالة ابتدائية `pending` وعدد محاولات `1` قبل بدء الاتصال الخارجي، مع تدوين العملية في `logs/app.log`.
5. **الاتصال بالواجهة الخارجية عبر cURL:** يرسل النظام بيانات العميل إلى الواجهة الخارجية `https://jsonplaceholder.typicode.com/users` مع ضبط مهلة الاتصال (`5` ثوانٍ للاتصال و `10` ثوانٍ للتنفيذ) وتفعيل فحص شهادة SSL عبر ملف `config/cacert.pem`.
6. **تحديث حالة التكامل وإرجاع النتيجة:**
   - في حال نجاح المزامنة الخارجية (كود HTTP بين `200` و `299`): تُحدّث حالة السجل في `integration_logs` إلى `success` مع حفظ كود وجسم الاستجابة، وتُرجع الواجهة كود `201 Created`.
   - في حال فشل الاتصال الخارجي أو انتهاء المهلة: تُحدّث حالة السجل في `integration_logs` إلى `failed` مع حفظ سبب الخطأ، وتُرجع الواجهة كود `207 Multi-Status` لتأكيد حفظ العميل محلياً وجدولة المزامنة لإعادة المحاولة.

## متطلبات التشغيل

- خادم محلي يدعم PHP (الإصدار 7.4 أو أحدث) وقاعدة بيانات MySQL أو MariaDB (مثل XAMPP أو WampServer أو Laragon).
- تفعيل إضافتي `pdo_mysql` و `curl` و `openssl` في ملف إعدادات `php.ini`.
- اتصال بالإنترنت على الجهاز المحلي لإتمام عملية المزامنة مع الـ API الخارجي.
- أداة لاختبار الطلبات مثل Postman أو Insomnia أو `cURL`.

## خطوات التثبيت والتشغيل

1. **نقل المشروع إلى مجلد الخادم المحلي:**
   انسخ مجلد `task2-webhook-integration` وضعه في مسار الخادم المحلي لديك:
   - مسار XAMPP:
     `C:\xampp\htdocs\task2-webhook-integration`
   - مسار WampServer:
     `C:\wamp64\www\task2-webhook-integration`
   - مسار Laragon:
     `C:\laragon\www\task2-webhook-integration`

2. **تشغيل خدمات الخادم:**
   افتح لوحة تحكم الخادم المحلي وشغّل خدمتي Apache و MySQL.

3. **إعداد قاعدة البيانات والجداول:**
   افتح `http://localhost/phpmyadmin` واستورد ملف `database.sql`، أو نفّذ استعلامات SQL الموضحة في القسم التالي.

4. **مطابقة اسم قاعدة البيانات في إعدادات الاتصال:**
   افتح الملف `config/database.php` وتأكد من مطابقة قيمة المتغير `$db_name` لاسم قاعدة البيانات التي استوردت الجداول بداخلها (`internship_db` أو `webhook_db`).

5. **إرسال طلبات الاختبار:**
   وجّه طلبات `POST` عبر Postman أو `cURL` إلى الرابط:
   `http://localhost/task2-webhook-integration/webhook/customer.php`

## إعدادات قاعدة البيانات وملف SQL

تُضبط بيانات الاتصال داخل الملف `config/database.php` وفق الجدول التالي:

| المعامل | القيمة الافتراضية في الكود | الوصف |
| :--- | :--- | :--- |
| `$host` | `localhost` | عنوان خادم قاعدة البيانات |
| `$db_name` | `internship_db` | اسم قاعدة البيانات (يمكن تغييره إلى `webhook_db` حسب اسم قاعدتك) |
| `$username` | `root` | اسم مستخدم قاعدة البيانات |
| `$password` | `""` | كلمة مرور قاعدة البيانات (فارغة افتراضياً) |
| `charset` | `utf8mb4` | ترميز الاتصال لدعم النصوص العربية |

### كود إنشاء قاعدة البيانات والجداول (`database.sql`)

ينشئ الكود التالي جدول العملاء `customers` وجدول سجلات التكامل `integration_logs`:

```sql
CREATE DATABASE IF NOT EXISTS `internship_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `internship_db`;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `integration_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
  `attempts` INT(11) NOT NULL DEFAULT 1,
  `response_code` INT(11) DEFAULT NULL,
  `response_body` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### توصيف جدول العملاء (`customers`)

| العمود | النوع | القيود | الوصف |
| :--- | :--- | :--- | :--- |
| `id` | `INT(11)` | `PRIMARY KEY, AUTO_INCREMENT` | المعرف الفريد للعميل |
| `name` | `VARCHAR(100)` | `NOT NULL` | اسم العميل (إلزامي) |
| `phone` | `VARCHAR(20)` | `NOT NULL, UNIQUE` | رقم هاتف العميل (إلزامي وفريد) |
| `email` | `VARCHAR(100)` | `DEFAULT NULL` | البريد الإلكتروني للعميل (اختياري) |
| `status` | `ENUM('active', 'inactive')` | `DEFAULT 'active'` | حالة العميل |
| `created_at` | `TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | تاريخ ووقت إنشاء السجل |

### توصيف جدول سجلات التكامل (`integration_logs`)

| العمود | النوع | القيود | الوصف |
| :--- | :--- | :--- | :--- |
| `id` | `INT(11)` | `PRIMARY KEY, AUTO_INCREMENT` | المعرف الفريد لسجل التكامل |
| `customer_id` | `INT(11)` | `NOT NULL, INDEX` | معرف العميل المرتبط بالعملية |
| `status` | `ENUM('pending', 'success', 'failed')` | `DEFAULT 'pending'` | حالة المزامنة مع النظام الخارجي |
| `attempts` | `INT(11)` | `DEFAULT 1` | عدد محاولات الإرسال |
| `response_code` | `INT(11)` | `DEFAULT NULL` | كود حالة HTTP المستلم من النظام الخارجي |
| `response_body` | `TEXT` | `DEFAULT NULL` | نص الاستجابة أو رسالة الخطأ من النظام الخارجي |
| `created_at` | `TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | تاريخ ووقت بدء محاولة التكامل |
| `updated_at` | `TIMESTAMP` | `ON UPDATE CURRENT_TIMESTAMP` | تاريخ ووقت تحديث نتيجة التكامل |

## نقطة النهاية وطريقة الاستخدام

تستقبل نقطة نهاية الـ Webhook الطلبات وفق الإعدادات التالية:

- **طريقة الطلب:** `POST`
- **الرابط:** `http://localhost/task2-webhook-integration/webhook/customer.php`
- **الترويسة:** `Content-Type: application/json`

### معاملات جسم الطلب

| الحقل | النوع | الحالة | الوصف |
| :--- | :--- | :--- | :--- |
| `name` | `string` | إلزامي | اسم العميل |
| `phone` | `string` | إلزامي | رقم جوال العميل (يجب ألا يكون مسجلاً من قبل) |
| `email` | `string` | اختياري | البريد الإلكتروني للعميل بصيغة صحيحة |

## أمثلة الطلبات والاستجابات

### مثال الطلب الصحيح

**جسم الطلب (Request Body):**

```json
{
  "name": "أحمد علي",
  "phone": "0551234567",
  "email": "ahmed@example.com"
}
```

**الطلب باستخدام cURL:**

```bash
curl -X POST "http://localhost/task2-webhook-integration/webhook/customer.php" \
  -H "Content-Type: application/json" \
  -d '{"name":"أحمد علي","phone":"0551234567","email":"ahmed@example.com"}'
```

### 1. استجابة النجاح الكامل (`201 Created`)

تظهر عند حفظ العميل محلياً ونجاح إرسال بياناته إلى النظام الخارجي:

```json
{
  "status": "success",
  "webhook_received": true,
  "customer_saved": true,
  "external_api_synced": true,
  "message": "Webhook processed: customer saved locally and synced with external API successfully.",
  "customer_id": 1,
  "log_id": 1,
  "external_http_code": 201
}
```

### 2. استجابة النجاح الجزئي عند تعذر المزامنة الخارجية (`207 Multi-Status`)

تظهر عند نجاح حفظ العميل محلياً مع فشل الاتصال بالنظام الخارجي (بسبب انقطاع الإنترنت أو انتهاء المهلة أو خطأ في الخدمة الخارجية):

```json
{
  "status": "partial_success",
  "webhook_received": true,
  "customer_saved": true,
  "external_api_synced": false,
  "message": "Customer saved locally, but failed to sync with external API. Scheduled for retry.",
  "customer_id": 4,
  "log_id": 4,
  "external_http_code": 0,
  "error": "External service unreachable or SSL/timeout error."
}
```

### 3. استجابة تكرار رقم الهاتف (`409 Conflict`)

تظهر عند محاولة إرسال رقم هاتف مسجل مسبقاً في قاعدة البيانات:

```json
{
  "status": "error",
  "message": "Customer with this phone number already exists."
}
```

### 4. استجابات أخطاء التحقق من المدخلات (`400 Bad Request`)

عند إرسال جسم طلب فارغ:

```json
{
  "status": "error",
  "message": "Empty request body."
}
```

عند إرسال صيغة JSON غير صحيحة:

```json
{
  "status": "error",
  "message": "Invalid JSON format: Syntax error"
}
```

عند نقص حقل الاسم أو رقم الهاتف:

```json
{
  "status": "error",
  "message": "Name and phone are required fields."
}
```

عند إرسال بريد إلكتروني بصيغة غير صالحة:

```json
{
  "status": "error",
  "message": "Invalid email address format."
}
```

### 5. استجابة طريقة طلب غير مدعومة (`405 Method Not Allowed`)

تظهر عند إرسال طلب بطريقة غير `POST` (مثل `GET` أو `PUT` أو `DELETE`):

```json
{
  "status": "error",
  "message": "Method not allowed. Use POST."
}
```

### 6. استجابة خطأ الخادم أو قاعدة البيانات (`500 Internal Server Error`)

تظهر عند تعذر الاتصال بقاعدة البيانات أو عدم وجود أحد الجداول:

```json
{
  "status": "error",
  "message": "Internal database error occurred."
}
```

## أمثلة السجلات في ملف `logs/app.log`

يُدوّن النظام كل خطوة تلقائياً في الملف `logs/app.log` بالتنسيق التالي:

```text
[2026-10-20 19:06:34] INFO: Customer ID 1 created. Integration Log ID 1 initialized as PENDING.
[2026-10-20 19:06:36] SUCCESS: Customer ID 1 synced with External API (HTTP 201).
[2026-10-20 19:20:08] INFO: Customer ID 4 created. Integration Log ID 4 initialized as PENDING.
[2026-10-20 19:20:18] ERROR: External API sync failed for Customer ID 4. cURL Error (28): Operation timed out after 10003 milliseconds with 0 bytes received
```

## ملخص أكواد حالة HTTP

| الكود | الحالة | الوصف |
| :--- | :--- | :--- |
| `201 Created` | `success` | حفظ العميل محلياً ومزامنته مع النظام الخارجي بنجاح |
| `207 Multi-Status` | `partial_success` | حفظ العميل محلياً مع فشل المزامنة الخارجية وجدولتها للمحاولة لاحقاً |
| `400 Bad Request` | `error` | جسم الطلب فارغ، أو صيغة JSON/بريد إلكتروني خاطئة، أو نقص في الحقول الإلزامية |
| `405 Method Not Allowed` | `error` | إرسال الطلب بطريقة HTTP غير `POST` |
| `409 Conflict` | `error` | رقم الهاتف مسجل مسبقاً لعميل آخر |
| `500 Internal Server Error` | `error` | خطأ داخلي في قاعدة البيانات أو الخادم |
