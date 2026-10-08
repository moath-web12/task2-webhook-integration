# Task 2 - Webhook & External API Integration

خدمة Webhook تستقبل بيانات العملاء عبر REST API وتتحقق منها، ثم تحفظها في قاعدة البيانات وتسجل التفاصيل في سجلات النظام (Logs)[cite: 7].

---

## 🔄 مسار العمل (Flow)
`Incoming Webhook` ➔ `Validation` ➔ `Duplicate Check` ➔ `Database` ➔ `External API` ➔ `Logging`[cite: 7]

---

## ⚙️ طريقة التشغيل (Setup Steps)

1. **إعداد قاعدة البيانات:**
   * قم باستيراد ملف `database.sql` داخل سيرفر MySQL المحلّي[cite: 7].
2. **ضبط بيانات الاتصال:**
   * تأكد من مطابقة إعدادات قاعدة البيانات في ملف `config/database.php`[cite: 7].

---

## 🚀 طريقة الاختبار والتنفيذ (Execution)

افتح برنامج **Postman** وقم بإعداد الطلب كالتالي:

* **Method:** `POST`[cite: 7]
* **URL:** `http://localhost/task2-webhook-integration/webhook/customer.php`[cite: 7]
* **Headers:** `Content-Type: application/json`

### 📄 بيانات الطلب (Sample Request Payload):
اختر **Body** ➔ **raw** ➔ **JSON** وضَع الكود التالي:

```json
{
  "name": "Ahmed Ali",
  "phone": "0551234567",
  "email": "ahmed@example.com"
}
