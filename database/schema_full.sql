-- =============================================================================
-- Mini ERP Stock & Sales — Complete Unified Database Schema (F01 — F10)
-- Target RDBMS: MariaDB 10.4.32+ / MySQL 8.0+
-- Storage Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Currency: SAR (Saudi Riyal) | Quantities: Integers Only
-- All Foreign Keys: Strictly RESTRICT (Preserving Audit & Historical Records)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Roles Table (جدول أدوار النظام)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL COMMENT 'اسم الدور بالإنجليزية (admin, warehouse, sales)',
    `display_name` VARCHAR(100) NOT NULL COMMENT 'الاسم التعريفي للدور باللغة العربية',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول أدوار المستخدمين الثابتة';

-- -----------------------------------------------------------------------------
-- 2. Users Table (جدول المستخدمين)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT 'اسم تسجيل الدخول الفريد',
    `full_name` VARCHAR(150) NOT NULL COMMENT 'الاسم الكامل للمستخدم',
    `email` VARCHAR(191) NULL DEFAULT NULL COMMENT 'البريد الإلكتروني',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'تجزئة كلمة المرور بتقنية BCRYPT',
    `role_id` INT UNSIGNED NOT NULL COMMENT 'معرف الدور المرتبط',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'حالة الحساب: 1 نشط، 0 معطل',
    `created_at` DATETIME NOT NULL COMMENT 'تاريخ ووقت إنشاء الحساب',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ ووقت آخر تحديث',
    `last_login_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ ووقت آخر تسجيل دخول',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_role_id` (`role_id`),
    KEY `idx_users_is_active` (`is_active`),
    CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) 
        REFERENCES `roles` (`id`) 
        ON UPDATE CASCADE 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المستخدمين وبيانات الاعتماد';

