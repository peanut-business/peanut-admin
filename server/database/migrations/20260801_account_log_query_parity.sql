SET NAMES utf8mb4;

-- F01：LikeAdmin 1.9.4 余额明细数据模型与菜单权限契约。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'source_sn') = 1,
    'ALTER TABLE `pa_member_balance_log` MODIFY COLUMN `source_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT ''来源单号''',
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `source_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT ''来源单号'' AFTER `source_type`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'remark') = 1,
    'ALTER TABLE `pa_member_balance_log` MODIFY COLUMN `remark` VARCHAR(255) NULL DEFAULT '''' COMMENT ''备注''',
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `remark` VARCHAR(255) NULL DEFAULT '''' COMMENT ''备注'' AFTER `source_sn`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'update_time') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `update_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''更新时间'' AFTER `create_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `delete_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

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
SELECT @pa_finance_menu_id, 'C', '余额明细', 'icon-bar-chart', 90,
       'finance.account_log/lists', '/finance/account-log',
       'finance/account-log/index', 0, 1, 0
WHERE @pa_finance_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu`
      WHERE `type` = 'C' AND `paths` = '/finance/account-log'
  );

UPDATE `pa_system_menu`
SET `pid` = @pa_finance_menu_id,
    `name` = '余额明细',
    `perms` = 'finance.account_log/lists',
    `component` = 'finance/account-log/index',
    `is_show` = 1,
    `is_disable` = 0
WHERE `type` = 'C' AND `paths` = '/finance/account-log';
