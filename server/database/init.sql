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
  KEY `idx_admin_id` (`admin_id`)
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
  KEY `idx_role_id` (`role_id`)
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

-- 超级管理员（密码：admin123456）
INSERT IGNORE INTO `pa_admin` (`username`,`nickname`,`password`,`salt`,`root`,`create_time`,`update_time`)
VALUES ('admin','超级管理员', MD5(CONCAT(MD5('admin123456'),'abcd1234')), 'abcd1234', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;
