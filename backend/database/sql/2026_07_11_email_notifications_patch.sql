-- Email küldési napló az időpontfoglaló rendszerhez.
-- Ezt csak akkor futtasd, ha NEM a `php artisan migrate` megoldást használod.

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_id` bigint unsigned DEFAULT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `recipient_type` varchar(32) NOT NULL,
  `recipient_email` varchar(160) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` varchar(32) NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_logs_business_id_created_at_index` (`business_id`,`created_at`),
  KEY `email_logs_booking_id_event_type_index` (`booking_id`,`event_type`),
  KEY `email_logs_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `email_logs_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_logs_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
