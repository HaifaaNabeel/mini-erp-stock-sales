-- =============================================================================
-- Mini ERP Stock & Sales — F04: Categories, Products & Warehouses Schema
-- Target RDBMS: MariaDB 10.4.32 / MySQL 5.7+
-- Character set: utf8mb4 / Collation: utf8mb4_unicode_ci / Engine: InnoDB
-- =============================================================================

USE `mini_erp_stock_sales`;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Categories Table (التصنيفات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_categories_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Warehouses Table (المستودعات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `location` VARCHAR(255) NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_warehouses_code` (`code`),
    KEY `idx_warehouses_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Products Table (المنتجات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(64) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `sale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reorder_level` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_products_sku` (`sku`),
    KEY `idx_products_category_id` (`category_id`),
    KEY `idx_products_is_active` (`is_active`),
    CONSTRAINT `fk_products_category_id` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. User-Warehouse Assignments Table (ربط المستخدمين بالمستودعات)
-- Composite primary key (user_id, warehouse_id) prevents duplicate assignments
-- RESTRICT on delete prevents accidental cascade deletion of business assignments
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_warehouses` (
    `user_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

