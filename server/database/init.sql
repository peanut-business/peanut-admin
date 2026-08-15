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
  `login_time`  INT UNSIGNED NOT NULL DEFAULT 0,
  `login_ip`    VARCHAR(45)  NOT NULL DEFAULT '',
  `multipoint_login` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否允许多处登录：0否 1是',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`)
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
  KEY `idx_role_id` (`role_id`),
  UNIQUE KEY `uk_admin_role` (`admin_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员角色关联';

CREATE TABLE IF NOT EXISTS `pa_admin_dept` (
  `admin_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `dept_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`admin_id`, `dept_id`),
  KEY `idx_dept_id` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员部门关联';

CREATE TABLE IF NOT EXISTS `pa_admin_jobs` (
  `admin_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `jobs_id`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`admin_id`, `jobs_id`),
  KEY `idx_jobs_id` (`jobs_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员岗位关联';

CREATE TABLE IF NOT EXISTS `pa_admin_session` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `terminal`    TINYINT(1)   NOT NULL DEFAULT 1,
  `token`       VARCHAR(64)  NOT NULL DEFAULT '',
  `login_ip`    VARCHAR(45)  NOT NULL DEFAULT '',
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `expire_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_terminal` (`admin_id`, `terminal`),
  UNIQUE KEY `uk_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员会话';

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

-- H5 网页渠道默认配置；存在管理员配置时不覆盖。
INSERT IGNORE INTO `pa_config` (`type`,`name`,`value`) VALUES
  ('web_page', 'status',      '1'),
  ('web_page', 'page_status', '0'),
  ('web_page', 'page_url',    ''),
  ('mnp_setting', 'name',        ''),
  ('mnp_setting', 'original_id', ''),
  ('mnp_setting', 'qr_code',     ''),
  ('mnp_setting', 'app_id',      ''),
  ('mnp_setting', 'app_secret',  '');

CREATE TABLE IF NOT EXISTS `pa_jobs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '岗位名称',
  `code`        VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '岗位编码',
  `sort`        SMALLINT     NOT NULL DEFAULT 0  COMMENT '排序',
  `is_disable`  TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否禁用：0启用 1禁用',
  `status`      TINYINT(1)   NOT NULL DEFAULT 1  COMMENT '状态：0停用 1正常',
  `remark`      VARCHAR(200) NOT NULL DEFAULT '' COMMENT '备注',
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
  `storage`     VARCHAR(20)  NOT NULL DEFAULT 'local' COMMENT '存储引擎',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`cid`),
  KEY `idx_type` (`type`),
  KEY `idx_type_cid_source` (`type`,`cid`,`source`)
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

INSERT INTO `pa_crontab`
  (`name`,`type`,`command`,`params`,`status`,`expression`,`error`,`last_time`,`time`,`max_time`,`sort`,`remark`,`create_time`,`update_time`)
SELECT '退款状态收敛', 1, 'refund:reconcile', '', 1, '* * * * *', '', 0, 0, 0, 100,
       '查询支付渠道并收敛充值退款最终状态', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_crontab` WHERE `command` = 'refund:reconcile'
);

UPDATE `pa_crontab`
SET `name` = '退款状态收敛',
    `type` = 1,
    `params` = '',
    `status` = 1,
    `expression` = '* * * * *',
    `error` = '',
    `sort` = 100,
    `remark` = '查询支付渠道并收敛充值退款最终状态',
    `update_time` = UNIX_TIMESTAMP(),
    `delete_time` = NULL
WHERE `command` = 'refund:reconcile';

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
  (13,10, 'A', '管理员删除', '',                0, 'admin/delete','',              '',                    0, 1, 0),
  (14, 0, 'M', '仪表盘',     'icon-dashboard', 110, '',                    '/dashboard',           '',                          0, 1, 0),
  (15,14, 'C', '工作台',     'icon-dashboard', 100, 'workbench/index',     '/dashboard/workplace', 'dashboard/workplace/index', 0, 1, 0),
  (16, 1, 'C', '岗位管理',   'icon-idcard',      60, 'jobs/lists',  '/system/jobs',  'system/jobs/index',   0, 1, 0),
  (17,16, 'A', '岗位新增',   '',                30, 'jobs/add',    '',              '',                    0, 1, 0),
  (18,16, 'A', '岗位编辑',   '',                20, 'jobs/edit',   '',              '',                    0, 1, 0),
  (19,16, 'A', '岗位删除',   '',                10, 'jobs/delete', '',              '',                    0, 1, 0);

