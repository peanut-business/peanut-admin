-- MT03 decoration Tabbar Tenant ownership.
-- Legacy rows and the legacy global style belong to the completed default Tenant bootstrap owner.

SET @pa_mt03_tabbar_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_default_tenant_bootstrap', 'pa_config', 'pa_decorate_tabbar')
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_required_tables = 4,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_tabbar_requires_tenant_bootstrap_config_and_tabbar_tables`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

SET @pa_mt03_tabbar_bootstrap_count = (
  SELECT COUNT(*)
  FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed'
);
SET @pa_mt03_tabbar_default_tenant_id = (
  SELECT b.`tenant_id`
  FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed'
  LIMIT 1
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_bootstrap_count = 1 AND @pa_mt03_tabbar_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_tabbar_requires_completed_default_tenant_bootstrap_owner`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

SET @pa_mt03_tabbar_legacy_style_count = (
  SELECT COUNT(*) FROM `pa_config` WHERE `type` = 'tabbar'
);
SET @pa_mt03_tabbar_invalid_style_count = (
  SELECT COUNT(*) FROM `pa_config`
  WHERE `type` = 'tabbar'
    AND (`name` <> 'style' OR NOT JSON_VALID(`value`) OR JSON_TYPE(`value`) <> 'OBJECT')
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_legacy_style_count <= 1 AND @pa_mt03_tabbar_invalid_style_count = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_tabbar_invalid_legacy_style_preflight_failed`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

ALTER TABLE `pa_decorate_tabbar`
  ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_decorate_tabbar`
SET `tenant_id` = @pa_mt03_tabbar_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt03_tabbar_invalid_owned_rows = (
  SELECT COUNT(*)
  FROM `pa_decorate_tabbar` d
  LEFT JOIN `pa_tenant` t ON t.`id` = d.`tenant_id`
  WHERE d.`tenant_id` IS NULL OR d.`tenant_id` = 0 OR t.`id` IS NULL
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_invalid_owned_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_tabbar_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

ALTER TABLE `pa_decorate_tabbar`
  DROP INDEX `uk_decorate_tabbar_position`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_decorate_tabbar_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_decorate_tabbar_tenant_position` (`tenant_id`, `position`),
  ADD CONSTRAINT `fk_decorate_tabbar_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

CREATE TABLE `pa_decorate_tabbar_setting` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `style` LONGTEXT NOT NULL,
  `create_time` INT UNSIGNED NULL DEFAULT NULL,
  `update_time` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_decorate_tabbar_setting_tenant` (`tenant_id`),
  CONSTRAINT `chk_decorate_tabbar_setting_style` CHECK (JSON_VALID(`style`) AND JSON_TYPE(`style`) = 'OBJECT'),
  CONSTRAINT `fk_decorate_tabbar_setting_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant 装修 Tabbar 样式';

INSERT INTO `pa_decorate_tabbar_setting` (`tenant_id`, `style`, `create_time`, `update_time`)
SELECT
  @pa_mt03_tabbar_default_tenant_id,
  COALESCE(
    (SELECT `value` FROM `pa_config` WHERE `type` = 'tabbar' AND `name` = 'style' LIMIT 1),
    '{"default_color":"#666666","selected_color":"#2F80ED"}'
  ),
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP();

DELETE FROM `pa_config` WHERE `type` = 'tabbar';
