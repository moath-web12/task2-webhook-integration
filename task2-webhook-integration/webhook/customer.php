<?php
// task2-webhook-integration/api/webhook.php

// =========================================================================
// 1. إعداد هيدر الاستجابة (JSON) واستدعاء ملف الاتصال بقاعدة البيانات
// =========================================================================
header("Content-Type: application/json; charset=UTF-8");
require_once "../config/database.php";

$database = new Database();
$db = $database->getConnection();

// قراءة بيانات JSON الواردة من طلب الـ Webhook
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

// تسجيل نص الطلب المستلم في ملف السجلات المحلي (app.log)
logMessage("Incoming Request: " . $raw_input);

// =========================================================================
// 2. التحقق من اكتمال البيانات الأساسية (Name & Phone)
// =========================================================================
if (empty($data['name']) || empty($data['phone'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid data: Name and Phone are required"]);
    logMessage("Error: Invalid data provided");
    exit;
}

// =========================================================================
// 3. منع تكرار التسجيل لنفس رقم الهاتف
// =========================================================================
$check_stmt = $db->prepare("SELECT id FROM customers WHERE phone = :phone");
$check_stmt->bindParam(":phone", $data['phone']);
$check_stmt->execute();

if ($check_stmt->rowCount() > 0) {
    http_response_code(409); // Conflict
    echo json_encode(["error" => "Duplicate customer: Phone number already exists"]);
    logMessage("Error: Duplicate phone " . $data['phone']);
    exit;
}

// =========================================================================
// 4. حفظ العميل في قاعدة البيانات واستدعاء الـ API الخارجي
// =========================================================================
try {
    // أ) إدراج العميل الجديد في جدول العملاء (customers)
    $stmt = $db->prepare("INSERT INTO customers (name, phone, email) VALUES (:name, :phone, :email)");
    $email = isset($data['email']) ? $data['email'] : null;
    $stmt->bindParam(":name", $data['name']);
    $stmt->bindParam(":phone", $data['phone']);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    // استخراج المعرف الجديد الخاص بالعميل
    $customer_id = $db->lastInsertId();

    // ب) إرسال بيانات العميل إلى الـ API الخارجي
    $api_result = sendToExternalApi($data);

    // ج) تسجل نتيجة العملية في جدول الـ Webhook Logs (webhook_logs)
    $log_stmt = $db->prepare("INSERT INTO webhook_logs (customer_id, status, api_response, last_attempt_at) VALUES (:cid, :status, :resp, NOW())");
    $log_stmt->bindParam(":cid", $customer_id);
    $log_stmt->bindParam(":status", $api_result['status']);
    $log_stmt->bindParam(":resp", $api_result['response']);
    $log_stmt->execute();

    // تسجيل ملخص النتيجة في ملف السجلات
    logMessage("Customer ID: {$customer_id} | External API Status: {$api_result['http_code']} | Result: " . strtoupper($api_result['status']));

    // د) إرجاع استجابة نجاح المعالجة للعميل
    http_response_code(200);
    echo json_encode([
        "message" => "Customer processed successfully",
        "customer_id" => $customer_id,
        "external_api_status" => $api_result['status']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    logMessage("Database Error: " . $e->getMessage());
}

// =========================================================================
// 5. دالة إرسال البيانات إلى الـ API الخارجي باستخدام cURL
// =========================================================================
function sendToExternalApi($payload) {
    $url = "https://httpbin.org/post";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // لتجاوز قيود SSL في السيرفر المحلي
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // أقصى وقت انتظار للطلب 5 ثوانٍ

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $status = ($http_code >= 200 && $http_code < 300) ? 'success' : 'failed';
    $response_data = $response ? $response : "cURL Error: " . $curl_error;

    return [
        'status' => $status,
        'http_code' => $http_code,
        'response' => $response_data
    ];
}

// =========================================================================
// 6. دالة كتابة السجلات النصية لتتبع العمليات والأخطاء (Logging)
// =========================================================================
function logMessage($msg) {
    $log_file = "../logs/app.log";
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "{$timestamp}\n{$msg}\n-------------------\n", FILE_APPEND);
}