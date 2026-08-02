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
