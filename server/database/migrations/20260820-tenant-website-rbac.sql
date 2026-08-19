-- Register the personal settings route and move the canonical website settings menu node.
UPDATE `pa_system_menu`
SET `paths` = '/app-setting/website', `component` = 'system/config/index'
WHERE `type` = 'C' AND `name` = '网站设置' AND `paths` = '/system/config';

INSERT IGNORE INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT 0, 'M', '个人中心', 'icon-user', 40, '', '/user', '', 0, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/user');

INSERT IGNORE INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT parent.id, 'C', '个人设置', 'icon-user', 100, 'user:setting', '/user/setting', 'user/setting/index', 0, 1, 0
FROM `pa_system_menu` parent
WHERE parent.`type`='M' AND parent.`paths`='/user'
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `paths`='/user/setting');

INSERT IGNORE INTO `pa_permission`
  (`key`,`module_key`,`type`,`name`,`description`,`risk_level`,`status`,`manifest_version`,`created_at`,`updated_at`,`retired_at`)
VALUES
  ('user:setting','peanut.admin','menu','个人设置',NULL,'normal','active','fresh-schema-v1',UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),NULL);

INSERT IGNORE INTO `pa_role_permission`
  (`tenant_id`,`role_id`,`permission_id`,`granted_by_member_id`,`granted_at`)
SELECT role.`tenant_id`, role.`id`, permission.`id`, membership.`tenant_member_id`, UTC_TIMESTAMP(3)
FROM `pa_role` role
JOIN `pa_member_role` membership
  ON membership.`tenant_id` = role.`tenant_id` AND membership.`role_id` = role.`id`
JOIN `pa_permission` permission
  ON permission.`key` = 'user:setting' AND permission.`status` = 'active'
WHERE role.`is_builtin` = 1 AND role.`status` = 'active';

INSERT IGNORE INTO `pa_tenant_setting`
  (`tenant_id`,`namespace`,`config_json`,`revision`,`create_time`,`update_time`)
SELECT tenant.`id`, 'website',
       COALESCE((SELECT JSON_OBJECTAGG(config.`name`, config.`value`)
                 FROM `pa_config` config WHERE config.`type` = 'website'), JSON_OBJECT()),
       1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `pa_tenant` tenant
WHERE tenant.`status` = 'active';

INSERT IGNORE INTO `pa_tenant_setting`
  (`tenant_id`,`namespace`,`config_json`,`revision`,`create_time`,`update_time`)
SELECT tenant.`id`, 'copyright',
       JSON_OBJECT('config', COALESCE(
         (SELECT CAST(config.`value` AS JSON)
          FROM `pa_config` config
          WHERE config.`type` = 'copyright' AND config.`name` = 'config'
          LIMIT 1), JSON_ARRAY())),
       1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `pa_tenant` tenant
WHERE tenant.`status` = 'active';
