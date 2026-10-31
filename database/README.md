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

### F04 — التصنيفات والمنتجات والمستودعات وتعيينات المستخدمين (`database/schema_f04_core.sql`)

> [!NOTE]
> هذا المخطط تم إعداده وحفظه كملف SQL فقط (`database/schema_f04_core.sql`) بانتظار مراجعة مالك المشروع والموافقة على تنفيذه على قاعدة البيانات.

- **المحرك والترميز:** MariaDB 10.4.32 / InnoDB / `utf8mb4_unicode_ci`.
- **قواعد السلامة المطبقة:** منع الحذف التتابعي (`ON DELETE RESTRICT`) لمنع فقدان البيانات التاريخية، استخدام التعطيل الناعم (`is_active = 0`).

#### 1. جدول التصنيفات (`categories`):
- `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- `name`: `VARCHAR(100) NOT NULL`.
- `description`: `TEXT NULL DEFAULT NULL`.
- `is_active`: `TINYINT(1) NOT NULL DEFAULT 1` (فهرس `idx_categories_is_active`).
- `created_at`: `DATETIME NOT NULL`.
- `updated_at`: `DATETIME NULL DEFAULT NULL`.

#### 2. جدول المستودعات (`warehouses`):
- `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- `name`: `VARCHAR(150) NOT NULL`.
- `code`: `VARCHAR(50) NOT NULL UNIQUE` (فهرس فريد `uk_warehouses_code` مع تطبيع أحرف كبيرة TRIM + UPPERCASE).
- `location`: `VARCHAR(255) NULL DEFAULT NULL`.
- `is_active`: `TINYINT(1) NOT NULL DEFAULT 1` (فهرس `idx_warehouses_is_active`).
- `created_at`: `DATETIME NOT NULL`.
- `updated_at`: `DATETIME NULL DEFAULT NULL`.

#### 3. جدول المنتجات (`products`):
- `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- `sku`: `VARCHAR(64) NOT NULL UNIQUE` (فهرس فريد `uk_products_sku` مع تطبيع أحرف كبيرة TRIM + UPPERCASE).
- `name`: `VARCHAR(200) NOT NULL`.
- `description`: `TEXT NULL DEFAULT NULL`.
- `category_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `categories.id` مع `ON UPDATE CASCADE ON DELETE RESTRICT`).
- `sale_price`: `DECIMAL(12,2) NOT NULL DEFAULT 0.00` (التحقق خادميًا $\ge 0$).
- `reorder_level`: `INT NOT NULL DEFAULT 0` (التحقق خادميًا $\ge 0$).
- `is_active`: `TINYINT(1) NOT NULL DEFAULT 1` (فهرس `idx_products_is_active`).
- `created_at`: `DATETIME NOT NULL`.
- `updated_at`: `DATETIME NULL DEFAULT NULL`.

#### 4. جدول ربط المستخدمين بالمستودعات (`user_warehouses`):
- `user_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `users.id` مع `ON UPDATE RESTRICT ON DELETE RESTRICT`).
- `warehouse_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `warehouses.id` مع `ON UPDATE RESTRICT ON DELETE RESTRICT`).
- `created_at`: `DATETIME NOT NULL`.
- **المفتاح الأساسي المركب:** `PRIMARY KEY (user_id, warehouse_id)` لمنع التكرار نهائيًا.

---

### F05 — إدارة أرصدة المخزون بالمستودعات (`database/schema_f05_stock.sql`)

- **المحرك والترميز:** MariaDB 10.4.32 / InnoDB / `utf8mb4_unicode_ci`.
- **الجدول:** `warehouse_stock`
- **الحقول:**
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
  - `warehouse_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `warehouses.id` مع `RESTRICT`).
  - `product_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `products.id` مع `RESTRICT`).
  - `quantity`: `INT NOT NULL DEFAULT 0` (رصيد المخزون، يقبل أعداداً صحيحة فقط).
  - `reorder_level`: `INT NOT NULL DEFAULT 0` (حد إعادة الطلب).
  - `created_at`: `DATETIME NOT NULL`.
  - `updated_at`: `DATETIME NULL DEFAULT NULL`.
- **القيود والفهارس:**
  - `uk_warehouse_product`: `UNIQUE KEY (warehouse_id, product_id)`.
  - `chk_stock_quantity_nonneg`: `CHECK (quantity >= 0)`.
  - `chk_stock_reorder_nonneg`: `CHECK (reorder_level >= 0)`.

---

### F06 — حركات المخزون (`database/schema_f06_stock_movements.sql`)

- **المحرك والترميز:** MariaDB 10.4.32 / InnoDB / `utf8mb4_unicode_ci`.
- **الجدول:** `stock_movements`
- **الحقول:**
  - `id`: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
  - `warehouse_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `warehouses.id` مع `RESTRICT`).
  - `product_id`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `products.id` مع `RESTRICT`).
  - `movement_type`: `ENUM('in', 'adjustment') NOT NULL`.
  - `quantity_delta`: `INT NOT NULL` (التغير في الكمية، عدد صحيح غير صفري).
  - `before_quantity`: `INT NOT NULL DEFAULT 0` (الرصيد قبل الحركة، غير سالب).
  - `after_quantity`: `INT NOT NULL DEFAULT 0` (الرصيد بعد الحركة، غير سالب).
  - `reason`: `VARCHAR(255) NULL DEFAULT NULL` (سبب الحركة، إلزامي للتسوية).
  - `idempotency_key`: `VARCHAR(64) NOT NULL UNIQUE` (رمز العملية الفريد لمنع التكرار).
  - `created_by`: `INT UNSIGNED NOT NULL` (مفتاح خارجي لـ `users.id` مع `RESTRICT`).
  - `created_at`: `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- **قيود سلامة البيانات (CHECK Constraints):**
  - `chk_sm_qty_delta_nonzero`: `CHECK (quantity_delta <> 0)`
  - `chk_sm_before_qty_nonneg`: `CHECK (before_quantity >= 0)`
  - `chk_sm_after_qty_nonneg`: `CHECK (after_quantity >= 0)`
  - `chk_sm_math_balance`: `CHECK (after_quantity = before_quantity + quantity_delta)`
  - `chk_sm_in_positive`: `CHECK (movement_type <> 'in' OR quantity_delta > 0)`
  - `chk_sm_adjustment_reason`: `CHECK (movement_type <> 'adjustment' OR (reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) >= 5))`

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
