-- MT03 per-Tenant transaction policy ownership.
-- Other pa_config types remain instance-owned unless their own domain explicitly migrates them.

SET @pa_mt03_transaction_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('pa_tenant', 'pa_config')
);
SET @pa_mt03_transaction_sql = IF(
  @pa_mt03_transaction_required_table_count = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_transaction_requires_tenant_and_config_before_migration`'
);
PREPARE pa_mt03_transaction_stmt FROM @pa_mt03_transaction_sql;
EXECUTE pa_mt03_transaction_stmt;
DEALLOCATE PREPARE pa_mt03_transaction_stmt;

SET @pa_mt03_transaction_invalid_legacy_rows = (
  SELECT COUNT(*) FROM `pa_config`
  WHERE `type` = 'transaction'
    AND (`name` NOT IN (
      'cancel_unpaid_orders', 'cancel_unpaid_orders_times',
      'verification_orders', 'verification_orders_times'
    ) OR `value` NOT REGEXP '^[0-9]+$'
      OR (`name` IN ('cancel_unpaid_orders', 'verification_orders') AND CAST(`value` AS UNSIGNED) NOT IN (0, 1))
      OR (`name` IN ('cancel_unpaid_orders_times', 'verification_orders_times') AND CAST(`value` AS UNSIGNED) < 1))
);
SET @pa_mt03_transaction_sql = IF(
  @pa_mt03_transaction_invalid_legacy_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_transaction_invalid_legacy_policy`'
);
PREPARE pa_mt03_transaction_stmt FROM @pa_mt03_transaction_sql;
EXECUTE pa_mt03_transaction_stmt;
DEALLOCATE PREPARE pa_mt03_transaction_stmt;

CREATE TABLE `pa_transaction_setting` (
  `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`                    BIGINT UNSIGNED NOT NULL,
  `cancel_unpaid_orders`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `cancel_unpaid_orders_times`   INT UNSIGNED NOT NULL DEFAULT 30,
  `verification_orders`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `verification_orders_times`    INT UNSIGNED NOT NULL DEFAULT 24,
  `create_time`                  INT UNSIGNED NULL DEFAULT NULL,
  `update_time`                  INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transaction_setting_tenant` (`tenant_id`),
  CONSTRAINT `chk_transaction_setting_cancel_mode` CHECK (`cancel_unpaid_orders` IN (0, 1)),
  CONSTRAINT `chk_transaction_setting_cancel_time` CHECK (`cancel_unpaid_orders_times` > 0),
  CONSTRAINT `chk_transaction_setting_verify_mode` CHECK (`verification_orders` IN (0, 1)),
  CONSTRAINT `chk_transaction_setting_verify_time` CHECK (`verification_orders_times` > 0),
  CONSTRAINT `fk_transaction_setting_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tenant交易运营政策';

INSERT INTO `pa_transaction_setting` (
  `tenant_id`, `cancel_unpaid_orders`, `cancel_unpaid_orders_times`,
  `verification_orders`, `verification_orders_times`, `create_time`, `update_time`
)
SELECT
  t.`id`,
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='cancel_unpaid_orders' LIMIT 1), 1),
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='cancel_unpaid_orders_times' LIMIT 1), 30),
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='verification_orders' LIMIT 1), 1),
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='verification_orders_times' LIMIT 1), 24),
  UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `pa_tenant` t;

SET @pa_mt03_transaction_invalid_owned_rows = (
  SELECT COUNT(*) FROM `pa_transaction_setting` s
  LEFT JOIN `pa_tenant` t ON t.`id` = s.`tenant_id`
  WHERE t.`id` IS NULL OR s.`tenant_id` = 0
    OR s.`cancel_unpaid_orders` NOT IN (0, 1)
    OR s.`cancel_unpaid_orders_times` < 1
    OR s.`verification_orders` NOT IN (0, 1)
    OR s.`verification_orders_times` < 1
);
SET @pa_mt03_transaction_missing_tenants = (
  SELECT COUNT(*) FROM `pa_tenant` t
  LEFT JOIN `pa_transaction_setting` s ON s.`tenant_id` = t.`id`
  WHERE s.`tenant_id` IS NULL
);
SET @pa_mt03_transaction_sql = IF(
  @pa_mt03_transaction_invalid_owned_rows = 0 AND @pa_mt03_transaction_missing_tenants = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_transaction_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_transaction_stmt FROM @pa_mt03_transaction_sql;
EXECUTE pa_mt03_transaction_stmt;
DEALLOCATE PREPARE pa_mt03_transaction_stmt;

DELETE FROM `pa_config` WHERE `type` = 'transaction';
