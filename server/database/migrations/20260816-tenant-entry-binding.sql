CREATE TABLE `pa_tenant_entry_binding` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `client_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_entry_binding` (`host`, `client_key`),
  KEY `idx_tenant_entry_lookup` (`host`, `client_key`, `status`, `tenant_id`),
  CONSTRAINT `fk_tenant_entry_binding_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_tenant_entry_binding_status`
    CHECK (`status` IN ('active', 'disabled')),
  CONSTRAINT `chk_tenant_entry_binding_host`
    CHECK (`host` = LOWER(`host`) AND CHAR_LENGTH(`host`) BETWEEN 1 AND 253),
  CONSTRAINT `chk_tenant_entry_binding_client`
    CHECK (REGEXP_LIKE(`client_key`, '^[a-z][a-z0-9-]{0,63}$', 'c'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
