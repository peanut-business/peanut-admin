CREATE TABLE `pa_tenant_setting` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `namespace` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `config_json` JSON NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_setting_namespace` (`tenant_id`, `namespace`),
  CONSTRAINT `fk_tenant_setting_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_tenant_setting_namespace`
    CHECK (REGEXP_LIKE(`namespace`, '^[a-z][a-z0-9.-]{0,63}$', 'c'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='Tenant-owned application capability settings';
