-- MT03 notification tenant ownership: preflight, expand, backfill, verify, contract.
-- Provider/channel credentials remain application-owned and are deliberately absent here.

SET @pa_mt03_notice_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_notice_scene', 'pa_notice_template', 'pa_notice_log')
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_required_table_count = 4,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_requires_tenant_and_notice_tables`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

SET @pa_mt03_notice_active_tenant_count = (
  SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active'
);
SET @pa_mt03_notice_default_tenant_count = (
  SELECT COUNT(*) FROM `pa_tenant` WHERE `code` = 'default' AND `status` = 'active'
);
SET @pa_mt03_notice_default_tenant_id = (
  SELECT `id` FROM `pa_tenant` WHERE `code` = 'default' AND `status` = 'active' LIMIT 1
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_active_tenant_count = 1
    AND @pa_mt03_notice_default_tenant_count = 1
    AND @pa_mt03_notice_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_requires_exactly_one_active_default_tenant`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

SET @pa_mt03_notice_duplicate_business_keys = (
  SELECT
    (SELECT COUNT(*) FROM (
      SELECT `code` FROM `pa_notice_scene` GROUP BY `code` HAVING COUNT(*) > 1
    ) AS duplicate_scene_codes)
    + (SELECT COUNT(*) FROM (
      SELECT `code` FROM `pa_notice_template` GROUP BY `code` HAVING COUNT(*) > 1
    ) AS duplicate_template_codes)
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_duplicate_business_keys = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_duplicate_business_keys_require_resolution`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

ALTER TABLE `pa_notice_scene` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_notice_template` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_notice_log` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_notice_scene` SET `tenant_id` = @pa_mt03_notice_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_notice_template` SET `tenant_id` = @pa_mt03_notice_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_notice_log` SET `tenant_id` = @pa_mt03_notice_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_notice_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_notice_scene` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_notice_template` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_notice_log` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_notice_log` l
       JOIN `pa_notice_scene` s ON s.`id` = l.`scene_id`
       WHERE l.`scene_id` > 0 AND l.`tenant_id` <> s.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_notice_log` l
       JOIN `pa_notice_template` t ON t.`id` = l.`template_id`
       WHERE l.`template_id` > 0 AND l.`tenant_id` <> t.`tenant_id`)
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

ALTER TABLE `pa_notice_scene`
  DROP INDEX `uk_code`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_notice_scene_tenant_code` (`tenant_id`, `code`),
  ADD UNIQUE KEY `uk_notice_scene_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_notice_scene_tenant_sms` (`tenant_id`, `sms_status`, `id`),
  ADD CONSTRAINT `fk_notice_scene_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_notice_template`
  DROP INDEX `idx_code`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_notice_template_tenant_code` (`tenant_id`, `code`),
  ADD UNIQUE KEY `uk_notice_template_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_notice_template_tenant_channel` (`tenant_id`, `channel`, `is_disable`, `id`),
  ADD CONSTRAINT `fk_notice_template_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_notice_log`
  DROP INDEX `idx_scene_receiver`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_notice_log_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_notice_log_tenant_scene_receiver` (`tenant_id`, `scene_id`, `channel`, `receiver`, `status`, `send_time`, `id`),
  ADD KEY `idx_notice_log_tenant_list` (`tenant_id`, `status`, `channel`, `send_time`, `id`),
  ADD CONSTRAINT `fk_notice_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;
