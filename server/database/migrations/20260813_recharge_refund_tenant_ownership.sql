-- MT03 recharge/refund transaction Tenant ownership.
-- pa_payment_scene and pa_config(type=pay/recharge) remain instance-owned.

SET @pa_mt03_finance_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_default_tenant_bootstrap', 'pa_member', 'pa_recharge_order', 'pa_refund_record', 'pa_refund_log')
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_required_table_count = 6,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_requires_all_owned_tables_before_backfill`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

SET @pa_mt03_finance_member_owner_ready = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND COLUMN_NAME = 'tenant_id' AND IS_NULLABLE = 'NO'
);
SET @pa_mt03_finance_member_identity_ready = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND INDEX_NAME = 'uk_member_tenant_id' AND NON_UNIQUE = 0
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_member_owner_ready = 1 AND @pa_mt03_finance_member_identity_ready = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_requires_member_tenant_ownership_before_backfill`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

SET @pa_mt03_finance_legacy_rows = (
  (SELECT COUNT(*) FROM `pa_recharge_order`)
  + (SELECT COUNT(*) FROM `pa_refund_record`)
  + (SELECT COUNT(*) FROM `pa_refund_log`)
);
SET @pa_mt03_finance_backfill_tenant_count = (
  SELECT COUNT(*) FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed' AND t.`status` = 'active'
);
SET @pa_mt03_finance_default_tenant_id = (
  SELECT b.`tenant_id` FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`id` = 1 AND b.`status` = 'completed' AND t.`status` = 'active'
  LIMIT 1
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_legacy_rows = 0
    OR (@pa_mt03_finance_backfill_tenant_count = 1 AND @pa_mt03_finance_default_tenant_id > 0),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_requires_completed_default_tenant_for_backfill`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

SET @pa_mt03_finance_invalid_legacy_rows = (
  (SELECT COUNT(*) FROM `pa_recharge_order` o LEFT JOIN `pa_member` m ON m.`id` = o.`user_id` WHERE m.`id` IS NULL)
  + (SELECT COUNT(*) FROM `pa_refund_record` r
     LEFT JOIN `pa_recharge_order` o ON o.`id` = r.`order_id`
     LEFT JOIN `pa_member` m ON m.`id` = r.`user_id`
     WHERE o.`id` IS NULL OR m.`id` IS NULL OR o.`user_id` <> r.`user_id`)
  + (SELECT COUNT(*) FROM `pa_refund_log` l
     LEFT JOIN `pa_refund_record` r ON r.`id` = l.`record_id`
     LEFT JOIN `pa_member` m ON m.`id` = l.`user_id`
     WHERE r.`id` IS NULL OR m.`id` IS NULL OR r.`user_id` <> l.`user_id`)
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_invalid_legacy_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_legacy_relationship_verification_failed`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

ALTER TABLE `pa_recharge_order` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_refund_record` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_refund_log` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_recharge_order` SET `tenant_id` = @pa_mt03_finance_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_refund_record` SET `tenant_id` = @pa_mt03_finance_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_refund_log` SET `tenant_id` = @pa_mt03_finance_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_finance_invalid_rows = (
  (SELECT COUNT(*) FROM `pa_recharge_order` o
   JOIN `pa_member` m ON m.`id` = o.`user_id`
   WHERE o.`tenant_id` IS NULL OR o.`tenant_id` = 0 OR o.`tenant_id` <> m.`tenant_id`)
  + (SELECT COUNT(*) FROM `pa_refund_record` r
     JOIN `pa_recharge_order` o ON o.`id` = r.`order_id`
     JOIN `pa_member` m ON m.`id` = r.`user_id`
     WHERE r.`tenant_id` IS NULL OR r.`tenant_id` = 0
       OR r.`tenant_id` <> o.`tenant_id` OR r.`tenant_id` <> m.`tenant_id`)
  + (SELECT COUNT(*) FROM `pa_refund_log` l
     JOIN `pa_refund_record` r ON r.`id` = l.`record_id`
     JOIN `pa_member` m ON m.`id` = l.`user_id`
     WHERE l.`tenant_id` IS NULL OR l.`tenant_id` = 0
       OR l.`tenant_id` <> r.`tenant_id` OR l.`tenant_id` <> m.`tenant_id`)
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

ALTER TABLE `pa_recharge_order`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_recharge_order_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_recharge_order_tenant_member_time` (`tenant_id`, `user_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_recharge_order_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_recharge_order_member` FOREIGN KEY (`tenant_id`, `user_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_refund_record`
  DROP INDEX `uk_order_type_order_id`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY COLUMN `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD UNIQUE KEY `uk_refund_record_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_refund_record_tenant_order` (`tenant_id`, `order_type`, `order_id`),
  ADD KEY `idx_refund_record_tenant_status_time` (`tenant_id`, `refund_status`, `create_time`, `id`),
  ADD CONSTRAINT `fk_refund_record_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_record_member` FOREIGN KEY (`tenant_id`, `user_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_record_order` FOREIGN KEY (`tenant_id`, `order_id`) REFERENCES `pa_recharge_order` (`tenant_id`, `id`) ON DELETE RESTRICT;

SET @pa_mt03_finance_cross_tenant_order_duplicates = (
  SELECT COUNT(*) FROM (
    SELECT `order_type`, `order_id`
    FROM `pa_refund_record`
    GROUP BY `order_type`, `order_id`
    HAVING COUNT(*) > 1
  ) duplicates
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_cross_tenant_order_duplicates = 0,
  'ALTER TABLE `pa_refund_record` ADD UNIQUE KEY `uk_refund_record_order_global` (`order_type`,`order_id`)',
  'SELECT * FROM `pa_mt03_finance_refund_order_identity_must_remain_globally_unique`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

ALTER TABLE `pa_refund_log`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD UNIQUE KEY `uk_refund_log_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_refund_log_tenant_record_time` (`tenant_id`, `record_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_refund_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_log_member` FOREIGN KEY (`tenant_id`, `user_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_log_record` FOREIGN KEY (`tenant_id`, `record_id`) REFERENCES `pa_refund_record` (`tenant_id`, `id`) ON DELETE RESTRICT;
