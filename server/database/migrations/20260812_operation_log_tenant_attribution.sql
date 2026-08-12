-- MT03 tenant attribution for the tenant-only operation audit stream.
-- PlatformOperator audit requires a separate PM01 schema and must not use tenant_id 0/NULL.

SET @pa_mt03_audit_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_audit_sql = IF(
  @pa_mt03_audit_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_operation_log_backfill`'
);
PREPARE pa_mt03_audit_stmt FROM @pa_mt03_audit_sql;
EXECUTE pa_mt03_audit_stmt;
DEALLOCATE PREPARE pa_mt03_audit_stmt;

SET @pa_mt03_audit_legacy_rows = (SELECT COUNT(*) FROM `pa_operation_log`);
SET @pa_mt03_audit_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_audit_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_audit_sql = IF(
  @pa_mt03_audit_legacy_rows = 0
    OR (@pa_mt03_audit_active_tenant_count = 1 AND @pa_mt03_audit_default_tenant_id > 0),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_operation_log_backfill`'
);
PREPARE pa_mt03_audit_stmt FROM @pa_mt03_audit_sql;
EXECUTE pa_mt03_audit_stmt;
DEALLOCATE PREPARE pa_mt03_audit_stmt;

ALTER TABLE `pa_operation_log`
  ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD COLUMN `request_id` VARCHAR(128) NOT NULL DEFAULT '' AFTER `method`;

UPDATE `pa_operation_log`
SET `tenant_id` = @pa_mt03_audit_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt03_audit_invalid_rows = (
  SELECT COUNT(*) FROM `pa_operation_log` WHERE `tenant_id` IS NULL OR `tenant_id` = 0
);
SET @pa_mt03_audit_sql = IF(
  @pa_mt03_audit_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_operation_log_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_audit_stmt FROM @pa_mt03_audit_sql;
EXECUTE pa_mt03_audit_stmt;
DEALLOCATE PREPARE pa_mt03_audit_stmt;

ALTER TABLE `pa_operation_log`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_operation_log_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_operation_log_tenant_created` (`tenant_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_operation_log_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;