-- ─── 默认角色（普通管理员，仅授予「菜单管理」权限，用于演示 RBAC 限制）──────────
INSERT IGNORE INTO `pa_system_role` (`id`,`name`,`desc`,`sort`,`create_time`,`update_time`)
VALUES (1, '普通管理员', '系统预置角色（仅菜单管理权限，演示用）', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT IGNORE INTO `pa_system_role_menu` (`role_id`,`menu_id`) VALUES
  (1,1),(1,2),(1,3),(1,4),(1,5);

-- ─── 会员体系 ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `pa_member` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sn`          VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '会员编号',
  `account`     VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '用户账号',
  `password`    VARCHAR(100) NOT NULL DEFAULT '' COMMENT '用户密码',
  `nickname`    VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '昵称',
  `avatar`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
  `real_name`   VARCHAR(32)  NOT NULL DEFAULT '' COMMENT '真实姓名',
  `mobile`      VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '手机号',
  `channel`     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '注册来源：1小程序 2公众号 3手机H5 4电脑PC 5苹果APP 6安卓APP',
  `email`       VARCHAR(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `sex`         TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '性别：0未知 1男 2女',
  `birthday`    DATE                  DEFAULT NULL COMMENT '生日',
  `status`      TINYINT(1)   NOT NULL DEFAULT 1  COMMENT '状态：0禁用 1正常',
  `login_time`  INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '最后登录时间',
  `login_ip`    VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `is_new_user` TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '是否新用户：0否 1是',
  `balance`     DECIMAL(10,2) NOT NULL DEFAULT 0  COMMENT '余额（元）',
  `user_money`  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户可用余额',
  `total_recharge_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计充值金额',
  `points`      INT UNSIGNED  NOT NULL DEFAULT 0  COMMENT '积分',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_account` (`account`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_status` (`status`),
  KEY `idx_channel` (`channel`),
  KEY `idx_create_time` (`create_time`)
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
  `sn`            VARCHAR(32)   NOT NULL DEFAULT '' COMMENT '流水号',
  `member_id`     INT UNSIGNED  NOT NULL DEFAULT 0  COMMENT '会员ID',
  `change_object` TINYINT(2) UNSIGNED NOT NULL DEFAULT 1 COMMENT '变动对象：1用户余额',
  `change_type`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '变动类型：100/101/200/201',
  `action`        TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '动作：1增加 2减少',
  `change_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '变动金额（无符号）',
  `left_amount`   DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '变动后余额',
  `after_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Peanut旧字段，兼容镜像',
  `source_type`   TINYINT(2) NOT NULL DEFAULT 0 COMMENT 'Peanut旧字段',
  `source_sn`     VARCHAR(255) NULL DEFAULT NULL COMMENT '来源单号',
  `remark`        VARCHAR(255) NULL DEFAULT '' COMMENT '备注',
  `extra`         TEXT NULL COMMENT '扩展数据JSON',
  `admin_id`      INT UNSIGNED  NOT NULL DEFAULT 0  COMMENT '操作管理员',
  `create_time`   INT UNSIGNED  NOT NULL DEFAULT 0,
  `update_time`   INT UNSIGNED  NULL DEFAULT NULL,
  `delete_time`   INT UNSIGNED  NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_change_type` (`change_type`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员余额变动记录';

-- ─── 会员菜单种子 ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (20, 0, 'M', '会员管理',   'icon-user',       80, '',                   '/member',            '',                         0, 1, 0),
  (21,20, 'C', '会员列表',   'icon-user-group',  90, 'member/lists',       '/member/list',       'member/list/index',        0, 1, 0),
  (22,21, 'A', '会员详情',   '',                  0, 'member/detail',      '',                   '',                         0, 1, 0),
  (23,21, 'A', '会员状态',   '',                  0, 'member/status',      '',                   '',                         0, 1, 0),
  (24,21, 'A', '余额调整',   '',                  0, 'member/adjustBalance','',                  '',                         0, 1, 0),
  (59,21, 'A', '会员新增',   '',                  0, 'member/add',         '',                   '',                         0, 1, 0),
  (60,21, 'A', '会员编辑',   '',                  0, 'member/edit',        '',                   '',                         0, 1, 0),
  (61,21, 'A', '余额调整',   '',                  0, 'member/adjustMoney', '',                   '',                         0, 1, 0),
  (62,21, 'A', '用户详情',   '',                  0, 'user.user/detail',   '',                   '',                         0, 1, 0),
  (63,21, 'A', '用户编辑',   '',                  0, 'user.user/edit',     '',                   '',                         0, 1, 0),
  (64,21, 'A', '余额调整',   '',                  0, 'user.user/adjustMoney','',                 '',                         0, 1, 0),
  (65,21, 'A', '会员资料编辑','',                  0, 'member/profile/edit','',                  '',                         0, 1, 0),
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

-- 固定通知业务场景
CREATE TABLE IF NOT EXISTS `pa_notice_scene` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(50) NOT NULL DEFAULT '' COMMENT '业务场景标识',
  `name`            VARCHAR(100) NOT NULL DEFAULT '' COMMENT '业务场景名称',
  `description`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '场景说明',
  `recipient`       VARCHAR(50) NOT NULL DEFAULT '用户' COMMENT '接收对象',
  `variables`       JSON NULL COMMENT '可用模板变量',
  `sms_template_id` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '服务商短信模板ID',
  `sms_content`     VARCHAR(500) NOT NULL DEFAULT '' COMMENT '短信内容模板',
  `sms_status`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '短信通知 0-关闭 1-开启',
  `create_time`     INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_sms_status` (`sms_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知业务场景';

INSERT IGNORE INTO `pa_notice_scene`
  (`id`,`code`,`name`,`description`,`recipient`,`variables`,`sms_template_id`,`sms_content`,`sms_status`,`create_time`,`update_time`) VALUES
  (1,'login_code','登录验证码','用户使用手机号验证码登录','用户',JSON_ARRAY('code'),'','您的登录验证码是${code}，五分钟内有效。',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,'bind_mobile','绑定手机验证码','用户首次绑定手机号','用户',JSON_ARRAY('code'),'','您的绑定手机验证码是${code}，五分钟内有效。',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,'change_mobile','变更手机验证码','用户更换已绑定手机号','用户',JSON_ARRAY('code'),'','您的变更手机验证码是${code}，五分钟内有效。',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,'reset_password','找回密码验证码','用户通过手机号重置密码','用户',JSON_ARRAY('code'),'','您的找回密码验证码是${code}，五分钟内有效。',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 通知发送记录
CREATE TABLE IF NOT EXISTS `pa_notice_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '模板ID',
  `scene_id`    INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '业务场景ID',
  `channel`     TINYINT(2)   NOT NULL DEFAULT 1  COMMENT '渠道：1短信 2邮件 3推送',
  `provider`    VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '发送服务商',
  `receiver`    VARCHAR(200) NOT NULL DEFAULT '' COMMENT '接收者（手机号/邮箱/设备token）',
  `title`       VARCHAR(200) NOT NULL DEFAULT '' COMMENT '标题快照',
  `content`     TEXT                              COMMENT '内容快照（已替换变量）',
  `verify_code` VARCHAR(10)  NOT NULL DEFAULT '' COMMENT '验证码',
  `is_verified` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否已验证',
  `check_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '校验次数',
  `verified_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '验证时间',
  `status`      TINYINT(1)   NOT NULL DEFAULT 0  COMMENT '状态：0待发 1成功 2失败',
  `error`       VARCHAR(500) NOT NULL DEFAULT '' COMMENT '错误信息',
  `extra`       TEXT                              COMMENT '额外数据（JSON）',
  `send_time`   INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '发送时间',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_scene_receiver` (`scene_id`,`receiver`,`status`,`send_time`),
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
  (33,30, 'C', '通知场景',   'icon-file',         80, 'notice/scene/lists',    '/notice/template',     'notice/template/index',    0, 1, 0),
  (34,33, 'A', '模板新增',   '',                   0, 'notice/template/add',   '',                     '',                         0, 1, 0),
  (35,33, 'A', '模板编辑',   '',                   0, 'notice/template/edit',  '',                     '',                         0, 1, 0),
  (36,33, 'A', '模板删除',   '',                   0, 'notice/template/delete','',                     '',                         0, 1, 0),
  (37,30, 'C', '发送日志',   'icon-history',      70, 'notice/log/lists',      '/notice/log',          'notice/log/index',         0, 1, 0),
  (73,33, 'A', '场景详情',   '',                   0, 'notice/scene/detail',     '',                     '',                         0, 1, 0),
  (74,33, 'A', '场景设置',   '',                   0, 'notice/scene/save',       '',                     '',                         0, 1, 0),
  (75,37, 'A', '日志详情',   '',                   0, 'notice/log/detail',       '',                     '',                         0, 1, 0);

-- ─── 财务菜单种子 ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (38, 0, 'M', '财务管理', 'icon-fingerprint', 60, '',                              '/finance',             '',                          0, 1, 0),
  (39,38, 'C', '余额明细', 'icon-bar-chart',   90, 'finance.account_log/lists',     '/finance/account-log', 'finance/account-log/index', 0, 1, 0);

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

-- H5 网页渠道动作权限复用渠道配置页面（ID 延续当前初始化种子区间）。
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (95,55, 'A', 'H5渠道查看', '', 0, 'setting/web-page/config', '', '', 0, 1, 0),
  (96,55, 'A', 'H5渠道保存', '', 0, 'setting/web-page/save',   '', '', 0, 1, 0),
  (97,55, 'A', '小程序配置查看', '', 0, 'setting/mini-program/config', '', '', 0, 1, 0),
  (98,55, 'A', '小程序配置保存', '', 0, 'setting/mini-program/save',   '', '', 0, 1, 0);

-- ─── 内容管理基建（文章分类 + 文章）────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pa_article_cate` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(90)     NOT NULL DEFAULT '' COMMENT '分类名称',
  `sort`        INT             NOT NULL DEFAULT 0 COMMENT '排序（倒序）',
  `is_show`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否显示 0否1是',
  `create_time` INT UNSIGNED    NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED    NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED    NULL     DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章分类';

CREATE TABLE IF NOT EXISTS `pa_article` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `cid`         INT             NOT NULL DEFAULT 0 COMMENT '文章分类',
  `title`       VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '文章标题',
  `desc`        VARCHAR(255)    NULL DEFAULT '' COMMENT '简介',
  `abstract`    TEXT            NULL COMMENT '文章摘要',
  `image`       VARCHAR(128)    NULL DEFAULT NULL COMMENT '文章图片',
  `author`      VARCHAR(255)    NULL DEFAULT '' COMMENT '作者',
  `content`     TEXT            NULL COMMENT '文章内容',
  `click_virtual` INT           NULL DEFAULT 0 COMMENT '虚拟浏览量',
  `click_actual`  INT           NULL DEFAULT 0 COMMENT '实际浏览量',
  `sort`        INT             NULL DEFAULT 0 COMMENT '排序（倒序）',
  `is_show`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否显示 0否1是',
  `create_time` INT UNSIGNED    NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED    NOT NULL DEFAULT 0,
  `delete_time` INT UNSIGNED    NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章';

-- ─── 内容管理菜单种子 ──────────────────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (45, 0, 'M', '内容管理', 'icon-file',        55, '',                       '/article',              '',                       0, 1, 0),
  (46,45, 'C', '文章分类', 'icon-folder',      90, 'article.articleCate/lists',       '/article/cate',         'article/cate/index',     0, 1, 0),
  (47,46, 'A', '分类新增', '',                  0, 'article.articleCate/add',         '',                       '',                       0, 1, 0),
  (48,46, 'A', '分类编辑', '',                  0, 'article.articleCate/edit',        '',                       '',                       0, 1, 0),
  (49,46, 'A', '分类删除', '',                  0, 'article.articleCate/delete',      '',                       '',                       0, 1, 0),
  (50,45, 'C', '文章管理', 'icon-file',        80, 'article.article/lists',          '/article/list',         'article/list/index',     0, 1, 0),
  (51,50, 'A', '文章新增', '',                  0, 'article.article/add',            '',                       '',                       0, 1, 0),
  (52,50, 'A', '文章编辑', '',                  0, 'article.article/edit',           '',                       '',                       0, 1, 0),
  (53,50, 'A', '文章删除', '',                  0, 'article.article/delete',         '',                       '',                       0, 1, 0),
  (69,46, 'A', '分类详情', '',                  0, 'article.articleCate/detail',      '',                       '',                       0, 1, 0),
  (70,46, 'A', '分类状态', '',                  0, 'article.articleCate/updateStatus','',                       '',                       0, 1, 0),
  (71,50, 'A', '文章详情', '',                  0, 'article.article/detail',         '',                       '',                       0, 1, 0),
  (72,50, 'A', '文章状态', '',                  0, 'article.article/updateStatus',   '',                       '',                       0, 1, 0);

-- ─── M02 素材管理菜单与 API 权限种子 ──────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (84, 1, 'C', '素材管理',   'icon-folder', 50, 'file/lists',       '/system/file', 'system/file/index', 0, 1, 0),
  (85,84, 'A', '分类查看',   '',             0, 'file/cate/lists',  '',             '',                  0, 1, 0),
  (86,84, 'A', '上传图片',   '',             0, 'upload/image',     '',             '',                  0, 1, 0),
  (87,84, 'A', '上传视频',   '',             0, 'upload/video',     '',             '',                  0, 1, 0),
  (88,84, 'A', '上传文件',   '',             0, 'upload/file',      '',             '',                  0, 1, 0),
  (89,84, 'A', '分类新增',   '',             0, 'file/cate/add',    '',             '',                  0, 1, 0),
  (90,84, 'A', '分类编辑',   '',             0, 'file/cate/edit',   '',             '',                  0, 1, 0),
  (91,84, 'A', '分类删除',   '',             0, 'file/cate/delete', '',             '',                  0, 1, 0),
  (92,84, 'A', '素材移动',   '',             0, 'file/move',        '',             '',                  0, 1, 0),
  (93,84, 'A', '素材重命名', '',             0, 'file/rename',      '',             '',                  0, 1, 0),
  (94,84, 'A', '素材删除',   '',             0, 'file/delete',      '',             '',                  0, 1, 0);


-- 文章收藏
CREATE TABLE IF NOT EXISTS `pa_article_collect` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员ID',
  `article_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文章ID',
  `status`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '收藏状态 0-未收藏 1-已收藏',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_time` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_article` (`member_id`, `article_id`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章收藏';

-- ─── 充值订单表 ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pa_recharge_order` (
  `id`                    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `sn`                    VARCHAR(64)    NOT NULL COMMENT '充值订单编号',
  `user_id`               INT UNSIGNED   NOT NULL DEFAULT 0 COMMENT '用户ID',
  `pay_sn`                VARCHAR(255)   NULL DEFAULT '' COMMENT '支付编号',
  `pay_way`               TINYINT(2)     NOT NULL DEFAULT 2 COMMENT '支付方式：1余额 2微信 3支付宝',
  `pay_status`            TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '支付状态：0未支付 1已支付',
  `pay_time`              INT UNSIGNED   NULL DEFAULT NULL COMMENT '支付时间',
  `order_amount`          DECIMAL(10,2)  NOT NULL DEFAULT 0 COMMENT '充值金额',
  `order_terminal`        TINYINT(1)     NULL DEFAULT 1 COMMENT '支付终端',
  `transaction_id`        VARCHAR(128)   NULL DEFAULT NULL COMMENT '第三方交易流水号',
  `refund_status`         TINYINT(1)     NOT NULL DEFAULT 0 COMMENT '是否已发起退款：0否 1是',
  `refund_transaction_id` VARCHAR(255)   NULL DEFAULT NULL COMMENT '退款交易流水号',
  `create_time`           INT UNSIGNED   NULL DEFAULT NULL,
  `update_time`           INT UNSIGNED   NULL DEFAULT NULL,
  `delete_time`           INT UNSIGNED   NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_pay_status` (`pay_status`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值订单';

-- ─── 新增应用设置 + 财务菜单种子 ──────────────────────────────────────────────
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  -- 应用设置子项
  (54,40,'C','支付配置',  'icon-payment',    70, 'setting/pay/config',      '/app-setting/pay',      'app-setting/pay/index',      0,1,0),
  (55,40,'C','渠道配置',  'icon-share-alt',  60, 'setting/channel/config',  '/app-setting/channel',  'app-setting/channel/index',  0,1,0),
  (56,40,'C','页面装修',  'icon-brush',      50, 'setting/decorate/config', '/app-setting/decorate', 'app-setting/decorate/index', 0,1,0),
  -- 财务子项
  (57,38,'C','充值记录',  'icon-thunderbolt', 80, 'recharge.recharge/lists', '/finance/recharge',     'finance/recharge/index',     0,1,0);

-- ─── 退款模块（2026-07-27） ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `pa_refund_log` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `sn`            VARCHAR(32)  DEFAULT NULL,
  `record_id`     INT          NOT NULL,
  `user_id`       INT          NOT NULL DEFAULT 0,
  `handle_id`     INT          NOT NULL DEFAULT 0,
  `order_amount`  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `refund_msg`    TEXT         DEFAULT NULL,
  `create_time`   INT UNSIGNED DEFAULT 0,
  `update_time`   INT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款日志';

CREATE TABLE IF NOT EXISTS `pa_refund_record` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `sn`             VARCHAR(32)  NOT NULL DEFAULT '',
  `user_id`        INT          NOT NULL DEFAULT 0,
  `order_id`       INT          NOT NULL DEFAULT 0,
  `order_sn`       VARCHAR(64)  NOT NULL,
  `order_type`     VARCHAR(255) DEFAULT 'order',
  `order_amount`   DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_amount`  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `transaction_id` VARCHAR(255) DEFAULT NULL,
  `refund_way`     TINYINT(1)   NOT NULL DEFAULT 1,
  `refund_type`    TINYINT(1)   NOT NULL DEFAULT 1,
  `refund_status`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `refund_msg`     TEXT         DEFAULT NULL,
  `create_time`    INT UNSIGNED DEFAULT 0,
  `update_time`    INT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  UNIQUE KEY `uk_order_type_order_id` (`order_type`,`order_id`),
  KEY `idx_user_id`      (`user_id`),
  KEY `idx_order_sn`     (`order_sn`),
  KEY `idx_refund_status`(`refund_status`),
  KEY `idx_create_time`  (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款记录';

-- 退款菜单种子
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (58,38,'C','退款记录','icon-undo',70,'finance.refund/record','/finance/refund','finance/refund/index',0,1,0),
  (66,57,'A','退款','',0,'recharge.recharge/refund','','',0,1,0),
  (67,58,'A','重新退款','',0,'recharge.recharge/refundAgain','','',0,1,0),
  (68,58,'A','退款日志','',0,'finance.refund/log','','',0,1,0);

SET FOREIGN_KEY_CHECKS = 1;


SET NAMES utf8mb4;

-- A02 管理端认证会话增量：先执行本迁移，再发布对应 PHP 代码。
-- MySQL 不支持 ADD COLUMN IF NOT EXISTS，使用 information_schema 保持幂等。
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

CREATE TABLE IF NOT EXISTS `pa_admin_session` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '管理员ID',
    `terminal`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '登录终端：1 PC，2 Mobile',
    `token`       CHAR(64)     NOT NULL COMMENT '服务端会话令牌',
    `login_ip`    VARCHAR(45)  NOT NULL DEFAULT '' COMMENT '该会话绑定的登录IP',
    `update_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最近签发/续期时间',
    `expire_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '到期时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admin_terminal` (`admin_id`, `terminal`),
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员会话';

-- 兼容已执行过 A02 初版迁移的环境。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_admin_session' AND COLUMN_NAME = 'login_ip') = 0,
    'ALTER TABLE `pa_admin_session` ADD COLUMN `login_ip` VARCHAR(45) NOT NULL DEFAULT '''' COMMENT ''该会话绑定的登录IP'' AFTER `token`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;


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


SET NAMES utf8mb4;

-- U01 用户列表契约增量；已有 Peanut 扩展字段保持不变。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'account') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `account` VARCHAR(50) NOT NULL DEFAULT '''' COMMENT ''用户账号'' AFTER `sn`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'password') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `password` VARCHAR(100) NOT NULL DEFAULT '''' COMMENT ''用户密码'' AFTER `account`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'real_name') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `real_name` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''真实姓名'' AFTER `avatar`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'channel') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `channel` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''注册来源：1小程序 2公众号 3手机H5 4电脑PC 5苹果APP 6安卓APP'' AFTER `mobile`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'login_time') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `login_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''最后登录时间'' AFTER `status`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'login_ip') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `login_ip` VARCHAR(45) NOT NULL DEFAULT '''' COMMENT ''最后登录IP'' AFTER `login_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'is_new_user') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `is_new_user` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''是否新用户：0否 1是'' AFTER `login_ip`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'total_recharge_amount') = 0,
    'ALTER TABLE `pa_member` ADD COLUMN `total_recharge_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''累计充值金额'' AFTER `balance`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND INDEX_NAME = 'idx_account') = 0,
    'ALTER TABLE `pa_member` ADD INDEX `idx_account` (`account`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND INDEX_NAME = 'idx_channel') = 0,
    'ALTER TABLE `pa_member` ADD INDEX `idx_channel` (`channel`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_member` ADD INDEX `idx_create_time` (`create_time`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- Peanut 保留后台新增/编辑用户扩展，因此对应按钮/API 必须显式登记权限。
SET @pa_member_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `paths` = '/member/list' AND `type` = 'C'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '会员新增', '', 0, 'member/add', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/add');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '会员编辑', '', 0, 'member/edit', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/edit');


SET NAMES utf8mb4;

-- O03 角色契约增量：为角色启用软删除。
-- 关系表继续物理删除，角色删除操作由 RoleLogic 在同一事务中维护。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_system_role' AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_system_role` ADD COLUMN `delete_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;


SET NAMES utf8mb4;

-- D01 工作台菜单与权限。root 自动拥有；普通角色需在角色授权中显式勾选。
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (14, 0, 'M', '仪表盘', 'icon-dashboard', 110, '',                '/dashboard',           '',                          0, 1, 0),
  (15,14, 'C', '工作台', 'icon-dashboard', 100, 'workbench/index', '/dashboard/workplace', 'dashboard/workplace/index', 0, 1, 0);


SET NAMES utf8mb4;

-- U02 用户余额权威字段；Peanut 原 balance 作兼容镜像保留。
SET @pa_user_money_missing = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'user_money'
) = 0;

SET @pa_sql = IF(
    @pa_user_money_missing,
    'ALTER TABLE `pa_member` ADD COLUMN `user_money` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''用户可用余额'' AFTER `balance`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 只在首次新增权威字段时从旧 balance 初始化；重复执行不得反向覆盖真实余额。
SET @pa_sql = IF(
    @pa_user_money_missing,
    'UPDATE `pa_member` SET `user_money` = `balance` WHERE `user_money` <> `balance`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 账户流水升级为 LikeAdmin 分类模型；旧字段保留供现有调用方渐进迁移。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'sn') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `sn` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''流水号'' AFTER `id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'change_object') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `change_object` TINYINT(2) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''变动对象：1用户余额'' AFTER `member_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'change_type') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `change_type` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''变动类型：100/101/200/201'' AFTER `change_object`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'action') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `action` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT ''动作：1增加 2减少'' AFTER `change_type`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'left_amount') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `left_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''变动后余额'' AFTER `change_amount`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'source_sn') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `source_sn` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''来源单号'' AFTER `source_type`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND COLUMN_NAME = 'extra') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `extra` TEXT NULL COMMENT ''扩展数据JSON'' AFTER `remark`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

-- 只迁移 change_type=0 的旧 signed amount 记录，重复执行不会改写新流水。
UPDATE `pa_member_balance_log`
SET `sn` = CONCAT(DATE_FORMAT(FROM_UNIXTIME(`create_time`), '%Y%m%d%H%i%s'), LPAD(`id`, 6, '0')),
    `change_object` = 1,
    `change_type` = IF(`change_amount` >= 0, 200, 100),
    `action` = IF(`change_amount` >= 0, 1, 2),
    `left_amount` = `after_amount`,
    `change_amount` = ABS(`change_amount`),
    `source_sn` = '',
    `extra` = ''
WHERE `change_type` = 0;

ALTER TABLE `pa_member_balance_log`
    MODIFY COLUMN `change_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '变动金额（无符号）';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD UNIQUE INDEX `uk_sn` (`sn`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND INDEX_NAME = 'idx_change_type') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD INDEX `idx_change_type` (`change_type`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member_balance_log' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD INDEX `idx_create_time` (`create_time`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_member_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `paths` = '/member/list' AND `type` = 'C'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '余额调整', '', 0, 'member/adjustMoney', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/adjustMoney');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '会员资料编辑', '', 0, 'member/profile/edit', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'member/profile/edit');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '用户详情', '', 0, 'user.user/detail', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'user.user/detail');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '用户编辑', '', 0, 'user.user/edit', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'user.user/edit');

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_member_menu_id, 'A', '余额调整', '', 0, 'user.user/adjustMoney', '', '', 0, 1, 0
WHERE @pa_member_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'user.user/adjustMoney');


SET NAMES utf8mb4;

-- F01：LikeAdmin 1.9.4 余额明细数据模型与菜单权限契约。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'source_sn') = 1,
    'ALTER TABLE `pa_member_balance_log` MODIFY COLUMN `source_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT ''来源单号''',
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `source_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT ''来源单号'' AFTER `source_type`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'remark') = 1,
    'ALTER TABLE `pa_member_balance_log` MODIFY COLUMN `remark` VARCHAR(255) NULL DEFAULT '''' COMMENT ''备注''',
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `remark` VARCHAR(255) NULL DEFAULT '''' COMMENT ''备注'' AFTER `source_sn`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'update_time') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `update_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''更新时间'' AFTER `create_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pa_member_balance_log'
       AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_member_balance_log` ADD COLUMN `delete_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT 0, 'M', '财务管理', 'icon-fingerprint', 60, '', '/finance', '', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `type` = 'M' AND `paths` = '/finance'
);

SET @pa_finance_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'M' AND `paths` = '/finance'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_finance_menu_id, 'C', '余额明细', 'icon-bar-chart', 90,
       'finance.account_log/lists', '/finance/account-log',
       'finance/account-log/index', 0, 1, 0
WHERE @pa_finance_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu`
      WHERE `type` = 'C' AND `paths` = '/finance/account-log'
  );

UPDATE `pa_system_menu`
SET `pid` = @pa_finance_menu_id,
    `name` = '余额明细',
    `perms` = 'finance.account_log/lists',
    `component` = 'finance/account-log/index',
    `is_show` = 1,
    `is_disable` = 0
WHERE `type` = 'C' AND `paths` = '/finance/account-log';


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


SET NAMES utf8mb4;

-- M01 通知业务场景：固定场景与通用模板分离，保留 Peanut 自有扩展能力。
CREATE TABLE IF NOT EXISTS `pa_notice_scene` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(50) NOT NULL DEFAULT '' COMMENT '业务场景标识',
  `name`            VARCHAR(100) NOT NULL DEFAULT '' COMMENT '业务场景名称',
  `description`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '场景说明',
  `recipient`       VARCHAR(50) NOT NULL DEFAULT '用户' COMMENT '接收对象',
  `variables`       JSON NULL COMMENT '可用模板变量',
  `sms_template_id` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '服务商短信模板ID',
  `sms_content`     VARCHAR(500) NOT NULL DEFAULT '' COMMENT '短信内容模板',
  `sms_status`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '短信通知 0-关闭 1-开启',
  `create_time`     INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_sms_status` (`sms_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知业务场景';

-- 一张发送记录同时表达发送状态与验证码验证状态，不复制冗余通知记录表。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_notice_log' AND COLUMN_NAME = 'scene_id') = 0,
    'ALTER TABLE `pa_notice_log` ADD COLUMN `scene_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''业务场景ID'' AFTER `template_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_notice_log' AND COLUMN_NAME = 'verify_code') = 0,
    'ALTER TABLE `pa_notice_log` ADD COLUMN `verify_code` VARCHAR(10) NOT NULL DEFAULT '''' COMMENT ''验证码'' AFTER `content`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_notice_log' AND COLUMN_NAME = 'is_verified') = 0,
    'ALTER TABLE `pa_notice_log` ADD COLUMN `is_verified` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT ''是否已验证'' AFTER `verify_code`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_notice_log' AND COLUMN_NAME = 'check_count') = 0,
    'ALTER TABLE `pa_notice_log` ADD COLUMN `check_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''校验次数'' AFTER `is_verified`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_notice_log' AND COLUMN_NAME = 'verified_time') = 0,
    'ALTER TABLE `pa_notice_log` ADD COLUMN `verified_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''验证时间'' AFTER `check_count`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_notice_log' AND COLUMN_NAME = 'provider') = 0,
    'ALTER TABLE `pa_notice_log` ADD COLUMN `provider` VARCHAR(20) NOT NULL DEFAULT '''' COMMENT ''发送服务商'' AFTER `channel`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_notice_log' AND INDEX_NAME = 'idx_scene_receiver') = 0,
    'ALTER TABLE `pa_notice_log` ADD INDEX `idx_scene_receiver` (`scene_id`, `receiver`, `status`, `send_time`)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

INSERT INTO `pa_notice_scene`
  (`code`,`name`,`description`,`recipient`,`variables`,`sms_template_id`,`sms_content`,`sms_status`,`create_time`,`update_time`)
SELECT 'login_code', '登录验证码', '用户使用手机号验证码登录', '用户', JSON_ARRAY('code'), '', '您的登录验证码是${code}，五分钟内有效。', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `pa_notice_scene` WHERE `code` = 'login_code');

INSERT INTO `pa_notice_scene`
  (`code`,`name`,`description`,`recipient`,`variables`,`sms_template_id`,`sms_content`,`sms_status`,`create_time`,`update_time`)
SELECT 'bind_mobile', '绑定手机验证码', '用户首次绑定手机号', '用户', JSON_ARRAY('code'), '', '您的绑定手机验证码是${code}，五分钟内有效。', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `pa_notice_scene` WHERE `code` = 'bind_mobile');

INSERT INTO `pa_notice_scene`
  (`code`,`name`,`description`,`recipient`,`variables`,`sms_template_id`,`sms_content`,`sms_status`,`create_time`,`update_time`)
SELECT 'change_mobile', '变更手机验证码', '用户更换已绑定手机号', '用户', JSON_ARRAY('code'), '', '您的变更手机验证码是${code}，五分钟内有效。', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `pa_notice_scene` WHERE `code` = 'change_mobile');

INSERT INTO `pa_notice_scene`
  (`code`,`name`,`description`,`recipient`,`variables`,`sms_template_id`,`sms_content`,`sms_status`,`create_time`,`update_time`)
SELECT 'reset_password', '找回密码验证码', '用户通过手机号重置密码', '用户', JSON_ARRAY('code'), '', '您的找回密码验证码是${code}，五分钟内有效。', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `pa_notice_scene` WHERE `code` = 'reset_password');

-- 修复早期迁移通过非 UTF-8 客户端执行时产生的问号内容，不覆盖管理员已配置的正常模板。
UPDATE `pa_notice_scene`
SET `sms_content` = '您的登录验证码是${code}，五分钟内有效。', `update_time` = UNIX_TIMESTAMP()
WHERE `code` = 'login_code' AND REPLACE(`sms_content`, '?', '') = '${code}';

UPDATE `pa_notice_scene`
SET `sms_content` = '您的绑定手机验证码是${code}，五分钟内有效。', `update_time` = UNIX_TIMESTAMP()
WHERE `code` = 'bind_mobile' AND REPLACE(`sms_content`, '?', '') = '${code}';

UPDATE `pa_notice_scene`
SET `sms_content` = '您的变更手机验证码是${code}，五分钟内有效。', `update_time` = UNIX_TIMESTAMP()
WHERE `code` = 'change_mobile' AND REPLACE(`sms_content`, '?', '') = '${code}';

UPDATE `pa_notice_scene`
SET `sms_content` = '您的找回密码验证码是${code}，五分钟内有效。', `update_time` = UNIX_TIMESTAMP()
WHERE `code` = 'reset_password' AND REPLACE(`sms_content`, '?', '') = '${code}';

-- 现有模板页面原位升级为固定场景配置，菜单 ID 保持不变以保留角色关系。
SET @pa_notice_scene_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C' AND `paths` = '/notice/template'
    ORDER BY `id` ASC LIMIT 1
);

UPDATE `pa_system_menu`
SET `name` = '通知场景', `perms` = 'notice/scene/lists'
WHERE `id` = @pa_notice_scene_menu_id;

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_notice_scene_menu_id, 'A', '场景详情', '', 0, 'notice/scene/detail', '', '', 0, 1, 0
WHERE @pa_notice_scene_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'notice/scene/detail');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_notice_scene_menu_id, 'A', '场景设置', '', 0, 'notice/scene/save', '', '', 0, 1, 0
WHERE @pa_notice_scene_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'notice/scene/save');

SET @pa_notice_log_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C' AND `paths` = '/notice/log'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_notice_log_menu_id, 'A', '日志详情', '', 0, 'notice/log/detail', '', '', 0, 1, 0
WHERE @pa_notice_log_menu_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'notice/log/detail');


SET NAMES utf8mb4;

-- F02：LikeAdmin 1.9.4 充值、退款模型、幂等状态机与权限契约。
SET @pa_recharge_had_sn = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pa_recharge_order'
      AND COLUMN_NAME = 'sn'
);

CREATE TABLE IF NOT EXISTS `pa_recharge_order` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sn`                    VARCHAR(64)   NOT NULL COMMENT '充值订单编号',
  `user_id`               INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT '用户ID',
  `pay_sn`                VARCHAR(255)  NULL DEFAULT '' COMMENT '支付编号',
  `pay_way`               TINYINT(2)    NOT NULL DEFAULT 2 COMMENT '支付方式：1余额 2微信 3支付宝',
  `pay_status`            TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '支付状态：0未支付 1已支付',
  `pay_time`              INT UNSIGNED  NULL DEFAULT NULL COMMENT '支付时间',
  `order_amount`          DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '充值金额',
  `order_terminal`        TINYINT(1)    NULL DEFAULT 1 COMMENT '支付终端',
  `transaction_id`        VARCHAR(128)  NULL DEFAULT NULL COMMENT '第三方交易流水号',
  `refund_status`         TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '是否已发起退款：0否 1是',
  `refund_transaction_id` VARCHAR(255)  NULL DEFAULT NULL COMMENT '退款交易流水号',
  `create_time`           INT UNSIGNED  NULL DEFAULT NULL,
  `update_time`           INT UNSIGNED  NULL DEFAULT NULL,
  `delete_time`           INT UNSIGNED  NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_pay_status` (`pay_status`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值订单';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'sn') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `sn` VARCHAR(64) NOT NULL DEFAULT '''' COMMENT ''充值订单编号'' AFTER `id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'user_id') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''用户ID'' AFTER `sn`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'pay_sn') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `pay_sn` VARCHAR(255) NULL DEFAULT '''' COMMENT ''支付编号'' AFTER `user_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'pay_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `pay_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''支付状态：0未支付 1已支付'' AFTER `pay_way`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_amount') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT ''充值金额'' AFTER `pay_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_terminal') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `order_terminal` TINYINT(1) NULL DEFAULT 1 COMMENT ''支付终端'' AFTER `order_amount`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'refund_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `refund_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''是否已发起退款：0否 1是'' AFTER `transaction_id`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'refund_transaction_id') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `refund_transaction_id` VARCHAR(255) NULL DEFAULT NULL COMMENT ''退款交易流水号'' AFTER `refund_status`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_recharge_order` ADD COLUMN `delete_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

-- 旧 Peanut 充值表只在首次补 canonical sn 时迁移，避免重跑时再次映射 pay_way。
SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_sn') = 1,
    'UPDATE `pa_recharge_order` SET `sn` = `order_sn` WHERE `sn` = ''''',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'member_id') = 1,
    'UPDATE `pa_recharge_order` SET `user_id` = `member_id`, `pay_way` = CASE `pay_way` WHEN 1 THEN 2 WHEN 2 THEN 3 ELSE `pay_way` END',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'amount') = 1,
    'UPDATE `pa_recharge_order` SET `order_amount` = `amount`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    @pa_recharge_had_sn = 0 AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'status') = 1,
    'UPDATE `pa_recharge_order` SET `pay_status` = IF(`status` = 1, 1, 0)',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_recharge_order`
  MODIFY COLUMN `sn` VARCHAR(64) NOT NULL COMMENT '充值订单编号',
  MODIFY COLUMN `pay_way` TINYINT(2) NOT NULL DEFAULT 2 COMMENT '支付方式：1余额 2微信 3支付宝',
  MODIFY COLUMN `pay_time` INT UNSIGNED NULL DEFAULT NULL COMMENT '支付时间',
  MODIFY COLUMN `transaction_id` VARCHAR(128) NULL DEFAULT NULL COMMENT '第三方交易流水号',
  MODIFY COLUMN `create_time` INT UNSIGNED NULL DEFAULT NULL,
  MODIFY COLUMN `update_time` INT UNSIGNED NULL DEFAULT NULL;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_recharge_order` ADD UNIQUE KEY `uk_sn` (`sn`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_user_id') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_user_id` (`user_id`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_pay_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_pay_status` (`pay_status`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_refund_status') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_refund_status` (`refund_status`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_recharge_order` ADD KEY `idx_create_time` (`create_time`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

CREATE TABLE IF NOT EXISTS `pa_refund_record` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `sn`             VARCHAR(32) NOT NULL DEFAULT '',
  `user_id`        INT NOT NULL DEFAULT 0,
  `order_id`       INT NOT NULL DEFAULT 0,
  `order_sn`       VARCHAR(64) NOT NULL,
  `order_type`     VARCHAR(255) NULL DEFAULT 'order',
  `order_amount`   DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_amount`  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `transaction_id` VARCHAR(255) NULL DEFAULT NULL,
  `refund_way`     TINYINT(1) NOT NULL DEFAULT 1,
  `refund_type`    TINYINT(1) NOT NULL DEFAULT 1,
  `refund_status`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `refund_msg`     TEXT NULL DEFAULT NULL,
  `create_time`    INT UNSIGNED NULL DEFAULT 0,
  `update_time`    INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  UNIQUE KEY `uk_order_type_order_id` (`order_type`,`order_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_order_sn` (`order_sn`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款记录';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND COLUMN_NAME = 'refund_msg') = 0,
    'ALTER TABLE `pa_refund_record` ADD COLUMN `refund_msg` TEXT NULL DEFAULT NULL COMMENT ''退款信息'' AFTER `refund_status`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_refund_record`
  MODIFY COLUMN `sn` VARCHAR(32) NOT NULL DEFAULT '',
  MODIFY COLUMN `order_sn` VARCHAR(64) NOT NULL;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_refund_record` ADD UNIQUE KEY `uk_sn` (`sn`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND INDEX_NAME = 'uk_order_type_order_id') = 0,
    'ALTER TABLE `pa_refund_record` ADD UNIQUE KEY `uk_order_type_order_id` (`order_type`,`order_id`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_record' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_refund_record` ADD KEY `idx_create_time` (`create_time`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

CREATE TABLE IF NOT EXISTS `pa_refund_log` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `sn`            VARCHAR(32) NULL DEFAULT NULL,
  `record_id`     INT NOT NULL,
  `user_id`       INT NOT NULL DEFAULT 0,
  `handle_id`     INT NOT NULL DEFAULT 0,
  `order_amount`  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_amount` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `refund_status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `refund_msg`    TEXT NULL DEFAULT NULL,
  `create_time`   INT UNSIGNED NULL DEFAULT 0,
  `update_time`   INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_refund_status` (`refund_status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款日志';

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_log' AND INDEX_NAME = 'uk_sn') = 0,
    'ALTER TABLE `pa_refund_log` ADD UNIQUE KEY `uk_sn` (`sn`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_log' AND INDEX_NAME = 'idx_refund_status') = 0,
    'ALTER TABLE `pa_refund_log` ADD KEY `idx_refund_status` (`refund_status`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_refund_log' AND INDEX_NAME = 'idx_create_time') = 0,
    'ALTER TABLE `pa_refund_log` ADD KEY `idx_create_time` (`create_time`)', 'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT 0, 'M', '财务管理', 'icon-fingerprint', 60, '', '/finance', '', 0, 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `type` = 'M' AND `paths` = '/finance'
);

SET @pa_finance_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'M' AND `paths` = '/finance'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_finance_menu_id, 'C', '充值记录', 'icon-thunderbolt', 80,
       'recharge.recharge/lists', '/finance/recharge', 'finance/recharge/index', 0, 1, 0
WHERE @pa_finance_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE `type` = 'C' AND `paths` = '/finance/recharge'
  );

UPDATE `pa_system_menu`
SET `pid` = @pa_finance_menu_id,
    `name` = '充值记录',
    `perms` = 'recharge.recharge/lists',
    `component` = 'finance/recharge/index',
    `is_show` = 1,
    `is_disable` = 0
