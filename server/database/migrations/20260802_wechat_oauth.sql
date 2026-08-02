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
