-- MT03 Tenant-owned customer-service contact settings.
-- The public customer-service page remains owned by the Decoration Runtime.

SET @pa_mt03_customer_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('pa_tenant', 'pa_config', 'pa_file')
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_required_table_count = 3,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_requires_tenant_config_and_file`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

SET @pa_mt03_customer_file_owner_ready = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_file'
    AND COLUMN_NAME = 'tenant_id' AND IS_NULLABLE = 'NO'
);
SET @pa_mt03_customer_file_identity_ready = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_file'
    AND INDEX_NAME = 'uk_file_tenant_id' AND NON_UNIQUE = 0
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_file_owner_ready = 1 AND @pa_mt03_customer_file_identity_ready = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_requires_file_tenant_ownership`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

SET @pa_mt03_customer_invalid_legacy_rows = (
  SELECT COUNT(*) FROM `pa_config`
  WHERE `type` = 'customer_service'
    AND (`name` NOT IN ('qr_code', 'wechat', 'phone', 'service_time') OR `value` IS NULL)
);
SET @pa_mt03_customer_legacy_qr_uri = (
  SELECT `value` FROM `pa_config`
  WHERE `type` = 'customer_service' AND `name` = 'qr_code'
  LIMIT 1
);
SET @pa_mt03_customer_legacy_qr_owner_count = (
  SELECT COUNT(*) FROM `pa_file`
  WHERE `uri` = @pa_mt03_customer_legacy_qr_uri AND `delete_time` IS NULL
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_invalid_legacy_rows = 0
    AND (COALESCE(@pa_mt03_customer_legacy_qr_uri, '') = '' OR @pa_mt03_customer_legacy_qr_owner_count = 1),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_invalid_legacy_data`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

CREATE TABLE `pa_customer_service_setting` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `qr_file_id`   INT UNSIGNED NULL DEFAULT NULL,
  `wechat`       VARCHAR(100) NOT NULL DEFAULT '',
  `phone`        VARCHAR(50) NOT NULL DEFAULT '',
  `service_time` VARCHAR(100) NOT NULL DEFAULT '',
  `create_time`  INT UNSIGNED NULL DEFAULT NULL,
  `update_time`  INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer_service_setting_tenant` (`tenant_id`),
  KEY `idx_customer_service_setting_tenant_qr` (`tenant_id`, `qr_file_id`),
  CONSTRAINT `fk_customer_service_setting_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_service_setting_qr_file` FOREIGN KEY (`tenant_id`, `qr_file_id`) REFERENCES `pa_file` (`tenant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tenant客服联系方式';

INSERT INTO `pa_customer_service_setting` (
  `tenant_id`, `qr_file_id`, `wechat`, `phone`, `service_time`, `create_time`, `update_time`
)
SELECT
  t.`id`,
  CASE WHEN f.`tenant_id` = t.`id` THEN f.`id` ELSE NULL END,
  COALESCE((SELECT c.`value` FROM `pa_config` c WHERE c.`type`='customer_service' AND c.`name`='wechat' LIMIT 1), ''),
  COALESCE((SELECT c.`value` FROM `pa_config` c WHERE c.`type`='customer_service' AND c.`name`='phone' LIMIT 1), ''),
  COALESCE((SELECT c.`value` FROM `pa_config` c WHERE c.`type`='customer_service' AND c.`name`='service_time' LIMIT 1), ''),
  UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `pa_tenant` t
LEFT JOIN `pa_file` f
  ON f.`uri` = @pa_mt03_customer_legacy_qr_uri AND f.`delete_time` IS NULL;

SET @pa_mt03_customer_invalid_owned_rows = (
  SELECT COUNT(*) FROM `pa_customer_service_setting` s
  LEFT JOIN `pa_tenant` t ON t.`id` = s.`tenant_id`
  LEFT JOIN `pa_file` f ON f.`tenant_id` = s.`tenant_id` AND f.`id` = s.`qr_file_id`
  WHERE t.`id` IS NULL OR s.`tenant_id` = 0
    OR (s.`qr_file_id` IS NOT NULL AND f.`id` IS NULL)
);
SET @pa_mt03_customer_missing_tenants = (
  SELECT COUNT(*) FROM `pa_tenant` t
  LEFT JOIN `pa_customer_service_setting` s ON s.`tenant_id` = t.`id`
  WHERE s.`tenant_id` IS NULL
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_invalid_owned_rows = 0 AND @pa_mt03_customer_missing_tenants = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_backfill_verification_failed`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

DELETE FROM `pa_config` WHERE `type` = 'customer_service';
