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
