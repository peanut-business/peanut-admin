SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `pa_generator_table` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '配置所有者管理员ID',
  `table_name`    VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '数据库物理表名',
  `table_comment` VARCHAR(300) NOT NULL DEFAULT '' COMMENT '表说明',
  `module_name`   VARCHAR(32)  NOT NULL DEFAULT 'generated' COMMENT '生成模块名',
  `entity_name`   VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '生成实体类名',
  `template_type` VARCHAR(10)  NOT NULL DEFAULT 'crud' COMMENT '模板类型 crud/tree',
  `author`         VARCHAR(100) NOT NULL DEFAULT '' COMMENT '作者',
  `tree_config`   JSON NULL COMMENT '树结构字段配置',
  `relations`     JSON NULL COMMENT '模型关系配置',
  `create_time`   INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_owner_table` (`admin_id`,`table_name`),
  KEY `idx_owner_update` (`admin_id`,`update_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='安全代码生成器表配置';

CREATE TABLE IF NOT EXISTS `pa_generator_column` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id`       INT UNSIGNED NOT NULL DEFAULT 0,
  `column_name`    VARCHAR(64)  NOT NULL DEFAULT '',
  `column_comment` VARCHAR(300) NOT NULL DEFAULT '',
  `column_type`    VARCHAR(100) NOT NULL DEFAULT '',
  `php_type`       VARCHAR(20)  NOT NULL DEFAULT 'string',
  `is_required`    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_pk`          TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_insert`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_update`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_lists`       TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_query`       TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `query_type`     VARCHAR(20) NOT NULL DEFAULT '=',
  `view_type`      VARCHAR(20) NOT NULL DEFAULT 'input',
  `dict_type`      VARCHAR(100) NOT NULL DEFAULT '',
  `sort`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `create_time`    INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time`    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_table_column` (`table_id`,`column_name`),
  KEY `idx_table_sort` (`table_id`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='安全代码生成器字段配置';

CREATE TABLE IF NOT EXISTS `pa_generator_download` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '令牌所有者管理员ID',
  `token_hash`    CHAR(64) NOT NULL DEFAULT '' COMMENT '下载令牌SHA-256',
  `archive_path`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '生成根目录内相对路径',
  `download_name` VARCHAR(120) NOT NULL DEFAULT '',
  `expire_time`   INT UNSIGNED NOT NULL DEFAULT 0,
  `used_time`     INT UNSIGNED NOT NULL DEFAULT 0,
  `create_time`   INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_owner_expire` (`admin_id`,`expire_time`,`used_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='代码生成器一次性下载令牌';

-- 开发工具目录。
INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT 0, 'M', '开发工具', 'icon-tool', 20, '', '/dev-tools', '', 0, 1, 0
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/dev-tools'
);

SET @pa_dev_tools_menu_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `type`='M' AND `paths`='/dev-tools'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_dev_tools_menu_id, 'C', '代码生成器', 'icon-code', 90,
       'generator/lists', '/dev-tools/code', 'dev-tools/code/index', 0, 1, 0
WHERE @pa_dev_tools_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/lists');

SET @pa_generator_menu_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `perms`='generator/lists'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '读取数据表', '', 0, 'generator/source-tables', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/source-tables');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '导入数据表', '', 0, 'generator/import', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/import');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '查看配置', '', 0, 'generator/detail', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/detail');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '同步字段', '', 0, 'generator/sync', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/sync');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '编辑配置', '', 0, 'generator/update', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/update');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '删除配置', '', 0, 'generator/delete', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/delete');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '预览代码', '', 0, 'generator/preview', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/preview');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '生成代码', '', 0, 'generator/generate', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/generate');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '下载代码', '', 0, 'generator/download', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/download');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_generator_menu_id, 'A', '模型列表', '', 0, 'generator/models', '', '', 0, 1, 0
WHERE @pa_generator_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`='generator/models');

INSERT INTO `pa_crontab`
  (`name`,`type`,`command`,`params`,`status`,`expression`,`error`,`last_time`,`time`,`max_time`,`sort`,`remark`,`create_time`,`update_time`)
SELECT '代码生成归档清理',1,'generator:cleanup','',1,'0 3 * * *','',0,0,0,20,
       '清理已使用或过期的代码生成下载令牌和隔离归档',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `pa_crontab` WHERE `command`='generator:cleanup');
