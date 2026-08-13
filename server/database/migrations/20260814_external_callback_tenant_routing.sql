-- Trusted external callback routing. Runtime never falls back to a default Tenant.
-- Existing instance-owned channel configuration is adopted only into the explicitly
-- registered default Tenant; later SaaS bindings must be registered deliberately.

SET @pa_external_required = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
      'pa_tenant', 'pa_default_tenant_bootstrap', 'pa_config',
      'pa_oauth_attempt', 'pa_oauth_completion_ticket', 'pa_official_account_reply'
    )
);
SET @pa_external_sql = IF(
  @pa_external_required = 6,
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
  SELECT COUNT(*) FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`status` = 'completed' AND t.`status` = 'active'
);
SET @pa_external_default_tenant = (
  SELECT b.`tenant_id` FROM `pa_default_tenant_bootstrap` b
  JOIN `pa_tenant` t ON t.`id` = b.`tenant_id`
  WHERE b.`status` = 'completed' AND t.`status` = 'active'
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
