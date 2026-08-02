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
