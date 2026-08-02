SET NAMES utf8mb4;

-- C01 文章分类契约增量：只调整现有一级分类字段与权限，不引入树结构或级联关系。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_article_cate'
       AND COLUMN_NAME = 'name'
       AND CHARACTER_MAXIMUM_LENGTH = 90
       AND IS_NULLABLE = 'NO') = 0,
    'ALTER TABLE `pa_article_cate` MODIFY COLUMN `name` VARCHAR(90) NOT NULL DEFAULT '''' COMMENT ''分类名称''',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_article_cate'
       AND COLUMN_NAME = 'sort'
       AND DATA_TYPE = 'int'
       AND LOCATE('unsigned', COLUMN_TYPE) = 0
       AND IS_NULLABLE = 'NO') = 0,
    'ALTER TABLE `pa_article_cate` MODIFY COLUMN `sort` INT NOT NULL DEFAULT 0 COMMENT ''排序（倒序）''',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 将 Peanut 旧 slash 权限原位升级，保留已有角色与菜单关系。
UPDATE `pa_system_menu`
SET `perms` = 'article.articleCate/lists'
WHERE `type` = 'C'
  AND (`paths` = '/article/cate' OR `perms` = 'article/cate/lists');

SET @pa_article_cate_menu_id = (
    SELECT `id`
    FROM `pa_system_menu`
    WHERE `type` = 'C'
      AND (`paths` = '/article/cate' OR `perms` = 'article.articleCate/lists')
    ORDER BY (`paths` = '/article/cate') DESC, `id` ASC
    LIMIT 1
);

UPDATE `pa_system_menu`
SET `perms` = 'article.articleCate/add'
WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article/cate/add';

UPDATE `pa_system_menu`
SET `perms` = 'article.articleCate/edit'
WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article/cate/edit';

UPDATE `pa_system_menu`
SET `perms` = 'article.articleCate/delete'
WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article/cate/delete';

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_cate_menu_id, 'A', '分类新增', '', 0, 'article.articleCate/add', '', '', 0, 1, 0
WHERE @pa_article_cate_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu`
      WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article.articleCate/add'
  );

-- edit 是 Peanut 对 LikeAdmin 1.9.4 写权限漏登记的安全修正。
INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_cate_menu_id, 'A', '分类编辑', '', 0, 'article.articleCate/edit', '', '', 0, 1, 0
WHERE @pa_article_cate_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu`
      WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article.articleCate/edit'
  );

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_cate_menu_id, 'A', '分类删除', '', 0, 'article.articleCate/delete', '', '', 0, 1, 0
WHERE @pa_article_cate_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu`
      WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article.articleCate/delete'
  );

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_cate_menu_id, 'A', '分类详情', '', 0, 'article.articleCate/detail', '', '', 0, 1, 0
WHERE @pa_article_cate_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu`
      WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article.articleCate/detail'
  );

INSERT INTO `pa_system_menu`
    (`pid`, `type`, `name`, `icon`, `sort`, `perms`, `paths`, `component`, `is_cache`, `is_show`, `is_disable`)
SELECT @pa_article_cate_menu_id, 'A', '分类状态', '', 0, 'article.articleCate/updateStatus', '', '', 0, 1, 0
WHERE @pa_article_cate_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu`
      WHERE `pid` = @pa_article_cate_menu_id AND `perms` = 'article.articleCate/updateStatus'
  );