WHERE `type` = 'C' AND `paths` = '/finance/recharge';

SET @pa_recharge_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C' AND `paths` = '/finance/recharge'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_recharge_menu_id, 'A', '退款', '', 0, 'recharge.recharge/refund', '', '', 0, 1, 0
WHERE @pa_recharge_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`) = 'recharge.recharge/refund'
  );

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_finance_menu_id, 'C', '退款记录', 'icon-undo', 70,
       'finance.refund/record', '/finance/refund', 'finance/refund/index', 0, 1, 0
WHERE @pa_finance_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE `type` = 'C' AND `paths` = '/finance/refund'
  );

UPDATE `pa_system_menu`
SET `pid` = @pa_finance_menu_id,
    `name` = '退款记录',
    `perms` = 'finance.refund/record',
    `component` = 'finance/refund/index',
    `is_show` = 1,
    `is_disable` = 0
WHERE `type` = 'C' AND `paths` = '/finance/refund';

SET @pa_refund_menu_id = (
    SELECT `id` FROM `pa_system_menu`
    WHERE `type` = 'C' AND `paths` = '/finance/refund'
    ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_refund_menu_id, 'A', '重新退款', '', 0, 'recharge.recharge/refundAgain', '', '', 0, 1, 0
WHERE @pa_refund_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`) = 'recharge.recharge/refundagain'
  );

INSERT INTO `pa_system_menu`
    (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_refund_menu_id, 'A', '退款日志', '', 0, 'finance.refund/log', '', '', 0, 1, 0
WHERE @pa_refund_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`) = 'finance.refund/log'
  );

INSERT INTO `pa_crontab`
    (`name`,`type`,`command`,`params`,`status`,`expression`,`error`,`last_time`,`time`,`max_time`,`sort`,`remark`,`create_time`,`update_time`)
SELECT '退款状态收敛', 1, 'refund:reconcile', '', 1, '* * * * *', '', 0, 0, 0, 100,
       '查询支付渠道并收敛充值退款最终状态', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM `pa_crontab` WHERE `command` = 'refund:reconcile'
);

UPDATE `pa_crontab`
SET `name` = '退款状态收敛',
    `type` = 1,
    `params` = '',
    `status` = 1,
    `expression` = '* * * * *',
    `error` = '',
    `sort` = 100,
    `remark` = '查询支付渠道并收敛充值退款最终状态',
    `update_time` = UNIX_TIMESTAMP(),
    `delete_time` = NULL
WHERE `command` = 'refund:reconcile';


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


SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `pa_decorate_page` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint unsigned NOT NULL COMMENT '1首页 2个人中心 3客服 4PC首页 5系统风格',
  `name` varchar(100) NOT NULL DEFAULT '',
  `data` longtext NOT NULL,
  `meta` longtext NOT NULL,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_decorate_page_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装修页面';

CREATE TABLE IF NOT EXISTS `pa_decorate_tabbar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `position` tinyint unsigned NOT NULL,
  `name` varchar(20) NOT NULL DEFAULT '',
  `selected` varchar(255) NOT NULL DEFAULT '',
  `unselected` varchar(255) NOT NULL DEFAULT '',
  `link` varchar(1000) NOT NULL DEFAULT '{}',
  `is_show` tinyint unsigned NOT NULL DEFAULT 1,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_decorate_tabbar_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装修 Tabbar';

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 1, '移动端首页',
  '[{"title":"搜索","name":"search","disabled":1,"content":{},"styles":{}},{"title":"首页轮播图","name":"banner","content":{"enabled":1,"style":1,"bg_style":1,"data":[{"is_show":1,"image":"","bg":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{}},{"title":"导航菜单","name":"nav","content":{"enabled":1,"style":2,"per_line":5,"show_line":2,"data":[{"is_show":1,"image":"","name":"资讯中心","link":{"target_type":"shop","target":"news"}}]},"styles":{}},{"title":"首页中部轮播图","name":"middle-banner","content":{"enabled":1,"data":[{"is_show":1,"image":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{}},{"title":"资讯","name":"news","disabled":1,"content":{},"styles":{}}]',
  '[{"title":"页面设置","name":"page-meta","content":{"title":"首页","title_type":1,"title_img":"","bg_type":1,"bg_color":"#2F80ED","bg_image":"","text_color":1},"styles":{}}]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=1);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 2, '个人中心',
  '[{"title":"用户信息","name":"user-info","disabled":1,"content":{},"styles":{}},{"title":"我的服务","name":"my-service","content":{"enabled":1,"style":1,"title":"我的服务","data":[{"is_show":1,"image":"","name":"我的收藏","link":{"target_type":"shop","target":"favorites"}}]},"styles":{}},{"title":"个人中心广告图","name":"user-banner","content":{"enabled":1,"data":[{"is_show":1,"image":"","name":"","link":{"target_type":"shop","target":"profile"}}]},"styles":{}}]',
  '[{"title":"页面设置","name":"page-meta","content":{"title":"个人中心","title_type":1,"title_img":"","bg_type":1,"bg_color":"#2F80ED","bg_image":"","text_color":1},"styles":{}}]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=2);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 3, '客服设置',
  '[{"title":"客服设置","name":"customer-service","content":{"title":"添加客服二维码","time":"9:30 - 19:00","mobile":"","qrcode":"","remark":"长按添加客服或拨打客服热线"},"styles":{}}]',
  '[]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=3);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 4, 'PC 首页',
  '[{"title":"首页轮播图","name":"pc-banner","content":{"enabled":1,"data":[{"image":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{"position":"absolute","left":"40px","top":"75px","width":"750px","height":"340px"}}]',
  '[]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=4);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 5, '系统风格',
  '{"themeColorId":3,"topTextColor":"white","navigationBarColor":"#A74BFD","themeColor1":"#A74BFD","themeColor2":"#CB60FF","buttonColor":"white"}',
  '[]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=5);

INSERT INTO `pa_decorate_tabbar` (`position`,`name`,`selected`,`unselected`,`link`,`is_show`,`create_time`,`update_time`)
SELECT 0,'首页','','','{"target_type":"shop","target":"home"}',1,0,0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_tabbar` WHERE `position`=0);
INSERT INTO `pa_decorate_tabbar` (`position`,`name`,`selected`,`unselected`,`link`,`is_show`,`create_time`,`update_time`)
SELECT 1,'资讯','','','{"target_type":"shop","target":"news"}',1,0,0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_tabbar` WHERE `position`=1);
INSERT INTO `pa_decorate_tabbar` (`position`,`name`,`selected`,`unselected`,`link`,`is_show`,`create_time`,`update_time`)
SELECT 2,'我的','','','{"target_type":"shop","target":"profile"}',1,0,0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_tabbar` WHERE `position`=2);

INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'tabbar','style','{"default_color":"#666666","selected_color":"#2F80ED"}'
WHERE NOT EXISTS (SELECT 1 FROM `pa_config` WHERE `type`='tabbar' AND `name`='style');

-- 旧五键装修配置退出运行时，不保留双读写或兼容层。
DELETE FROM `pa_config` WHERE `type`='decorate';

SET @pa_decoration_root_id = (
  SELECT `id` FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/decoration' ORDER BY `id` LIMIT 1
);
INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT 0,'M','装修管理','icon-palette',30,'','/decoration','',0,1,0
WHERE @pa_decoration_root_id IS NULL;
SET @pa_decoration_root_id = (
  SELECT `id` FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/decoration' ORDER BY `id` LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_decoration_root_id,'C',`seed`.`name`,'',`seed`.`sort`,'',`seed`.`paths`,`seed`.`component`,0,1,0
FROM (
  SELECT '移动端装修' AS `name`,30 AS `sort`,'/decoration/mobile' AS `paths`,'decoration/mobile/index' AS `component`
  UNION ALL SELECT 'Tabbar 装修',20,'/decoration/tabbar','decoration/tabbar/index'
  UNION ALL SELECT 'PC 装修',10,'/decoration/pc','decoration/pc/index'
) AS `seed`
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `paths`=`seed`.`paths`);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT `parent`.`id`,'A',`seed`.`name`,'',0,`seed`.`perms`,'','',0,1,0
FROM (
  SELECT '/decoration/mobile' AS `parent_path`,'移动装修列表' AS `name`,'decoration/mobile/page/lists' AS `perms`
  UNION ALL SELECT '/decoration/mobile','移动装修查看','decoration/mobile/page/detail'
  UNION ALL SELECT '/decoration/mobile','移动装修保存','decoration/mobile/page/save'
  UNION ALL SELECT '/decoration/mobile','装修文章选择','decoration/mobile/article'
  UNION ALL SELECT '/decoration/tabbar','Tabbar 查看','decoration/tabbar/detail'
  UNION ALL SELECT '/decoration/tabbar','Tabbar 保存','decoration/tabbar/save'
  UNION ALL SELECT '/decoration/pc','PC 装修列表','decoration/pc/page/lists'
  UNION ALL SELECT '/decoration/pc','PC 装修查看','decoration/pc/page/detail'
  UNION ALL SELECT '/decoration/pc','PC 装修保存','decoration/pc/page/save'
) AS `seed`
JOIN `pa_system_menu` AS `parent` ON `parent`.`paths`=`seed`.`parent_path` AND `parent`.`type`='C'
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`=`seed`.`perms`);


SET NAMES utf8mb4;

-- CH01 H5 网页渠道：仅补缺失默认值，不覆盖已保存配置。
INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'web_page', 'status', '1'
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` WHERE `type` = 'web_page' AND `name` = 'status'
);

INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'web_page', 'page_status', '0'
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` WHERE `type` = 'web_page' AND `name` = 'page_status'
);

INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'web_page', 'page_url', ''
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` WHERE `type` = 'web_page' AND `name` = 'page_url'
);

SET @pa_app_setting_menu_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `type` = 'M' AND `paths` = '/app-setting'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_app_setting_menu_id, 'C', '渠道配置', 'icon-share-alt', 60,
       'setting/channel/config', '/app-setting/channel', 'app-setting/channel/index', 0, 1, 0
WHERE @pa_app_setting_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `paths` = '/app-setting/channel'
  );

SET @pa_channel_menu_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `type` = 'C' AND `paths` = '/app-setting/channel'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_channel_menu_id, 'A', 'H5渠道查看', '', 0,
       'setting/web-page/config', '', '', 0, 1, 0
WHERE @pa_channel_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'setting/web-page/config'
  );

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_channel_menu_id, 'A', 'H5渠道保存', '', 0,
       'setting/web-page/save', '', '', 0, 1, 0
WHERE @pa_channel_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'setting/web-page/save'
  );


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


SET NAMES utf8mb4;

-- CH02 微信小程序：单一 mnp_setting 配置模型。
INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'mnp_setting', `seed`.`name`, ''
FROM (
  SELECT 'name' AS `name`
  UNION ALL SELECT 'original_id'
  UNION ALL SELECT 'qr_code'
  UNION ALL SELECT 'app_id'
  UNION ALL SELECT 'app_secret'
) AS `seed`
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config`
  WHERE `type` = 'mnp_setting' AND `name` = `seed`.`name`
);

-- 清除未被业务消费的旧小程序三字段，避免形成双字段配置模型。
DELETE FROM `pa_config`
WHERE `type` = 'channel'
  AND `name` IN ('wechat_mini_status','wechat_mini_appid','wechat_mini_secret');

SET @pa_channel_menu_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `type` = 'C' AND `paths` = '/app-setting/channel'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_channel_menu_id, 'A', '小程序配置查看', '', 0,
       'setting/mini-program/config', '', '', 0, 1, 0
WHERE @pa_channel_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'setting/mini-program/config'
  );

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_channel_menu_id, 'A', '小程序配置保存', '', 0,
       'setting/mini-program/save', '', '', 0, 1, 0
WHERE @pa_channel_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `perms` = 'setting/mini-program/save'
  );


SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `pa_official_account_reply` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则名称',
  `keyword` varchar(255) NOT NULL DEFAULT '' COMMENT '关键词',
  `reply_type` tinyint unsigned NOT NULL COMMENT '1关注 2关键词 3默认',
  `matching_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '1全匹配 2模糊匹配',
  `content_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '1文本',
  `content` text NOT NULL COMMENT '回复内容',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '0停用 1启用',
  `sort` int unsigned NOT NULL DEFAULT 0 COMMENT '匹配优先级，升序',
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  `delete_time` int unsigned NOT NULL DEFAULT 0,
  `singleton_active_key` tinyint unsigned GENERATED ALWAYS AS (
    CASE
      WHEN `delete_time` = 0 AND `status` = 1 AND `reply_type` IN (1, 3) THEN `reply_type`
      ELSE NULL
    END
  ) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_oa_reply_singleton_active` (`singleton_active_key`),
  KEY `idx_oa_reply_state` (`reply_type`,`status`,`delete_time`,`sort`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='微信公众号自动回复';

INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'oa_setting', `seed`.`name`, `seed`.`value`
FROM (
  SELECT 'name' AS `name`, '' AS `value`
  UNION ALL SELECT 'original_id', ''
  UNION ALL SELECT 'qr_code', ''
  UNION ALL SELECT 'app_id', ''
  UNION ALL SELECT 'app_secret', ''
  UNION ALL SELECT 'token', ''
  UNION ALL SELECT 'encoding_aes_key', ''
  UNION ALL SELECT 'encryption_type', '1'
  UNION ALL SELECT 'menu', '[]'
) AS `seed`
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config`
  WHERE `type` = 'oa_setting' AND `name` = `seed`.`name`
);

INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'open_platform', `seed`.`name`, ''
FROM (
  SELECT 'app_id' AS `name`
  UNION ALL SELECT 'app_secret'
) AS `seed`
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config`
  WHERE `type` = 'open_platform' AND `name` = `seed`.`name`
);

-- 旧字段不再被业务消费，清除后只保留唯一配置模型。
DELETE FROM `pa_config`
WHERE `type` = 'channel'
  AND `name` IN (
    'wechat_oa_status','wechat_oa_appid','wechat_oa_secret',
    'wechat_open_status','wechat_open_appid','wechat_open_secret'
  );

SET @pa_channel_menu_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `type` = 'C' AND `paths` = '/app-setting/channel'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_channel_menu_id, 'A', `seed`.`name`, '', 0, `seed`.`perms`, '', '', 0, 1, 0
FROM (
  SELECT '公众号配置查看' AS `name`, 'setting/official-account/config' AS `perms`
  UNION ALL SELECT '公众号配置保存', 'setting/official-account/save'
  UNION ALL SELECT '公众号菜单查看', 'setting/official-account/menu'
  UNION ALL SELECT '公众号菜单保存', 'setting/official-account/menu/save'
  UNION ALL SELECT '公众号菜单发布', 'setting/official-account/menu/publish'
  UNION ALL SELECT '公众号回复列表', 'setting/official-account/reply/lists'
  UNION ALL SELECT '公众号回复详情', 'setting/official-account/reply/detail'
  UNION ALL SELECT '公众号回复新增', 'setting/official-account/reply/add'
  UNION ALL SELECT '公众号回复编辑', 'setting/official-account/reply/edit'
  UNION ALL SELECT '公众号回复删除', 'setting/official-account/reply/delete'
  UNION ALL SELECT '公众号回复状态', 'setting/official-account/reply/status'
  UNION ALL SELECT '开放平台配置查看', 'setting/open-platform/config'
  UNION ALL SELECT '开放平台配置保存', 'setting/open-platform/save'
) AS `seed`
WHERE @pa_channel_menu_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE `perms` = `seed`.`perms`
  );


SET NAMES utf8mb4;

-- S01：充值开关、金额边界与终端支付场景。
CREATE TABLE IF NOT EXISTS `pa_payment_scene` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `terminal`    TINYINT UNSIGNED NOT NULL COMMENT '终端：1小程序 2公众号 3H5 4PC 5iOS 6Android',
  `pay_way`     TINYINT UNSIGNED NOT NULL COMMENT '支付渠道：2微信 3支付宝',
  `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0关闭 1开启',
  `is_default`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否默认：0否 1是',
  `create_time` INT UNSIGNED NULL DEFAULT NULL,
  `update_time` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_terminal_pay_way` (`terminal`,`pay_way`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值终端支付场景';

INSERT INTO `pa_payment_scene`
  (`terminal`,`pay_way`,`status`,`is_default`,`create_time`,`update_time`)
VALUES
  (1,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (2,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (3,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (4,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (5,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (5,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (6,2,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
  (6,3,0,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `terminal` = VALUES(`terminal`);

INSERT INTO `pa_config` (`type`,`name`,`value`,`create_time`,`update_time`)
SELECT 'recharge', seed.name, seed.value, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 'status' AS name, '0' AS value
  UNION ALL SELECT 'min_amount', '0.01'
  UNION ALL SELECT 'max_amount', '99999.00'
) seed
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` c WHERE c.`type` = 'recharge' AND c.`name` = seed.name
);

-- 支付回调身份校验所需 canonical 配置；仅补缺，不覆盖已有商户配置。
INSERT INTO `pa_config` (`type`,`name`,`value`,`create_time`,`update_time`)
SELECT 'pay', seed.name, '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 'wx_pay_platform_cert_path' AS name
  UNION ALL SELECT 'ali_pay_seller_id'
) seed
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` c WHERE c.`type` = 'pay' AND c.`name` = seed.name
);

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'pay_sn') = 0,
  'ALTER TABLE `pa_recharge_order` ADD COLUMN `pay_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT ''支付请求编号'' AFTER `user_id`',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'order_terminal') = 0,
  'ALTER TABLE `pa_recharge_order` ADD COLUMN `order_terminal` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''支付终端'' AFTER `order_amount`',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order' AND COLUMN_NAME = 'transaction_id') = 0,
  'ALTER TABLE `pa_recharge_order` ADD COLUMN `transaction_id` VARCHAR(128) NULL DEFAULT NULL COMMENT ''第三方交易流水号'' AFTER `order_terminal`',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

UPDATE `pa_recharge_order` SET `pay_sn` = NULL WHERE `pay_sn` = '';
UPDATE `pa_recharge_order` SET `transaction_id` = NULL WHERE `transaction_id` = '';

