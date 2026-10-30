# database/

**الغرض:** حفظ ملفات وبنية ومخططات قاعدة البيانات (SQL Schema)، وبيانات التأسيس (Seed Data) غير السرية، وملاحظات الترحيل لمشروع Mini ERP Stock & Sales.

---

## المخططات المنفذة (Implemented Schemas)

### F03 — المصادقة والصلاحيات وإدارة المستخدمين (`database/schema_f03_auth.sql`)

- **المحرك والترميز:** MariaDB 10.4.32 / InnoDB / `utf8mb4_unicode_ci`.
- **اسم قاعدة البيانات المعتمد:** `mini_erp_stock_sales`.

#### 1. جدول الأدوار (`roles`):
- `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- `name`: `VARCHAR(50) NOT NULL UNIQUE` — الأدوار الثابتة:
  - `1`: `admin` (مدير النظام)
  - `2`: `warehouse` (مستخدم المستودع)
  - `3`: `sales` (مستخدم المبيعات)
- `display_name`: `VARCHAR(100) NOT NULL`.

#### 2. جدول المستخدمين (`users`):
- `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- `username`: `VARCHAR(50) NOT NULL UNIQUE` (تطبيع أحرف صغيرة وإزالة المسافات الزائدة).
- `full_name`: `VARCHAR(150) NOT NULL`.
- `email`: `VARCHAR(191) NULL DEFAULT NULL UNIQUE` (يقبل قيماً فارغة متعددة).
- `password_hash`: `VARCHAR(255) NOT NULL` (مشفّر باستخدام خوارزمية BCRYPT عبر `password_hash()`).
- `role_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي مرتبط بـ `roles.id` مع `ON UPDATE CASCADE ON DELETE RESTRICT`).
- `is_active`: `TINYINT(1) NOT NULL DEFAULT 1` (فهرس `idx_users_is_active`).
- `created_at`: `DATETIME NOT NULL`.
- `updated_at`: `DATETIME NULL DEFAULT NULL`.
- `last_login_at`: `DATETIME NULL DEFAULT NULL`.

---

## إعداد أول مدير نظام يدويًا عبر phpMyAdmin (Initial Admin Setup)

وفق متطلبات الأمان والتأسيس المعتمدة:
1. يتم الدخول إلى phpMyAdmin واختيار قاعدة البيانات `mini_erp_stock_sales`.
2. يتم توليد هاش كلمة المرور باستخدام دالة PHP المعيارية `password_hash('Admin@123456', PASSWORD_BCRYPT)`.
3. الهاش الناتج:
   `$2y$10$qOXkK6xYlhdEFEtGzz0xaOLFCA..mqE/LIXV2UaR.DlAs/Ln7u/cS`
4. تنفيذ جملة الإدراج التالية:

```sql
INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `password_hash`, `role_id`, `is_active`, `created_at`, `updated_at`, `last_login_at`)
VALUES (
    1,
    'admin',
    'مدير النظام',
    'admin@minierp.local',
    '$2y$10$qOXkK6xYlhdEFEtGzz0xaOLFCA..mqE/LIXV2UaR.DlAs/Ln7u/cS',
    1,
    1,
    NOW(),
    NULL,
    NULL
)
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);
```

بيانات الدخول الأولية (بيئة التطوير المحلية):
- **اسم المستخدم:** `admin`
- **كلمة المرور:** `Admin@123456`
