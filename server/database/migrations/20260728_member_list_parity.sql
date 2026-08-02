SET NAMES utf8mb4;

-- U01 用户列表契约增量；已有 Peanut 扩展字段保持不变。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'account') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `account` VARCHAR(50) NOT NULL DEFAULT '''' COMMENT ''用户账号'' AFTER `sn`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'password') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `password` VARCHAR(100) NOT NULL DEFAULT '''' COMMENT ''用户密码'' AFTER `account`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'real_name') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `real_name` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''真实姓名'' AFTER `avatar`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'channel') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `channel` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''注册来源：1小程序 2公众号 3手机H5 4电脑PC 5苹果APP 6安卓APP'' AFTER `mobile`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'login_time') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `login_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''最后登录时间'' AFTER `status`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'login_ip') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `login_ip` VARCHAR(45) NOT NULL DEFAULT '''' COMMENT ''最后登录IP'' AFTER `login_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'is_new_user') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `is_new_user` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''是否新用户：0否 1是'' AFTER `login_ip`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'total_recharge_amount') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `total_recharge_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''累计充值金额'' AFTER `balance`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND INDEX_NAME = 'idx_account') = 0,
    'ALTER TABLE `pa_member` ADD INDEX `idx_account` (`account`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND INDEX_NAME = 'idx_channel') = 0,
    'ALTER TABLE `pa_member` ADD INDEX `idx_channel` (`channel`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_member` ADD INDEX `idx_create_time` (`create_time`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- Peanut 保留后台新增/编辑用户扩展，因此对应按钮/API 必须显式登记权限。
SET @pa_member_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `paths` = '/member/list' AND `type` = 'C'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '会员新增', '', 0, 'member/add', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/add');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '会员编辑', '', 0, 'member/edit', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/edit');
