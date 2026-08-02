SET NAMES utf8mb4;

-- D01 工作台菜单与权限。root 自动拥有；普通角色需在角色授权中显式勾选。
INSERT IGNORE INTO `pa_system_menu`
  (`id`,`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`) VALUES
  (14, 0, 'M', '仪表盘', 'icon-dashboard', 110, '',                '/dashboard',           '',                          0, 1, 0),
  (15,14, 'C', '工作台', 'icon-dashboard', 100, 'workbench/index', '/dashboard/workplace', 'dashboard/workplace/index', 0, 1, 0);
