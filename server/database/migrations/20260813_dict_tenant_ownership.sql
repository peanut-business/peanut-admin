-- MT02 dictionary Tenant ownership: expand, backfill, verify, and contract.
-- Legacy rows belong to the completed default-Tenant bootstrap ledger entry.

SET @pa_mt02_dict_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_default_tenant_bootstrap', 'pa_dict_type', 'pa_dict_data')
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_required_tables = 4,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_dict_requires_tenant_bootstrap_and_dictionary_tables`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

SET @pa_mt02_dict_bootstrap_count = (
  SELECT COUNT(*)
  FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed'
);
SET @pa_mt02_dict_default_tenant_id = (
  SELECT b.`tenant_id`
  FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed'
  LIMIT 1
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_bootstrap_count = 1 AND @pa_mt02_dict_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_dict_requires_completed_default_tenant_bootstrap`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

SET @pa_mt02_dict_orphan_count = (
  SELECT COUNT(*)
  FROM `pa_dict_data` d
  LEFT JOIN `pa_dict_type` t ON t.`id` = d.`type_id`
  WHERE t.`id` IS NULL
);
SET @pa_mt02_dict_duplicate_type_count = (
  SELECT COUNT(*) FROM (
    SELECT `type`
    FROM `pa_dict_type`
    WHERE `delete_time` IS NULL
    GROUP BY `type`
    HAVING COUNT(*) > 1
  ) duplicate_types
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_orphan_count = 0 AND @pa_mt02_dict_duplicate_type_count = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_dict_legacy_integrity_preflight_failed`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

ALTER TABLE `pa_dict_type` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_dict_data` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_dict_type`
SET `tenant_id` = @pa_mt02_dict_default_tenant_id
WHERE `tenant_id` IS NULL;
UPDATE `pa_dict_data` d
JOIN `pa_dict_type` t ON t.`id` = d.`type_id`
SET d.`tenant_id` = t.`tenant_id`, d.`type_value` = t.`type`
WHERE d.`tenant_id` IS NULL OR d.`type_value` <> t.`type`;

SET @pa_mt02_dict_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_dict_type` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_dict_data` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*)
       FROM `pa_dict_data` d
       JOIN `pa_dict_type` t ON t.`id` = d.`type_id`
       WHERE d.`tenant_id` <> t.`tenant_id` OR d.`type_value` <> t.`type`)
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_dict_tenant_backfill_verification_failed`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

ALTER TABLE `pa_dict_type`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD COLUMN `active_type` VARCHAR(100)
    GENERATED ALWAYS AS (CASE WHEN `delete_time` IS NULL THEN `type` ELSE NULL END) STORED,
  ADD UNIQUE KEY `uk_dict_type_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_dict_type_tenant_active_type` (`tenant_id`, `active_type`),
  ADD KEY `idx_dict_type_tenant_status_name` (`tenant_id`, `is_disable`, `name`, `id`),
  ADD CONSTRAINT `fk_dict_type_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_dict_data`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_dict_data_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_dict_data_tenant_type_status_sort`
    (`tenant_id`, `type_id`, `is_disable`, `sort`, `id`),
  ADD CONSTRAINT `fk_dict_data_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_dict_data_tenant_type`
    FOREIGN KEY (`tenant_id`, `type_id`)
    REFERENCES `pa_dict_type` (`tenant_id`, `id`) ON DELETE RESTRICT;
