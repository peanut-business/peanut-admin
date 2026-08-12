-- MT03 hot-search term tenant ownership: expand, backfill, verify, contract.
-- hot_search.status remains instance-level in pa_config; only pa_hot_search terms are Tenant-owned.

SET @pa_mt03_hot_search_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_hot_search_sql = IF(
  @pa_mt03_hot_search_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_hot_search_backfill`'
);
PREPARE pa_mt03_hot_search_stmt FROM @pa_mt03_hot_search_sql;
EXECUTE pa_mt03_hot_search_stmt;
DEALLOCATE PREPARE pa_mt03_hot_search_stmt;

SET @pa_mt03_hot_search_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_hot_search_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_hot_search_sql = IF(
  @pa_mt03_hot_search_active_tenant_count = 1 AND @pa_mt03_hot_search_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_hot_search_backfill`'
);
PREPARE pa_mt03_hot_search_stmt FROM @pa_mt03_hot_search_sql;
EXECUTE pa_mt03_hot_search_stmt;
DEALLOCATE PREPARE pa_mt03_hot_search_stmt;

ALTER TABLE `pa_hot_search` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_hot_search` SET `tenant_id` = @pa_mt03_hot_search_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_hot_search_invalid_rows = (
  SELECT COUNT(*) FROM `pa_hot_search` WHERE `tenant_id` IS NULL OR `tenant_id` = 0
);
SET @pa_mt03_hot_search_sql = IF(
  @pa_mt03_hot_search_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_hot_search_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_hot_search_stmt FROM @pa_mt03_hot_search_sql;
EXECUTE pa_mt03_hot_search_stmt;
DEALLOCATE PREPARE pa_mt03_hot_search_stmt;

ALTER TABLE `pa_hot_search`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_hot_search_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_hot_search_tenant_sort` (`tenant_id`, `sort`, `id`),
  ADD CONSTRAINT `fk_hot_search_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;
