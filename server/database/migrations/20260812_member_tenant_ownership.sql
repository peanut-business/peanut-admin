-- MT03 product-member tenant ownership: expand, backfill, verify, contract.
-- pa_member remains the application's consumer-member model; it is not Core TenantMember.

SET @pa_mt03_member_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_member', 'pa_member_tag', 'pa_member_tag_relation', 'pa_member_balance_log')
);
SET @pa_mt03_member_sql = IF(
  @pa_mt03_member_required_table_count = 5,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_member_requires_all_owned_tables_before_backfill`'
);
PREPARE pa_mt03_member_stmt FROM @pa_mt03_member_sql;
EXECUTE pa_mt03_member_stmt;
DEALLOCATE PREPARE pa_mt03_member_stmt;

SET @pa_mt03_member_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_member_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_member_sql = IF(
  @pa_mt03_member_active_tenant_count = 1 AND @pa_mt03_member_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_member_requires_exactly_one_active_tenant_for_backfill`'
);
PREPARE pa_mt03_member_stmt FROM @pa_mt03_member_sql;
EXECUTE pa_mt03_member_stmt;
DEALLOCATE PREPARE pa_mt03_member_stmt;

ALTER TABLE `pa_member` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_member_tag` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_member_tag_relation` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_member_balance_log` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_member` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_member_tag` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_member_tag_relation` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_member_balance_log` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_member_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_member` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_tag` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_tag_relation` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_balance_log` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_tag_relation` r
       LEFT JOIN `pa_member` m ON m.`id` = r.`member_id`
       LEFT JOIN `pa_member_tag` t ON t.`id` = r.`tag_id`
       WHERE m.`id` IS NULL OR t.`id` IS NULL OR r.`tenant_id` <> m.`tenant_id` OR r.`tenant_id` <> t.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_member_balance_log` l
       LEFT JOIN `pa_member` m ON m.`id` = l.`member_id`
       WHERE m.`id` IS NULL OR l.`tenant_id` <> m.`tenant_id`)
);
SET @pa_mt03_member_sql = IF(
  @pa_mt03_member_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_member_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_member_stmt FROM @pa_mt03_member_sql;
EXECUTE pa_mt03_member_stmt;
DEALLOCATE PREPARE pa_mt03_member_stmt;

ALTER TABLE `pa_member`
  DROP INDEX `uk_sn`,
  DROP INDEX `uk_mobile_nonempty`,
  ADD COLUMN `account_unique` VARCHAR(50) GENERATED ALWAYS AS (NULLIF(`account`, '')) STORED AFTER `account`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_tenant_sn` (`tenant_id`, `sn`),
  ADD UNIQUE KEY `uk_member_tenant_account` (`tenant_id`, `account_unique`),
  ADD UNIQUE KEY `uk_member_tenant_mobile` (`tenant_id`, `mobile_unique`),
  ADD KEY `idx_member_tenant_status_channel` (`tenant_id`, `status`, `channel`, `id`),
  ADD CONSTRAINT `fk_member_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_member_tag`
  DROP INDEX `uk_name`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_tag_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_tag_tenant_name` (`tenant_id`, `name`),
  ADD KEY `idx_member_tag_tenant_live` (`tenant_id`, `delete_time`, `id`),
  ADD CONSTRAINT `fk_member_tag_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_member_tag_relation`
  DROP INDEX `uk_member_tag`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_tag_relation_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_tag_relation_tenant_pair` (`tenant_id`, `member_id`, `tag_id`),
  ADD KEY `idx_member_tag_relation_tenant_tag` (`tenant_id`, `tag_id`, `member_id`),
  ADD CONSTRAINT `fk_member_tag_relation_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_member_tag_relation_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_member_tag_relation_tag` FOREIGN KEY (`tenant_id`, `tag_id`) REFERENCES `pa_member_tag` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_member_balance_log`
  DROP INDEX `uk_sn`,
  ADD COLUMN `source_sn_unique` VARCHAR(255) GENERATED ALWAYS AS (NULLIF(`source_sn`, '')) STORED AFTER `source_sn`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_balance_log_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_balance_log_tenant_sn` (`tenant_id`, `sn`),
  ADD UNIQUE KEY `uk_member_balance_log_tenant_source` (`tenant_id`, `source_sn_unique`),
  ADD KEY `idx_member_balance_log_tenant_member_time` (`tenant_id`, `member_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_member_balance_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_member_balance_log_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;