ALTER TABLE `pa_recharge_order`
  MODIFY COLUMN `pay_sn` VARCHAR(255) NULL DEFAULT NULL COMMENT '支付请求编号',
  MODIFY COLUMN `transaction_id` VARCHAR(128) NULL DEFAULT NULL COMMENT '第三方交易流水号';

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order'
     AND COLUMN_NAME = 'pay_sn' AND NON_UNIQUE = 0) = 0,
  'ALTER TABLE `pa_recharge_order` ADD UNIQUE KEY `uk_pay_sn` (`pay_sn`)',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_recharge_order'
     AND COLUMN_NAME = 'transaction_id' AND NON_UNIQUE = 0) = 0,
  'ALTER TABLE `pa_recharge_order` ADD UNIQUE KEY `uk_transaction_id` (`transaction_id`)',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_pay_setting_id = (
  SELECT `id` FROM `pa_system_menu`
  WHERE `type` = 'C' AND `paths` = '/app-setting/pay'
  ORDER BY `id` LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_pay_setting_id, 'A', seed.name, '', 0, seed.perms, '', '', 0, 1, 0
FROM (
  SELECT '充值配置查看' AS name, 'setting/recharge/config' AS perms
  UNION ALL SELECT '充值配置保存', 'setting/recharge/save'
) seed
WHERE @pa_pay_setting_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` m WHERE LOWER(m.`perms`) = LOWER(seed.perms)
  );


SET NAMES utf8mb4;

-- 一次性把旧网站字段迁入唯一配置模型；运行时不再双读双写旧键。
SET @old_logo = (SELECT value FROM pa_config WHERE type='website' AND name='logo' LIMIT 1);
SET @old_favicon = (SELECT value FROM pa_config WHERE type='website' AND name='favicon' LIMIT 1);
SET @old_copyright = (SELECT value FROM pa_config WHERE type='website' AND name='copyright' LIMIT 1);
SET @old_icp = (SELECT value FROM pa_config WHERE type='website' AND name='icp' LIMIT 1);

INSERT INTO pa_config (type,name,value) VALUES
('website','web_favicon',COALESCE(@old_favicon,'')),
('website','web_logo',COALESCE(@old_logo,'')),
('website','login_image',''),
('website','shop_name',COALESCE((SELECT value FROM pa_config c WHERE c.type='website' AND c.name='name' LIMIT 1),'Peanut Admin')),
('website','shop_logo',COALESCE(@old_logo,'')),
('website','pc_logo',COALESCE(@old_logo,'')),
('website','pc_title',COALESCE((SELECT value FROM pa_config c WHERE c.type='website' AND c.name='name' LIMIT 1),'Peanut Admin')),
('website','pc_ico',COALESCE(@old_favicon,'')),
('website','pc_desc',''),
('website','pc_keywords',''),
('website','h5_favicon',COALESCE(@old_favicon,'')),
('agreement','service_title','服务协议'),
('agreement','service_content',''),
('agreement','privacy_title','隐私政策'),
('agreement','privacy_content',''),
('site_statistics','clarity_code',''),
('default_image','user_avatar','favicon.ico'),
('login','login_way','[1,2]'),
('login','coerce_mobile','0'),
('login','login_agreement','0'),
('pay','wx_pay_status','0'),
('pay','wx_pay_appid',''),
('pay','wx_pay_mch_id',''),
('pay','wx_pay_secret',''),
('pay','wx_pay_cert_path',''),
('pay','wx_pay_cert_key_path',''),
('pay','ali_pay_status','0'),
('pay','ali_pay_app_id',''),
('pay','ali_pay_private_key',''),
('pay','ali_pay_public_key',''),
('storage','default','local')
ON DUPLICATE KEY UPDATE value=value;

SET @copyright_json = JSON_ARRAY();
SET @copyright_json = IF(
  COALESCE(@old_copyright,'')='',
  @copyright_json,
  JSON_ARRAY_APPEND(@copyright_json,'$',JSON_OBJECT('key','版权信息','value',@old_copyright))
);
SET @copyright_json = IF(
  COALESCE(@old_icp,'')='',
  @copyright_json,
  JSON_ARRAY_APPEND(@copyright_json,'$',JSON_OBJECT('key','ICP备案','value',@old_icp))
);
INSERT INTO pa_config (type,name,value)
VALUES ('copyright','config',CAST(@copyright_json AS CHAR))
ON DUPLICATE KEY UPDATE value=value;

DELETE FROM pa_config
WHERE type='website' AND name IN ('logo','favicon','copyright','icp');
DELETE FROM pa_config WHERE type='siteStatistics';

SET @setting_root_id = (
  SELECT id FROM pa_system_menu WHERE type='M' AND paths='/app-setting' ORDER BY id LIMIT 1
);

DELETE FROM pa_system_menu WHERE LOWER(perms)='setting/pay/config/set';

INSERT INTO pa_system_menu
  (pid,type,name,icon,sort,perms,paths,component,is_cache,is_show,is_disable)
SELECT @setting_root_id,'C',seed.name,seed.icon,seed.sort,'',seed.paths,seed.component,0,1,0
FROM (
  SELECT '网站设置' name,'icon-desktop' icon,90 sort,'/system/config' paths,'system/config/index' component
  UNION ALL SELECT '用户设置','icon-user',80,'/app-setting/user','app-setting/user/index'
  UNION ALL SELECT '支付设置','icon-payment',70,'/app-setting/pay','app-setting/pay/index'
  UNION ALL SELECT '存储设置','icon-storage',60,'/system/storage','system/storage/index'
) seed
WHERE @setting_root_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM pa_system_menu m WHERE m.paths=seed.paths);

INSERT INTO pa_system_menu
  (pid,type,name,icon,sort,perms,paths,component,is_cache,is_show,is_disable)
SELECT parent.id,'A',seed.name,'',0,seed.perms,'','',0,1,0
FROM (
  SELECT '/app-setting/website' parent_path,'网站配置查看' name,'config/website' perms
  UNION ALL SELECT '/app-setting/website','网站配置保存','config/website/save'
  UNION ALL SELECT '/app-setting/website','备案配置查看','config/copyright'
  UNION ALL SELECT '/app-setting/website','备案配置保存','config/copyright/save'
  UNION ALL SELECT '/app-setting/website','协议配置查看','config/agreement'
  UNION ALL SELECT '/app-setting/website','协议配置保存','config/agreement/save'
  UNION ALL SELECT '/app-setting/website','统计配置查看','config/statistics'
  UNION ALL SELECT '/app-setting/website','统计配置保存','config/statistics/save'
  UNION ALL SELECT '/app-setting/user','用户配置查看','config/user'
  UNION ALL SELECT '/app-setting/user','用户配置保存','config/user/save'
  UNION ALL SELECT '/app-setting/user','登录配置查看','config/login'
  UNION ALL SELECT '/app-setting/user','登录配置保存','config/login/save'
  UNION ALL SELECT '/app-setting/pay','支付配置查看','setting/pay/config'
  UNION ALL SELECT '/app-setting/pay','支付配置保存','setting/pay/save'
  UNION ALL SELECT '/app-setting/storage','存储引擎列表','storage/lists'
  UNION ALL SELECT '/app-setting/storage','存储配置查看','storage/detail'
  UNION ALL SELECT '/app-setting/storage','存储配置保存','storage/setup'
  UNION ALL SELECT '/app-setting/storage','默认存储切换','storage/change'
) seed
JOIN pa_system_menu parent ON parent.paths=seed.parent_path AND parent.type='C'
WHERE NOT EXISTS (
  SELECT 1 FROM pa_system_menu m WHERE LOWER(m.perms)=LOWER(seed.perms)
);


SET NAMES utf8mb4;

SET @pa_system_root_id = (
  SELECT `id` FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/system' ORDER BY `id` LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_system_root_id,'C',`seed`.`name`,`seed`.`icon`,`seed`.`sort`,'',`seed`.`paths`,`seed`.`component`,0,1,0
FROM (
  SELECT '字典管理' AS `name`,'icon-book' AS `icon`,40 AS `sort`,'/system/dict' AS `paths`,'system/dict/index' AS `component`
  UNION ALL SELECT '定时任务','icon-clock-circle',30,'/system/crontab','system/crontab/index'
  UNION ALL SELECT '操作日志','icon-file',20,'/system/log','system/log/index'
  UNION ALL SELECT '系统维护','icon-tool',10,'/system/maintenance','system/maintenance/index'
) AS `seed`
WHERE @pa_system_root_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `paths`=`seed`.`paths`);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT `parent`.`id`,'A',`seed`.`name`,'',0,`seed`.`perms`,'','',0,1,0
FROM (
  SELECT '/system/dict' AS `parent_path`,'字典类型列表' AS `name`,'dict/type/lists' AS `perms`
  UNION ALL SELECT '/system/dict','字典类型选择','dict/type/all'
  UNION ALL SELECT '/system/dict','字典类型详情','dict/type/detail'
  UNION ALL SELECT '/system/dict','字典类型新增','dict/type/add'
  UNION ALL SELECT '/system/dict','字典类型编辑','dict/type/edit'
  UNION ALL SELECT '/system/dict','字典类型删除','dict/type/delete'
  UNION ALL SELECT '/system/dict','字典类型状态','dict/type/status'
  UNION ALL SELECT '/system/dict','字典数据列表','dict/data/lists'
  UNION ALL SELECT '/system/dict','字典数据选择','dict/data/bytype'
  UNION ALL SELECT '/system/dict','字典数据详情','dict/data/detail'
  UNION ALL SELECT '/system/dict','字典数据新增','dict/data/add'
  UNION ALL SELECT '/system/dict','字典数据编辑','dict/data/edit'
  UNION ALL SELECT '/system/dict','字典数据删除','dict/data/delete'
  UNION ALL SELECT '/system/dict','字典数据状态','dict/data/status'
  UNION ALL SELECT '/system/crontab','定时任务列表','crontab/lists'
  UNION ALL SELECT '/system/crontab','定时任务详情','crontab/detail'
  UNION ALL SELECT '/system/crontab','Cron 预览','crontab/expression'
  UNION ALL SELECT '/system/crontab','定时任务新增','crontab/add'
  UNION ALL SELECT '/system/crontab','定时任务编辑','crontab/edit'
  UNION ALL SELECT '/system/crontab','定时任务删除','crontab/delete'
  UNION ALL SELECT '/system/crontab','定时任务启停','crontab/operate'
  UNION ALL SELECT '/system/log','操作日志列表','log/lists'
  UNION ALL SELECT '/system/log','操作日志清空','log/clear'
  UNION ALL SELECT '/system/maintenance','系统环境信息','system/info'
  UNION ALL SELECT '/system/maintenance','系统缓存清理','system/clearcache'
) AS `seed`
JOIN `pa_system_menu` AS `parent` ON `parent`.`paths`=`seed`.`parent_path` AND `parent`.`type`='C'
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`)=LOWER(`seed`.`perms`));


SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `pa_oauth_principal` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`    VARCHAR(32) NOT NULL COMMENT '提供商',
  `union_scope` VARCHAR(64) NOT NULL COMMENT '联合身份作用域',
  `union_id`    VARCHAR(191) NOT NULL COMMENT '联合身份',
  `member_id`   INT UNSIGNED NOT NULL COMMENT '会员ID',
  `create_time` INT UNSIGNED NULL DEFAULT NULL,
  `update_time` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provider_scope_union` (`provider`,`union_scope`,`union_id`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth联合身份归属';

CREATE TABLE IF NOT EXISTS `pa_oauth_identity` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider`     VARCHAR(32) NOT NULL COMMENT '提供商',
  `client_key`   VARCHAR(64) NOT NULL COMMENT '客户端配置标识',
  `subject`      VARCHAR(191) NOT NULL COMMENT '客户端内外部身份',
  `principal_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '联合身份ID',
  `member_id`    INT UNSIGNED NOT NULL COMMENT '会员ID',
  `terminal`     TINYINT UNSIGNED NOT NULL COMMENT '业务终端',
  `create_time`  INT UNSIGNED NULL DEFAULT NULL,
  `update_time`  INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provider_client_subject` (`provider`,`client_key`,`subject`),
  UNIQUE KEY `uk_member_provider_client` (`member_id`,`provider`,`client_key`),
  KEY `idx_member_terminal` (`member_id`,`terminal`),
  KEY `idx_principal_id` (`principal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth客户端身份';

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_oauth_identity'
     AND INDEX_NAME = 'uk_member_provider_client') = 0,
  'ALTER TABLE `pa_oauth_identity` ADD UNIQUE KEY `uk_member_provider_client` (`member_id`,`provider`,`client_key`)',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

CREATE TABLE IF NOT EXISTS `pa_oauth_attempt` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `state_hash`  CHAR(64) NOT NULL COMMENT 'state SHA-256',
  `scene`       VARCHAR(32) NOT NULL COMMENT 'oa/open_pc',
  `return_path` VARCHAR(500) NOT NULL DEFAULT '/' COMMENT '站内返回路径',
  `expires_at`  INT UNSIGNED NOT NULL,
  `used_at`     INT UNSIGNED NULL DEFAULT NULL,
  `create_time` INT UNSIGNED NULL DEFAULT NULL,
  `update_time` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_state_hash` (`state_hash`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth一次性state';

CREATE TABLE IF NOT EXISTS `pa_oauth_completion_ticket` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_hash`       CHAR(64) NOT NULL COMMENT '票据 SHA-256',
  `member_id`        INT UNSIGNED NOT NULL,
  `need_profile`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `need_mobile`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`       INT UNSIGNED NOT NULL,
  `used_at`          INT UNSIGNED NULL DEFAULT NULL,
  `create_time`      INT UNSIGNED NULL DEFAULT NULL,
  `update_time`      INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth首登补全票据';

-- 空手机号映射为 NULL，既允许多个未绑定会员，又保证非空手机号唯一。
SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND COLUMN_NAME = 'mobile_unique') = 0,
  'ALTER TABLE `pa_member` ADD COLUMN `mobile_unique` VARCHAR(20) GENERATED ALWAYS AS (NULLIF(`mobile`,'''')) STORED',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

SET @pa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member' AND INDEX_NAME = 'uk_mobile_nonempty') = 0,
  'ALTER TABLE `pa_member` ADD UNIQUE KEY `uk_mobile_nonempty` (`mobile_unique`)',
  'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql; EXECUTE pa_stmt; DEALLOCATE PREPARE pa_stmt;

-- canonical OAuth 开关；旧 channel 表单只用于一次性迁移，不再被运行时消费。
INSERT INTO `pa_config` (`type`,`name`,`value`,`create_time`,`update_time`)
SELECT 'login', seed.name, seed.value, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 'third_auth' AS name,
    IF(EXISTS(SELECT 1 FROM `pa_config` WHERE `type`='channel' AND `name` IN ('wechat_open_status','wechat_oa_status') AND `value`='1'),'1','0') AS value
  UNION ALL
  SELECT 'wechat_auth',
    IF(EXISTS(SELECT 1 FROM `pa_config` WHERE `type`='channel' AND `name` IN ('wechat_open_status','wechat_oa_status') AND `value`='1'),'1','0')
) seed
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` c WHERE c.`type`='login' AND c.`name`=seed.name
);


CREATE TABLE IF NOT EXISTS `pa_schema_migration` (
  `migration` varchar(191) NOT NULL COMMENT '迁移文件名',
  `checksum` char(64) NOT NULL COMMENT '迁移文件 SHA-256',
  `batch` int unsigned NOT NULL DEFAULT 0 COMMENT '执行批次',
  `status` varchar(16) NOT NULL DEFAULT 'running' COMMENT 'running/applied/failed',
  `started_at` bigint unsigned NOT NULL DEFAULT 0 COMMENT '开始时间',
  `applied_at` bigint unsigned DEFAULT NULL COMMENT '完成时间',
  `error` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败摘要',
  PRIMARY KEY (`migration`),
  KEY `idx_status_batch` (`status`, `batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移账本';


SET NAMES utf8mb4;

-- 只替换空值或旧 ThinkPHP favicon 占位；已配置的会员头像保持不变。
INSERT INTO `pa_config` (`type`,`name`,`value`,`create_time`,`update_time`)
SELECT 'default_image', 'user_avatar', 'brand/avatar-member.svg', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` WHERE `type`='default_image' AND `name`='user_avatar'
);

UPDATE `pa_config`
SET `value`='brand/avatar-member.svg', `update_time`=UNIX_TIMESTAMP()
WHERE `type`='default_image'
  AND `name`='user_avatar'
  AND (`value`='' OR `value`='favicon.ico');


SET NAMES utf8mb4;

-- PB06 新资源引用会保留云/CDN 绝对 URL，封面字段必须容纳完整地址。
ALTER TABLE `pa_article`
  MODIFY COLUMN `image` VARCHAR(2048) NULL DEFAULT NULL COMMENT '文章图片：local 相对 URI 或云/CDN 绝对 URL';

ALTER TABLE `pa_decorate_tabbar`
  MODIFY COLUMN `selected` VARCHAR(2048) NOT NULL DEFAULT '' COMMENT '选中图标：local 相对 URI 或云/CDN 绝对 URL',
  MODIFY COLUMN `unselected` VARCHAR(2048) NOT NULL DEFAULT '' COMMENT '未选图标：local 相对 URI 或云/CDN 绝对 URL';


SET NAMES utf8mb4;

-- PB07 不保留可重放验证码明文：部署时令历史未消费验证码失效并脱敏快照。
UPDATE `pa_notice_log`
SET `content` = CASE
        WHEN `verify_code` <> '' THEN REPLACE(`content`, `verify_code`, '****')
        ELSE `content`
    END,
    `verify_code` = ''
WHERE `verify_code` <> '';

ALTER TABLE `pa_notice_log`
  CHANGE COLUMN `verify_code` `verify_code_hash` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '验证码单向慢哈希';

-- 通用模板/邮件扩展没有产品触发器或 UI 消费，退出 Runtime；历史模板表留存只读数据。
DELETE FROM `pa_system_role_menu`
WHERE `menu_id` IN (
  SELECT `id` FROM `pa_system_menu`
  WHERE `perms` IN ('notice/template/add', 'notice/template/edit', 'notice/template/delete')
);

DELETE FROM `pa_system_menu`
WHERE `perms` IN ('notice/template/add', 'notice/template/edit', 'notice/template/delete');


SET NAMES utf8mb4;

-- PB07 旧 Channel CRUD 已退出；删除不再消费的重复凭据和未实现的 AES 资料。
DELETE FROM `pa_config`
WHERE (`type` = 'channel' AND `name` IN (
  'wechat_open_status', 'wechat_open_appid', 'wechat_open_secret',
  'wechat_oa_status', 'wechat_oa_appid', 'wechat_oa_secret',
  'qq_status', 'qq_appid', 'qq_secret'
)) OR (`type` = 'oa_setting' AND `name` IN ('encoding_aes_key', 'encryption_type'));


SET NAMES utf8mb4;

SET @pa_default_tenant_id = (
    SELECT `id` FROM `pa_tenant` WHERE `code` = 'default' AND `status` = 'active' LIMIT 1
);
SET @pa_tenant_ready = IF(@pa_default_tenant_id IS NULL, 0, 1);
SET @pa_sql = IF(
    @pa_tenant_ready = 1,
    'SELECT 1',
    'SELECT * FROM `MT02_DEFAULT_TENANT_REQUIRED`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_admin` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_admin` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_admin_tenant_username` (`tenant_id`, `username`),
    ADD CONSTRAINT `fk_admin_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_system_role` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_system_role` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_system_role`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_system_role_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_system_role_tenant_name` (`tenant_id`, `name`),
    ADD CONSTRAINT `fk_system_role_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_dept` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_dept` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_dept`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_dept_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_dept_tenant_parent` (`tenant_id`, `pid`),
    ADD CONSTRAINT `fk_dept_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_jobs` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_jobs` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_jobs`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_jobs_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_jobs_tenant_code` (`tenant_id`, `code`),
    ADD CONSTRAINT `fk_jobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_admin_role` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_admin_role` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin_role`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_role_tenant` (`tenant_id`, `admin_id`, `role_id`),
    ADD CONSTRAINT `fk_admin_role_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_admin_dept` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_admin_dept` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin_dept`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_dept_tenant` (`tenant_id`, `admin_id`, `dept_id`),
    ADD CONSTRAINT `fk_admin_dept_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_admin_jobs` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_admin_jobs` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin_jobs`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_jobs_tenant` (`tenant_id`, `admin_id`, `jobs_id`),
    ADD CONSTRAINT `fk_admin_jobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_system_role_menu` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_system_role_menu` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_system_role_menu`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_role_menu_tenant` (`tenant_id`, `role_id`, `menu_id`),
    ADD CONSTRAINT `fk_role_menu_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

-- MT02 Article tenant ownership: expand, backfill, verify, contract.
-- The bootstrap/RBAC slice owns pa_tenant creation. This migration refuses to guess a Tenant.

SET @pa_mt02_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt02_sql = IF(
  @pa_mt02_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_requires_pa_tenant_before_article_backfill`'
);
PREPARE pa_mt02_stmt FROM @pa_mt02_sql;
EXECUTE pa_mt02_stmt;
DEALLOCATE PREPARE pa_mt02_stmt;

SET @pa_mt02_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt02_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt02_sql = IF(
  @pa_mt02_active_tenant_count = 1 AND @pa_mt02_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_requires_exactly_one_active_tenant_for_article_backfill`'
);
PREPARE pa_mt02_stmt FROM @pa_mt02_sql;
EXECUTE pa_mt02_stmt;
DEALLOCATE PREPARE pa_mt02_stmt;

ALTER TABLE `pa_article_cate` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_article` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_article_collect` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_article_cate` SET `tenant_id` = @pa_mt02_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_article` SET `tenant_id` = @pa_mt02_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_article_collect` SET `tenant_id` = @pa_mt02_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt02_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_article_cate` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_article` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_article_collect` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_article` a
       JOIN `pa_article_cate` c ON c.`id` = a.`cid`
       WHERE a.`tenant_id` <> c.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_article_collect` ac
       JOIN `pa_article` a ON a.`id` = ac.`article_id`
       WHERE ac.`tenant_id` <> a.`tenant_id`)
);
SET @pa_mt02_sql = IF(
  @pa_mt02_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_tenant_backfill_verification_failed`'
);
PREPARE pa_mt02_stmt FROM @pa_mt02_sql;
EXECUTE pa_mt02_stmt;
DEALLOCATE PREPARE pa_mt02_stmt;

ALTER TABLE `pa_article_cate`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_article_cate_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_article_cate_tenant_visible` (`tenant_id`, `is_show`, `sort`, `id`),
  ADD CONSTRAINT `fk_article_cate_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_article`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN `cid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文章分类',
  ADD UNIQUE KEY `uk_article_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_article_tenant_visible_cate` (`tenant_id`, `is_show`, `cid`, `sort`, `id`),
  ADD CONSTRAINT `fk_article_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_article_tenant_cate` FOREIGN KEY (`tenant_id`, `cid`) REFERENCES `pa_article_cate` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_article_collect`
  DROP INDEX `uk_member_article`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_article_collect_tenant_member_article` (`tenant_id`, `member_id`, `article_id`),
  ADD KEY `idx_article_collect_tenant_member_status` (`tenant_id`, `member_id`, `status`, `id`),
  ADD CONSTRAINT `fk_article_collect_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_article_collect_tenant_article` FOREIGN KEY (`tenant_id`, `article_id`) REFERENCES `pa_article` (`tenant_id`, `id`) ON DELETE RESTRICT;


-- MT03 crontab tenant ownership: expand, backfill, verify, contract.
-- The MT02 bootstrap slice owns pa_tenant creation. This migration refuses to guess a Tenant.

SET @pa_mt03_crontab_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_crontab_sql = IF(
  @pa_mt03_crontab_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_crontab_backfill`'
);
PREPARE pa_mt03_crontab_stmt FROM @pa_mt03_crontab_sql;
EXECUTE pa_mt03_crontab_stmt;
DEALLOCATE PREPARE pa_mt03_crontab_stmt;

