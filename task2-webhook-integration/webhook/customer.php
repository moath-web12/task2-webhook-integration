<?php
// ==============================================================================
// 1. إعدادات بيئة التشغيل والحماية (Security & Environment Settings)
// ==============================================================================

// إخفاء تفاصيل أخطاء PHP عن المستخدم النهائي لحماية أمان السيرفر ومنع تسريب المسارات
ini_set('display_errors', 0);
error_reporting(0);

// ==============================================================================
// 2. إعداد ترويسات الاستجابة والتحقق من طريقة الطلب (Headers & Method Check)
// ==============================================================================

// تحديد صيغة البيانات المرجعة كـ JSON بترميز UTF-8
header("Content-Type: application/json; charset=UTF-8");

// حصر الوصول للـ API عبر دالة POST فقط
header("Access-Control-Allow-Methods: POST");

// التحقق الصارم من أن الطلب وارد باستخدام طريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status"  => "error", 
        "message" => "Method not allowed. Use POST."
    ]);
    exit();
}

// ==============================================================================
// 3. تضمين الملفات والدوال المساعدة (Dependencies & Helpers)
// ==============================================================================

// تضمين ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/../config/database.php';

/**
 * دالة مساعدة لتسجيل أحداث النظام والعمليات في ملف نصي خارجي
 * 
 * @param string $message الرسالة المراد تسجيلها
 * @return void
 */
