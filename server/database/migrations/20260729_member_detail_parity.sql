SET NAMES utf8mb4;

-- U02 用户余额权威字段；Peanut 原 balance 作兼容镜像保留。
SET @pa_user_money_missing = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'user_money'
) = 0;

SET @pa_sql = IF(
    @pa_user_money_missing,
    'ALTER TABLE `pa_member` ADD COLUMN `user_money` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''用户可用余额'' AFTER `balance`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 只在首次新增权威字段时从旧 balance 初始化；重复执行不得反向覆盖真实余额。
SET @pa_sql = IF(
    @pa_user_money_missing,
    'UPDATE `pa_member` SET `user_money` = `balance` WHERE `user_money` <> `balance`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 账户流水升级为 LikeAdmin 分类模型；旧字段保留供现有调用方渐进迁移。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'sn') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `sn` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''流水号'' AFTER `id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'change_object') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `change_object` TINYINT(2) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''变动对象：1用户余额'' AFTER `member_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'change_type') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `change_type` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''变动类型：100/101/200/201'' AFTER `change_object`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'action') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `action` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''动作：1增加 2减少'' AFTER `change_type`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'left_amount') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `left_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''变动后余额'' AFTER `change_amount`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'source_sn') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `source_sn` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''来源单号'' AFTER `source_type`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'extra') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `extra` TEXT NULL COMMENT ''扩展数据JSON'' AFTER `remark`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 只迁移 change_type=0 的旧 signed amount 记录，重复执行不会改写新流水。
UPDATE `pa_member_balance_log`
SET `sn` = CONCAT(DATE_FORMAT(FROM_UNIXTIME(`create_time`), '%Y%m%d%H%i%s'), LPAD(`id`, 6, '0')),
    `change_object` = 1,
    `change_type` = IF(`change_amount` >= 0, 200, 100),
    `action` = IF(`change_amount` >= 0, 1, 2),
    `left_amount` = `after_amount`,
    `change_amount` = ABS(`change_amount`),
    `source_sn` = '',
    `extra` = ''
WHERE `change_type` = 0;

ALTER TABLE `pa_member_balance_log`
    MODIFY COLUMN `change_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '变动金额（无符号）';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD UNIQUE INDEX `uk_sn` (`sn`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND INDEX_NAME = 'idx_change_type') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD INDEX `idx_change_type` (`change_type`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD INDEX `idx_create_time` (`create_time`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_member_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `paths` = '/member/list' AND `type` = 'C'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '余额调整', '', 0, 'member/adjustMoney', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/adjustMoney');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '会员资料编辑', '', 0, 'member/profile/edit', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/profile/edit');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '用户详情', '', 0, 'user.user/detail', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'user.user/detail');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '用户编辑', '', 0, 'user.user/edit', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'user.user/edit');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '余额调整', '', 0, 'user.user/adjustMoney', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'user.user/adjustMoney');
