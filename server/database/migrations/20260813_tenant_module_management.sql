CREATE TABLE `pa_module_installation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(96) NOT NULL,
  `installed_version` VARCHAR(32) NOT NULL,
  `manifest_schema_version` INT UNSIGNED NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `installed_at` DATETIME(3) NULL,
  `activated_at` DATETIME(3) NULL,
  `upgraded_at` DATETIME(3) NULL,
  `last_error_code` VARCHAR(96) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_installation_key` (`module_key`),
  KEY `idx_module_installation_status` (`status`, `module_key`),
  CONSTRAINT `chk_module_installation_status` CHECK (`status` IN ('installing','active','upgrading','maintenance','failed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
