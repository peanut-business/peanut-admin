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
