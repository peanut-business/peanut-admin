CREATE TABLE `pa_plugin_installation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_key` VARCHAR(96) NOT NULL,
  `installed_version` VARCHAR(32) NOT NULL,
  `source` VARCHAR(255) NOT NULL,
  `artifact_sha256` CHAR(64) NOT NULL,
  `lock_digest` CHAR(64) NOT NULL,
  `composer_identity_json` JSON NOT NULL,
  `npm_identity_json` JSON NOT NULL,
  `frontend_identity_json` JSON NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `installed_at` DATETIME(3) NULL,
  `activated_at` DATETIME(3) NULL,
  `upgraded_at` DATETIME(3) NULL,
  `uninstalled_at` DATETIME(3) NULL,
  `last_error_code` VARCHAR(96) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_installation_key` (`plugin_key`),
  KEY `idx_plugin_installation_status` (`status`, `plugin_key`),
  CONSTRAINT `chk_plugin_installation_status` CHECK (`status` IN (
    'installing','active','upgrading','maintenance','failed','uninstalled'
  ))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_plugin_module` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_key` VARCHAR(96) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `module_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_module_key` (`module_key`),
  KEY `idx_plugin_module_plugin` (`plugin_key`, `module_key`),
  CONSTRAINT `fk_plugin_module_plugin` FOREIGN KEY (`plugin_key`)
    REFERENCES `pa_plugin_installation` (`plugin_key`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_module_migration` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(96) NOT NULL,
  `migration_key` VARCHAR(160) NOT NULL,
  `module_version` VARCHAR(32) NOT NULL,
  `checksum` CHAR(64) NOT NULL,
  `batch_no` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `started_at` DATETIME(3) NOT NULL,
  `finished_at` DATETIME(3) NULL,
  `error_code` VARCHAR(96) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_migration` (`module_key`, `migration_key`),
  KEY `idx_module_migration_batch` (`batch_no`, `status`),
  CONSTRAINT `chk_module_migration_status` CHECK (`status` IN (
    'applying','applied','rolled_back','failed'
  ))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_protected_resource` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `ownership` VARCHAR(32) NOT NULL,
  `provider_key` VARCHAR(160) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `retired_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_protected_resource_key` (`key`),
  KEY `idx_protected_resource_module` (`module_key`, `status`),
  CONSTRAINT `chk_protected_resource_ownership` CHECK (`ownership` IN ('tenant_owned', 'business_target_owned', 'shared_master', 'global_reference', 'platform_internal')),
  CONSTRAINT `chk_protected_resource_status` CHECK (`status` IN ('active', 'retired')),
  CONSTRAINT `chk_protected_resource_retired` CHECK ((`status` = 'retired' AND `retired_at` IS NOT NULL) OR `status` <> 'retired')
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_target_type` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `resolver_key` VARCHAR(160) NOT NULL,
  `catalog_provider_key` VARCHAR(160) NOT NULL,
  `id_format` VARCHAR(16) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_target_type_key` (`key`),
  KEY `idx_target_type_module` (`module_key`, `status`),
  CONSTRAINT `chk_target_type_id_format` CHECK (`id_format` IN ('decimal', 'uuid', 'ulid', 'string')),
  CONSTRAINT `chk_target_type_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `protected_resource_id` BIGINT UNSIGNED NOT NULL,
  `operation` VARCHAR(64) NOT NULL,
  `access_mode` VARCHAR(32) NOT NULL,
  `target_cardinality` VARCHAR(32) NOT NULL,
  `permission_match` VARCHAR(8) NOT NULL DEFAULT 'all',
  `audit_level` VARCHAR(32) NOT NULL DEFAULT 'deny_and_write',
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation` (`protected_resource_id`, `operation`),
  CONSTRAINT `fk_resource_operation_resource` FOREIGN KEY (`protected_resource_id`)
    REFERENCES `pa_protected_resource` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_resource_operation_access` CHECK (`access_mode` IN ('tenant_wide', 'rule_filtered', 'explicit_targets', 'global_reference_read', 'system_internal')),
  CONSTRAINT `chk_resource_operation_cardinality` CHECK (`target_cardinality` IN ('none', 'one_required', 'many_readable', 'aggregate_read', 'policy_publish', 'bulk_write')),
  CONSTRAINT `chk_resource_operation_permission_match` CHECK (`permission_match` IN ('all', 'any')),
  CONSTRAINT `chk_resource_operation_audit` CHECK (`audit_level` IN ('deny', 'write', 'deny_and_write', 'all')),
  CONSTRAINT `chk_resource_operation_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation_target_type` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_operation_id` BIGINT UNSIGNED NOT NULL,
  `target_type_id` BIGINT UNSIGNED NOT NULL,
  `target_role` VARCHAR(64) NOT NULL DEFAULT 'primary',
  `input_mode` VARCHAR(16) NOT NULL DEFAULT 'explicit',
  `policy_selection_permission_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation_target_type` (`resource_operation_id`, `target_role`, `target_type_id`),
  CONSTRAINT `fk_operation_target_operation` FOREIGN KEY (`resource_operation_id`)
    REFERENCES `pa_resource_operation` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_target_type` FOREIGN KEY (`target_type_id`)
    REFERENCES `pa_target_type` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_target_selection_permission` FOREIGN KEY (`policy_selection_permission_id`)
    REFERENCES `pa_permission` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_operation_target_input` CHECK (`input_mode` IN ('explicit', 'derived', 'either')),
  CONSTRAINT `chk_operation_target_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation_permission` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_operation_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation_permission` (`resource_operation_id`, `permission_id`),
  CONSTRAINT `fk_operation_permission_operation` FOREIGN KEY (`resource_operation_id`)
    REFERENCES `pa_resource_operation` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_permission_permission` FOREIGN KEY (`permission_id`)
    REFERENCES `pa_permission` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_data_condition_definition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `category` VARCHAR(32) NOT NULL,
  `target_mode` VARCHAR(32) NOT NULL,
  `config_schema_json` JSON NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_data_condition_key` (`key`),
  CONSTRAINT `chk_data_condition_category` CHECK (`category` IN ('tenant', 'self', 'department', 'selected', 'relation')),
  CONSTRAINT `chk_data_condition_target_mode` CHECK (`target_mode` IN ('none', 'department', 'resource')),
  CONSTRAINT `chk_data_condition_definition_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation_condition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_operation_id` BIGINT UNSIGNED NOT NULL,
  `condition_definition_id` BIGINT UNSIGNED NOT NULL,
  `selector_resource_key` VARCHAR(160) NULL,
  `selector_resource_key_norm` VARCHAR(160) GENERATED ALWAYS AS (COALESCE(`selector_resource_key`, '')) STORED,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation_condition` (`resource_operation_id`, `condition_definition_id`, `selector_resource_key_norm`),
  CONSTRAINT `fk_operation_condition_operation` FOREIGN KEY (`resource_operation_id`)
    REFERENCES `pa_resource_operation` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_condition_definition` FOREIGN KEY (`condition_definition_id`)
    REFERENCES `pa_data_condition_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_operation_condition_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_menu_definition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `scope` VARCHAR(16) NOT NULL,
  `parent_key` VARCHAR(160) NULL,
  `type` VARCHAR(16) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `route_name` VARCHAR(160) NULL,
  `route_path` VARCHAR(255) NULL,
  `component_key` VARCHAR(160) NULL,
  `icon` VARCHAR(64) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `required_permission_id` BIGINT UNSIGNED NULL,
  `client_keys_json` JSON NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_definition_key` (`key`),
  UNIQUE KEY `uk_menu_route_name` (`scope`, `route_name`),
  KEY `idx_menu_module` (`module_key`, `scope`, `status`, `sort_order`),
  CONSTRAINT `fk_menu_permission` FOREIGN KEY (`required_permission_id`)
    REFERENCES `pa_permission` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_menu_scope` CHECK (`scope` IN ('platform','tenant')),
  CONSTRAINT `chk_menu_type` CHECK (`type` IN ('group','page','link')),
  CONSTRAINT `chk_menu_status` CHECK (`status` IN ('active','retired')),
  CONSTRAINT `chk_menu_page` CHECK (`type` <> 'page' OR (
    `route_name` IS NOT NULL AND `component_key` IS NOT NULL
    AND `required_permission_id` IS NOT NULL
  ))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_definition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `setting_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `schema_json` JSON NOT NULL,
  `required_flag` TINYINT UNSIGNED NOT NULL,
  `secret_flag` TINYINT UNSIGNED NOT NULL,
  `deployment_scope_flag` TINYINT UNSIGNED NOT NULL,
  `tenant_scope_flag` TINYINT UNSIGNED NOT NULL,
  `target_scope_flag` TINYINT UNSIGNED NOT NULL,
  `target_resource_key` VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `target_operation` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `default_json` JSON NULL,
  `definition_digest` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_definition` (`module_key`, `setting_key`),
  CONSTRAINT `chk_setting_definition_required` CHECK (`required_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_secret` CHECK (`secret_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_deployment_scope` CHECK (`deployment_scope_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_tenant_scope` CHECK (`tenant_scope_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_target_scope` CHECK (`target_scope_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_target` CHECK ((
    `target_scope_flag` = 1 AND `target_resource_key` IS NOT NULL
    AND `target_operation` IS NOT NULL
  ) OR (
    `target_scope_flag` = 0 AND `target_resource_key` IS NULL
    AND `target_operation` IS NULL
  )),
  CONSTRAINT `chk_setting_definition_default` CHECK (`secret_flag` = 0 OR `default_json` IS NULL),
  CONSTRAINT `chk_setting_definition_status` CHECK (`status` IN ('active','retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_deployment_value` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `definition_id` BIGINT UNSIGNED NOT NULL,
  `value_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_json` JSON NULL,
  `ciphertext` VARBINARY(8192) NULL,
  `nonce` BINARY(24) NULL,
  `key_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NULL,
  `updated_by_operator_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_deployment_value` (`definition_id`),
  CONSTRAINT `fk_setting_deployment_definition` FOREIGN KEY (`definition_id`)
    REFERENCES `pa_setting_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_deployment_operator` FOREIGN KEY (`updated_by_operator_id`)
    REFERENCES `pa_platform_operator` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_setting_deployment_state` CHECK (`value_state` IN ('set','unset')),
  CONSTRAINT `chk_setting_deployment_interval` CHECK (`expires_at` IS NULL OR `expires_at` > `effective_at`),
  CONSTRAINT `chk_setting_deployment_storage` CHECK (
    (`value_state` = 'unset' AND `value_json` IS NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
    OR (`value_state` = 'set' AND ((`value_json` IS NOT NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
      OR (`value_json` IS NULL AND `ciphertext` IS NOT NULL AND OCTET_LENGTH(`ciphertext`) > 0 AND `nonce` IS NOT NULL AND `key_id` IS NOT NULL)))
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_tenant_value` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `definition_id` BIGINT UNSIGNED NOT NULL,
  `value_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_json` JSON NULL,
  `ciphertext` VARBINARY(8192) NULL,
  `nonce` BINARY(24) NULL,
  `key_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NULL,
  `updated_by_member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_tenant_value` (`tenant_id`, `definition_id`),
  KEY `idx_setting_tenant_definition` (`definition_id`),
  CONSTRAINT `fk_setting_tenant_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_tenant_definition` FOREIGN KEY (`definition_id`)
    REFERENCES `pa_setting_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_tenant_member` FOREIGN KEY (`updated_by_member_id`)
    REFERENCES `pa_tenant_member` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_setting_tenant_state` CHECK (`value_state` IN ('set','unset')),
  CONSTRAINT `chk_setting_tenant_interval` CHECK (`expires_at` IS NULL OR `expires_at` > `effective_at`),
  CONSTRAINT `chk_setting_tenant_storage` CHECK (
    (`value_state` = 'unset' AND `value_json` IS NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
    OR (`value_state` = 'set' AND ((`value_json` IS NOT NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
      OR (`value_json` IS NULL AND `ciphertext` IS NOT NULL AND OCTET_LENGTH(`ciphertext`) > 0 AND `nonce` IS NOT NULL AND `key_id` IS NOT NULL)))
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_target_value` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `definition_id` BIGINT UNSIGNED NOT NULL,
  `target_resource_key` VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_json` JSON NULL,
  `ciphertext` VARBINARY(8192) NULL,
  `nonce` BINARY(24) NULL,
  `key_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NULL,
  `updated_by_member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_target_value` (`tenant_id`, `definition_id`, `target_resource_key`, `target_id`),
  KEY `idx_setting_target_definition` (`definition_id`),
  CONSTRAINT `fk_setting_target_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_target_definition` FOREIGN KEY (`definition_id`)
    REFERENCES `pa_setting_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_target_member` FOREIGN KEY (`updated_by_member_id`)
    REFERENCES `pa_tenant_member` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_setting_target_state` CHECK (`value_state` IN ('set','unset')),
  CONSTRAINT `chk_setting_target_interval` CHECK (`expires_at` IS NULL OR `expires_at` > `effective_at`),
  CONSTRAINT `chk_setting_target_storage` CHECK (
    (`value_state` = 'unset' AND `value_json` IS NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
    OR (`value_state` = 'set' AND ((`value_json` IS NOT NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
      OR (`value_json` IS NULL AND `ciphertext` IS NOT NULL AND OCTET_LENGTH(`ciphertext`) > 0 AND `nonce` IS NOT NULL AND `key_id` IS NOT NULL)))
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
