-- =============================================================================
-- Mini ERP Stock & Sales — F03: Authentication, Authorization & Users Schema
-- Target RDBMS: MariaDB 10.4.32 / MySQL 5.7+
-- Character set: utf8mb4 / Collation: utf8mb4_unicode_ci / Engine: InnoDB
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `mini_erp_stock_sales`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `mini_erp_stock_sales`;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Roles Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Seed Fixed Roles (admin, warehouse, sales)
-- -----------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `display_name`) VALUES
(1, 'admin', 'مدير النظام'),
(2, 'warehouse', 'مستخدم المستودع'),
(3, 'sales', 'مستخدم المبيعات')
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `display_name` = VALUES(`display_name`);

-- -----------------------------------------------------------------------------
-- 2. Users Table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(191) NULL DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_role_id` (`role_id`),
    KEY `idx_users_is_active` (`is_active`),
    CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) 
        REFERENCES `roles` (`id`) 
        ON UPDATE CASCADE 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Initial Admin User (Manual Seed for phpMyAdmin / SQL execution)
-- Password plain: Admin@123456
-- Generated via PHP: password_hash('Admin@123456', PASSWORD_BCRYPT)
-- -----------------------------------------------------------------------------
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
ON DUPLICATE KEY UPDATE 
    `username` = VALUES(`username`);
