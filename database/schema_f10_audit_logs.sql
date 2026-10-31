-- ==============================================================================
-- Mini ERP Stock & Sales
-- Feature: F10 — Audit Logs, System Integration Testing & Final Validation
-- Database Migration Script: Schema Definition for Centralized Audit Logs
-- Target RDBMS: MariaDB 10.4.32 / MySQL 8.0+ (InnoDB, utf8mb4_unicode_ci)
-- Policy: Historical preservation, no plain passwords or secrets
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'رقم سجل التدقيق الأساسي',
  `user_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'المستخدم المنفذ للعملية (يقبل NULL للعمليات غير المصادق عليها أو النظامية)',
  `action` VARCHAR(50) NOT NULL COMMENT 'نوع العملية المنفذة (action / event_type)',
  `entity_type` VARCHAR(50) NOT NULL COMMENT 'نوع الكيان المتأثر (users, categories, products, warehouses, stock, sales_invoices, etc.)',
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

