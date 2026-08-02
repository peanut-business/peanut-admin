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
