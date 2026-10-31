-- ==============================================================================
-- Mini ERP Stock & Sales
-- Feature: F07 — Sales Invoices (فواتير المبيعات)
-- Database Migration Script: Schema Definition for Sales Invoices & Customers
-- Target RDBMS: MariaDB 10.4.32 / MySQL 8.0+ (InnoDB, utf8mb4_unicode_ci)
-- Policy: Foreign keys strictly ON UPDATE RESTRICT ON DELETE RESTRICT
-- Note: Forward-compatible with F08 (Cancellation), F09 (Reports), and F10 (Audit)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. Table: customers (العملاء)
-- Minimal master entity required for sales invoices without unnecessary CRM scope.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL COMMENT 'اسم العميل الكامل أو المؤسسة',
  `phone` VARCHAR(30) DEFAULT NULL COMMENT 'رقم الهاتف للتواصل',
  `email` VARCHAR(191) DEFAULT NULL COMMENT 'البريد الإلكتروني للعميل',
  `address` VARCHAR(255) DEFAULT NULL COMMENT 'العنوان أو بيانات الموقع الجغرافي',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = نشط، 0 = معطل (Soft Disable)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ إنشاء السجل',
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث للسجل',
  PRIMARY KEY (`id`),
  KEY `idx_customers_is_active` (`is_active`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء الأساسي';

-- ------------------------------------------------------------------------------
-- 2. Table: invoice_sequences (سلسلة ترقيم الفواتير اليومية الذرية)
-- Dedicated atomic sequence counter to guarantee gapless, collision-proof
-- daily sequential numbering (INV-YYYYMMDD-XXXX) under high concurrency.
-- Enforces hard ceiling of 9999 invoices per calendar date via CHECK constraint.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_sequences` (
  `sequence_date` DATE NOT NULL COMMENT 'تاريخ يوم التسلسل (مطابق لتاريخ الفاتورة)',
  `current_sequence` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'آخر رقم تسلسلي تم توليده في هذا اليوم (1 إلى 9999)',
  PRIMARY KEY (`sequence_date`),
  CONSTRAINT `chk_is_sequence_range` CHECK (`current_sequence` >= 0 AND `current_sequence` <= 9999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عداد الترقيم اليومي الذري لفواتير المبيعات';

-- ------------------------------------------------------------------------------
-- 3. Table: sales_invoices (فواتير المبيعات)
-- Header record of sales transactions with server-calculated financial values.
-- Currency is strictly SAR (enforced via CHECK constraint).
-- Includes full F08 cancellation structure and F09 reporting compliance.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales_invoices` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL COMMENT 'رقم الفاتورة الفريد بنمط INV-YYYYMMDD-XXXX',
  `customer_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف العميل المرتبط',
  `warehouse_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف مستودع الصرف',
  `created_by` INT(10) UNSIGNED NOT NULL COMMENT 'معرف المستخدم الذي أنشأ الفاتورة',
  `invoice_date` DATE NOT NULL COMMENT 'تاريخ الفاتورة المعياري',
  `status` ENUM('active', 'cancelled') NOT NULL DEFAULT 'active' COMMENT 'حالة الفاتورة (نشطة / ملغاة)',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'المجموع قبل الخصم بالريال السعودي',
  `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'نسبة الخصم الإجمالية من 0.00 إلى 100.00',
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'قيمة الخصم المحسوبة بالريال السعودي',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'الإجمالي النهائي بعد الخصم بالريال السعودي',
  `currency_code` VARCHAR(3) NOT NULL DEFAULT 'SAR' COMMENT 'العملة المعتمدة حصراً SAR',
  `notes` VARCHAR(255) DEFAULT NULL COMMENT 'ملاحظات أو بيان الفاتورة',
  `idempotency_key` VARCHAR(64) NOT NULL COMMENT 'رمز فريد 32 حرفاً لمنع تكرار الإرسال أو الخصم',
  `cancelled_at` DATETIME DEFAULT NULL COMMENT 'تاريخ ووقت الإلغاء (F08)',
  `cancelled_by` INT(10) UNSIGNED DEFAULT NULL COMMENT 'المستخدم المنفذ للإلغاء (F08)',
  `cancellation_reason` VARCHAR(255) DEFAULT NULL COMMENT 'سبب الإلغاء الإلزامي (F08)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ إنشاء الفاتورة في النظام',
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
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
  CONSTRAINT `fk_si_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_si_warehouse_id` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_si_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_si_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول فواتير المبيعات';

-- ------------------------------------------------------------------------------
-- 4. Table: sales_invoice_items (بنود فواتير المبيعات)
-- Detailed line items preserving historical unit price, product name, and SKU snapshots.
-- Enforces positive quantity, non-negative price, and math balance.
-- Application must enforce at least one item per invoice within the ACID transaction.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف الفاتورة الأصلية',
  `product_id` INT(10) UNSIGNED NOT NULL COMMENT 'معرف المنتج المباع',
  `sku_snapshot` VARCHAR(50) NOT NULL COMMENT 'لقطة ثابتة لرمز SKU وقت البيع',
  `product_name_snapshot` VARCHAR(255) NOT NULL COMMENT 'لقطة ثابتة لاسم المنتج وقت البيع',
  `unit_price` DECIMAL(12,2) NOT NULL COMMENT 'سعر بيع الوحدة التاريخي الفعلي وقت البيع',
  `quantity` INT(11) NOT NULL COMMENT 'الكمية المباعة (عدد صحيح موجب حصراً)',
  `line_total` DECIMAL(12,2) NOT NULL COMMENT 'إجمالي السطر = الكمية × سعر الوحدة',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ إنشاء البند',
  PRIMARY KEY (`id`),
  KEY `idx_sii_invoice_id` (`invoice_id`),
  KEY `idx_sii_product_id` (`product_id`),
  CONSTRAINT `fk_sii_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_sii_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `chk_sii_quantity_pos` CHECK (`quantity` > 0),
  CONSTRAINT `chk_sii_unit_price_nonneg` CHECK (`unit_price` >= 0.00),
  CONSTRAINT `chk_sii_line_total_math` CHECK (`line_total` = `quantity` * `unit_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول بنود وتفاصيل فواتير المبيعات';

-- ------------------------------------------------------------------------------
-- 5. Extension to Table: stock_movements (حركات المخزون)
-- Backward-compatible schema evolution:
-- - Preserves all 6 F06 CHECK constraints, indexes, and existing foreign keys untouched.
-- - Adds 'sale' and 'sale_reversal' to movement_type ENUM.
-- - Adds sales_invoice_id (FK to sales_invoices) to bind movements to invoices.
-- - Adds reversal_of_movement_id (FK to stock_movements) to bind reversals to original sales.
-- - Enforces UNIQUE constraint uk_sm_reversal_of so a sale movement can NEVER be reversed twice.
-- - Enforces MariaDB CHECK constraints for sale (< 0), reversal (> 0), and strict linkage.
-- ------------------------------------------------------------------------------

-- Step 5.1: Extend movement_type ENUM
ALTER TABLE `stock_movements`
  MODIFY COLUMN `movement_type` ENUM('in', 'adjustment', 'sale', 'sale_reversal') NOT NULL COMMENT 'نوع الحركة المخزنية';

-- Step 5.2: Add relational foreign key columns
ALTER TABLE `stock_movements`
  ADD COLUMN `sales_invoice_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'معرف الفاتورة المرتبطة بحركة البيع أو الإلغاء' AFTER `idempotency_key`,
  ADD COLUMN `reversal_of_movement_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'معرف حركة البيع الأصلية المعكوسة (للحركات من نوع sale_reversal حصراً)' AFTER `sales_invoice_id`;

-- Step 5.3: Add indexes and uniqueness constraints
ALTER TABLE `stock_movements`
  ADD KEY `idx_sm_sales_invoice_id` (`sales_invoice_id`),
  ADD UNIQUE KEY `uk_sm_reversal_of` (`reversal_of_movement_id`);

-- Step 5.4: Add foreign key constraints
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_sm_sales_invoice_id` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_sm_reversal_of` FOREIGN KEY (`reversal_of_movement_id`) REFERENCES `stock_movements` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- Step 5.5: Add CHECK constraints for sale, sale_reversal, and linkage integrity
-- All 6 existing F06 CHECK constraints remain fully active and enforced:
--   1. chk_sm_qty_delta_nonzero (`quantity_delta` <> 0)
--   2. chk_sm_before_qty_nonneg (`before_quantity` >= 0)
--   3. chk_sm_after_qty_nonneg (`after_quantity` >= 0)
--   4. chk_sm_math_balance (`after_quantity` = `before_quantity` + `quantity_delta`)
--   5. chk_sm_in_positive (`movement_type` <> 'in' OR `quantity_delta` > 0)
--   6. chk_sm_adjustment_reason (`movement_type` <> 'adjustment' OR (`reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`reason`)) >= 5))
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `chk_sm_sale_qty_negative` CHECK (`movement_type` <> 'sale' OR `quantity_delta` < 0),
  ADD CONSTRAINT `chk_sm_reversal_qty_positive` CHECK (`movement_type` <> 'sale_reversal' OR `quantity_delta` > 0),
  ADD CONSTRAINT `chk_sm_reversal_movement_logic` CHECK (
    (`movement_type` = 'sale_reversal' AND `reversal_of_movement_id` IS NOT NULL)
    OR
    (`movement_type` <> 'sale_reversal' AND `reversal_of_movement_id` IS NULL)
  ),
  ADD CONSTRAINT `chk_sm_invoice_relationship` CHECK (
    (`movement_type` IN ('sale', 'sale_reversal') AND `sales_invoice_id` IS NOT NULL)
    OR
    (`movement_type` IN ('in', 'adjustment') AND `sales_invoice_id` IS NULL)
  );