SET @pa_mt03_crontab_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_crontab_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_crontab_sql = IF(
  @pa_mt03_crontab_active_tenant_count = 1 AND @pa_mt03_crontab_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_crontab_backfill`'
);
PREPARE pa_mt03_crontab_stmt FROM @pa_mt03_crontab_sql;
EXECUTE pa_mt03_crontab_stmt;
DEALLOCATE PREPARE pa_mt03_crontab_stmt;

ALTER TABLE `pa_crontab` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_crontab` SET `tenant_id` = @pa_mt03_crontab_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_crontab_invalid_rows = (
  SELECT COUNT(*) FROM `pa_crontab` WHERE `tenant_id` IS NULL OR `tenant_id` = 0
);
SET @pa_mt03_crontab_sql = IF(
  @pa_mt03_crontab_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_crontab_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_crontab_stmt FROM @pa_mt03_crontab_sql;
EXECUTE pa_mt03_crontab_stmt;
DEALLOCATE PREPARE pa_mt03_crontab_stmt;

ALTER TABLE `pa_crontab`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_crontab_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_crontab_tenant_status_last` (`tenant_id`, `status`, `last_time`, `id`),
  ADD CONSTRAINT `fk_crontab_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;


-- MT03 file tenant ownership: expand, backfill, verify, contract.
-- The MT02 bootstrap slice owns pa_tenant creation. This migration refuses to guess a Tenant.

SET @pa_mt03_file_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_file_sql = IF(
  @pa_mt03_file_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_file_backfill`'
);
PREPARE pa_mt03_file_stmt FROM @pa_mt03_file_sql;
EXECUTE pa_mt03_file_stmt;
DEALLOCATE PREPARE pa_mt03_file_stmt;

SET @pa_mt03_file_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_file_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_file_sql = IF(
  @pa_mt03_file_active_tenant_count = 1 AND @pa_mt03_file_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_file_backfill`'
);
PREPARE pa_mt03_file_stmt FROM @pa_mt03_file_sql;
EXECUTE pa_mt03_file_stmt;
DEALLOCATE PREPARE pa_mt03_file_stmt;

ALTER TABLE `pa_file_cate` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_file` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_file_cate` SET `tenant_id` = @pa_mt03_file_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_file` SET `tenant_id` = @pa_mt03_file_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_file_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_file_cate` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_file` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_file` f
       JOIN `pa_file_cate` c ON c.`id` = f.`cid`
       WHERE f.`cid` > 0 AND f.`tenant_id` <> c.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_file_cate` c
       JOIN `pa_file_cate` p ON p.`id` = c.`pid`
       WHERE c.`pid` > 0 AND (c.`tenant_id` <> p.`tenant_id` OR c.`type` <> p.`type`))
);
SET @pa_mt03_file_sql = IF(
  @pa_mt03_file_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_file_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_file_stmt FROM @pa_mt03_file_sql;
EXECUTE pa_mt03_file_stmt;
DEALLOCATE PREPARE pa_mt03_file_stmt;

ALTER TABLE `pa_file_cate`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_file_cate_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_file_cate_tenant_type_parent` (`tenant_id`, `type`, `pid`, `id`),
  ADD CONSTRAINT `fk_file_cate_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_file`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_file_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_file_tenant_type_cid_source` (`tenant_id`, `type`, `cid`, `source`, `id`),
  ADD CONSTRAINT `fk_file_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;


-- MT03 product-member tenant ownership: expand, backfill, verify, contract.
-- pa_member remains the application's consumer-member model; it is not Core TenantMember.

SET @pa_mt03_member_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_member', 'pa_member_tag', 'pa_member_tag_relation', 'pa_member_balance_log')
);
SET @pa_mt03_member_sql = IF(
  @pa_mt03_member_required_table_count = 5,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_member_requires_all_owned_tables_before_backfill`'
);
PREPARE pa_mt03_member_stmt FROM @pa_mt03_member_sql;
EXECUTE pa_mt03_member_stmt;
DEALLOCATE PREPARE pa_mt03_member_stmt;

SET @pa_mt03_member_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_member_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_member_sql = IF(
  @pa_mt03_member_active_tenant_count = 1 AND @pa_mt03_member_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_member_requires_exactly_one_active_tenant_for_backfill`'
);
PREPARE pa_mt03_member_stmt FROM @pa_mt03_member_sql;
EXECUTE pa_mt03_member_stmt;
DEALLOCATE PREPARE pa_mt03_member_stmt;

ALTER TABLE `pa_member` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_member_tag` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_member_tag_relation` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_member_balance_log` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_member` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_member_tag` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_member_tag_relation` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_member_balance_log` SET `tenant_id` = @pa_mt03_member_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_member_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_member` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_tag` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_tag_relation` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_balance_log` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_member_tag_relation` r
       LEFT JOIN `pa_member` m ON m.`id` = r.`member_id`
       LEFT JOIN `pa_member_tag` t ON t.`id` = r.`tag_id`
       WHERE m.`id` IS NULL OR t.`id` IS NULL OR r.`tenant_id` <> m.`tenant_id` OR r.`tenant_id` <> t.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_member_balance_log` l
       LEFT JOIN `pa_member` m ON m.`id` = l.`member_id`
       WHERE m.`id` IS NULL OR l.`tenant_id` <> m.`tenant_id`)
);
SET @pa_mt03_member_sql = IF(
  @pa_mt03_member_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_member_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_member_stmt FROM @pa_mt03_member_sql;
EXECUTE pa_mt03_member_stmt;
DEALLOCATE PREPARE pa_mt03_member_stmt;

ALTER TABLE `pa_member`
  DROP INDEX `uk_sn`,
  DROP INDEX `uk_mobile_nonempty`,
  ADD COLUMN `account_unique` VARCHAR(50) GENERATED ALWAYS AS (NULLIF(`account`, '')) STORED AFTER `account`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_tenant_sn` (`tenant_id`, `sn`),
  ADD UNIQUE KEY `uk_member_tenant_account` (`tenant_id`, `account_unique`),
  ADD UNIQUE KEY `uk_member_tenant_mobile` (`tenant_id`, `mobile_unique`),
  ADD KEY `idx_member_tenant_status_channel` (`tenant_id`, `status`, `channel`, `id`),
  ADD CONSTRAINT `fk_member_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_member_tag`
  DROP INDEX `uk_name`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_tag_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_tag_tenant_name` (`tenant_id`, `name`),
  ADD KEY `idx_member_tag_tenant_live` (`tenant_id`, `delete_time`, `id`),
  ADD CONSTRAINT `fk_member_tag_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_member_tag_relation`
  DROP INDEX `uk_member_tag`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_tag_relation_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_tag_relation_tenant_pair` (`tenant_id`, `member_id`, `tag_id`),
  ADD KEY `idx_member_tag_relation_tenant_tag` (`tenant_id`, `tag_id`, `member_id`),
  ADD CONSTRAINT `fk_member_tag_relation_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_member_tag_relation_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_member_tag_relation_tag` FOREIGN KEY (`tenant_id`, `tag_id`) REFERENCES `pa_member_tag` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_member_balance_log`
  DROP INDEX `uk_sn`,
  ADD COLUMN `source_sn_unique` VARCHAR(255) GENERATED ALWAYS AS (NULLIF(`source_sn`, '')) STORED AFTER `source_sn`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_member_balance_log_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_member_balance_log_tenant_sn` (`tenant_id`, `sn`),
  ADD UNIQUE KEY `uk_member_balance_log_tenant_source` (`tenant_id`, `source_sn_unique`),
  ADD KEY `idx_member_balance_log_tenant_member_time` (`tenant_id`, `member_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_member_balance_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_member_balance_log_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;


-- MT03 notification tenant ownership: preflight, expand, backfill, verify, contract.
-- Provider/channel credentials remain application-owned and are deliberately absent here.

SET @pa_mt03_notice_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_notice_scene', 'pa_notice_template', 'pa_notice_log')
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_required_table_count = 4,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_requires_tenant_and_notice_tables`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

SET @pa_mt03_notice_active_tenant_count = (
  SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active'
);
SET @pa_mt03_notice_default_tenant_count = (
  SELECT COUNT(*) FROM `pa_tenant` WHERE `code` = 'default' AND `status` = 'active'
);
SET @pa_mt03_notice_default_tenant_id = (
  SELECT `id` FROM `pa_tenant` WHERE `code` = 'default' AND `status` = 'active' LIMIT 1
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_active_tenant_count = 1
    AND @pa_mt03_notice_default_tenant_count = 1
    AND @pa_mt03_notice_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_requires_exactly_one_active_default_tenant`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

SET @pa_mt03_notice_duplicate_business_keys = (
  SELECT
    (SELECT COUNT(*) FROM (
      SELECT `code` FROM `pa_notice_scene` GROUP BY `code` HAVING COUNT(*) > 1
    ) AS duplicate_scene_codes)
    + (SELECT COUNT(*) FROM (
      SELECT `code` FROM `pa_notice_template` GROUP BY `code` HAVING COUNT(*) > 1
    ) AS duplicate_template_codes)
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_duplicate_business_keys = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_duplicate_business_keys_require_resolution`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

ALTER TABLE `pa_notice_scene` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_notice_template` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_notice_log` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_notice_scene` SET `tenant_id` = @pa_mt03_notice_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_notice_template` SET `tenant_id` = @pa_mt03_notice_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_notice_log` SET `tenant_id` = @pa_mt03_notice_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_notice_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_notice_scene` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_notice_template` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_notice_log` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_notice_log` l
       JOIN `pa_notice_scene` s ON s.`id` = l.`scene_id`
       WHERE l.`scene_id` > 0 AND l.`tenant_id` <> s.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_notice_log` l
       JOIN `pa_notice_template` t ON t.`id` = l.`template_id`
       WHERE l.`template_id` > 0 AND l.`tenant_id` <> t.`tenant_id`)
);
SET @pa_mt03_notice_sql = IF(
  @pa_mt03_notice_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_notice_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_notice_stmt FROM @pa_mt03_notice_sql;
EXECUTE pa_mt03_notice_stmt;
DEALLOCATE PREPARE pa_mt03_notice_stmt;

ALTER TABLE `pa_notice_scene`
  DROP INDEX `uk_code`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_notice_scene_tenant_code` (`tenant_id`, `code`),
  ADD UNIQUE KEY `uk_notice_scene_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_notice_scene_tenant_sms` (`tenant_id`, `sms_status`, `id`),
  ADD CONSTRAINT `fk_notice_scene_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_notice_template`
  DROP INDEX `idx_code`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_notice_template_tenant_code` (`tenant_id`, `code`),
  ADD UNIQUE KEY `uk_notice_template_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_notice_template_tenant_channel` (`tenant_id`, `channel`, `is_disable`, `id`),
  ADD CONSTRAINT `fk_notice_template_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_notice_log`
  DROP INDEX `idx_scene_receiver`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_notice_log_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_notice_log_tenant_scene_receiver` (`tenant_id`, `scene_id`, `channel`, `receiver`, `status`, `send_time`, `id`),
  ADD KEY `idx_notice_log_tenant_list` (`tenant_id`, `status`, `channel`, `send_time`, `id`),
  ADD CONSTRAINT `fk_notice_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;


-- MT03 tenant attribution for the tenant-only operation audit stream.
-- PlatformOperator audit requires a separate PM01 schema and must not use tenant_id 0/NULL.

SET @pa_mt03_audit_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_audit_sql = IF(
  @pa_mt03_audit_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_operation_log_backfill`'
);
PREPARE pa_mt03_audit_stmt FROM @pa_mt03_audit_sql;
EXECUTE pa_mt03_audit_stmt;
DEALLOCATE PREPARE pa_mt03_audit_stmt;

SET @pa_mt03_audit_legacy_rows = (SELECT COUNT(*) FROM `pa_operation_log`);
SET @pa_mt03_audit_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_audit_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_audit_sql = IF(
  @pa_mt03_audit_legacy_rows = 0
    OR (@pa_mt03_audit_active_tenant_count = 1 AND @pa_mt03_audit_default_tenant_id > 0),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_operation_log_backfill`'
);
PREPARE pa_mt03_audit_stmt FROM @pa_mt03_audit_sql;
EXECUTE pa_mt03_audit_stmt;
DEALLOCATE PREPARE pa_mt03_audit_stmt;

ALTER TABLE `pa_operation_log`
  ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD COLUMN `request_id` VARCHAR(128) NOT NULL DEFAULT '' AFTER `method`;

UPDATE `pa_operation_log`
SET `tenant_id` = @pa_mt03_audit_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt03_audit_invalid_rows = (
  SELECT COUNT(*) FROM `pa_operation_log` WHERE `tenant_id` IS NULL OR `tenant_id` = 0
);
SET @pa_mt03_audit_sql = IF(
  @pa_mt03_audit_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_operation_log_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_audit_stmt FROM @pa_mt03_audit_sql;
EXECUTE pa_mt03_audit_stmt;
DEALLOCATE PREPARE pa_mt03_audit_stmt;

ALTER TABLE `pa_operation_log`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_operation_log_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_operation_log_tenant_created` (`tenant_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_operation_log_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;


-- MT02 Article collection membership Tenant integrity.
-- Preflight every historical relationship before adding the composite member foreign key.

SET @pa_mt02_collect_member_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_member', 'pa_article', 'pa_article_collect')
);
SET @pa_mt02_collect_member_sql = IF(
  @pa_mt02_collect_member_required_tables = 3,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_collect_member_requires_owned_tables`'
);
PREPARE pa_mt02_collect_member_stmt FROM @pa_mt02_collect_member_sql;
EXECUTE pa_mt02_collect_member_stmt;
DEALLOCATE PREPARE pa_mt02_collect_member_stmt;

SET @pa_mt02_collect_member_required_columns = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND (
      (TABLE_NAME = 'pa_member' AND COLUMN_NAME IN ('id', 'tenant_id'))
      OR (TABLE_NAME = 'pa_article' AND COLUMN_NAME IN ('id', 'tenant_id'))
      OR (TABLE_NAME = 'pa_article_collect' AND COLUMN_NAME IN ('tenant_id', 'member_id', 'article_id'))
    )
);
SET @pa_mt02_collect_member_parent_indexes = (
  SELECT COUNT(*) FROM (
    SELECT TABLE_NAME, INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
        (TABLE_NAME = 'pa_member' AND INDEX_NAME = 'uk_member_tenant_id')
        OR (TABLE_NAME = 'pa_article' AND INDEX_NAME = 'uk_article_tenant_id')
      )
      AND NON_UNIQUE = 0
    GROUP BY TABLE_NAME, INDEX_NAME
    HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) = 'tenant_id,id'
  ) AS required_parent_indexes
);
SET @pa_mt02_collect_member_sql = IF(
  @pa_mt02_collect_member_required_columns = 7
    AND @pa_mt02_collect_member_parent_indexes = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_collect_member_requires_tenant_parent_keys`'
);
PREPARE pa_mt02_collect_member_stmt FROM @pa_mt02_collect_member_sql;
EXECUTE pa_mt02_collect_member_stmt;
DEALLOCATE PREPARE pa_mt02_collect_member_stmt;

SET @pa_mt02_collect_member_invalid_rows = (
  SELECT COUNT(*)
  FROM `pa_article_collect` collect
  LEFT JOIN `pa_member` member ON member.`id` = collect.`member_id`
  LEFT JOIN `pa_article` article ON article.`id` = collect.`article_id`
  WHERE collect.`tenant_id` IS NULL
    OR collect.`tenant_id` = 0
    OR member.`id` IS NULL
    OR article.`id` IS NULL
    OR member.`tenant_id` <> collect.`tenant_id`
    OR article.`tenant_id` <> collect.`tenant_id`
);
SET @pa_mt02_collect_member_sql = IF(
  @pa_mt02_collect_member_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_collect_membership_preflight_failed`'
);
PREPARE pa_mt02_collect_member_stmt FROM @pa_mt02_collect_member_sql;
EXECUTE pa_mt02_collect_member_stmt;
DEALLOCATE PREPARE pa_mt02_collect_member_stmt;

ALTER TABLE `pa_article_collect`
  ADD CONSTRAINT `fk_article_collect_tenant_member`
    FOREIGN KEY (`tenant_id`, `member_id`)
    REFERENCES `pa_member` (`tenant_id`, `id`)
    ON DELETE RESTRICT;


-- MT03 Tenant-owned customer-service contact settings.
-- The public customer-service page remains owned by the Decoration Runtime.

SET @pa_mt03_customer_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('pa_tenant', 'pa_config', 'pa_file')
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_required_table_count = 3,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_requires_tenant_config_and_file`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

SET @pa_mt03_customer_file_owner_ready = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_file'
    AND COLUMN_NAME = 'tenant_id' AND IS_NULLABLE = 'NO'
);
SET @pa_mt03_customer_file_identity_ready = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_file'
    AND INDEX_NAME = 'uk_file_tenant_id' AND NON_UNIQUE = 0
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_file_owner_ready = 1 AND @pa_mt03_customer_file_identity_ready = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_requires_file_tenant_ownership`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

SET @pa_mt03_customer_invalid_legacy_rows = (
  SELECT COUNT(*) FROM `pa_config`
  WHERE `type` = 'customer_service'
    AND (`name` NOT IN ('qr_code', 'wechat', 'phone', 'service_time') OR `value` IS NULL)
);
SET @pa_mt03_customer_legacy_qr_uri = (
  SELECT `value` FROM `pa_config`
  WHERE `type` = 'customer_service' AND `name` = 'qr_code'
  LIMIT 1
);
SET @pa_mt03_customer_legacy_qr_owner_count = (
  SELECT COUNT(*) FROM `pa_file`
  WHERE `uri` = @pa_mt03_customer_legacy_qr_uri AND `delete_time` IS NULL
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_invalid_legacy_rows = 0
    AND (COALESCE(@pa_mt03_customer_legacy_qr_uri, '') = '' OR @pa_mt03_customer_legacy_qr_owner_count = 1),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_invalid_legacy_data`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

CREATE TABLE `pa_customer_service_setting` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `qr_file_id`   INT UNSIGNED NULL DEFAULT NULL,
  `wechat`       VARCHAR(100) NOT NULL DEFAULT '',
  `phone`        VARCHAR(50) NOT NULL DEFAULT '',
  `service_time` VARCHAR(100) NOT NULL DEFAULT '',
  `create_time`  INT UNSIGNED NULL DEFAULT NULL,
  `update_time`  INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer_service_setting_tenant` (`tenant_id`),
  KEY `idx_customer_service_setting_tenant_qr` (`tenant_id`, `qr_file_id`),
  CONSTRAINT `fk_customer_service_setting_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_service_setting_qr_file` FOREIGN KEY (`tenant_id`, `qr_file_id`) REFERENCES `pa_file` (`tenant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tenant客服联系方式';

INSERT INTO `pa_customer_service_setting` (
  `tenant_id`, `qr_file_id`, `wechat`, `phone`, `service_time`, `create_time`, `update_time`
)
SELECT
  t.`id`,
  CASE WHEN f.`tenant_id` = t.`id` THEN f.`id` ELSE NULL END,
  COALESCE((SELECT c.`value` FROM `pa_config` c WHERE c.`type`='customer_service' AND c.`name`='wechat' LIMIT 1), ''),
  COALESCE((SELECT c.`value` FROM `pa_config` c WHERE c.`type`='customer_service' AND c.`name`='phone' LIMIT 1), ''),
  COALESCE((SELECT c.`value` FROM `pa_config` c WHERE c.`type`='customer_service' AND c.`name`='service_time' LIMIT 1), ''),
  UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `pa_tenant` t
LEFT JOIN `pa_file` f
  ON f.`uri` = @pa_mt03_customer_legacy_qr_uri AND f.`delete_time` IS NULL;

SET @pa_mt03_customer_invalid_owned_rows = (
  SELECT COUNT(*) FROM `pa_customer_service_setting` s
  LEFT JOIN `pa_tenant` t ON t.`id` = s.`tenant_id`
  LEFT JOIN `pa_file` f ON f.`tenant_id` = s.`tenant_id` AND f.`id` = s.`qr_file_id`
  WHERE t.`id` IS NULL OR s.`tenant_id` = 0
    OR (s.`qr_file_id` IS NOT NULL AND f.`id` IS NULL)
);
SET @pa_mt03_customer_missing_tenants = (
  SELECT COUNT(*) FROM `pa_tenant` t
  LEFT JOIN `pa_customer_service_setting` s ON s.`tenant_id` = t.`id`
  WHERE s.`tenant_id` IS NULL
);
SET @pa_mt03_customer_sql = IF(
  @pa_mt03_customer_invalid_owned_rows = 0 AND @pa_mt03_customer_missing_tenants = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_customer_service_backfill_verification_failed`'
);
PREPARE pa_mt03_customer_stmt FROM @pa_mt03_customer_sql;
EXECUTE pa_mt03_customer_stmt;
DEALLOCATE PREPARE pa_mt03_customer_stmt;

DELETE FROM `pa_config` WHERE `type` = 'customer_service';


-- Bind the canonical decoration pages to the formal fresh-install default Tenant.

SET @pa_mt02_decorate_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_decorate_page')
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_required_tables = 2,
  'SELECT 1',
  'SELECT * FROM `pa_fresh_decorate_requires_tenant_and_page_tables`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

SET @pa_mt02_decorate_default_count = (
  SELECT COUNT(*) FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
);
SET @pa_mt02_decorate_default_tenant_id = (
  SELECT `id` FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
  LIMIT 1
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_default_count = 1 AND @pa_mt02_decorate_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_fresh_decorate_requires_default_tenant`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

SET @pa_mt02_decorate_duplicate_type_count = (
  SELECT COUNT(*) FROM (
    SELECT `type`
    FROM `pa_decorate_page`
    GROUP BY `type`
    HAVING COUNT(*) > 1
  ) duplicate_types
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_duplicate_type_count = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_decorate_legacy_duplicate_type_preflight_failed`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

ALTER TABLE `pa_decorate_page`
  ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_decorate_page`
SET `tenant_id` = @pa_mt02_decorate_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt02_decorate_invalid_rows = (
  SELECT COUNT(*)
  FROM `pa_decorate_page` p
  LEFT JOIN `pa_tenant` t ON t.`id` = p.`tenant_id`
  WHERE p.`tenant_id` IS NULL OR p.`tenant_id` = 0 OR t.`id` IS NULL
);
SET @pa_mt02_decorate_sql = IF(
  @pa_mt02_decorate_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_decorate_tenant_backfill_verification_failed`'
);
PREPARE pa_mt02_decorate_stmt FROM @pa_mt02_decorate_sql;
EXECUTE pa_mt02_decorate_stmt;
DEALLOCATE PREPARE pa_mt02_decorate_stmt;

ALTER TABLE `pa_decorate_page`
  DROP INDEX `uk_decorate_page_type`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_decorate_page_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_decorate_page_tenant_type` (`tenant_id`, `type`),
  ADD CONSTRAINT `fk_decorate_page_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;


-- Bind canonical Tabbar rows and style to the formal fresh-install default Tenant.

SET @pa_mt03_tabbar_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_config', 'pa_decorate_tabbar')
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_required_tables = 3,
  'SELECT 1',
  'SELECT * FROM `pa_fresh_tabbar_requires_tenant_config_and_tabbar_tables`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

SET @pa_mt03_tabbar_default_count = (
  SELECT COUNT(*) FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
);
SET @pa_mt03_tabbar_default_tenant_id = (
  SELECT `id` FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
  LIMIT 1
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_default_count = 1 AND @pa_mt03_tabbar_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_fresh_tabbar_requires_default_tenant`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

SET @pa_mt03_tabbar_legacy_style_count = (
  SELECT COUNT(*) FROM `pa_config` WHERE `type` = 'tabbar'
);
SET @pa_mt03_tabbar_invalid_style_count = (
  SELECT COUNT(*) FROM `pa_config`
  WHERE `type` = 'tabbar'
    AND (`name` <> 'style' OR NOT JSON_VALID(`value`) OR JSON_TYPE(`value`) <> 'OBJECT')
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_legacy_style_count <= 1 AND @pa_mt03_tabbar_invalid_style_count = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_tabbar_invalid_legacy_style_preflight_failed`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

ALTER TABLE `pa_decorate_tabbar`
  ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_decorate_tabbar`
SET `tenant_id` = @pa_mt03_tabbar_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt03_tabbar_invalid_owned_rows = (
  SELECT COUNT(*)
  FROM `pa_decorate_tabbar` d
  LEFT JOIN `pa_tenant` t ON t.`id` = d.`tenant_id`
  WHERE d.`tenant_id` IS NULL OR d.`tenant_id` = 0 OR t.`id` IS NULL
);
SET @pa_mt03_tabbar_sql = IF(
  @pa_mt03_tabbar_invalid_owned_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_tabbar_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_tabbar_stmt FROM @pa_mt03_tabbar_sql;
EXECUTE pa_mt03_tabbar_stmt;
DEALLOCATE PREPARE pa_mt03_tabbar_stmt;

ALTER TABLE `pa_decorate_tabbar`
  DROP INDEX `uk_decorate_tabbar_position`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_decorate_tabbar_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_decorate_tabbar_tenant_position` (`tenant_id`, `position`),
  ADD CONSTRAINT `fk_decorate_tabbar_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

CREATE TABLE `pa_decorate_tabbar_setting` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `style` LONGTEXT NOT NULL,
  `create_time` INT UNSIGNED NULL DEFAULT NULL,
  `update_time` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_decorate_tabbar_setting_tenant` (`tenant_id`),
  CONSTRAINT `chk_decorate_tabbar_setting_style` CHECK (JSON_VALID(`style`) AND JSON_TYPE(`style`) = 'OBJECT'),
  CONSTRAINT `fk_decorate_tabbar_setting_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant 装修 Tabbar 样式';

INSERT INTO `pa_decorate_tabbar_setting` (`tenant_id`, `style`, `create_time`, `update_time`)
SELECT
  @pa_mt03_tabbar_default_tenant_id,
  COALESCE(
    (SELECT `value` FROM `pa_config` WHERE `type` = 'tabbar' AND `name` = 'style' LIMIT 1),
    '{"default_color":"#666666","selected_color":"#2F80ED"}'
  ),
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP();

DELETE FROM `pa_config` WHERE `type` = 'tabbar';


-- Bind canonical dictionary rows to the formal fresh-install default Tenant.

SET @pa_mt02_dict_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_dict_type', 'pa_dict_data')
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_required_tables = 3,
  'SELECT 1',
  'SELECT * FROM `pa_fresh_dict_requires_tenant_and_dictionary_tables`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

SET @pa_mt02_dict_default_count = (
  SELECT COUNT(*) FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
);
SET @pa_mt02_dict_default_tenant_id = (
  SELECT `id` FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
  LIMIT 1
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_default_count = 1 AND @pa_mt02_dict_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_fresh_dict_requires_default_tenant`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

SET @pa_mt02_dict_orphan_count = (
  SELECT COUNT(*)
  FROM `pa_dict_data` d
  LEFT JOIN `pa_dict_type` t ON t.`id` = d.`type_id`
  WHERE t.`id` IS NULL
);
SET @pa_mt02_dict_duplicate_type_count = (
  SELECT COUNT(*) FROM (
    SELECT `type`
    FROM `pa_dict_type`
    WHERE `delete_time` IS NULL
    GROUP BY `type`
    HAVING COUNT(*) > 1
  ) duplicate_types
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_orphan_count = 0 AND @pa_mt02_dict_duplicate_type_count = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_dict_legacy_integrity_preflight_failed`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

ALTER TABLE `pa_dict_type` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_dict_data` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_dict_type`
SET `tenant_id` = @pa_mt02_dict_default_tenant_id
WHERE `tenant_id` IS NULL;
UPDATE `pa_dict_data` d
JOIN `pa_dict_type` t ON t.`id` = d.`type_id`
SET d.`tenant_id` = t.`tenant_id`, d.`type_value` = t.`type`
WHERE d.`tenant_id` IS NULL OR d.`type_value` <> t.`type`;

SET @pa_mt02_dict_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_dict_type` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_dict_data` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*)
       FROM `pa_dict_data` d
       JOIN `pa_dict_type` t ON t.`id` = d.`type_id`
       WHERE d.`tenant_id` <> t.`tenant_id` OR d.`type_value` <> t.`type`)
);
SET @pa_mt02_dict_sql = IF(
  @pa_mt02_dict_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_dict_tenant_backfill_verification_failed`'
);
PREPARE pa_mt02_dict_stmt FROM @pa_mt02_dict_sql;
EXECUTE pa_mt02_dict_stmt;
DEALLOCATE PREPARE pa_mt02_dict_stmt;

ALTER TABLE `pa_dict_type`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD COLUMN `active_type` VARCHAR(100)
    GENERATED ALWAYS AS (CASE WHEN `delete_time` IS NULL THEN `type` ELSE NULL END) STORED,
  ADD UNIQUE KEY `uk_dict_type_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_dict_type_tenant_active_type` (`tenant_id`, `active_type`),
  ADD KEY `idx_dict_type_tenant_status_name` (`tenant_id`, `is_disable`, `name`, `id`),
  ADD CONSTRAINT `fk_dict_type_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_dict_data`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_dict_data_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_dict_data_tenant_type_status_sort`
    (`tenant_id`, `type_id`, `is_disable`, `sort`, `id`),
  ADD CONSTRAINT `fk_dict_data_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_dict_data_tenant_type`
    FOREIGN KEY (`tenant_id`, `type_id`)
    REFERENCES `pa_dict_type` (`tenant_id`, `id`) ON DELETE RESTRICT;


-- MT03 hot-search term tenant ownership: expand, backfill, verify, contract.
-- hot_search.status remains instance-level in pa_config; only pa_hot_search terms are Tenant-owned.

SET @pa_mt03_hot_search_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt03_hot_search_sql = IF(
  @pa_mt03_hot_search_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_pa_tenant_before_hot_search_backfill`'
);
PREPARE pa_mt03_hot_search_stmt FROM @pa_mt03_hot_search_sql;
EXECUTE pa_mt03_hot_search_stmt;
DEALLOCATE PREPARE pa_mt03_hot_search_stmt;

SET @pa_mt03_hot_search_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_hot_search_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_hot_search_sql = IF(
  @pa_mt03_hot_search_active_tenant_count = 1 AND @pa_mt03_hot_search_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_requires_exactly_one_active_tenant_for_hot_search_backfill`'
);
PREPARE pa_mt03_hot_search_stmt FROM @pa_mt03_hot_search_sql;
EXECUTE pa_mt03_hot_search_stmt;
DEALLOCATE PREPARE pa_mt03_hot_search_stmt;

ALTER TABLE `pa_hot_search` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_hot_search` SET `tenant_id` = @pa_mt03_hot_search_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_hot_search_invalid_rows = (
  SELECT COUNT(*) FROM `pa_hot_search` WHERE `tenant_id` IS NULL OR `tenant_id` = 0
);
SET @pa_mt03_hot_search_sql = IF(
  @pa_mt03_hot_search_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_hot_search_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_hot_search_stmt FROM @pa_mt03_hot_search_sql;
EXECUTE pa_mt03_hot_search_stmt;
DEALLOCATE PREPARE pa_mt03_hot_search_stmt;

ALTER TABLE `pa_hot_search`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_hot_search_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_hot_search_tenant_sort` (`tenant_id`, `sort`, `id`),
  ADD CONSTRAINT `fk_hot_search_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;


-- MT03 OAuth identity, browser state, and completion ticket Tenant ownership.
-- pa_config OAuth switches and channel credentials remain instance-owned.

SET @pa_mt03_oauth_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
      'pa_tenant', 'pa_member', 'pa_oauth_principal', 'pa_oauth_identity',
      'pa_oauth_attempt', 'pa_oauth_completion_ticket'
    )
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_required_table_count = 6,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_requires_all_owned_tables_before_backfill`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

SET @pa_mt03_oauth_member_owner_ready = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND COLUMN_NAME = 'tenant_id' AND IS_NULLABLE = 'NO'
);
SET @pa_mt03_oauth_member_identity_ready = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND INDEX_NAME = 'uk_member_tenant_id' AND NON_UNIQUE = 0
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_member_owner_ready = 1 AND @pa_mt03_oauth_member_identity_ready = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_requires_member_tenant_ownership_before_backfill`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

SET @pa_mt03_oauth_invalid_legacy_rows = (
  (SELECT COUNT(*) FROM `pa_oauth_principal` p
   LEFT JOIN `pa_member` m ON m.`id` = p.`member_id`
   WHERE m.`id` IS NULL)
  + (SELECT COUNT(*) FROM `pa_oauth_identity` i
     LEFT JOIN `pa_member` m ON m.`id` = i.`member_id`
     LEFT JOIN `pa_oauth_principal` p ON p.`id` = i.`principal_id`
     WHERE m.`id` IS NULL
       OR (i.`principal_id` IS NOT NULL AND (p.`id` IS NULL OR p.`member_id` <> i.`member_id`)))
  + (SELECT COUNT(*) FROM `pa_oauth_completion_ticket` c
     LEFT JOIN `pa_member` m ON m.`id` = c.`member_id`
     WHERE m.`id` IS NULL)
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_invalid_legacy_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_legacy_relationship_verification_failed`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

SET @pa_mt03_oauth_attempt_rows = (SELECT COUNT(*) FROM `pa_oauth_attempt`);
SET @pa_mt03_oauth_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_oauth_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_attempt_rows = 0
    OR (@pa_mt03_oauth_active_tenant_count = 1 AND @pa_mt03_oauth_default_tenant_id > 0),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_requires_exactly_one_active_tenant_for_legacy_attempts`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

ALTER TABLE `pa_oauth_principal` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_oauth_identity` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_oauth_attempt` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_oauth_completion_ticket` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_oauth_principal` p
JOIN `pa_member` m ON m.`id` = p.`member_id`
SET p.`tenant_id` = m.`tenant_id`
WHERE p.`tenant_id` IS NULL;
UPDATE `pa_oauth_identity` i
JOIN `pa_member` m ON m.`id` = i.`member_id`
SET i.`tenant_id` = m.`tenant_id`
WHERE i.`tenant_id` IS NULL;
UPDATE `pa_oauth_completion_ticket` c
JOIN `pa_member` m ON m.`id` = c.`member_id`
SET c.`tenant_id` = m.`tenant_id`
WHERE c.`tenant_id` IS NULL;
UPDATE `pa_oauth_attempt`
SET `tenant_id` = @pa_mt03_oauth_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt03_oauth_invalid_rows = (
  (SELECT COUNT(*) FROM `pa_oauth_principal` p
   JOIN `pa_member` m ON m.`id` = p.`member_id`
   WHERE p.`tenant_id` IS NULL OR p.`tenant_id` = 0 OR p.`tenant_id` <> m.`tenant_id`)
  + (SELECT COUNT(*) FROM `pa_oauth_identity` i
     JOIN `pa_member` m ON m.`id` = i.`member_id`
     LEFT JOIN `pa_oauth_principal` p ON p.`id` = i.`principal_id`
     WHERE i.`tenant_id` IS NULL OR i.`tenant_id` = 0 OR i.`tenant_id` <> m.`tenant_id`
       OR (i.`principal_id` IS NOT NULL AND i.`tenant_id` <> p.`tenant_id`))
  + (SELECT COUNT(*) FROM `pa_oauth_attempt` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
  + (SELECT COUNT(*) FROM `pa_oauth_completion_ticket` c
     JOIN `pa_member` m ON m.`id` = c.`member_id`
     WHERE c.`tenant_id` IS NULL OR c.`tenant_id` = 0 OR c.`tenant_id` <> m.`tenant_id`)
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

ALTER TABLE `pa_oauth_principal`
  DROP INDEX `uk_provider_scope_union`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_principal_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_oauth_principal_tenant_union` (`tenant_id`, `provider`, `union_scope`, `union_id`),
  ADD KEY `idx_oauth_principal_tenant_member` (`tenant_id`, `member_id`),
  ADD CONSTRAINT `fk_oauth_principal_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_principal_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_oauth_identity`
  DROP INDEX `uk_provider_client_subject`,
  DROP INDEX `uk_member_provider_client`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_identity_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_oauth_identity_tenant_subject` (`tenant_id`, `provider`, `client_key`, `subject`),
  ADD UNIQUE KEY `uk_oauth_identity_tenant_member_client` (`tenant_id`, `member_id`, `provider`, `client_key`),
  ADD KEY `idx_oauth_identity_tenant_member_terminal` (`tenant_id`, `member_id`, `terminal`),
  ADD KEY `idx_oauth_identity_tenant_principal` (`tenant_id`, `principal_id`),
  ADD CONSTRAINT `fk_oauth_identity_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_identity_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_identity_principal` FOREIGN KEY (`tenant_id`, `principal_id`) REFERENCES `pa_oauth_principal` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_oauth_attempt`
  DROP INDEX `uk_state_hash`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_attempt_tenant_state` (`tenant_id`, `state_hash`),
  ADD KEY `idx_oauth_attempt_tenant_expires` (`tenant_id`, `expires_at`),
  ADD CONSTRAINT `fk_oauth_attempt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_oauth_completion_ticket`
  DROP INDEX `uk_token_hash`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_ticket_tenant_token` (`tenant_id`, `token_hash`),
  ADD KEY `idx_oauth_ticket_tenant_member` (`tenant_id`, `member_id`),
  ADD KEY `idx_oauth_ticket_tenant_expires` (`tenant_id`, `expires_at`),
  ADD CONSTRAINT `fk_oauth_ticket_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_ticket_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;


SET NAMES utf8mb4;

SET @pa_org_tenant_mismatch_count = (
  SELECT
    (SELECT COUNT(*) FROM `pa_admin_role` x LEFT JOIN `pa_admin` a ON a.`tenant_id` = x.`tenant_id` AND a.`id` = x.`admin_id` LEFT JOIN `pa_system_role` r ON r.`tenant_id` = x.`tenant_id` AND r.`id` = x.`role_id` WHERE a.`id` IS NULL OR r.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_admin_dept` x LEFT JOIN `pa_admin` a ON a.`tenant_id` = x.`tenant_id` AND a.`id` = x.`admin_id` LEFT JOIN `pa_dept` d ON d.`tenant_id` = x.`tenant_id` AND d.`id` = x.`dept_id` WHERE a.`id` IS NULL OR d.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_admin_jobs` x LEFT JOIN `pa_admin` a ON a.`tenant_id` = x.`tenant_id` AND a.`id` = x.`admin_id` LEFT JOIN `pa_jobs` j ON j.`tenant_id` = x.`tenant_id` AND j.`id` = x.`jobs_id` WHERE a.`id` IS NULL OR j.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_system_role_menu` x LEFT JOIN `pa_system_role` r ON r.`tenant_id` = x.`tenant_id` AND r.`id` = x.`role_id` LEFT JOIN `pa_system_menu` m ON m.`id` = x.`menu_id` WHERE r.`id` IS NULL OR m.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_dept` d LEFT JOIN `pa_dept` p ON p.`tenant_id` = d.`tenant_id` AND p.`id` = d.`pid` WHERE d.`pid` <> 0 AND p.`id` IS NULL)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_admin` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `username` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_admin` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `nickname` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_system_role` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `name` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_dept` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `name` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_jobs` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `name` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_jobs` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `code` HAVING COUNT(*) > 1) duplicates)
);
SET @pa_sql = IF(
  @pa_org_tenant_mismatch_count = 0,
  'SELECT 1',
  'SELECT * FROM `MT02_ORG_TENANT_RELATION_MISMATCH`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_admin`
  ADD COLUMN `active_username` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `username`, NULL)) STORED AFTER `username`,
  ADD COLUMN `active_nickname` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `nickname`, NULL)) STORED AFTER `nickname`,
  ADD UNIQUE KEY `uk_admin_tenant_active_username` (`tenant_id`, `active_username`),
  ADD UNIQUE KEY `uk_admin_tenant_active_nickname` (`tenant_id`, `active_nickname`);
ALTER TABLE `pa_system_role`
  ADD COLUMN `active_name` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `name`, NULL)) STORED AFTER `name`,
  ADD UNIQUE KEY `uk_system_role_tenant_active_name` (`tenant_id`, `active_name`);
