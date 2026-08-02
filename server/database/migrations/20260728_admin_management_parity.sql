SET NAMES utf8mb4;

-- O01/O02 管理员域增量。
-- 兼容已执行 A02/O04 的环境；所有列、索引和关系表均可重复执行。

-- LikeAdmin 以软删除范围校验账号唯一；去掉物理唯一键后，已删除账号可再次创建。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin' AND INDEX_NAME = 'uk_username') > 0,
    'ALTER TABLE `pa_admin` DROP INDEX `uk_username`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin' AND INDEX_NAME = 'idx_username') = 0,
    'ALTER TABLE `pa_admin` ADD INDEX `idx_username` (`username`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

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

CREATE TABLE IF NOT EXISTS `pa_admin_dept` (
    `admin_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '管理员ID',
    `dept_id`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门ID',
    UNIQUE KEY `uk_admin_dept` (`admin_id`, `dept_id`),
    KEY `idx_dept_id` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员部门关联';

CREATE TABLE IF NOT EXISTS `pa_admin_jobs` (
    `admin_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '管理员ID',
    `jobs_id`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '岗位ID',
    UNIQUE KEY `uk_admin_jobs` (`admin_id`, `jobs_id`),
    KEY `idx_jobs_id` (`jobs_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员岗位关联';

-- 角色筛选按 role_id 反查管理员，补充联合唯一键无法覆盖的右侧索引。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin_role' AND INDEX_NAME = 'idx_role_id') = 0,
    'ALTER TABLE `pa_admin_role` ADD INDEX `idx_role_id` (`role_id`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;
