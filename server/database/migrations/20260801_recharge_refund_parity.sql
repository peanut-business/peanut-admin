SET NAMES utf8mb4;

-- F02：LikeAdmin 1.9.4 充值、退款模型、幂等状态机与权限契约。
SET @pa_recharge_had_sn = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pa_recharge_order'
      AND COLUMN_NAME = 'sn'
);

CREATE TABLE IF NOT EXISTS `pa_recharge_order` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sn`                    VARCHAR(64)   NOT NULL COMMENT '充值订单编号',
  `user_id`               INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT '用户ID',
  `pay_sn`                VARCHAR(255)  NULL DEFAULT '' COMMENT '支付编号',
  `pay_way`               TINYINT(2)    NOT NULL DEFAULT 2 COMMENT '支付方式：1余额 2微信 3支付宝',
  `pay_status`            TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '支付状态：0未支付 1已支付',
  `pay_time`              INT UNSIGNED  NULL DEFAULT NULL COMMENT '支付时间',
  `order_amount`          DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '充值金额',
  `order_terminal`        TINYINT(1)    NULL DEFAULT 1 COMMENT '支付终端',
  `transaction_id`        VARCHAR(128)  NULL DEFAULT NULL COMMENT '第三方交易流水号',
  `refund_status`         TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '是否已发起退款：0否 1是',
  `refund_transaction_id` VARCHAR(255)  NULL DEFAULT NULL COMMENT '退款交易流水号',
  `create_time`           INT UNSIGNED  NULL DEFAULT NULL,
  `update_time`           INT UNSIGNED  NULL DEFAULT NULL,
  `delete_time`           INT UNSIGNED  NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_pay_status` (`pay_status`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值订单';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'sn') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `sn` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''充值订单编号'' AFTER `id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'user_id') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''用户ID'' AFTER `sn`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'pay_sn') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `pay_sn` VARCHAR(255) NULL DEFAULT '''' COMMENT ''支付编号'' AFTER `user_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'pay_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `pay_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''支付状态：0未支付 1已支付'' AFTER `pay_way`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_amount') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT ''充值金额'' AFTER `pay_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_terminal') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `order_terminal` TINYINT(1) NULL DEFAULT 1 COMMENT ''支付终端'' AFTER `order_amount`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'refund_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `refund_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''是否已发起退款：0否 1是'' AFTER `transaction_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'refund_transaction_id') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `refund_transaction_id` VARCHAR(255) NULL DEFAULT NULL COMMENT ''退款交易流水号'' AFTER `refund_status`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `delete_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

-- 旧 Peanut 充值表只在首次补 canonical sn 时迁移，避免重跑时再次映射 pay_way。
SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_sn') = 1,
    'UPDATE `pa_recharge_order` SET `sn` = `order_sn` WHERE `sn` = ''''',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'member_id') = 1,
    'UPDATE `pa_recharge_order` SET `user_id` = `member_id`, `pay_way` = CASE `pay_way` WHEN 1 THEN 2 WHEN 2 THEN 3 ELSE `pay_way` END',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'amount') = 1,
    'UPDATE `pa_recharge_order` SET `order_amount` = `amount`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'status') = 1,
    'UPDATE `pa_recharge_order` SET `pay_status` = IF(`status` = 1, 1, 0)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_recharge_order`
  MODIFY COLUMN `sn` VARCHAR(64) NOT NULL COMMENT '充值订单编号',
  MODIFY COLUMN `pay_way` TINYINT(2) NOT NULL DEFAULT 2 COMMENT '支付方式：1余额 2微信 3支付宝',
  MODIFY COLUMN `pay_time` INT UNSIGNED NULL DEFAULT NULL COMMENT '支付时间',
  MODIFY COLUMN `transaction_id` VARCHAR(128) NULL DEFAULT NULL COMMENT '第三方交易流水号',
  MODIFY COLUMN `create_time` INT UNSIGNED NULL DEFAULT NULL,
  MODIFY COLUMN `update_time` INT UNSIGNED NULL DEFAULT NULL;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_recharge_order` ADD UNIQUE KEY `uk_sn` (`sn`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_user_id') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_user_id` (`user_id`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_pay_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_pay_status` (`pay_status`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_refund_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_refund_status` (`refund_status`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_create_time` (`create_time`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

CREATE TABLE IF NOT EXISTS `pa_refund_record` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `sn`             VARCHAR(32) NOT NULL DEFAULT '',
  `user_id`        INT NOT NULL DEFAULT 0,
  `order_id`       INT NOT NULL DEFAULT 0,
  `order_sn`       VARCHAR(64) NOT NULL,
  `order_type`     VARCHAR(255) NULL DEFAULT 'order',
  `order_amount`   DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_amount`  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `transaction_id` VARCHAR(255) NULL DEFAULT NULL,
  `refund_way`     TINYINT(1) NOT NULL DEFAULT 1,
  `refund_type`    TINYINT(1) NOT NULL DEFAULT 1,
  `refund_status`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `refund_msg`     TEXT NULL DEFAULT NULL,
  `create_time`    INT UNSIGNED NULL DEFAULT 0,
  `update_time`    INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  UNIQUE KEY `uk_order_type_order_id` (`order_type`,`order_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_order_sn` (`order_sn`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款记录';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND COLUMN_NAME = 'refund_msg') = 0,
    'ALTER TABLE `pa_refund_record` ADD COLUMN `refund_msg` TEXT NULL DEFAULT NULL COMMENT ''退款信息'' AFTER `refund_status`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_refund_record`
  MODIFY COLUMN `sn` VARCHAR(32) NOT NULL DEFAULT '',
  MODIFY COLUMN `order_sn` VARCHAR(64) NOT NULL;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_refund_record` ADD UNIQUE KEY `uk_sn` (`sn`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND INDEX_NAME = 'uk_order_type_order_id') = 0,
    'ALTER TABLE `pa_refund_record` ADD UNIQUE KEY `uk_order_type_order_id` (`order_type`,`order_id`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_refund_record` ADD KEY `idx_create_time` (`create_time`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

CREATE TABLE IF NOT EXISTS `pa_refund_log` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `sn`            VARCHAR(32) NULL DEFAULT NULL,
  `record_id`     INT NOT NULL,
  `user_id`       INT NOT NULL DEFAULT 0,
  `handle_id`     INT NOT NULL DEFAULT 0,
  `order_amount`  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `refund_msg`    TEXT NULL DEFAULT NULL,
  `create_time`   INT UNSIGNED NULL DEFAULT 0,
  `update_time`   INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款日志';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_log' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_refund_log` ADD UNIQUE KEY `uk_sn` (`sn`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_log' AND INDEX_NAME = 'idx_refund_status') = 0,
    'ALTER TABLE `pa_refund_log` ADD KEY `idx_refund_status` (`refund_status`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_log' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_refund_log` ADD KEY `idx_create_time` (`create_time`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT 0, 'M', '财务管理', 'icon-fingerprint', 60, '', '/finance', '', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `type` = 'M' AND `paths` = '/finance'
);

SET @pa_finance_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'M' AND `paths` = '/finance'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_finance_menu_id, 'C', '充值记录', 'icon-thunderbolt', 80,
       'recharge.recharge/lists', '/finance/recharge', 'finance/recharge/index', 0, 1, 0
WHERE @pa_finance_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE `type` = 'C' AND `paths` = '/finance/recharge'
  );

UPDATE `pa_system_menu`
SET `pid` = @pa_finance_menu_id,
    `name` = '充值记录',
    `perms` = 'recharge.recharge/lists',
    `component` = 'finance/recharge/index',
    `is_show` = 1,
    `is_disable` = 0
WHERE `type` = 'C' AND `paths` = '/finance/recharge';

SET @pa_recharge_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C' AND `paths` = '/finance/recharge'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_recharge_menu_id, 'A', '退款', '', 0, 'recharge.recharge/refund', '', '', 0, 1, 0
WHERE @pa_recharge_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`) = 'recharge.recharge/refund'
  );

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_finance_menu_id, 'C', '退款记录', 'icon-undo', 70,
       'finance.refund/record', '/finance/refund', 'finance/refund/index', 0, 1, 0
WHERE @pa_finance_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE `type` = 'C' AND `paths` = '/finance/refund'
  );

UPDATE `pa_system_menu`
SET `pid` = @pa_finance_menu_id,
    `name` = '退款记录',
    `perms` = 'finance.refund/record',
    `component` = 'finance/refund/index',
    `is_show` = 1,
    `is_disable` = 0
WHERE `type` = 'C' AND `paths` = '/finance/refund';

SET @pa_refund_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C' AND `paths` = '/finance/refund'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_refund_menu_id, 'A', '重新退款', '', 0, 'recharge.recharge/refundAgain', '', '', 0, 1, 0
WHERE @pa_refund_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`) = 'recharge.recharge/refundagain'
  );

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_refund_menu_id, 'A', '退款日志', '', 0, 'finance.refund/log', '', '', 0, 1, 0
WHERE @pa_refund_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`) = 'finance.refund/log'
  );

INSERT INTO `pa_crontab`
    (`name`,`type`,`command`,`params`,`status`,`expression`,`error`,`last_time`,`time`,`max_time`,`sort`,`remark`,`create_time`,`update_time`)
SELECT '退款状态收敛', 1, 'refund:reconcile', '', 1, '* * * * *', '', 0, 0, 0, 100,
       '查询支付渠道并收敛充值退款最终状态', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_crontab` WHERE `command` = 'refund:reconcile'
);

UPDATE `pa_crontab`
SET `name` = '退款状态收敛',
    `type` = 1,
    `params` = '',
    `status` = 1,
    `expression` = '* * * * *',
    `error` = '',
    `sort` = 100,
    `remark` = '查询支付渠道并收敛充值退款最终状态',
    `update_time` = UNIX_TIMESTAMP(),
    `delete_time` = NULL
WHERE `command` = 'refund:reconcile';