ALTER TABLE `pa_dept`
  ADD COLUMN `active_name` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `name`, NULL)) STORED AFTER `name`,
  ADD UNIQUE KEY `uk_dept_tenant_active_name` (`tenant_id`, `active_name`);
ALTER TABLE `pa_jobs`
  ADD COLUMN `active_name` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `name`, NULL)) STORED AFTER `name`,
  ADD COLUMN `active_code` VARCHAR(64)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `code`, NULL)) STORED AFTER `code`,
  ADD UNIQUE KEY `uk_jobs_tenant_active_name` (`tenant_id`, `active_name`),
  ADD UNIQUE KEY `uk_jobs_tenant_active_code` (`tenant_id`, `active_code`);

ALTER TABLE `pa_admin_role`
  ADD CONSTRAINT `fk_admin_role_admin_owner`
    FOREIGN KEY (`tenant_id`, `admin_id`) REFERENCES `pa_admin` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_admin_role_role_owner`
    FOREIGN KEY (`tenant_id`, `role_id`) REFERENCES `pa_system_role` (`tenant_id`, `id`) ON DELETE RESTRICT;
ALTER TABLE `pa_admin_dept`
  ADD CONSTRAINT `fk_admin_dept_admin_owner`
    FOREIGN KEY (`tenant_id`, `admin_id`) REFERENCES `pa_admin` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_admin_dept_dept_owner`
    FOREIGN KEY (`tenant_id`, `dept_id`) REFERENCES `pa_dept` (`tenant_id`, `id`) ON DELETE RESTRICT;
ALTER TABLE `pa_admin_jobs`
  ADD CONSTRAINT `fk_admin_jobs_admin_owner`
    FOREIGN KEY (`tenant_id`, `admin_id`) REFERENCES `pa_admin` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_admin_jobs_jobs_owner`
    FOREIGN KEY (`tenant_id`, `jobs_id`) REFERENCES `pa_jobs` (`tenant_id`, `id`) ON DELETE RESTRICT;
ALTER TABLE `pa_system_role_menu`
  ADD CONSTRAINT `fk_role_menu_role_owner`
    FOREIGN KEY (`tenant_id`, `role_id`) REFERENCES `pa_system_role` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_role_menu_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `pa_system_menu` (`id`) ON DELETE RESTRICT;


-- MT03 recharge/refund transaction Tenant ownership.
-- pa_payment_scene and pa_config(type=pay/recharge) remain instance-owned.

SET @pa_mt03_finance_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_tenant', 'pa_member', 'pa_recharge_order', 'pa_refund_record', 'pa_refund_log')
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_required_table_count = 5,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_requires_all_owned_tables_before_backfill`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

SET @pa_mt03_finance_member_owner_ready = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND COLUMN_NAME = 'tenant_id' AND IS_NULLABLE = 'NO'
);
SET @pa_mt03_finance_member_identity_ready = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND INDEX_NAME = 'uk_member_tenant_id' AND NON_UNIQUE = 0
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_member_owner_ready = 1 AND @pa_mt03_finance_member_identity_ready = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_requires_member_tenant_ownership_before_backfill`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

SET @pa_mt03_finance_legacy_rows = (
  (SELECT COUNT(*) FROM `pa_recharge_order`)
  + (SELECT COUNT(*) FROM `pa_refund_record`)
  + (SELECT COUNT(*) FROM `pa_refund_log`)
);
SET @pa_mt03_finance_default_tenant_count = (
  SELECT COUNT(*) FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
);
SET @pa_mt03_finance_default_tenant_id = (
  SELECT `id` FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
  LIMIT 1
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_legacy_rows = 0
    OR (@pa_mt03_finance_default_tenant_count = 1 AND @pa_mt03_finance_default_tenant_id > 0),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_requires_completed_default_tenant_for_backfill`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

