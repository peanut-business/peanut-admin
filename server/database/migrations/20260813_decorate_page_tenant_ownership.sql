-- MT02 decoration page Tenant ownership: preflight, expand, backfill, verify, and contract.
-- Legacy pages belong to the completed default-Tenant bootstrap ledger entry.

SET @pa_mt02_decorate_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_default_tenant_bootstrap', 'pa_decorate_page')
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_required_tables = 3,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_decorate_requires_tenant_bootstrap_and_page_tables`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

SET @pa_mt02_decorate_bootstrap_count = (
  SELECT COUNT(*)
  FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed'
);
SET @pa_mt02_decorate_default_tenant_id = (
  SELECT b.`tenant_id`
  FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed'
  LIMIT 1
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_bootstrap_count = 1 AND @pa_mt02_decorate_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_decorate_requires_completed_default_tenant_bootstrap_owner`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

SET @pa_mt02_decorate_duplicate_type_count = (
  SELECT COUNT(*) FROM (
    SELECT `type`
    FROM `pa_decorate_page`
    GROUP BY `type`
    HAVING COUNT(*) > 1
  ) duplicate_types
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_duplicate_type_count = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_decorate_legacy_duplicate_type_preflight_failed`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

ALTER TABLE `pa_decorate_page`
  ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_decorate_page`
SET `tenant_id` = @pa_mt02_decorate_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt02_decorate_invalid_rows = (
  SELECT COUNT(*)
  FROM `pa_decorate_page` p
  LEFT JOIN `pa_tenant` t ON t.`id` = p.`tenant_id`
  WHERE p.`tenant_id` IS NULL OR p.`tenant_id` = 0 OR t.`id` IS NULL
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_decorate_tenant_backfill_verification_failed`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

ALTER TABLE `pa_decorate_page`
  DROP INDEX `uk_decorate_page_type`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_decorate_page_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_decorate_page_tenant_type` (`tenant_id`, `type`),
  ADD CONSTRAINT `fk_decorate_page_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;
