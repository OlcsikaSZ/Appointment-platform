-- Email központ: sablonbeállítások + újraküldéshez szükséges napló snapshot.
-- Ezt csak akkor használd, ha nem Laravel migrációval dolgozol.

CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `settings` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_settings_business_id_unique` (`business_id`),
  CONSTRAINT `email_settings_business_id_foreign`
    FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_payload := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_logs' AND COLUMN_NAME = 'payload'
);
SET @sql := IF(@has_payload = 0,
  'ALTER TABLE `email_logs` ADD COLUMN `payload` LONGTEXT NULL AFTER `error_message`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_resent_from := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_logs' AND COLUMN_NAME = 'resent_from_id'
);
SET @sql := IF(@has_resent_from = 0,
  'ALTER TABLE `email_logs` ADD COLUMN `resent_from_id` BIGINT UNSIGNED NULL AFTER `id`, ADD INDEX `email_logs_resent_from_id_index` (`resent_from_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
