CREATE TABLE `pa_fixture_delivery_record` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `reference` VARCHAR(96) NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fixture_delivery_tenant_ref` (`tenant_id`, `reference`),
  KEY `idx_fixture_delivery_tenant_status` (`tenant_id`, `status`, `id`),
  CONSTRAINT `fk_fixture_delivery_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_fixture_delivery_status` CHECK (`status` IN ('recorded'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
