SET NAMES utf8mb4;

-- A02 管理端认证会话增量：先执行本迁移，再发布对应 PHP 代码。
-- MySQL 不支持 ADD COLUMN IF NOT EXISTS，使用 information_schema 保持幂等。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin' AND COLUMN_NAME = 'login_time') = 0,
    'ALTER TABLE `pa_admin` ADD COLUMN `login_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''最后登录时间'' AFTER `disable`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin' AND COLUMN_NAME = 'login_ip') = 0,
    'ALTER TABLE `pa_admin` ADD COLUMN `login_ip` VARCHAR(45) NOT NULL DEFAULT '''' COMMENT ''最后登录IP'' AFTER `login_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin' AND COLUMN_NAME = 'multipoint_login') = 0,
    'ALTER TABLE `pa_admin` ADD COLUMN `multipoint_login` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''是否允许多处登录：0否 1是'' AFTER `login_ip`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

CREATE TABLE IF NOT EXISTS `pa_admin_session` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '管理员ID',
    `terminal`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '登录终端：1 PC，2 Mobile',
    `token`       CHAR(64)     NOT NULL COMMENT '服务端会话令牌',
    `login_ip`    VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '该会话绑定的登录IP',
    `update_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最近签发/续期时间',
    `expire_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '到期时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admin_terminal` (`admin_id`, `terminal`),
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员会话';

-- 兼容已执行过 A02 初版迁移的环境。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin_session' AND COLUMN_NAME = 'login_ip') = 0,
    'ALTER TABLE `pa_admin_session` ADD COLUMN `login_ip` VARCHAR(45) NOT NULL DEFAULT '''' COMMENT ''该会话绑定的登录IP'' AFTER `token`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;