SET @pa_mt03_finance_invalid_legacy_rows = (
  (SELECT COUNT(*) FROM `pa_recharge_order` o LEFT JOIN `pa_member` m ON m.`id` = o.`user_id` WHERE m.`id` IS NULL)
  + (SELECT COUNT(*) FROM `pa_refund_record` r
     LEFT JOIN `pa_recharge_order` o ON o.`id` = r.`order_id`
     LEFT JOIN `pa_member` m ON m.`id` = r.`user_id`
     WHERE o.`id` IS NULL OR m.`id` IS NULL OR o.`user_id` <> r.`user_id`)
  + (SELECT COUNT(*) FROM `pa_refund_log` l
     LEFT JOIN `pa_refund_record` r ON r.`id` = l.`record_id`
     LEFT JOIN `pa_member` m ON m.`id` = l.`user_id`
     WHERE r.`id` IS NULL OR m.`id` IS NULL OR r.`user_id` <> l.`user_id`)
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_invalid_legacy_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_legacy_relationship_verification_failed`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

ALTER TABLE `pa_recharge_order` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_refund_record` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_refund_log` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_recharge_order` SET `tenant_id` = @pa_mt03_finance_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_refund_record` SET `tenant_id` = @pa_mt03_finance_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_refund_log` SET `tenant_id` = @pa_mt03_finance_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt03_finance_invalid_rows = (
  (SELECT COUNT(*) FROM `pa_recharge_order` o
   JOIN `pa_member` m ON m.`id` = o.`user_id`
   WHERE o.`tenant_id` IS NULL OR o.`tenant_id` = 0 OR o.`tenant_id` <> m.`tenant_id`)
  + (SELECT COUNT(*) FROM `pa_refund_record` r
     JOIN `pa_recharge_order` o ON o.`id` = r.`order_id`
     JOIN `pa_member` m ON m.`id` = r.`user_id`
     WHERE r.`tenant_id` IS NULL OR r.`tenant_id` = 0
       OR r.`tenant_id` <> o.`tenant_id` OR r.`tenant_id` <> m.`tenant_id`)
  + (SELECT COUNT(*) FROM `pa_refund_log` l
     JOIN `pa_refund_record` r ON r.`id` = l.`record_id`
     JOIN `pa_member` m ON m.`id` = l.`user_id`
     WHERE l.`tenant_id` IS NULL OR l.`tenant_id` = 0
       OR l.`tenant_id` <> r.`tenant_id` OR l.`tenant_id` <> m.`tenant_id`)
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_finance_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

ALTER TABLE `pa_recharge_order`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_recharge_order_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_recharge_order_tenant_member_time` (`tenant_id`, `user_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_recharge_order_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_recharge_order_member` FOREIGN KEY (`tenant_id`, `user_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_refund_record`
  DROP INDEX `uk_order_type_order_id`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY COLUMN `order_id` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD UNIQUE KEY `uk_refund_record_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_refund_record_tenant_order` (`tenant_id`, `order_type`, `order_id`),
  ADD KEY `idx_refund_record_tenant_status_time` (`tenant_id`, `refund_status`, `create_time`, `id`),
  ADD CONSTRAINT `fk_refund_record_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_record_member` FOREIGN KEY (`tenant_id`, `user_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_record_order` FOREIGN KEY (`tenant_id`, `order_id`) REFERENCES `pa_recharge_order` (`tenant_id`, `id`) ON DELETE RESTRICT;

SET @pa_mt03_finance_cross_tenant_order_duplicates = (
  SELECT COUNT(*) FROM (
    SELECT `order_type`, `order_id`
    FROM `pa_refund_record`
    GROUP BY `order_type`, `order_id`
    HAVING COUNT(*) > 1
  ) duplicates
);
SET @pa_mt03_finance_sql = IF(
  @pa_mt03_finance_cross_tenant_order_duplicates = 0,
  'ALTER TABLE `pa_refund_record` ADD UNIQUE KEY `uk_refund_record_order_global` (`order_type`,`order_id`)',
  'SELECT * FROM `pa_mt03_finance_refund_order_identity_must_remain_globally_unique`'
);
PREPARE pa_mt03_finance_stmt FROM @pa_mt03_finance_sql;
EXECUTE pa_mt03_finance_stmt;
DEALLOCATE PREPARE pa_mt03_finance_stmt;

ALTER TABLE `pa_refund_log`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  ADD UNIQUE KEY `uk_refund_log_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_refund_log_tenant_record_time` (`tenant_id`, `record_id`, `create_time`, `id`),
  ADD CONSTRAINT `fk_refund_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_log_member` FOREIGN KEY (`tenant_id`, `user_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refund_log_record` FOREIGN KEY (`tenant_id`, `record_id`) REFERENCES `pa_refund_record` (`tenant_id`, `id`) ON DELETE RESTRICT;


SET NAMES utf8mb4;

CREATE TABLE `pa_task_job` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `task_type` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `handler_key` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_json` JSON NOT NULL,
  `payload_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `trusted_envelope` TEXT NOT NULL,
  `idempotency_key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
  `priority` SMALLINT NOT NULL DEFAULT 0,
  `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` SMALLINT UNSIGNED NOT NULL,
  `available_at` DATETIME(3) NOT NULL,
  `lease_owner_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `lease_token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `lease_expires_at` DATETIME(3) NULL,
  `last_error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `created_by_member_id` BIGINT UNSIGNED NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `completed_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_job_key` (`job_key`),
  UNIQUE KEY `uk_task_job_tenant_id` (`tenant_id`, `id`),
  UNIQUE KEY `uk_task_job_idempotency` (`tenant_id`, `created_by_member_id`, `task_type`, `idempotency_key_hash`),
  KEY `idx_task_job_claim` (`tenant_id`, `status`, `available_at`, `priority`, `id`),
  KEY `idx_task_job_lease` (`status`, `lease_expires_at`, `id`),
  CONSTRAINT `fk_task_job_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_task_job_member` FOREIGN KEY (`tenant_id`, `created_by_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_task_job_key` CHECK (`job_key` REGEXP '^job_[0-9a-f]{32}$'),
  CONSTRAINT `chk_task_job_payload_hash` CHECK (`payload_hash` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_task_job_status` CHECK (`status` IN ('queued','running','succeeded','dead','cancelled')),
  CONSTRAINT `chk_task_job_attempts` CHECK (`max_attempts` BETWEEN 1 AND 10 AND `attempt_count` <= `max_attempts`),
  CONSTRAINT `chk_task_job_revision` CHECK (`revision` >= 1),
  CONSTRAINT `chk_task_job_lease` CHECK ((`status` = 'running') = (`lease_owner_hash` IS NOT NULL AND `lease_token_hash` IS NOT NULL AND `lease_expires_at` IS NOT NULL)),
  CONSTRAINT `chk_task_job_completion` CHECK ((`status` IN ('succeeded','dead','cancelled')) = (`completed_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_task_job_attempt` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `attempt_number` SMALLINT UNSIGNED NOT NULL,
  `worker_id_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `lease_token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'running',
  `error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `started_at` DATETIME(3) NOT NULL,
  `completed_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_attempt_number` (`tenant_id`, `job_id`, `attempt_number`),
  KEY `idx_task_attempt_status` (`tenant_id`, `status`, `started_at`, `id`),
  CONSTRAINT `fk_task_attempt_job` FOREIGN KEY (`tenant_id`, `job_id`) REFERENCES `pa_task_job` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_task_attempt_status` CHECK (`status` IN ('running','succeeded','retry','dead','abandoned')),
  CONSTRAINT `chk_task_attempt_completion` CHECK ((`status` = 'running') = (`completed_at` IS NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_task_job_event` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `event_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `actor_member_id` BIGINT UNSIGNED NULL,
  `metadata_json` JSON NOT NULL,
  `occurred_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_event_job` (`tenant_id`, `job_id`, `id`),
  KEY `idx_task_event_time` (`tenant_id`, `occurred_at`, `id`),
  CONSTRAINT `fk_task_event_job` FOREIGN KEY (`tenant_id`, `job_id`) REFERENCES `pa_task_job` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_task_event_member` FOREIGN KEY (`tenant_id`, `actor_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_task_event_key` CHECK (`event_key` REGEXP '^tenant\\.task\\.[a-z_]+$')
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_import_export_operation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_key` VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `created_by_member_id` BIGINT UNSIGNED NOT NULL,
  `provider_key` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `direction` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `format` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'csv',
  `status` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
  `input_file_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `result_file_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `error_file_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `task_job_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `schema_revision` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mapping_json` JSON NOT NULL,
  `processed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `accepted_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `rejected_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `attempt_number` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `idempotency_key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `last_error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `retention_until` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `completed_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_import_export_key` (`operation_key`),
  UNIQUE KEY `uk_import_export_tenant_id` (`tenant_id`, `id`),
  UNIQUE KEY `uk_import_export_idempotency` (`tenant_id`, `created_by_member_id`, `direction`, `provider_key`, `idempotency_key_hash`),
  KEY `idx_import_export_status` (`tenant_id`, `status`, `id`),
  KEY `idx_import_export_task` (`tenant_id`, `task_job_key`),
  KEY `idx_import_export_retention` (`status`, `retention_until`, `id`),
  CONSTRAINT `fk_import_export_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_import_export_member` FOREIGN KEY (`tenant_id`, `created_by_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_import_export_key` CHECK (`operation_key` REGEXP '^iox_[0-9a-f]{32}$'),
  CONSTRAINT `chk_import_export_direction` CHECK (`direction` IN ('import','export')),
  CONSTRAINT `chk_import_export_format` CHECK (`format` = 'csv'),
  CONSTRAINT `chk_import_export_status` CHECK (`status` IN ('queued','running','cancel_requested','succeeded','failed','cancelled','expired')),
  CONSTRAINT `chk_import_export_input` CHECK ((`direction` = 'import') = (`input_file_key` IS NOT NULL)),
  CONSTRAINT `chk_import_export_files` CHECK ((`result_file_key` IS NULL OR `result_file_key` REGEXP '^file_[0-9a-f]{32}$') AND (`error_file_key` IS NULL OR `error_file_key` REGEXP '^file_[0-9a-f]{32}$')),
  CONSTRAINT `chk_import_export_task` CHECK (`task_job_key` IS NULL OR `task_job_key` REGEXP '^job_[0-9a-f]{32}$'),
  CONSTRAINT `chk_import_export_hashes` CHECK (`idempotency_key_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_import_export_progress` CHECK (`accepted_rows` + `rejected_rows` <= `processed_rows` AND `processed_rows` <= 100000 AND `total_rows` <= 100000),
  CONSTRAINT `chk_import_export_attempt` CHECK (`attempt_number` <= 10),
  CONSTRAINT `chk_import_export_revision` CHECK (`revision` >= 1),
  CONSTRAINT `chk_import_export_completion` CHECK ((`status` IN ('succeeded','failed','cancelled','expired')) = (`completed_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_import_export_row_error` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `operation_id` BIGINT UNSIGNED NOT NULL,
  `row_number` INT UNSIGNED NOT NULL,
  `column_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `column_key_unique` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (COALESCE(`column_key`, '')) STORED,
  `error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `occurred_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_import_export_row_error` (`tenant_id`, `operation_id`, `row_number`, `column_key_unique`, `error_code`),
  KEY `idx_import_export_row_error` (`tenant_id`, `operation_id`, `row_number`, `id`),
  CONSTRAINT `fk_import_export_row_operation` FOREIGN KEY (`tenant_id`, `operation_id`) REFERENCES `pa_import_export_operation` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_import_export_row_number` CHECK (`row_number` >= 2 AND `row_number` <= 100001),
  CONSTRAINT `chk_import_export_column` CHECK (`column_key` IS NULL OR `column_key` REGEXP '^[a-z][a-z0-9_]{0,63}$'),
  CONSTRAINT `chk_import_export_error_code` CHECK (`error_code` REGEXP '^[A-Z][A-Z0-9_]{2,63}$')
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_file_object` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `storage_provider_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `storage_key` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `media_type` VARCHAR(127) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL,
  `sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ready',
  `created_by_member_id` BIGINT UNSIGNED NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `archived_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_file_object_key` (`file_key`),
  UNIQUE KEY `uk_file_object_storage` (`tenant_id`, `storage_provider_key`, `storage_key`),
  KEY `idx_file_object_status` (`tenant_id`, `status`, `id`),
  KEY `idx_file_object_sha256` (`tenant_id`, `sha256`),
  CONSTRAINT `fk_file_object_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_file_object_member` FOREIGN KEY (`tenant_id`, `created_by_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_file_object_key` CHECK (`file_key` REGEXP '^file_[0-9a-f]{32}$'),
  CONSTRAINT `chk_file_object_sha256` CHECK (`sha256` REGEXP '^[0-9a-f]{64}$'),
  CONSTRAINT `chk_file_object_status` CHECK (`status` IN ('ready', 'archived')),
  CONSTRAINT `chk_file_object_revision` CHECK (`revision` >= 1),
  CONSTRAINT `chk_file_object_archive_shape` CHECK ((`status` = 'ready' AND `archived_at` IS NULL) OR (`status` = 'archived' AND `archived_at` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT `parent`.`id`,'A','操作日志异步导出','',0,'log/export','','',0,1,0
FROM `pa_system_menu` AS `parent`
WHERE `parent`.`paths`='/system/log' AND `parent`.`type`='C'
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`)='log/export'
  );


SET @pa_mt04_login_client_column = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pa_login_challenge'
    AND COLUMN_NAME = 'client_key'
);
SET @pa_mt04_login_client_sql = IF(
  @pa_mt04_login_client_column = 0,
  'ALTER TABLE `pa_login_challenge` ADD COLUMN `client_key` VARCHAR(64) NOT NULL DEFAULT ''admin-web'' AFTER `purpose`',
  'SELECT 1'
);
PREPARE pa_mt04_login_client_stmt FROM @pa_mt04_login_client_sql;
EXECUTE pa_mt04_login_client_stmt;
DEALLOCATE PREPARE pa_mt04_login_client_stmt;

SET @pa_mt04_login_client_check = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pa_login_challenge'
    AND CONSTRAINT_NAME = 'chk_login_challenge_client'
);
SET @pa_mt04_login_check_sql = IF(
  @pa_mt04_login_client_check = 0,
  'ALTER TABLE `pa_login_challenge` ADD CONSTRAINT `chk_login_challenge_client` CHECK (REGEXP_LIKE(`client_key`, ''^[a-z][a-z0-9-]{0,63}$'', ''c''))',
  'SELECT 1'
);
PREPARE pa_mt04_login_check_stmt FROM @pa_mt04_login_check_sql;
EXECUTE pa_mt04_login_check_stmt;
DEALLOCATE PREPARE pa_mt04_login_check_stmt;

ALTER TABLE `pa_login_challenge` ALTER COLUMN `client_key` DROP DEFAULT;


CREATE TABLE `pa_module_installation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(96) NOT NULL,
  `installed_version` VARCHAR(32) NOT NULL,
  `manifest_schema_version` INT UNSIGNED NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `installed_at` DATETIME(3) NULL,
  `activated_at` DATETIME(3) NULL,
  `upgraded_at` DATETIME(3) NULL,
  `last_error_code` VARCHAR(96) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_installation_key` (`module_key`),
  KEY `idx_module_installation_status` (`status`, `module_key`),
  CONSTRAINT `chk_module_installation_status` CHECK (`status` IN ('installing','active','upgrading','maintenance','failed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- MT03 per-Tenant transaction policy ownership.
-- Other pa_config types remain instance-owned unless their own domain explicitly migrates them.

SET @pa_mt03_transaction_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('pa_tenant', 'pa_config')
);
SET @pa_mt03_transaction_sql = IF(
  @pa_mt03_transaction_required_table_count = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_transaction_requires_tenant_and_config_before_migration`'
);
PREPARE pa_mt03_transaction_stmt FROM @pa_mt03_transaction_sql;
EXECUTE pa_mt03_transaction_stmt;
DEALLOCATE PREPARE pa_mt03_transaction_stmt;

SET @pa_mt03_transaction_invalid_legacy_rows = (
  SELECT COUNT(*) FROM `pa_config`
  WHERE `type` = 'transaction'
    AND (`name` NOT IN (
      'cancel_unpaid_orders', 'cancel_unpaid_orders_times',
      'verification_orders', 'verification_orders_times'
    ) OR `value` NOT REGEXP '^[0-9]+$'
      OR (`name` IN ('cancel_unpaid_orders', 'verification_orders') AND CAST(`value` AS UNSIGNED) NOT IN (0, 1))
      OR (`name` IN ('cancel_unpaid_orders_times', 'verification_orders_times') AND CAST(`value` AS UNSIGNED) < 1))
);
SET @pa_mt03_transaction_sql = IF(
  @pa_mt03_transaction_invalid_legacy_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_transaction_invalid_legacy_policy`'
);
PREPARE pa_mt03_transaction_stmt FROM @pa_mt03_transaction_sql;
EXECUTE pa_mt03_transaction_stmt;
DEALLOCATE PREPARE pa_mt03_transaction_stmt;

CREATE TABLE `pa_transaction_setting` (
  `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`                    BIGINT UNSIGNED NOT NULL,
  `cancel_unpaid_orders`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `cancel_unpaid_orders_times`   INT UNSIGNED NOT NULL DEFAULT 30,
  `verification_orders`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `verification_orders_times`    INT UNSIGNED NOT NULL DEFAULT 24,
  `create_time`                  INT UNSIGNED NULL DEFAULT NULL,
  `update_time`                  INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transaction_setting_tenant` (`tenant_id`),
  CONSTRAINT `chk_transaction_setting_cancel_mode` CHECK (`cancel_unpaid_orders` IN (0, 1)),
  CONSTRAINT `chk_transaction_setting_cancel_time` CHECK (`cancel_unpaid_orders_times` > 0),
  CONSTRAINT `chk_transaction_setting_verify_mode` CHECK (`verification_orders` IN (0, 1)),
  CONSTRAINT `chk_transaction_setting_verify_time` CHECK (`verification_orders_times` > 0),
  CONSTRAINT `fk_transaction_setting_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tenant交易运营政策';

INSERT INTO `pa_transaction_setting` (
  `tenant_id`, `cancel_unpaid_orders`, `cancel_unpaid_orders_times`,
  `verification_orders`, `verification_orders_times`, `create_time`, `update_time`
)
SELECT
  t.`id`,
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='cancel_unpaid_orders' LIMIT 1), 1),
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='cancel_unpaid_orders_times' LIMIT 1), 30),
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='verification_orders' LIMIT 1), 1),
  COALESCE((SELECT CAST(c.`value` AS UNSIGNED) FROM `pa_config` c WHERE c.`type`='transaction' AND c.`name`='verification_orders_times' LIMIT 1), 24),
  UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `pa_tenant` t;

SET @pa_mt03_transaction_invalid_owned_rows = (
  SELECT COUNT(*) FROM `pa_transaction_setting` s
  LEFT JOIN `pa_tenant` t ON t.`id` = s.`tenant_id`
  WHERE t.`id` IS NULL OR s.`tenant_id` = 0
    OR s.`cancel_unpaid_orders` NOT IN (0, 1)
    OR s.`cancel_unpaid_orders_times` < 1
    OR s.`verification_orders` NOT IN (0, 1)
    OR s.`verification_orders_times` < 1
);
SET @pa_mt03_transaction_missing_tenants = (
  SELECT COUNT(*) FROM `pa_tenant` t
  LEFT JOIN `pa_transaction_setting` s ON s.`tenant_id` = t.`id`
  WHERE s.`tenant_id` IS NULL
);
SET @pa_mt03_transaction_sql = IF(
  @pa_mt03_transaction_invalid_owned_rows = 0 AND @pa_mt03_transaction_missing_tenants = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_transaction_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_transaction_stmt FROM @pa_mt03_transaction_sql;
EXECUTE pa_mt03_transaction_stmt;
DEALLOCATE PREPARE pa_mt03_transaction_stmt;

DELETE FROM `pa_config` WHERE `type` = 'transaction';


-- Register every historically valid Tenant Admin API route before default-deny is enabled.
-- Each exact URI is its own permission node. Existing role grants are inherited only from
-- the nearest existing capability; no runtime URI alias can enlarge authorization.

DROP TEMPORARY TABLE IF EXISTS `pa_admin_permission_seed`;
CREATE TEMPORARY TABLE `pa_admin_permission_seed` (
  `perms` VARCHAR(100) NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `parent_permission` VARCHAR(100) NOT NULL DEFAULT '',
  `parent_path` VARCHAR(200) NOT NULL DEFAULT '',
  `inherit_permission` VARCHAR(100) NOT NULL DEFAULT '',
  `inherit_path` VARCHAR(200) NOT NULL DEFAULT '',
  `type` CHAR(1) NOT NULL DEFAULT 'A',
  `paths` VARCHAR(200) NOT NULL DEFAULT '',
  `component` VARCHAR(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`perms`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pa_admin_permission_seed`
  (`perms`,`name`,`parent_permission`,`parent_path`,`inherit_permission`,`inherit_path`,`type`,`paths`,`component`)
VALUES
  ('menu/all','菜单选择','menu/lists','','menu/lists','','A','',''),
  ('menu/detail','菜单详情','menu/lists','','menu/lists','','A','',''),
  ('menu/status','菜单状态','menu/lists','','menu/edit','','A','',''),
  ('role/all','角色选择','role/lists','','role/lists','','A','',''),
  ('role/detail','角色详情','role/lists','','role/lists','','A','',''),
  ('admin/detail','管理员详情','admin/lists','','admin/lists','','A','',''),
  ('admin/status','管理员状态','admin/lists','','admin/edit','','A','',''),
  ('dept/all','部门选择','dept/lists','','dept/lists','','A','',''),
  ('dept/leaderdept','负责人部门','dept/lists','','dept/lists','','A','',''),
  ('dept/detail','部门详情','dept/lists','','dept/lists','','A','',''),
  ('dept/status','部门状态','dept/lists','','dept/edit','','A','',''),
  ('jobs/all','岗位选择','jobs/lists','','jobs/lists','','A','',''),
  ('jobs/detail','岗位详情','jobs/lists','','jobs/lists','','A','',''),
  ('jobs/status','岗位状态','jobs/lists','','jobs/edit','','A','',''),
  ('log/export/status','导出状态','log/lists','','log/export','','A','',''),
  ('log/export/download','导出下载','log/lists','','log/export','','A','',''),
  ('article.articlecate/all','文章分类选择','article.articlecate/lists','','article.articlecate/lists','','A','',''),
  ('finance/account-log/lists','余额明细 REST 列表','finance.account_log/lists','','finance.account_log/lists','','A','',''),
  ('finance/account-log/change-types','余额变动类型','finance.account_log/lists','','finance.account_log/lists','','A','',''),
  ('finance.account_log/getumchangetype','余额变动类型兼容接口','finance.account_log/lists','','finance.account_log/lists','','A','',''),
  ('setting/transaction/config','交易设置','', '/app-setting','', '/app-setting','C','/app-setting/transaction','app-setting/transaction/index'),
  ('setting/transaction/save','交易设置保存','setting/transaction/config','','','/app-setting','A','',''),
  ('finance/recharge/lists','充值记录 REST 列表','recharge.recharge/lists','','recharge.recharge/lists','','A','',''),
  ('finance/recharge/refund','充值退款 REST 接口','recharge.recharge/lists','','recharge.recharge/refund','','A','',''),
  ('finance/recharge/refundagain','重新退款 REST 接口','finance.refund/record','','recharge.recharge/refundagain','','A','',''),
  ('finance/refund/stat','退款统计 REST 接口','finance.refund/record','','finance.refund/record','','A','',''),
  ('finance/refund/record','退款记录 REST 接口','finance.refund/record','','finance.refund/record','','A','',''),
  ('finance/refund/log','退款日志 REST 接口','finance.refund/record','','finance.refund/log','','A','',''),
  ('finance.refund/stat','退款统计兼容接口','finance.refund/record','','finance.refund/record','','A','','');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT
  COALESCE(
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE LOWER(p.`perms`)=LOWER(seed.`parent_permission`)), 0),
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE p.`paths`=seed.`parent_path`), 0)
  ),
  seed.`type`, seed.`name`, '', 0, seed.`perms`, seed.`paths`, seed.`component`, 0, 1, 0
FROM `pa_admin_permission_seed` seed
WHERE COALESCE(
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE LOWER(p.`perms`)=LOWER(seed.`parent_permission`)), 0),
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE p.`paths`=seed.`parent_path`), 0)
  ) IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` existing WHERE LOWER(existing.`perms`)=LOWER(seed.`perms`)
  );

-- Preserve only grants that already authorized the corresponding capability.
INSERT IGNORE INTO `pa_system_role_menu` (`tenant_id`,`role_id`,`menu_id`)
SELECT DISTINCT inherited.`tenant_id`, inherited.`role_id`, registered.`id`
FROM `pa_admin_permission_seed` seed
JOIN `pa_system_menu` registered ON LOWER(registered.`perms`)=LOWER(seed.`perms`)
JOIN `pa_system_menu` source ON (
  (seed.`inherit_permission`<>'' AND LOWER(source.`perms`)=LOWER(seed.`inherit_permission`))
  OR (seed.`inherit_path`<>'' AND source.`paths`=seed.`inherit_path`)
)
JOIN `pa_system_role_menu` inherited ON inherited.`menu_id`=source.`id`;

SET @pa_admin_permission_missing_nodes = (
  SELECT COUNT(*)
  FROM `pa_admin_permission_seed` seed
  LEFT JOIN `pa_system_menu` registered
    ON LOWER(registered.`perms`)=LOWER(seed.`perms`) AND registered.`is_disable`=0
  WHERE registered.`id` IS NULL
);
SET @pa_admin_permission_sql = IF(
  @pa_admin_permission_missing_nodes=0,
  'SELECT 1',
  'SELECT * FROM `pa_admin_api_permission_registration_failed`'
);
PREPARE pa_admin_permission_stmt FROM @pa_admin_permission_sql;
EXECUTE pa_admin_permission_stmt;
DEALLOCATE PREPARE pa_admin_permission_stmt;

DROP TEMPORARY TABLE `pa_admin_permission_seed`;


-- Trusted external callback routing for the formal fresh-install default Tenant.

SET @pa_external_required = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
      'pa_tenant', 'pa_config',
      'pa_oauth_attempt', 'pa_oauth_completion_ticket', 'pa_official_account_reply'
    )
);
SET @pa_external_sql = IF(
  @pa_external_required = 5,
  'SELECT 1',
  'SELECT * FROM `pa_external_callback_requires_tenant_oauth_config_and_reply_tables`'
);
PREPARE pa_external_stmt FROM @pa_external_sql;
EXECUTE pa_external_stmt;
DEALLOCATE PREPARE pa_external_stmt;

CREATE TABLE `pa_external_channel_binding` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(64) NOT NULL,
  `callback_key` CHAR(64) NOT NULL COMMENT 'server generated opaque callback route key',
  `identity_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 of canonical public provider identity',
  `identity_hint` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'non-secret audit/display hint',
  `config_json` JSON NOT NULL COMMENT 'server-controlled provider verifier/transport config',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_external_callback_key` (`provider`, `callback_key`),
  UNIQUE KEY `uk_external_provider_identity` (`provider`, `identity_hash`),
  UNIQUE KEY `uk_external_tenant_provider` (`tenant_id`, `provider`),
  KEY `idx_external_tenant_status` (`tenant_id`, `status`, `id`),
  CONSTRAINT `fk_external_binding_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tenant-owned external provider binding and callback verifier configuration';

SET @pa_external_default_count = (
  SELECT COUNT(*) FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
);
SET @pa_external_default_tenant = (
  SELECT `id` FROM `pa_tenant`
  WHERE `code` = 'default' AND `status` = 'active'
  LIMIT 1
);
SET @pa_external_sql = IF(
  @pa_external_default_count = 1 AND @pa_external_default_tenant > 0,
  'SELECT 1',
  'SELECT * FROM `pa_external_callback_requires_one_explicit_active_default_binding`'
);
PREPARE pa_external_stmt FROM @pa_external_sql;
EXECUTE pa_external_stmt;
DEALLOCATE PREPARE pa_external_stmt;

SET @pa_wx_appid = (SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_appid' LIMIT 1);
SET @pa_wx_mchid = (SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_mch_id' LIMIT 1);
SET @pa_ali_appid = (SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='ali_pay_app_id' LIMIT 1);
SET @pa_ali_seller = (SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='ali_pay_seller_id' LIMIT 1);
SET @pa_oa_appid = (SELECT `value` FROM `pa_config` WHERE `type`='oa_setting' AND `name`='app_id' LIMIT 1);
SET @pa_oa_original = (SELECT `value` FROM `pa_config` WHERE `type`='oa_setting' AND `name`='original_id' LIMIT 1);
SET @pa_mnp_appid = (SELECT `value` FROM `pa_config` WHERE `type`='mnp_setting' AND `name`='app_id' LIMIT 1);
SET @pa_open_appid = (SELECT `value` FROM `pa_config` WHERE `type`='open_platform' AND `name`='app_id' LIMIT 1);

INSERT INTO `pa_external_channel_binding`
  (`tenant_id`,`provider`,`callback_key`,`identity_hash`,`identity_hint`,`config_json`,`status`,`create_time`,`update_time`)
SELECT @pa_external_default_tenant, seed.provider, LOWER(HEX(RANDOM_BYTES(32))),
  SHA2(LOWER(TRIM(seed.identity)), 256), RIGHT(TRIM(seed.identity), 8), seed.config_json,
  seed.status, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 'payment.wechat' provider, CONCAT(COALESCE(@pa_wx_appid,''), ':', COALESCE(@pa_wx_mchid,'')) identity,
    JSON_OBJECT(
      'wx_pay_status', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_status' LIMIT 1), '0'),
      'wx_pay_appid', COALESCE(@pa_wx_appid,''),
      'wx_pay_mch_id', COALESCE(@pa_wx_mchid,''),
      'wx_pay_secret', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_secret' LIMIT 1), ''),
      'wx_pay_cert_path', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_cert_path' LIMIT 1), ''),
      'wx_pay_cert_key_path', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_cert_key_path' LIMIT 1), ''),
      'wx_pay_platform_cert_path', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_platform_cert_path' LIMIT 1), '')
    ) config_json,
    IF(COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='wx_pay_status' LIMIT 1), '0')='1',1,0) status
  UNION ALL
  SELECT 'payment.alipay', CONCAT(COALESCE(@pa_ali_appid,''), ':', COALESCE(@pa_ali_seller,'')),
    JSON_OBJECT(
      'ali_pay_status', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='ali_pay_status' LIMIT 1), '0'),
      'ali_pay_app_id', COALESCE(@pa_ali_appid,''),
      'ali_pay_seller_id', COALESCE(@pa_ali_seller,''),
      'ali_pay_private_key', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='ali_pay_private_key' LIMIT 1), ''),
      'ali_pay_public_key', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='ali_pay_public_key' LIMIT 1), '')
    ),
    IF(COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='pay' AND `name`='ali_pay_status' LIMIT 1), '0')='1',1,0)
  UNION ALL
  SELECT 'wechat.official-account', COALESCE(NULLIF(@pa_oa_original,''), @pa_oa_appid),
    JSON_OBJECT(
      'app_id', COALESCE(@pa_oa_appid,''),
      'original_id', COALESCE(@pa_oa_original,''),
      'app_secret', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='oa_setting' AND `name`='app_secret' LIMIT 1), ''),
      'token', COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='oa_setting' AND `name`='token' LIMIT 1), '')
    ), IF(COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='oa_setting' AND `name`='token' LIMIT 1), '')='',0,1)
  UNION ALL
  SELECT 'oauth.wechat.oa', COALESCE(@pa_oa_appid,''),
    JSON_OBJECT('app_id',COALESCE(@pa_oa_appid,''),'app_secret',COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='oa_setting' AND `name`='app_secret' LIMIT 1),'')),
    IF(COALESCE(@pa_oa_appid,'')='',0,1)
  UNION ALL
  SELECT 'oauth.wechat.mini-program', COALESCE(@pa_mnp_appid,''),
    JSON_OBJECT('app_id',COALESCE(@pa_mnp_appid,''),'app_secret',COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='mnp_setting' AND `name`='app_secret' LIMIT 1),'')),
    IF(COALESCE(@pa_mnp_appid,'')='',0,1)
  UNION ALL
  SELECT 'oauth.wechat.open-pc', COALESCE(@pa_open_appid,''),
    JSON_OBJECT('app_id',COALESCE(@pa_open_appid,''),'app_secret',COALESCE((SELECT `value` FROM `pa_config` WHERE `type`='open_platform' AND `name`='app_secret' LIMIT 1),'')),
    IF(COALESCE(@pa_open_appid,'')='',0,1)
) seed
WHERE CASE seed.provider
  WHEN 'payment.wechat' THEN COALESCE(@pa_wx_appid,'') <> '' AND COALESCE(@pa_wx_mchid,'') <> ''
  WHEN 'payment.alipay' THEN COALESCE(@pa_ali_appid,'') <> '' AND COALESCE(@pa_ali_seller,'') <> ''
  ELSE TRIM(seed.identity) <> ''
