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

-- 字典类型
CREATE TABLE IF NOT EXISTS `pa_dict_type` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL DEFAULT '' COMMENT '字典名称',
  `type`        VARCHAR(100) NOT NULL DEFAULT '' COMMENT '字典类型（英文标识）',
  `is_disable`  TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否禁用：0启用 1禁用',
  `remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`)
  -- 不设 UNIQUE(type)：软删除保留行，唯一键会阻止类型标识复用；
  -- 类型唯一性由 DictTypeLogic::typeExists 在活跃记录范围内保证。
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='字典类型';

-- 字典数据
CREATE TABLE IF NOT EXISTS `pa_dict_data` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL DEFAULT '' COMMENT '数据名称',
  `value`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '数据值',
  `type_id`     INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '字典类型id',
  `type_value`  VARCHAR(100) NOT NULL DEFAULT '' COMMENT '冗余：字典类型标识（随类型编辑级联更新）',
  `sort`        SMALLINT     NOT NULL DEFAULT 0  COMMENT '排序',
  `is_disable`  TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否禁用：0启用 1禁用',
  `remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='字典数据';

-- 文件分类
CREATE TABLE IF NOT EXISTS `pa_file_cate` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`         INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '父级ID',
  `type`        TINYINT(2)   NOT NULL DEFAULT 10 COMMENT '类型：10图片 20视频 30文件',
  `name`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '分类名称',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件分类';

-- 文件（素材）
CREATE TABLE IF NOT EXISTS `pa_file` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cid`         INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '分类ID',
  `source_id`   INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '上传者ID',
  `source`      TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '来源：0后台 1用户',
  `type`        TINYINT(2)   NOT NULL DEFAULT 10 COMMENT '类型：10图片 20视频 30文件',
  `name`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '文件名称',
  `uri`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT '相对路径',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`cid`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件';

-- 定时任务（由 `php think crontab` 每分钟被系统 cron 调度，逐条比对 cron 表达式并派发 console 命令）
CREATE TABLE IF NOT EXISTS `pa_crontab` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL DEFAULT '' COMMENT '任务名称',
  `type`        TINYINT(1)   NOT NULL DEFAULT 1  COMMENT '类型：1定时任务',
  `command`     VARCHAR(100) NOT NULL DEFAULT '' COMMENT '命令（think console 命令名）',
  `params`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '命令参数（空格分隔）',
  `status`      TINYINT(1)   NOT NULL DEFAULT 2  COMMENT '状态：1运行 2停止 3错误',
  `expression`  VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'crontab 运行规则',
  `error`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '最近一次错误信息',
  `last_time`   INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '最后执行时间',
  `time`        DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '最近一次耗时（秒）',
  `max_time`    DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '最大耗时（秒）',
  `sort`        INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '排序',
  `remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='定时任务';

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

-- ─── 会员体系 ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `pa_member` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sn`          VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '会员编号',
  `nickname`    VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '昵称',
  `avatar`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
  `mobile`      VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '手机号',
  `email`       VARCHAR(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `sex`         TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '性别：0未知 1男 2女',
  `birthday`    DATE                  DEFAULT NULL COMMENT '生日',
  `status`      TINYINT(1)   NOT NULL DEFAULT 1  COMMENT '状态：0禁用 1正常',
  `balance`     DECIMAL(10,2) NOT NULL DEFAULT 0  COMMENT '余额（元）',
  `points`      INT UNSIGNED  NOT NULL DEFAULT 0  COMMENT '积分',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员';

CREATE TABLE IF NOT EXISTS `pa_member_tag` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '标签名称',
  `remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员标签';

