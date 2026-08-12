-- MT03 file tenant ownership: expand, backfill, verify, contract.
-- The MT02 bootstrap slice owns pa_tenant creation. This migration refuses to guess a Tenant.

SET @pa_mt03_file_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_file_sql = IF(
  @pa_mt03_file_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_file_backfill`'
);
PREPARE pa_mt03_file_stmt FROM @pa_mt03_file_sql;
EXECUTE pa_mt03_file_stmt;
DEALLOCATE PREPARE pa_mt03_file_stmt;

SET @pa_mt03_file_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_file_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_file_sql = IF(
  @pa_mt03_file_active_tenant_count = 1 AND @pa_mt03_file_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_file_backfill`'
);
PREPARE pa_mt03_file_stmt FROM @pa_mt03_file_sql;
EXECUTE pa_mt03_file_stmt;
DEALLOCATE PREPARE pa_mt03_file_stmt;

ALTER TABLE `pa_file_cate` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_file` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_file_cate` SET `tenant_id` = @pa_mt03_file_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_file` SET `tenant_id` = @pa_mt03_file_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_file_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_file_cate` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_file` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_file` f
       JOIN `pa_file_cate` c ON c.`id` = f.`cid`
       WHERE f.`cid` > 0 AND f.`tenant_id` <> c.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_file_cate` c
       JOIN `pa_file_cate` p ON p.`id` = c.`pid`
       WHERE c.`pid` > 0 AND (c.`tenant_id` <> p.`tenant_id` OR c.`type` <> p.`type`))
);
SET @pa_mt03_file_sql = IF(
  @pa_mt03_file_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_file_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_file_stmt FROM @pa_mt03_file_sql;
EXECUTE pa_mt03_file_stmt;
DEALLOCATE PREPARE pa_mt03_file_stmt;

ALTER TABLE `pa_file_cate`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_file_cate_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_file_cate_tenant_type_parent` (`tenant_id`, `type`, `pid`, `id`),
  ADD CONSTRAINT `fk_file_cate_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_file`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_file_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_file_tenant_type_cid_source` (`tenant_id`, `type`, `cid`, `source`, `id`),
  ADD CONSTRAINT `fk_file_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;
