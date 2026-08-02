SET NAMES utf8mb4;

-- O05 岗位管理契约增量：status 为对外权威状态，is_disable 保留兼容并由业务层双写。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_jobs' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE `pa_jobs` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''状态：0停用 1正常'' AFTER `is_disable`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

UPDATE `pa_jobs`
SET `status` = IF(`is_disable` = 0, 1, 0)
WHERE `delete_time` IS NULL;

-- LikeAdmin 前后端均将备注限制为 200 字符；先安全截断历史超长值再收窄列。
UPDATE `pa_jobs` SET `remark` = LEFT(`remark`, 200) WHERE CHAR_LENGTH(`remark`) > 200;

SET @pa_sql = IF(
    (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_jobs' AND COLUMN_NAME = 'remark') > 200,
    'ALTER TABLE `pa_jobs` MODIFY COLUMN `remark` VARCHAR(200) NOT NULL DEFAULT '''' COMMENT ''备注''',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 动态菜单以服务端记录为准；缺少菜单种子时静态 `/system/jobs` 会被权限路由过滤。
INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT 1, 'C', '岗位管理', 'icon-idcard', 60, 'jobs/lists', '/system/jobs', 'system/jobs/index', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `paths` = '/system/jobs' AND `type` = 'C'
);

SET @pa_jobs_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `paths` = '/system/jobs' AND `type` = 'C'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_jobs_menu_id, 'A', '新增岗位', '', 30, 'jobs/add', '', '', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'jobs/add');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_jobs_menu_id, 'A', '编辑岗位', '', 20, 'jobs/edit', '', '', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'jobs/edit');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_jobs_menu_id, 'A', '删除岗位', '', 10, 'jobs/delete', '', '', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'jobs/delete');
