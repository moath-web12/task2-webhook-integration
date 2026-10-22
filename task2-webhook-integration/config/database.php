<?php
// config/database.php

/**
 * فئة إدارة الاتصال بقاعدة البيانات (Database Connection Class)
 * تتولى الاتصال بقاعدة البيانات MySQL باستخدام تقنية PDO مع دعم ترميز UTF-8
 */
class Database {
    // =========================================================================
    // بيانات الاتصال بقاعدة البيانات المحتفظ بها (Database Credentials)
    // =========================================================================
    private $host = "localhost";
    private $db_name = "internship_db";
    private $username = "root";
    private $password = "";
    
    /**
     * @var PDO|null كائن الاتصال بقاعدة البيانات
     */
    public $conn;

    /**
     * إنشاء وإرجاع كائن الاتصال بقاعدة البيانات PDO
     * 
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            // إنشاء كائن الاتصال ببروتوكول PDO
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );

            // ضبط ترميز النصوص لدعم اللغة العربية بشكل كامل (utf8mb4)
            $this->conn->exec("set names utf8mb4");

            // تفعيل نمط معالجة الأخطاء واستثناءات قواعد البيانات (PDO Exceptions)
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch(PDOException $exception) {
            // في حال فشل الاتصال يتم إرجاع رمز خطأ 500 ورسالة بالخطأ بصيغة JSON
            http_response_code(500);
            echo json_encode(["error" => "Database connection failed: " . $exception->getMessage()]);
            exit;
        }

        return $this->conn;
    }
}