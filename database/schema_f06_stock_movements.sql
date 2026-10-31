-- =============================================================================
-- Mini ERP Stock & Sales — F06: Stock Movements Schema
-- Target RDBMS: MariaDB 10.4.32 / MySQL 5.7+
-- Character set: utf8mb4 / Collation: utf8mb4_unicode_ci / Engine: InnoDB
-- =============================================================================

USE `mini_erp_stock_sales`;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Create stock_movements Table (سجل حركات المخزون التراكمي التاريخي)
-- Tracks all stock changes (in / adjustment) with full before/after balance audit.
-- Records are immutable and append-only.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `movement_type` ENUM('in', 'adjustment') NOT NULL,
    `quantity_delta` INT NOT NULL,
    `before_quantity` INT NOT NULL DEFAULT 0,
    `after_quantity` INT NOT NULL DEFAULT 0,
    `reason` VARCHAR(255) NULL DEFAULT NULL,
    `idempotency_key` VARCHAR(64) NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- منع تكرار تسجيل الحركة عند تكرار إرسال الطلب (Idempotency Protection)
    UNIQUE KEY `uk_sm_idempotency` (`idempotency_key`),

    -- فهارس الاستعلام والبحث والتصفية التاريخية
    KEY `idx_sm_warehouse_id` (`warehouse_id`),
    KEY `idx_sm_product_id` (`product_id`),
    KEY `idx_sm_movement_type` (`movement_type`),
    KEY `idx_sm_created_at` (`created_at`),
    KEY `idx_sm_created_by` (`created_by`),

    -- قيود المفاتيح الأجنبية (RESTRICT يمنع الحذف أو التعديل العرضي للبيانات المرجعية)
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

    -- قيود سلامة البيانات والمطابقة الرياضية (Enforced in MariaDB 10.4.32)
    -- 1. كمية الفرق لا يمكن أن تكون صفرية
    CONSTRAINT `chk_sm_qty_delta_nonzero` CHECK (`quantity_delta` != 0),

    -- 2. الرصيد قبل الحركة لا يمكن أن يكون سالباً
    CONSTRAINT `chk_sm_before_qty_nonneg` CHECK (`before_quantity` >= 0),

    -- 3. الرصيد بعد الحركة لا يمكن أن يكون سالباً
    CONSTRAINT `chk_sm_after_qty_nonneg` CHECK (`after_quantity` >= 0),

    -- 4. التحقق الرياضي الصارم من صحة المعادلة التراكمية: الرصيد بعد = الرصيد قبل + الفرق
    CONSTRAINT `chk_sm_math_balance` CHECK (`after_quantity` = `before_quantity` + `quantity_delta`),

    -- 5. حركة إدخال المخزون (in) يجب أن تكون موجبة حصراً
    CONSTRAINT `chk_sm_in_positive` CHECK (`movement_type` != 'in' OR `quantity_delta` > 0),

    -- 6. حركة تسوية المخزون (adjustment) تتطلب سبباً إلزامياً لا يقل عن 5 أحرف
    CONSTRAINT `chk_sm_adjustment_reason` CHECK (
        `movement_type` != 'adjustment' OR
        (`reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`reason`)) >= 5)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

