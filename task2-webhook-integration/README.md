# Task 2 - Webhook & External API Integration

خدمة تستقبل بيانات العميل عبر Webhook وتحفظها ثم ترسلها لـ REST API خارجي مع تسجيل كامل التفاصيل.

## Flow
Incoming Webhook → Validation → Duplicate Check → Database → External API → Logging

## Endpoint
`POST /webhook/customer.php`

```json
{
  "name": "Ahmed Ali",
  "phone": "0551234567",
  "email": "ahmed@example.com"
}