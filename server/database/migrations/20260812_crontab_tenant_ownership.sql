-- MT03 crontab tenant ownership: expand, backfill, verify, contract.
-- The MT02 bootstrap slice owns pa_tenant creation. This migration refuses to guess a Tenant.

SET @pa_mt03_crontab_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_crontab_sql = IF(
  @pa_mt03_crontab_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_crontab_backfill`'
);
PREPARE pa_mt03_crontab_stmt FROM @pa_mt03_crontab_sql;
EXECUTE pa_mt03_crontab_stmt;
DEALLOCATE PREPARE pa_mt03_crontab_stmt;

SET @pa_mt03_crontab_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_crontab_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_crontab_sql = IF(
  @pa_mt03_crontab_active_tenant_count = 1 AND @pa_mt03_crontab_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_crontab_backfill`'
);
PREPARE pa_mt03_crontab_stmt FROM @pa_mt03_crontab_sql;
EXECUTE pa_mt03_crontab_stmt;
DEALLOCATE PREPARE pa_mt03_crontab_stmt;

ALTER TABLE `pa_crontab` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_crontab` SET `tenant_id` = @pa_mt03_crontab_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_crontab_invalid_rows = (
  SELECT COUNT(*) FROM `pa_crontab` WHERE `tenant_id` IS NULL OR `tenant_id` = 0
);
SET @pa_mt03_crontab_sql = IF(
  @pa_mt03_crontab_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_crontab_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_crontab_stmt FROM @pa_mt03_crontab_sql;
EXECUTE pa_mt03_crontab_stmt;
DEALLOCATE PREPARE pa_mt03_crontab_stmt;

ALTER TABLE `pa_crontab`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_crontab_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_crontab_tenant_status_last` (`tenant_id`, `status`, `last_time`, `id`),
  ADD CONSTRAINT `fk_crontab_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;
