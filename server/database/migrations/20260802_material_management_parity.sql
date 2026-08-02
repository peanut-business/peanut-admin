SET NAMES utf8mb4;

-- M02 素材管理增量：记录实际存储引擎，并补齐组合查询索引与菜单/API 权限。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_file' AND COLUMN_NAME = 'storage') = 0,
    'ALTER TABLE `pa_file` ADD COLUMN `storage` VARCHAR(20) NOT NULL DEFAULT '''' COMMENT ''存储引擎'' AFTER `uri`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

UPDATE `pa_file`
SET `storage` = 'local'
WHERE (`storage` = '' OR `storage` IS NULL)
  AND `uri` LIKE 'storage/%';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_file' AND INDEX_NAME = 'idx_type_cid_source') = 0,
    'ALTER TABLE `pa_file` ADD INDEX `idx_type_cid_source` (`type`, `cid`, `source`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

UPDATE `pa_system_menu`
SET `name` = '素材管理',
    `icon` = 'icon-folder',
    `sort` = 50,
    `perms` = 'file/lists',
    `component` = 'system/file/index',
    `is_disable` = 0
WHERE `type` = 'C' AND `paths` = '/system/file';

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT 1, 'C', '素材管理', 'icon-folder', 50, 'file/lists', '/system/file', 'system/file/index', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `type` = 'C' AND `paths` = '/system/file'
);

SET @pa_file_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C' AND `paths` = '/system/file'
    ORDER BY `id` ASC
    LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '分类查看', '', 0, 'file/cate/lists', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'file/cate/lists');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '上传图片', '', 0, 'upload/image', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'upload/image');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '上传视频', '', 0, 'upload/video', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'upload/video');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '上传文件', '', 0, 'upload/file', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'upload/file');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '分类新增', '', 0, 'file/cate/add', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'file/cate/add');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '分类编辑', '', 0, 'file/cate/edit', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'file/cate/edit');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '分类删除', '', 0, 'file/cate/delete', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'file/cate/delete');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '素材移动', '', 0, 'file/move', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'file/move');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '素材重命名', '', 0, 'file/rename', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'file/rename');

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_file_menu_id, 'A', '素材删除', '', 0, 'file/delete', '', '', 0, 1, 0
WHERE @pa_file_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'file/delete');