-- -----------------------------------------------------------------------------
-- 3. Categories Table (جدول تصنيفات المنتجات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT 'اسم التصنيف',
    `description` TEXT NULL DEFAULT NULL COMMENT 'وصف التصنيف',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 نشط، 0 معطل',
    `created_at` DATETIME NOT NULL COMMENT 'تاريخ الإنشاء',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ آخر تحديث',
    PRIMARY KEY (`id`),
    KEY `idx_categories_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول تصنيفات المنتجات';

-- -----------------------------------------------------------------------------
-- 4. Warehouses Table (جدول المستودعات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL COMMENT 'اسم المستودع',
    `code` VARCHAR(50) NOT NULL COMMENT 'كود المستودع الفريد',
    `location` VARCHAR(255) NULL DEFAULT NULL COMMENT 'موقع المستودع',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 نشط، 0 معطل',
    `created_at` DATETIME NOT NULL COMMENT 'تاريخ الإنشاء',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ آخر تحديث',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_warehouses_code` (`code`),
    KEY `idx_warehouses_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المستودعات ومواقع التخزين';

-- -----------------------------------------------------------------------------
-- 5. User-Warehouse Assignments Table (جدول تعيين المستخدمين بالمستودعات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_warehouses` (
    `user_id` INT UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
    `warehouse_id` INT UNSIGNED NOT NULL COMMENT 'معرف المستودع المصرح به',
    `created_at` DATETIME NOT NULL COMMENT 'تاريخ التعيين',
    PRIMARY KEY (`user_id`, `warehouse_id`),
    KEY `idx_uw_warehouse_id` (`warehouse_id`),
    CONSTRAINT `fk_uw_user_id` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_uw_warehouse_id` FOREIGN KEY (`warehouse_id`)
        REFERENCES `warehouses` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول ربط صلاحيات المستخدمين بالمستودعات';

-- -----------------------------------------------------------------------------
-- 6. Products Table (جدول المنتجات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(64) NOT NULL COMMENT 'رمز التخزين الفريد للمنتج',
    `name` VARCHAR(200) NOT NULL COMMENT 'اسم المنتج الكامل',
    `description` TEXT NULL DEFAULT NULL COMMENT 'وصف المنتج وتفاصيله',
    `category_id` INT UNSIGNED NOT NULL COMMENT 'معرف التصنيف المرتبط',
    `sale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'سعر البيع الافتراضي بالريال السعودي',
    `reorder_level` INT NOT NULL DEFAULT 0 COMMENT 'حد إعادة الطلب الافتراضي',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 نشط، 0 معطل',
    `created_at` DATETIME NOT NULL COMMENT 'تاريخ الإنشاء',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ آخر تحديث',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_products_sku` (`sku`),
    KEY `idx_products_category_id` (`category_id`),
    KEY `idx_products_is_active` (`is_active`),
    CONSTRAINT `fk_products_category_id` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `chk_prod_price_nonneg` CHECK (`sale_price` >= 0.00),
    CONSTRAINT `chk_prod_reorder_nonneg` CHECK (`reorder_level` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول بطاقات المنتجات الأساسية';

-- -----------------------------------------------------------------------------
-- 7. Customers Table (جدول العملاء)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL COMMENT 'اسم العميل الكامل أو المنشأة',
    `phone` VARCHAR(30) DEFAULT NULL COMMENT 'رقم الهاتف للتواصل',
    `email` VARCHAR(191) DEFAULT NULL COMMENT 'البريد الإلكتروني للعميل',
    `address` VARCHAR(255) DEFAULT NULL COMMENT 'العنوان الجغرافي',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 نشط، 0 معطل',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ إنشاء السجل',
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
    PRIMARY KEY (`id`),
    KEY `idx_customers_is_active` (`is_active`),
    KEY `idx_customers_name` (`name`),
    KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء الأساسي';

-- -----------------------------------------------------------------------------
-- 8. Invoice Sequences Table (جدول ترقيم الفواتير اليومية الذري)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_sequences` (
    `sequence_date` DATE NOT NULL COMMENT 'تاريخ الفاتورة اليومي',
    `current_sequence` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'الرقم التسلسلي الحالي (1 إلى 9999)',
    PRIMARY KEY (`sequence_date`),
    CONSTRAINT `chk_is_sequence_range` CHECK (`current_sequence` >= 0 AND `current_sequence` <= 9999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عداد الترقيم اليومي الذري لفواتير المبيعات';

-- -----------------------------------------------------------------------------
-- 9. Sales Invoices Table (جدول فواتير المبيعات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales_invoices` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50) NOT NULL COMMENT 'رقم الفاتورة الفريد بنمط INV-YYYYMMDD-XXXX',
    `customer_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف العميل',
    `warehouse_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف مستودع الصرف',
    `created_by` INT(10) UNSIGNED NOT NULL COMMENT 'المستخدم المنشئ للفاتورة',
    `invoice_date` DATE NOT NULL COMMENT 'تاريخ الفاتورة',
    `status` ENUM('active', 'cancelled') NOT NULL DEFAULT 'active' COMMENT 'حالة الفاتورة',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'المجموع قبل الخصم (ر.س)',
    `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'نسبة الخصم المئوية (0 إلى 100%)',
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'قيمة الخصم بالريال السعودي',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'الإجمالي النهائي بعد الخصم (ر.س)',
    `currency_code` VARCHAR(3) NOT NULL DEFAULT 'SAR' COMMENT 'العملة المعتمدة SAR',
    `notes` VARCHAR(255) DEFAULT NULL COMMENT 'ملاحظات الفاتورة',
    `idempotency_key` VARCHAR(64) NOT NULL COMMENT 'رمز منع التكرار الفريد',
    `cancelled_at` DATETIME DEFAULT NULL COMMENT 'تاريخ ووقت الإلغاء',
    `cancelled_by` INT(10) UNSIGNED DEFAULT NULL COMMENT 'المستخدم المنفذ للإلغاء',
    `cancellation_reason` VARCHAR(255) DEFAULT NULL COMMENT 'سبب الإلغاء الإلزامي',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_si_invoice_number` (`invoice_number`),
    UNIQUE KEY `uk_si_idempotency` (`idempotency_key`),
    KEY `idx_si_customer_id` (`customer_id`),
    KEY `idx_si_warehouse_id` (`warehouse_id`),
    KEY `idx_si_created_by` (`created_by`),
    KEY `idx_si_status` (`status`),
    KEY `idx_si_invoice_date` (`invoice_date`),
    KEY `idx_si_created_at` (`created_at`),
    KEY `idx_si_cancelled_by` (`cancelled_by`),
    CONSTRAINT `fk_si_customer_id` FOREIGN KEY (`customer_id`) 
        REFERENCES `customers` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `fk_si_warehouse_id` FOREIGN KEY (`warehouse_id`) 
        REFERENCES `warehouses` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `fk_si_created_by` FOREIGN KEY (`created_by`) 
        REFERENCES `users` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `fk_si_cancelled_by` FOREIGN KEY (`cancelled_by`) 
        REFERENCES `users` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `chk_si_currency_sar` CHECK (BINARY `currency_code` = 'SAR'),
    CONSTRAINT `chk_si_subtotal_nonneg` CHECK (`subtotal` >= 0.00),
    CONSTRAINT `chk_si_discount_pct_range` CHECK (`discount_percentage` >= 0.00 AND `discount_percentage` <= 100.00),
    CONSTRAINT `chk_si_discount_amt_range` CHECK (`discount_amount` >= 0.00 AND `discount_amount` <= `subtotal`),
    CONSTRAINT `chk_si_total_nonneg` CHECK (`total_amount` >= 0.00),
    CONSTRAINT `chk_si_math_balance` CHECK (`total_amount` = `subtotal` - `discount_amount`),
    CONSTRAINT `chk_si_cancellation_logic` CHECK (
        (`status` = 'active' AND `cancelled_at` IS NULL AND `cancelled_by` IS NULL AND `cancellation_reason` IS NULL)
        OR
        (`status` = 'cancelled' AND `cancelled_at` IS NOT NULL AND `cancelled_by` IS NOT NULL AND `cancellation_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`cancellation_reason`)) >= 5)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول فواتير المبيعات ورؤوس العمليات';

-- -----------------------------------------------------------------------------
-- 10. Sales Invoice Items Table (جدول بنود فواتير المبيعات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف الفاتورة الأصلية',
    `product_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف المنتج المباع',
    `sku_snapshot` VARCHAR(50) NOT NULL COMMENT 'لقطة ثابتة لرمز SKU وقت البيع',
    `product_name_snapshot` VARCHAR(255) NOT NULL COMMENT 'لقطة ثابتة لاسم المنتج وقت البيع',
    `unit_price` DECIMAL(12,2) NOT NULL COMMENT 'سعر بيع الوحدة التاريخي الفعلي',
    `quantity` INT(11) NOT NULL COMMENT 'الكمية المباعة (عدد صحيح موجب)',
    `line_total` DECIMAL(12,2) NOT NULL COMMENT 'إجمالي السطر = الكمية × سعر الوحدة',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ إنشاء البند',
    PRIMARY KEY (`id`),
    KEY `idx_sii_invoice_id` (`invoice_id`),
    KEY `idx_sii_product_id` (`product_id`),
    CONSTRAINT `fk_sii_invoice_id` FOREIGN KEY (`invoice_id`) 
        REFERENCES `sales_invoices` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `fk_sii_product_id` FOREIGN KEY (`product_id`) 
        REFERENCES `products` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `chk_sii_quantity_pos` CHECK (`quantity` > 0),
    CONSTRAINT `chk_sii_unit_price_nonneg` CHECK (`unit_price` >= 0.00),
    CONSTRAINT `chk_sii_line_total_math` CHECK (`line_total` = `quantity` * `unit_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول بنود وتفاصيل فواتير المبيعات';

-- -----------------------------------------------------------------------------
-- 11. Warehouse Stock Table (جدول أرصدة المخزون بالمستودعات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warehouse_stock` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `warehouse_id` INT UNSIGNED NOT NULL COMMENT 'معرف المستودع',
    `product_id` INT UNSIGNED NOT NULL COMMENT 'معرف المنتج',
    `quantity` INT NOT NULL DEFAULT 0 COMMENT 'رصيد المخزون الحالي (عدد صحيح)',
    `reorder_level` INT NOT NULL DEFAULT 0 COMMENT 'حد إعادة الطلب الفعال بالمستودع',
    `created_at` DATETIME NOT NULL COMMENT 'تاريخ الإنشاء',
    `updated_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ آخر تحديث',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_warehouse_product` (`warehouse_id`, `product_id`),
    KEY `idx_ws_warehouse_id` (`warehouse_id`),
    KEY `idx_ws_product_id` (`product_id`),
    CONSTRAINT `fk_ws_warehouse_id` FOREIGN KEY (`warehouse_id`)
        REFERENCES `warehouses` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_ws_product_id` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `chk_ws_quantity_nonneg` CHECK (`quantity` >= 0),
    CONSTRAINT `chk_ws_reorder_nonneg`  CHECK (`reorder_level` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول أرصدة المنتجات المستقلة بكل مستودع';

-- -----------------------------------------------------------------------------
-- 12. Stock Movements Table (سجل حركات المخزون التراكمي التاريخي)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `warehouse_id` INT UNSIGNED NOT NULL COMMENT 'معرف المستودع',
    `product_id` INT UNSIGNED NOT NULL COMMENT 'معرف المنتج',
    `movement_type` ENUM('in', 'adjustment', 'sale', 'sale_reversal') NOT NULL COMMENT 'نوع الحركة',
    `quantity_delta` INT NOT NULL COMMENT 'كمية التغير (موجب للتوريد والاسترجاع، سالب للبيع)',
    `before_quantity` INT NOT NULL DEFAULT 0 COMMENT 'الرصيد قبل الحركة مباشرة',
    `after_quantity` INT NOT NULL DEFAULT 0 COMMENT 'الرصيد بعد الحركة مباشرة',
    `reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سبب الحركة أو مبرر التسوية',
    `idempotency_key` VARCHAR(64) NOT NULL COMMENT 'رمز فريد لمنع تكرار الحركة',
    `sales_invoice_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'معرف الفاتورة المرتبطة بالبيع أو الإلغاء',
    `reversal_of_movement_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'معرف حركة البيع المعكوسة',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'معرف المستخدم الذي نفذ الحركة',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ ووقت الحركة بدقة',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sm_idempotency` (`idempotency_key`),
    UNIQUE KEY `uk_sm_reversal_of` (`reversal_of_movement_id`),
    KEY `idx_sm_warehouse_id` (`warehouse_id`),
    KEY `idx_sm_product_id` (`product_id`),
    KEY `idx_sm_movement_type` (`movement_type`),
    KEY `idx_sm_created_at` (`created_at`),
    KEY `idx_sm_created_by` (`created_by`),
    KEY `idx_sm_sales_invoice_id` (`sales_invoice_id`),
    CONSTRAINT `fk_sm_warehouse_id` FOREIGN KEY (`warehouse_id`)
        REFERENCES `warehouses` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_sm_product_id` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_sm_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_sm_sales_invoice_id` FOREIGN KEY (`sales_invoice_id`) 
        REFERENCES `sales_invoices` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `fk_sm_reversal_of` FOREIGN KEY (`reversal_of_movement_id`) 
        REFERENCES `stock_movements` (`id`) 
        ON UPDATE RESTRICT 
        ON DELETE RESTRICT,
    CONSTRAINT `chk_sm_qty_delta_nonzero` CHECK (`quantity_delta` != 0),
    CONSTRAINT `chk_sm_before_qty_nonneg` CHECK (`before_quantity` >= 0),
    CONSTRAINT `chk_sm_after_qty_nonneg` CHECK (`after_quantity` >= 0),
    CONSTRAINT `chk_sm_math_balance` CHECK (`after_quantity` = `before_quantity` + `quantity_delta`),
    CONSTRAINT `chk_sm_in_positive` CHECK (`movement_type` != 'in' OR `quantity_delta` > 0),
    CONSTRAINT `chk_sm_adjustment_reason` CHECK (
        `movement_type` != 'adjustment' OR
        (`reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`reason`)) >= 5)
    ),
    CONSTRAINT `chk_sm_sale_qty_negative` CHECK (`movement_type` <> 'sale' OR `quantity_delta` < 0),
    CONSTRAINT `chk_sm_reversal_qty_positive` CHECK (`movement_type` <> 'sale_reversal' OR `quantity_delta` > 0),
    CONSTRAINT `chk_sm_reversal_movement_logic` CHECK (
        (`movement_type` = 'sale_reversal' AND `reversal_of_movement_id` IS NOT NULL)
        OR
        (`movement_type` <> 'sale_reversal' AND `reversal_of_movement_id` IS NULL)
    ),
    CONSTRAINT `chk_sm_invoice_relationship` CHECK (
        (`movement_type` IN ('sale', 'sale_reversal') AND `sales_invoice_id` IS NOT NULL)
        OR
        (`movement_type` IN ('in', 'adjustment') AND `sales_invoice_id` IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='دفتر أستاذ حركات المخزون التراكمي التاريخي غير القابل للتعديل';

-- -----------------------------------------------------------------------------
-- 13. Audit Logs Table (جدول سجل التدقيق المركزي للعمليات الحساسة)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'رقم سجل التدقيق الأساسي',
    `user_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'المستخدم المنفذ للعملية (يقبل NULL في العمليات غير المصادق عليها)',
    `action` VARCHAR(50) NOT NULL COMMENT 'نوع العملية المنفذة',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'نوع الكيان المتأثر (users, products, categories, stock, sales_invoices, etc.)',
    `entity_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'المعرف الفريد للكيان المتأثر',
    `details` TEXT NOT NULL COMMENT 'تفاصيل العملية ووصفها البشري باللغة العربية',
    `metadata` LONGTEXT DEFAULT NULL COMMENT 'بيانات إضافية منظمة بصيغة JSON خالية من أي أسرار أو كلمات مرور',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'عنوان IP لمنفذ العملية',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT 'معرف المتصفح أو بيئة التنفيذ',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ ووقت تسجيل الحدث بدقة',
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_user_id` (`user_id`),
    KEY `idx_audit_logs_action` (`action`),
    KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_logs_created_at` (`created_at`),
    CONSTRAINT `fk_audit_logs_user_id` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل التدقيق المركزي لعمليات النظام الحرجة';

SET FOREIGN_KEY_CHECKS = 1;

