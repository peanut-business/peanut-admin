SET NAMES utf8mb4;

-- O04 部门契约增量：先执行本迁移，再发布对应 PHP 代码。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_dept' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE `pa_dept` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''状态：0停用 1正常'' AFTER `is_disable`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_dept' AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_dept` ADD COLUMN `delete_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

UPDATE `pa_dept`
SET `status` = IF(`is_disable` = 0, 1, 0)
WHERE `delete_time` IS NULL;

CREATE TABLE IF NOT EXISTS `pa_admin_dept` (
    `admin_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '管理员ID',
    `dept_id`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门ID',
    UNIQUE KEY `uk_admin_dept` (`admin_id`, `dept_id`),
    KEY `idx_dept_id` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员部门关联';

-- 动态菜单以服务端记录为准；缺少菜单种子时静态 `/system/dept` 会被权限路由过滤。
INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT 1, 'C', '部门管理', 'icon-mind-mapping', 70, 'dept/lists', '/system/dept', 'system/dept/index', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `paths` = '/system/dept' AND `type` = 'C'
);

SET @pa_dept_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `paths` = '/system/dept' AND `type` = 'C'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_dept_menu_id, 'A', '新增部门', '', 30, 'dept/add', '', '', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'dept/add');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_dept_menu_id, 'A', '编辑部门', '', 20, 'dept/edit', '', '', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'dept/edit');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_dept_menu_id, 'A', '删除部门', '', 10, 'dept/delete', '', '', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'dept/delete');