CREATE TABLE IF NOT EXISTS `pa_member_tag_relation` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `tag_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_tag` (`member_id`, `tag_id`),
  KEY `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员标签关联';

-- 会员余额变动记录
CREATE TABLE IF NOT EXISTS `pa_member_balance_log` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `member_id`     INT UNSIGNED  NOT NULL DEFAULT 0  COMMENT '会员ID',
  `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0  COMMENT '变动金额（正增负减）',
  `after_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0  COMMENT '变动后余额',
  `source_type`   TINYINT(2)    NOT NULL DEFAULT 0  COMMENT '来源：0手动调整',
  `remark`        VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '备注',
  `admin_id`      INT UNSIGNED  NOT NULL DEFAULT 0  COMMENT '操作管理员',
  `create_time`   INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员余额变动记录';

-- ─── 会员菜单种子 ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (20, 0, 'M', '会员管理',   'icon-user',       80, '',                   '/member',            '',                         0, 1, 0),
  (21,20, 'C', '会员列表',   'icon-user-group',  90, 'member/lists',       '/member/list',       'member/list/index',        0, 1, 0),
  (22,21, 'A', '会员详情',   '',                  0, 'member/detail',      '',                   '',                         0, 1, 0),
  (23,21, 'A', '会员状态',   '',                  0, 'member/status',      '',                   '',                         0, 1, 0),
  (24,21, 'A', '余额调整',   '',                  0, 'member/adjustBalance','',                  '',                         0, 1, 0),
  (25,20, 'C', '会员标签',   'icon-tag',         80, 'member/tag/lists',   '/member/tag',        'member/tag/index',         0, 1, 0),
  (26,25, 'A', '标签新增',   '',                  0, 'member/tag/add',     '',                   '',                         0, 1, 0),
  (27,25, 'A', '标签编辑',   '',                  0, 'member/tag/edit',    '',                   '',                         0, 1, 0),
  (28,25, 'A', '标签删除',   '',                  0, 'member/tag/delete',  '',                   '',                         0, 1, 0);

-- ─── 通知基建 ────────────────────────────────────────────────────────────────

-- 通知模板
CREATE TABLE IF NOT EXISTS `pa_notice_template` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL DEFAULT '' COMMENT '模板名称',
  `code`        VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '模板标识（英文）',
  `channel`     TINYINT(2)   NOT NULL DEFAULT 1  COMMENT '渠道：1短信 2邮件 3推送',
  `title`       VARCHAR(200) NOT NULL DEFAULT '' COMMENT '标题（邮件/推送用）',
  `content`     TEXT                              COMMENT '内容（支持变量{xxx}）',
  `is_disable`  TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否禁用：0启用 1禁用',
  `remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`),
  KEY `idx_channel` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知模板';

-- 通知发送记录
CREATE TABLE IF NOT EXISTS `pa_notice_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '模板ID',
  `channel`     TINYINT(2)   NOT NULL DEFAULT 1  COMMENT '渠道：1短信 2邮件 3推送',
  `receiver`    VARCHAR(200) NOT NULL DEFAULT '' COMMENT '接收者（手机号/邮箱/设备token）',
  `title`       VARCHAR(200) NOT NULL DEFAULT '' COMMENT '标题快照',
  `content`     TEXT                              COMMENT '内容快照（已替换变量）',
  `status`      TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '状态：0待发 1成功 2失败',
  `error`       VARCHAR(500) NOT NULL DEFAULT '' COMMENT '错误信息',
  `extra`       TEXT                              COMMENT '额外数据（JSON）',
  `send_time`   INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '发送时间',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_channel` (`channel`),
  KEY `idx_status` (`status`),
  KEY `idx_send_time` (`send_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知发送记录';

-- ─── 通知菜单种子 ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (30, 0, 'M', '通知管理',   'icon-notification', 70, '',                      '/notice',              '',                         0, 1, 0),
  (31,30, 'C', '渠道配置',   'icon-settings',     90, 'notice/channel/detail', '/notice/channel',      'notice/channel/index',     0, 1, 0),
  (32,31, 'A', '渠道保存',   '',                   0, 'notice/channel/save',   '',                     '',                         0, 1, 0),
  (33,30, 'C', '模板管理',   'icon-file',         80, 'notice/template/lists', '/notice/template',     'notice/template/index',    0, 1, 0),
  (34,33, 'A', '模板新增',   '',                   0, 'notice/template/add',   '',                     '',                         0, 1, 0),
  (35,33, 'A', '模板编辑',   '',                   0, 'notice/template/edit',  '',                     '',                         0, 1, 0),
  (36,33, 'A', '模板删除',   '',                   0, 'notice/template/delete','',                     '',                         0, 1, 0),
  (37,30, 'C', '发送日志',   'icon-history',      70, 'notice/log/lists',      '/notice/log',          'notice/log/index',         0, 1, 0);

-- ─── 财务菜单种子 ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (38, 0, 'M', '财务管理', 'icon-fingerprint', 60, '',                              '/finance',             '',                          0, 1, 0),
  (39,38, 'C', '账户流水', 'icon-bar-chart',   90, 'finance/account-log/lists',     '/finance/account-log', 'finance/account-log/index', 0, 1, 0);

-- ─── 应用设置基建（热门搜索词表）─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pa_hot_search` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(200)    NOT NULL DEFAULT '' COMMENT '搜索词',
  `sort`        SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序（倒序）',
  `create_time` INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='热门搜索词';

-- ─── 应用设置菜单种子 ──────────────────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (40, 0, 'M', '应用设置', 'icon-apps',        50, '',                                '/app-setting',                    '',                                     0, 1, 0),
  (41,40, 'C', '热门搜索', 'icon-search',      90, 'setting/hot-search/config',       '/app-setting/hot-search',         'app-setting/hot-search/index',         0, 1, 0),
  (42,41, 'A', '热门搜索保存', '',              0, 'setting/hot-search/save',         '',                                '',                                     0, 1, 0),
  (43,40, 'C', '客服设置', 'icon-customer-service', 80, 'setting/customer-service/config', '/app-setting/customer-service',  'app-setting/customer-service/index',   0, 1, 0),
  (44,43, 'A', '客服设置保存', '',              0, 'setting/customer-service/save',   '',                                '',                                     0, 1, 0);

SET FOREIGN_KEY_CHECKS = 1;