END;

-- Empty installations still receive explicit disabled bindings for every existing
-- external provider. They remain unavailable until their tenant owner supplies a
-- unique identity and complete verifier configuration; runtime never invents one.
INSERT INTO `pa_external_channel_binding`
  (`tenant_id`,`provider`,`callback_key`,`identity_hash`,`identity_hint`,`config_json`,`status`,`create_time`,`update_time`)
SELECT @pa_external_default_tenant, provider, LOWER(HEX(RANDOM_BYTES(32))),
  SHA2(CONCAT('unconfigured:', provider, ':', @pa_external_default_tenant), 256), '', JSON_OBJECT(),
  0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 'payment.wechat' provider
  UNION ALL SELECT 'payment.alipay'
  UNION ALL SELECT 'wechat.official-account'
  UNION ALL SELECT 'oauth.wechat.oa'
  UNION ALL SELECT 'oauth.wechat.mini-program'
  UNION ALL SELECT 'oauth.wechat.open-pc'
) providers
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_external_channel_binding` b
  WHERE b.`tenant_id` = @pa_external_default_tenant AND b.`provider` = providers.provider
);

DELETE FROM `pa_oauth_completion_ticket`
WHERE `used_at` IS NOT NULL OR `expires_at` < UNIX_TIMESTAMP();
SET @pa_external_live_ticket_count = (SELECT COUNT(*) FROM `pa_oauth_completion_ticket`);
SET @pa_external_sql = IF(
  @pa_external_live_ticket_count = 0,
  'SELECT 1',
  'SELECT * FROM `pa_external_callback_cannot_guess_provider_for_live_oauth_ticket`'
);
PREPARE pa_external_stmt FROM @pa_external_sql;
EXECUTE pa_external_stmt;
DEALLOCATE PREPARE pa_external_stmt;
ALTER TABLE `pa_oauth_completion_ticket`
  ADD COLUMN `binding_id` BIGINT UNSIGNED NOT NULL AFTER `tenant_id`,
  ADD KEY `idx_oauth_ticket_binding` (`binding_id`,`expires_at`),
  ADD CONSTRAINT `fk_oauth_ticket_external_binding`
    FOREIGN KEY (`binding_id`) REFERENCES `pa_external_channel_binding` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_official_account_reply`
  ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_official_account_reply`
SET `tenant_id` = @pa_external_default_tenant
WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_official_account_reply`
  DROP INDEX `uk_oa_reply_singleton_active`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oa_reply_tenant_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uk_oa_reply_tenant_singleton_active` (`tenant_id`,`singleton_active_key`),
  ADD KEY `idx_oa_reply_tenant_state` (`tenant_id`,`reply_type`,`status`,`delete_time`,`sort`,`id`),
  ADD CONSTRAINT `fk_oa_reply_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;


SET NAMES utf8mb4;

-- The Decoration Runtime is the only writable owner for mobile pages and
-- customer-service content. Remove grants before deleting the retired menus.
DELETE FROM `pa_system_role_menu`
WHERE `menu_id` IN (
  SELECT `id`
  FROM `pa_system_menu`
  WHERE `paths` IN ('/app-setting/decorate', '/app-setting/customer-service')
     OR `perms` IN (
       'setting/decorate/config',
       'setting/decorate/save',
       'setting/customer-service/config',
       'setting/customer-service/save'
     )
);

DELETE FROM `pa_system_menu`
WHERE `paths` IN ('/app-setting/decorate', '/app-setting/customer-service')
   OR `perms` IN (
     'setting/decorate/config',
     'setting/decorate/save',
     'setting/customer-service/config',
     'setting/customer-service/save'
   );


CREATE TABLE `pa_plugin_installation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_key` VARCHAR(96) NOT NULL,
  `installed_version` VARCHAR(32) NOT NULL,
  `source` VARCHAR(255) NOT NULL,
  `artifact_sha256` CHAR(64) NOT NULL,
  `lock_digest` CHAR(64) NOT NULL,
  `composer_identity_json` JSON NOT NULL,
  `npm_identity_json` JSON NOT NULL,
  `frontend_identity_json` JSON NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `installed_at` DATETIME(3) NULL,
  `activated_at` DATETIME(3) NULL,
  `upgraded_at` DATETIME(3) NULL,
  `uninstalled_at` DATETIME(3) NULL,
  `last_error_code` VARCHAR(96) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_installation_key` (`plugin_key`),
  KEY `idx_plugin_installation_status` (`status`, `plugin_key`),
  CONSTRAINT `chk_plugin_installation_status` CHECK (`status` IN (
    'installing','active','upgrading','maintenance','failed','uninstalled'
  ))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_plugin_module` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_key` VARCHAR(96) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `module_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_module_key` (`module_key`),
  KEY `idx_plugin_module_plugin` (`plugin_key`, `module_key`),
  CONSTRAINT `fk_plugin_module_plugin` FOREIGN KEY (`plugin_key`)
    REFERENCES `pa_plugin_installation` (`plugin_key`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_module_migration` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(96) NOT NULL,
  `migration_key` VARCHAR(160) NOT NULL,
  `module_version` VARCHAR(32) NOT NULL,
  `checksum` CHAR(64) NOT NULL,
  `batch_no` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `started_at` DATETIME(3) NOT NULL,
  `finished_at` DATETIME(3) NULL,
  `error_code` VARCHAR(96) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_migration` (`module_key`, `migration_key`),
  KEY `idx_module_migration_batch` (`batch_no`, `status`),
  CONSTRAINT `chk_module_migration_status` CHECK (`status` IN (
    'applying','applied','rolled_back','failed'
  ))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_protected_resource` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `ownership` VARCHAR(32) NOT NULL,
  `provider_key` VARCHAR(160) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `retired_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_protected_resource_key` (`key`),
  KEY `idx_protected_resource_module` (`module_key`, `status`),
  CONSTRAINT `chk_protected_resource_ownership` CHECK (`ownership` IN ('tenant_owned', 'business_target_owned', 'shared_master', 'global_reference', 'platform_internal')),
  CONSTRAINT `chk_protected_resource_status` CHECK (`status` IN ('active', 'retired')),
  CONSTRAINT `chk_protected_resource_retired` CHECK ((`status` = 'retired' AND `retired_at` IS NOT NULL) OR `status` <> 'retired')
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_target_type` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `resolver_key` VARCHAR(160) NOT NULL,
  `catalog_provider_key` VARCHAR(160) NOT NULL,
  `id_format` VARCHAR(16) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_target_type_key` (`key`),
  KEY `idx_target_type_module` (`module_key`, `status`),
  CONSTRAINT `chk_target_type_id_format` CHECK (`id_format` IN ('decimal', 'uuid', 'ulid', 'string')),
  CONSTRAINT `chk_target_type_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `protected_resource_id` BIGINT UNSIGNED NOT NULL,
  `operation` VARCHAR(64) NOT NULL,
  `access_mode` VARCHAR(32) NOT NULL,
  `target_cardinality` VARCHAR(32) NOT NULL,
  `permission_match` VARCHAR(8) NOT NULL DEFAULT 'all',
  `audit_level` VARCHAR(32) NOT NULL DEFAULT 'deny_and_write',
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation` (`protected_resource_id`, `operation`),
  CONSTRAINT `fk_resource_operation_resource` FOREIGN KEY (`protected_resource_id`)
    REFERENCES `pa_protected_resource` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_resource_operation_access` CHECK (`access_mode` IN ('tenant_wide', 'rule_filtered', 'explicit_targets', 'global_reference_read', 'system_internal')),
  CONSTRAINT `chk_resource_operation_cardinality` CHECK (`target_cardinality` IN ('none', 'one_required', 'many_readable', 'aggregate_read', 'policy_publish', 'bulk_write')),
  CONSTRAINT `chk_resource_operation_permission_match` CHECK (`permission_match` IN ('all', 'any')),
  CONSTRAINT `chk_resource_operation_audit` CHECK (`audit_level` IN ('deny', 'write', 'deny_and_write', 'all')),
  CONSTRAINT `chk_resource_operation_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation_target_type` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_operation_id` BIGINT UNSIGNED NOT NULL,
  `target_type_id` BIGINT UNSIGNED NOT NULL,
  `target_role` VARCHAR(64) NOT NULL DEFAULT 'primary',
  `input_mode` VARCHAR(16) NOT NULL DEFAULT 'explicit',
  `policy_selection_permission_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation_target_type` (`resource_operation_id`, `target_role`, `target_type_id`),
  CONSTRAINT `fk_operation_target_operation` FOREIGN KEY (`resource_operation_id`)
    REFERENCES `pa_resource_operation` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_target_type` FOREIGN KEY (`target_type_id`)
    REFERENCES `pa_target_type` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_target_selection_permission` FOREIGN KEY (`policy_selection_permission_id`)
    REFERENCES `pa_permission` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_operation_target_input` CHECK (`input_mode` IN ('explicit', 'derived', 'either')),
  CONSTRAINT `chk_operation_target_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation_permission` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_operation_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation_permission` (`resource_operation_id`, `permission_id`),
  CONSTRAINT `fk_operation_permission_operation` FOREIGN KEY (`resource_operation_id`)
    REFERENCES `pa_resource_operation` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_permission_permission` FOREIGN KEY (`permission_id`)
    REFERENCES `pa_permission` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_data_condition_definition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `category` VARCHAR(32) NOT NULL,
  `target_mode` VARCHAR(32) NOT NULL,
  `config_schema_json` JSON NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_version` VARCHAR(32) NOT NULL,
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_data_condition_key` (`key`),
  CONSTRAINT `chk_data_condition_category` CHECK (`category` IN ('tenant', 'self', 'department', 'selected', 'relation')),
  CONSTRAINT `chk_data_condition_target_mode` CHECK (`target_mode` IN ('none', 'department', 'resource')),
  CONSTRAINT `chk_data_condition_definition_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_resource_operation_condition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_operation_id` BIGINT UNSIGNED NOT NULL,
  `condition_definition_id` BIGINT UNSIGNED NOT NULL,
  `selector_resource_key` VARCHAR(160) NULL,
  `selector_resource_key_norm` VARCHAR(160) GENERATED ALWAYS AS (COALESCE(`selector_resource_key`, '')) STORED,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_resource_operation_condition` (`resource_operation_id`, `condition_definition_id`, `selector_resource_key_norm`),
  CONSTRAINT `fk_operation_condition_operation` FOREIGN KEY (`resource_operation_id`)
    REFERENCES `pa_resource_operation` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_operation_condition_definition` FOREIGN KEY (`condition_definition_id`)
    REFERENCES `pa_data_condition_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_operation_condition_status` CHECK (`status` IN ('active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_menu_definition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(160) NOT NULL,
  `module_key` VARCHAR(96) NOT NULL,
  `scope` VARCHAR(16) NOT NULL,
  `parent_key` VARCHAR(160) NULL,
  `type` VARCHAR(16) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `route_name` VARCHAR(160) NULL,
  `route_path` VARCHAR(255) NULL,
  `component_key` VARCHAR(160) NULL,
  `icon` VARCHAR(64) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `required_permission_id` BIGINT UNSIGNED NULL,
  `client_keys_json` JSON NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `manifest_digest` CHAR(64) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_definition_key` (`key`),
  UNIQUE KEY `uk_menu_route_name` (`scope`, `route_name`),
  KEY `idx_menu_module` (`module_key`, `scope`, `status`, `sort_order`),
  CONSTRAINT `fk_menu_permission` FOREIGN KEY (`required_permission_id`)
    REFERENCES `pa_permission` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_menu_scope` CHECK (`scope` IN ('platform','tenant')),
  CONSTRAINT `chk_menu_type` CHECK (`type` IN ('group','page','link')),
  CONSTRAINT `chk_menu_status` CHECK (`status` IN ('active','retired')),
  CONSTRAINT `chk_menu_page` CHECK (`type` <> 'page' OR (
    `route_name` IS NOT NULL AND `component_key` IS NOT NULL
    AND `required_permission_id` IS NOT NULL
  ))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_definition` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `setting_key` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `schema_json` JSON NOT NULL,
  `required_flag` TINYINT UNSIGNED NOT NULL,
  `secret_flag` TINYINT UNSIGNED NOT NULL,
  `deployment_scope_flag` TINYINT UNSIGNED NOT NULL,
  `tenant_scope_flag` TINYINT UNSIGNED NOT NULL,
  `target_scope_flag` TINYINT UNSIGNED NOT NULL,
  `target_resource_key` VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `target_operation` VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `default_json` JSON NULL,
  `definition_digest` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_definition` (`module_key`, `setting_key`),
  CONSTRAINT `chk_setting_definition_required` CHECK (`required_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_secret` CHECK (`secret_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_deployment_scope` CHECK (`deployment_scope_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_tenant_scope` CHECK (`tenant_scope_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_target_scope` CHECK (`target_scope_flag` IN (0, 1)),
  CONSTRAINT `chk_setting_definition_target` CHECK ((
    `target_scope_flag` = 1 AND `target_resource_key` IS NOT NULL
    AND `target_operation` IS NOT NULL
  ) OR (
    `target_scope_flag` = 0 AND `target_resource_key` IS NULL
    AND `target_operation` IS NULL
  )),
  CONSTRAINT `chk_setting_definition_default` CHECK (`secret_flag` = 0 OR `default_json` IS NULL),
  CONSTRAINT `chk_setting_definition_status` CHECK (`status` IN ('active','retired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_deployment_value` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `definition_id` BIGINT UNSIGNED NOT NULL,
  `value_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_json` JSON NULL,
  `ciphertext` VARBINARY(8192) NULL,
  `nonce` BINARY(24) NULL,
  `key_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NULL,
  `updated_by_operator_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_deployment_value` (`definition_id`),
  CONSTRAINT `fk_setting_deployment_definition` FOREIGN KEY (`definition_id`)
    REFERENCES `pa_setting_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_deployment_operator` FOREIGN KEY (`updated_by_operator_id`)
    REFERENCES `pa_platform_operator` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_setting_deployment_state` CHECK (`value_state` IN ('set','unset')),
  CONSTRAINT `chk_setting_deployment_interval` CHECK (`expires_at` IS NULL OR `expires_at` > `effective_at`),
  CONSTRAINT `chk_setting_deployment_storage` CHECK (
    (`value_state` = 'unset' AND `value_json` IS NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
    OR (`value_state` = 'set' AND ((`value_json` IS NOT NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
      OR (`value_json` IS NULL AND `ciphertext` IS NOT NULL AND OCTET_LENGTH(`ciphertext`) > 0 AND `nonce` IS NOT NULL AND `key_id` IS NOT NULL)))
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_tenant_value` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `definition_id` BIGINT UNSIGNED NOT NULL,
  `value_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_json` JSON NULL,
  `ciphertext` VARBINARY(8192) NULL,
  `nonce` BINARY(24) NULL,
  `key_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NULL,
  `updated_by_member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_tenant_value` (`tenant_id`, `definition_id`),
  KEY `idx_setting_tenant_definition` (`definition_id`),
  CONSTRAINT `fk_setting_tenant_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_tenant_definition` FOREIGN KEY (`definition_id`)
    REFERENCES `pa_setting_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_tenant_member` FOREIGN KEY (`updated_by_member_id`)
    REFERENCES `pa_tenant_member` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_setting_tenant_state` CHECK (`value_state` IN ('set','unset')),
  CONSTRAINT `chk_setting_tenant_interval` CHECK (`expires_at` IS NULL OR `expires_at` > `effective_at`),
  CONSTRAINT `chk_setting_tenant_storage` CHECK (
    (`value_state` = 'unset' AND `value_json` IS NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
    OR (`value_state` = 'set' AND ((`value_json` IS NOT NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
      OR (`value_json` IS NULL AND `ciphertext` IS NOT NULL AND OCTET_LENGTH(`ciphertext`) > 0 AND `nonce` IS NOT NULL AND `key_id` IS NOT NULL)))
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_setting_target_value` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `definition_id` BIGINT UNSIGNED NOT NULL,
  `target_resource_key` VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_state` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value_json` JSON NULL,
  `ciphertext` VARBINARY(8192) NULL,
  `nonce` BINARY(24) NULL,
  `key_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `effective_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NULL,
  `updated_by_member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_target_value` (`tenant_id`, `definition_id`, `target_resource_key`, `target_id`),
  KEY `idx_setting_target_definition` (`definition_id`),
  CONSTRAINT `fk_setting_target_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_target_definition` FOREIGN KEY (`definition_id`)
    REFERENCES `pa_setting_definition` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_setting_target_member` FOREIGN KEY (`updated_by_member_id`)
    REFERENCES `pa_tenant_member` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_setting_target_state` CHECK (`value_state` IN ('set','unset')),
  CONSTRAINT `chk_setting_target_interval` CHECK (`expires_at` IS NULL OR `expires_at` > `effective_at`),
  CONSTRAINT `chk_setting_target_storage` CHECK (
    (`value_state` = 'unset' AND `value_json` IS NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
    OR (`value_state` = 'set' AND ((`value_json` IS NOT NULL AND `ciphertext` IS NULL AND `nonce` IS NULL AND `key_id` IS NULL)
      OR (`value_json` IS NULL AND `ciphertext` IS NOT NULL AND OCTET_LENGTH(`ciphertext`) > 0 AND `nonce` IS NOT NULL AND `key_id` IS NOT NULL)))
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