function writeLog($message) {
    $logDir = __DIR__ . '/../logs';

    // إنشاء مجلد السجلات في حال عدم وجوده مع صلاحيات كاملة
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile   = $logDir . '/app.log';
    $timestamp = date('Y-m-d H:i:s');

    // كتابة السطر في نهاية الملف دون مسح السجلات القديمة
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// ==============================================================================
// 4. تنفيذ ومعالجة العمليات والمنطق البرمجي (Main Execution Flow)
// ==============================================================================

try {
    // --------------------------------------------------------------------------
    // أ. تهيئة الاتصال بقاعدة البيانات
    // --------------------------------------------------------------------------
    $database = new Database();
    $db       = $database->getConnection();

    // التحقق من نجاح إنشاء كائن الاتصال
    if (!$db) {
        throw new Exception("Database connection failed.");
    }

    // --------------------------------------------------------------------------
    // ب. قراءة وفحص صحة بيانات JSON الواردة
    // --------------------------------------------------------------------------
    $rawInput = file_get_contents("php://input");

    // فحص ما إذا كان جسم الطلب فارغاً
    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error", 
            "message" => "Empty request body."
        ]);
        exit();
    }

    // فك تشفير JSON إلى مصفوفة ترابطية
    $data = json_decode($rawInput, true);

    // التحقق الدقيق من سلامة صياغة كود الـ JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error",
            "message" => "Invalid JSON format: " . json_last_error_msg()
        ]);
        exit();
    }

    // --------------------------------------------------------------------------
    // ج. تنظيف البيانات والتحقق من صحة المدخلات (Data Sanitization & Validation)
    // --------------------------------------------------------------------------
    $name  = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');

    // التحقق من وجود الحقول الإلزامية الأساسية
    if (empty($name) || empty($phone)) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error", 
            "message" => "Name and phone are required fields."
        ]);
        exit();
    }

    // [التحقق الجديد المضاف]: التأكد من أن رقم الجوال يتكون من أرقام فقط ولا يحتوي على حروف مثل nn
    if (!preg_match('/^[0-9]+$/', $phone)) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error", 
            "message" => "Invalid phone number format. Only numbers are allowed."
        ]);
        exit();
    }

    // التحقق من صحة صياغة البريد الإلكتروني في حال تم إرساله
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error", 
            "message" => "Invalid email address format."
        ]);
        exit();
    }

    // --------------------------------------------------------------------------
    // د. التحقق من عدم تكرار رقم الجوال في قاعدة البيانات
    // --------------------------------------------------------------------------
    $checkStmt = $db->prepare("SELECT id FROM customers WHERE phone = :phone LIMIT 1");
    $checkStmt->bindParam(':phone', $phone);
    $checkStmt->execute();

    // في حال وجود العميل مسبقاً بنفس رقم الجوال يتم إرجاع تعارض (Conflict 409)
    if ($checkStmt->rowCount() > 0) {
        http_response_code(409); // Conflict
        echo json_encode([
            "status"  => "error", 
            "message" => "Customer with this phone number already exists."
        ]);
        exit();
    }

    // --------------------------------------------------------------------------
    // هـ. حفظ بيانات العميل الجديد محلياً
    // --------------------------------------------------------------------------
    $insertCustomer = $db->prepare("INSERT INTO customers (name, phone, email, status) VALUES (:name, :phone, :email, 'active')");
    $insertCustomer->bindParam(':name', $name);
    $insertCustomer->bindParam(':phone', $phone);
    $insertCustomer->bindParam(':email', $email);
    $insertCustomer->execute();

    // الحصول على المعرف الخاص بالعميل المضاف
    $customerId = $db->lastInsertId();

    // --------------------------------------------------------------------------
    // و. تسجيل عملية الربط بحالة (Pending) قبل إرسال الطلب الخارجي
    // --------------------------------------------------------------------------
    // إنشاء سجل التكامل بحالة (Pending) قبل محاولة الإرسال للخارج (مطلب أساسي للمشرف)
    $insertLog = $db->prepare("INSERT INTO integration_logs (customer_id, status, attempts, created_at) VALUES (:cid, 'pending', 1, NOW())");
    $insertLog->bindParam(':cid', $customerId);
    $insertLog->execute();
    $logId = $db->lastInsertId();

    // كتابة سجل المتابعة في الملف النصي
    writeLog("INFO: Customer ID $customerId created. Integration Log ID $logId initialized as PENDING.");

    // --------------------------------------------------------------------------
    // ز. إعداد وإرسال البيانات للخدمة الخارجية (External API Integration via cURL)
    // --------------------------------------------------------------------------
    // إرسال البيانات إلى External API مع تفعيل SSL ومعالجة الـ Timeout
    $externalApiUrl = "https://jsonplaceholder.typicode.com/users"; // API خارجي موثوق يدعم SSL للاختبار
    
    // تجهيز حزمة البيانات المرسلة بصيغة JSON
    $payload = json_encode([
        "customer_id" => (int)$customerId,
        "name"        => $name,
        "phone"       => $phone,
        "email"       => $email
    ]);

    // تهيئة جلسة cURL
    $ch = curl_init($externalApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // ضبط مهلة الاتصال (Timeout) لمنع تعليق السيرفر
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // تفعيل التحقق من شهادة SSL (مطلب المشرف: عدم تعطيلها)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // إذا كان ملف شهادة cacert.pem موجوداً في مجلد config (لحل مشكلة HTTP Status 0 في WampServer)
    $caCertPath = __DIR__ . '/../config/cacert.pem';
    if (file_exists($caCertPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
    }

    // تنفيذ الطلب واستخراج النتائج وحالات الخطأ
    $externalResponse = curl_exec($ch);
    $curlErrno        = curl_errno($ch);
    $curlError        = curl_error($ch);
    $httpStatusCode   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // --------------------------------------------------------------------------
    // ح. فحص نتيجة التكامل الخارجي وتحديث السجل
    // --------------------------------------------------------------------------
    // 8. فحص نتيجة الإرسال الخارجي وتحديث السجل
    if ($curlErrno !== 0 || $httpStatusCode < 200 || $httpStatusCode >= 300) {
        // حالة الفشل في النظام الخارجي (مع نجاح الحفظ الداخلي)
        $errorDetail = $curlErrno !== 0 
            ? "cURL Error ($curlErrno): $curlError" 
            : "Unexpected HTTP Status: $httpStatusCode | Body: $externalResponse";
        
        // تحديث حالة السجل إلى فاشل (failed)
        $updateLog = $db->prepare("UPDATE integration_logs SET status = 'failed', response_code = :code, response_body = :body WHERE id = :id");
        $updateLog->bindParam(':code', $httpStatusCode, PDO::PARAM_INT);
        $updateLog->bindParam(':body', $errorDetail);
        $updateLog->bindParam(':id', $logId);
        $updateLog->execute();

        // تدوين الخطأ في ملف السجلات
        writeLog("ERROR: External API sync failed for Customer ID $customerId. $errorDetail");

        // إرجاع 202 Accepted أو 207 Multi-Status للتمييز بأن الـ Webhook استلم وحفظ البيانات محلياً لكن فشل الإرسال الخارجي
        http_response_code(207);
        echo json_encode([
            "status"              => "partial_success",
            "webhook_received"    => true,
            "customer_saved"      => true,
            "external_api_synced" => false,
            "message"             => "Customer saved locally, but failed to sync with external API. Scheduled for retry.",
            "customer_id"         => (int)$customerId,
            "log_id"              => (int)$logId,
            "external_http_code"  => $httpStatusCode,
            "error"               => $curlErrno !== 0 ? "External service unreachable or SSL/timeout error." : "External service rejected the request."
        ]);
        exit();
    }

    // --------------------------------------------------------------------------
    // ط. حالة النجاح الكامل (حفظ محلي + مزامنة خارجية ناجحة)
    // --------------------------------------------------------------------------
    // 9. حالة النجاح الكامل (حفظ داخلي + إرسال خارجي)
    $updateLog = $db->prepare("UPDATE integration_logs SET status = 'success', response_code = :code, response_body = :body WHERE id = :id");
    $updateLog->bindParam(':code', $httpStatusCode, PDO::PARAM_INT);
    $updateLog->bindParam(':body', $externalResponse);
    $updateLog->bindParam(':id', $logId);
    $updateLog->execute();

    // تدوين نجاح العملية بالكامل في ملف السجلات
    writeLog("SUCCESS: Customer ID $customerId synced with External API (HTTP $httpStatusCode).");

    // إرجاع استجابة النجاح النهائية (201 Created)
    http_response_code(201);
    echo json_encode([
        "status"              => "success",
        "webhook_received"    => true,
        "customer_saved"      => true,
        "external_api_synced" => true,
        "message"             => "Webhook processed: customer saved locally and synced with external API successfully.",
        "customer_id"         => (int)$customerId,
        "log_id"              => (int)$logId,
        "external_http_code"  => $httpStatusCode
    ]);

// ==============================================================================
// 5. إدارة الاستثناءات والأخطاء الحرجة (Error Handling & Exceptions)
// ==============================================================================
} catch (PDOException $e) {
    // التقاط وتسجيل أخطاء قاعدة البيانات
    writeLog("CRITICAL DB ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status"  => "error", 
        "message" => "Internal database error occurred."
    ]);
} catch (Exception $e) {
    // التقاط وتسجيل الأخطاء العامة للنظام
    writeLog("CRITICAL SYSTEM ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status"  => "error", 
        "message" => "Internal server error occurred."
    ]);
}
?>