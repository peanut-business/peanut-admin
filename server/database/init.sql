SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `pa_admin` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`    VARCHAR(50)  NOT NULL DEFAULT '',
  `nickname`    VARCHAR(50)  NOT NULL DEFAULT '',
  `password`    VARCHAR(64)  NOT NULL DEFAULT '',
  `salt`        VARCHAR(16)  NOT NULL DEFAULT '',
  `avatar`      VARCHAR(255) NOT NULL DEFAULT '',
  `root`        TINYINT(1)   NOT NULL DEFAULT 0,
  `disable`     TINYINT(1)   NOT NULL DEFAULT 0,
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员';

CREATE TABLE IF NOT EXISTS `pa_system_role` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL DEFAULT '',
  `desc`        VARCHAR(255) NOT NULL DEFAULT '',
  `sort`        SMALLINT     NOT NULL DEFAULT 0,
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色';

CREATE TABLE IF NOT EXISTS `pa_admin_role` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `role_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  UNIQUE KEY `uk_admin_role` (`admin_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员角色关联';

CREATE TABLE IF NOT EXISTS `pa_system_menu` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`        INT UNSIGNED NOT NULL DEFAULT 0,
  `type`       CHAR(1)      NOT NULL DEFAULT 'C' COMMENT 'M目录 C菜单 A按钮',
  `name`       VARCHAR(50)  NOT NULL DEFAULT '',
  `icon`       VARCHAR(100) NOT NULL DEFAULT '',
  `sort`       SMALLINT     NOT NULL DEFAULT 0,
  `perms`      VARCHAR(100) NOT NULL DEFAULT '' COMMENT '权限标识',
  `paths`      VARCHAR(200) NOT NULL DEFAULT '' COMMENT '路由路径',
  `component`  VARCHAR(200) NOT NULL DEFAULT '' COMMENT '前端组件',
  `is_cache`   TINYINT(1)   NOT NULL DEFAULT 0,
  `is_show`    TINYINT(1)   NOT NULL DEFAULT 1,
  `is_disable` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统菜单';

CREATE TABLE IF NOT EXISTS `pa_system_role_menu` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `menu_id` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_role_id` (`role_id`),
  UNIQUE KEY `uk_role_menu` (`role_id`, `menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色菜单关联';

CREATE TABLE IF NOT EXISTS `pa_dept` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`         INT UNSIGNED NOT NULL DEFAULT 0,
  `name`        VARCHAR(50)  NOT NULL DEFAULT '',
  `leader`      VARCHAR(50)  NOT NULL DEFAULT '',
  `mobile`      VARCHAR(20)  NOT NULL DEFAULT '',
  `sort`        SMALLINT     NOT NULL DEFAULT 0,
  `is_disable`  TINYINT(1)   NOT NULL DEFAULT 0,
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='部门';

CREATE TABLE IF NOT EXISTS `pa_operation_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `username`    VARCHAR(50)  NOT NULL DEFAULT '',
  `ip`          VARCHAR(50)  NOT NULL DEFAULT '',
  `uri`         VARCHAR(200) NOT NULL DEFAULT '',
  `method`      VARCHAR(10)  NOT NULL DEFAULT '',
  `params`      TEXT,
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志';

CREATE TABLE IF NOT EXISTS `pa_config` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`        VARCHAR(30)  NOT NULL DEFAULT '',
  `name`        VARCHAR(60)  NOT NULL DEFAULT '',
  `value`       TEXT,
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_name` (`type`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置';

CREATE TABLE IF NOT EXISTS `pa_jobs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '岗位名称',
  `code`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '岗位编码',
  `sort`        SMALLINT     NOT NULL DEFAULT 0  COMMENT '排序',
  `is_disable`  TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否禁用：0启用 1禁用',
  `remark`      VARCHAR(500) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
  -- 不设 UNIQUE(code)：软删除会保留行，唯一键会阻止编码复用；
  -- 编码唯一性由 JobsLogic::codeExists 在活跃记录范围内保证（软删的编码可复用）。
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='岗位';

-- 超级管理员（密码：admin123456）
INSERT IGNORE INTO `pa_admin` (`id`,`username`,`nickname`,`password`,`salt`,`root`,`create_time`,`update_time`)
VALUES (1,'admin','超级管理员', MD5(CONCAT(MD5('admin123456'),'abcd1234')), 'abcd1234', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ─── 系统菜单种子（权限管理模块）───────────────────────────────────────────
-- perms 需与 AuthMiddleware 推导一致：路由 admin/menu/lists → 去掉 admin/ → menu/lists
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (1,  0, 'M', '权限管理',   'icon-settings', 100, '',            '/system',       '',                    0, 1, 0),
  (2,  1, 'C', '菜单管理',   'icon-menu',      90, 'menu/lists',  '/system/menu',  'system/menu/index',   0, 1, 0),
  (3,  2, 'A', '菜单新增',   '',                0, 'menu/add',    '',              '',                    0, 1, 0),
  (4,  2, 'A', '菜单编辑',   '',                0, 'menu/edit',   '',              '',                    0, 1, 0),
  (5,  2, 'A', '菜单删除',   '',                0, 'menu/delete', '',              '',                    0, 1, 0),
  (6,  1, 'C', '角色管理',   'icon-user-group', 80, 'role/lists',  '/system/role',  'system/role/index',   0, 1, 0),
  (7,  6, 'A', '角色新增',   '',                0, 'role/add',    '',              '',                    0, 1, 0),
  (8,  6, 'A', '角色编辑',   '',                0, 'role/edit',   '',              '',                    0, 1, 0),
  (9,  6, 'A', '角色删除',   '',                0, 'role/delete', '',              '',                    0, 1, 0),
  (10, 1, 'C', '管理员管理', 'icon-user',       70, 'admin/lists', '/system/admin', 'system/admin/index',  0, 1, 0),
  (11,10, 'A', '管理员新增', '',                0, 'admin/add',   '',              '',                    0, 1, 0),
  (12,10, 'A', '管理员编辑', '',                0, 'admin/edit',  '',              '',                    0, 1, 0),
  (13,10, 'A', '管理员删除', '',                0, 'admin/delete','',              '',                    0, 1, 0);

-- ─── 默认角色（普通管理员，仅授予「菜单管理」权限，用于演示 RBAC 限制）──────────
INSERT IGNORE INTO `pa_system_role` (`id`,`name`,`desc`,`sort`,`create_time`,`update_time`)
VALUES (1, '普通管理员', '系统预置角色（仅菜单管理权限，演示用）', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT IGNORE INTO `pa_system_role_menu` (`role_id`,`menu_id`) VALUES
  (1,1),(1,2),(1,3),(1,4),(1,5);

SET FOREIGN_KEY_CHECKS = 1;
