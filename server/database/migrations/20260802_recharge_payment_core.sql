SET NAMES utf8mb4;

-- S01：充值开关、金额边界与终端支付场景。
CREATE TABLE IF NOT EXISTS `pa_payment_scene` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `terminal`    TINYINT UNSIGNED NOT NULL COMMENT '终端：1小程序 2公众号 3H5 4PC 5iOS 6Android',
  `pay_way`     TINYINT UNSIGNED NOT NULL COMMENT '支付渠道：2微信 3支付宝',
  `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0关闭 1开启',
  `is_default`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否默认：0否 1是',
  `create_time` INT UNSIGNED NULL DEFAULT NULL,
  `update_time` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_terminal_pay_way` (`terminal`,`pay_way`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值终端支付场景';

INSERT INTO `pa_payment_scene`
  (`terminal`,`pay_way`,`status`,`is_default`,`create_time`,`update_time`)
VALUES
  (1,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (5,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (5,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (6,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (6,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `terminal` = VALUES(`terminal`);

INSERT INTO `pa_config` (`type`,`name`,`value`,`create_time`,`update_time`)
SELECT 'recharge', seed.name, seed.value, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 'status' AS name, '0' AS value
  UNION ALL SELECT 'min_amount', '0.01'
  UNION ALL SELECT 'max_amount', '99999.00'
) seed
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` c WHERE c.`type` = 'recharge' AND c.`name` = seed.name
);

-- 支付回调身份校验所需 canonical 配置；仅补缺，不覆盖已有商户配置。
INSERT INTO `pa_config` (`type`,`name`,`value`,`create_time`,`update_time`)
SELECT 'pay', seed.name, '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 'wx_pay_platform_cert_path' AS name
  UNION ALL SELECT 'ali_pay_seller_id'
) seed
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` c WHERE c.`type` = 'pay' AND c.`name` = seed.name
);

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'pay_sn') = 0,
  'ALTER TABLE `pa_recharge_order` ADD COLUMN `pay_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT ''支付请求编号'' AFTER `user_id`',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_terminal') = 0,
  'ALTER TABLE `pa_recharge_order` ADD COLUMN `order_terminal` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''支付终端'' AFTER `order_amount`',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'transaction_id') = 0,
  'ALTER TABLE `pa_recharge_order` ADD COLUMN `transaction_id` VARCHAR(128) NULL DEFAULT NULL COMMENT ''第三方交易流水号'' AFTER `order_terminal`',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

UPDATE `pa_recharge_order` SET `pay_sn` = NULL WHERE `pay_sn` = '';
UPDATE `pa_recharge_order` SET `transaction_id` = NULL WHERE `transaction_id` = '';

ALTER TABLE `pa_recharge_order`
  MODIFY COLUMN `pay_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT '支付请求编号',
  MODIFY COLUMN `transaction_id` VARCHAR(128) NULL DEFAULT NULL COMMENT '第三方交易流水号';

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order'
     AND COLUMN_NAME = 'pay_sn' AND NON_UNIQUE = 0) = 0,
  'ALTER TABLE `pa_recharge_order` ADD UNIQUE KEY `uk_pay_sn` (`pay_sn`)',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order'
     AND COLUMN_NAME = 'transaction_id' AND NON_UNIQUE = 0) = 0,
  'ALTER TABLE `pa_recharge_order` ADD UNIQUE KEY `uk_transaction_id` (`transaction_id`)',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_pay_setting_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `type` = 'C' AND `paths` = '/app-setting/pay'
  ORDER BY `id` LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_pay_setting_id, 'A', seed.name, '', 0, seed.perms, '', '', 0, 1, 0
FROM (
  SELECT '充值配置查看' AS name, 'setting/recharge/config' AS perms
  UNION ALL SELECT '充值配置保存', 'setting/recharge/save'
) seed
WHERE @pa_pay_setting_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` m WHERE LOWER(m.`perms`) = LOWER(seed.perms)
  );
