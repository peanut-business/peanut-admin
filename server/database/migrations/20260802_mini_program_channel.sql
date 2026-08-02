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
