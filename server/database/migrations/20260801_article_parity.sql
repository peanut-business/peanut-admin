SET NAMES utf8mb4;

-- C02 文章业务能力增量：建立单一权威文章模型。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'cid') = 0,
    'ALTER TABLE `pa_article` ADD COLUMN `cid` INT NOT NULL DEFAULT 0 COMMENT ''文章分类'' AFTER `id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'desc') = 0,
    'ALTER TABLE `pa_article` ADD COLUMN `desc` VARCHAR(255) NULL DEFAULT '''' COMMENT ''简介'' AFTER `title`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'abstract') = 0,
    'ALTER TABLE `pa_article` ADD COLUMN `abstract` TEXT NULL COMMENT ''文章摘要'' AFTER `desc`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'click_virtual') = 0,
    'ALTER TABLE `pa_article` ADD COLUMN `click_virtual` INT NULL DEFAULT 0 COMMENT ''虚拟浏览量'' AFTER `content`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'click_actual') = 0,
    'ALTER TABLE `pa_article` ADD COLUMN `click_actual` INT NULL DEFAULT 0 COMMENT ''实际浏览量'' AFTER `click_virtual`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_article`
    MODIFY COLUMN `title` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '文章标题',
    MODIFY COLUMN `image` VARCHAR(128) NULL DEFAULT NULL COMMENT '文章图片',
    MODIFY COLUMN `author` VARCHAR(255) NULL DEFAULT '' COMMENT '作者',
    MODIFY COLUMN `content` TEXT NULL COMMENT '文章内容',
    MODIFY COLUMN `sort` INT NULL DEFAULT 0 COMMENT '排序（倒序）';

-- 一次性迁移已有 Peanut 数据，迁移完成后删除旧字段和索引。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'cate_id') > 0,
    'UPDATE `pa_article` SET `cid` = `cate_id` WHERE `cid` = 0 AND `cate_id` <> 0',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'intro') > 0,
    'UPDATE `pa_article` SET `desc` = `intro` WHERE (`desc` IS NULL OR `desc` = '''') AND `intro` <> ''''',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'click_num') > 0,
    'UPDATE `pa_article` SET `click_actual` = `click_num` WHERE COALESCE(`click_actual`, 0) = 0 AND `click_num` <> 0',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND INDEX_NAME = 'idx_cate_id') > 0,
    'ALTER TABLE `pa_article` DROP INDEX `idx_cate_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'cate_id') > 0,
    'ALTER TABLE `pa_article` DROP COLUMN `cate_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'intro') > 0,
    'ALTER TABLE `pa_article` DROP COLUMN `intro`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND COLUMN_NAME = 'click_num') > 0,
    'ALTER TABLE `pa_article` DROP COLUMN `click_num`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article' AND INDEX_NAME = 'idx_cid') = 0,
    'ALTER TABLE `pa_article` ADD INDEX `idx_cid` (`cid`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

-- 收藏关系由物理删除改为 LikeAdmin 的 status=1/0 状态切换；既有行均视为已收藏。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article_collect' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE `pa_article_collect` ADD COLUMN `status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''收藏状态 0-未收藏 1-已收藏'' AFTER `article_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article_collect' AND COLUMN_NAME = 'update_time') = 0,
    'ALTER TABLE `pa_article_collect` ADD COLUMN `update_time` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `create_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_article_collect' AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_article_collect` ADD COLUMN `delete_time` INT NULL DEFAULT NULL AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

UPDATE `pa_article_collect` SET `status` = 1 WHERE `delete_time` IS NULL;

-- 将 Peanut 旧 slash 权限原位升级，保留已有角色与菜单关系。
UPDATE `pa_system_menu`
SET `perms` = 'article.article/lists'
WHERE `type` = 'C'
  AND (`paths` = '/article/list' OR `perms` = 'article/lists');

SET @pa_article_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C'
      AND (`paths` = '/article/list' OR `perms` = 'article.article/lists')
    ORDER BY (`paths` = '/article/list') DESC, `id` ASC
    LIMIT 1
);

UPDATE `pa_system_menu` SET `perms` = 'article.article/add'
WHERE `pid` = @pa_article_menu_id AND `perms` = 'article/add';
UPDATE `pa_system_menu` SET `perms` = 'article.article/edit'
WHERE `pid` = @pa_article_menu_id AND `perms` = 'article/edit';
UPDATE `pa_system_menu` SET `perms` = 'article.article/delete'
WHERE `pid` = @pa_article_menu_id AND `perms` = 'article/delete';
UPDATE `pa_system_menu` SET `perms` = 'article.article/updateStatus'
WHERE `pid` = @pa_article_menu_id AND `perms` = 'article/status';

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_menu_id, 'A', '文章新增', '', 0, 'article.article/add', '', '', 0, 1, 0
WHERE @pa_article_menu_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `pid` = @pa_article_menu_id AND `perms` = 'article.article/add'
);

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_menu_id, 'A', '文章编辑', '', 0, 'article.article/edit', '', '', 0, 1, 0
WHERE @pa_article_menu_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `pid` = @pa_article_menu_id AND `perms` = 'article.article/edit'
);

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_menu_id, 'A', '文章删除', '', 0, 'article.article/delete', '', '', 0, 1, 0
WHERE @pa_article_menu_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `pid` = @pa_article_menu_id AND `perms` = 'article.article/delete'
);

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_menu_id, 'A', '文章详情', '', 0, 'article.article/detail', '', '', 0, 1, 0
WHERE @pa_article_menu_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `pid` = @pa_article_menu_id AND `perms` = 'article.article/detail'
);

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_menu_id, 'A', '文章状态', '', 0, 'article.article/updateStatus', '', '', 0, 1, 0
WHERE @pa_article_menu_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `pid` = @pa_article_menu_id AND `perms` = 'article.article/updateStatus'
);
