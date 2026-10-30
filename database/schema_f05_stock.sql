-- =============================================================================
-- Mini ERP Stock & Sales — F05: Warehouse Stock Schema & Migration
-- Target RDBMS: MariaDB 10.4.32 / MySQL 5.7+
-- Character set: utf8mb4 / Collation: utf8mb4_unicode_ci / Engine: InnoDB
-- =============================================================================

USE `mini_erp_stock_sales`;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Create warehouse_stock Table (أرصدة المخزون بالمستودعات)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `warehouse_stock` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `reorder_level` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
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
    CONSTRAINT `chk_ws_quantity_nonneg`  CHECK (`quantity` >= 0),
    CONSTRAINT `chk_ws_reorder_nonneg`   CHECK (`reorder_level` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Safe Data Migration & Initialization (ترحيل البيانات المبدئي)
-- Initialize warehouse_stock for all active products across all active warehouses
-- Transfer existing products.reorder_level if present (currently 0 for all products)
-- -----------------------------------------------------------------------------
INSERT INTO `warehouse_stock` (`warehouse_id`, `product_id`, `quantity`, `reorder_level`, `created_at`)
SELECT 
    w.id AS warehouse_id,
    p.id AS product_id,
    0 AS quantity,
    COALESCE(p.reorder_level, 0) AS reorder_level,
    NOW() AS created_at
FROM `warehouses` w
CROSS JOIN `products` p
LEFT JOIN `warehouse_stock` ws ON ws.warehouse_id = w.id AND ws.product_id = p.id
WHERE ws.id IS NULL;

