-- =====================================================================
-- Manifold Apps — production schema + admin seed (MySQL / MariaDB)
-- Import via cPanel → phpMyAdmin → select the DB → Import (or SQL tab).
-- Safe to re-run: uses CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
--
-- Sessions & cache use the file driver, so no framework tables are needed —
-- only the app's own tables below.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---- Auth (single hub user) --------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Flow-er ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `flower_clients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flower_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flower_types_client_id_foreign` (`client_id`),
  CONSTRAINT `flower_types_client_id_foreign`
    FOREIGN KEY (`client_id`) REFERENCES `flower_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flower_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flower_templates_type_id_foreign` (`type_id`),
  CONSTRAINT `flower_templates_type_id_foreign`
    FOREIGN KEY (`type_id`) REFERENCES `flower_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flower_steps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flower_steps_template_id_position_index` (`template_id`, `position`),
  CONSTRAINT `flower_steps_template_id_foreign`
    FOREIGN KEY (`template_id`) REFERENCES `flower_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flower_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'in_progress',
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flower_runs_template_id_status_index` (`template_id`, `status`),
  CONSTRAINT `flower_runs_template_id_foreign`
    FOREIGN KEY (`template_id`) REFERENCES `flower_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flower_step_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `step_id` BIGINT UNSIGNED NOT NULL,
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `duration_seconds` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flower_step_logs_run_id_index` (`run_id`),
  KEY `flower_step_logs_step_id_index` (`step_id`),
  CONSTRAINT `flower_step_logs_run_id_foreign`
    FOREIGN KEY (`run_id`) REFERENCES `flower_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flower_step_logs_step_id_foreign`
    FOREIGN KEY (`step_id`) REFERENCES `flower_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---- Admin user -------------------------------------------------------------
-- Generate the bcrypt hash locally, then paste it in place of PASTE_BCRYPT_HASH:
--   php -r "echo password_hash('YOUR_ADMIN_PASSWORD', PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;"
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `created_at`, `updated_at`)
VALUES ('Claudiu', 'claudiu.man@gmail.com', 'PASTE_BCRYPT_HASH', NOW(), NOW());
