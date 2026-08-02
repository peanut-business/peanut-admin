SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `pa_decorate_page` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint unsigned NOT NULL COMMENT '1首页 2个人中心 3客服 4PC首页 5系统风格',
  `name` varchar(100) NOT NULL DEFAULT '',
  `data` longtext NOT NULL,
  `meta` longtext NOT NULL,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_decorate_page_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装修页面';

CREATE TABLE IF NOT EXISTS `pa_decorate_tabbar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `position` tinyint unsigned NOT NULL,
  `name` varchar(20) NOT NULL DEFAULT '',
  `selected` varchar(255) NOT NULL DEFAULT '',
  `unselected` varchar(255) NOT NULL DEFAULT '',
  `link` varchar(1000) NOT NULL DEFAULT '{}',
  `is_show` tinyint unsigned NOT NULL DEFAULT 1,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_decorate_tabbar_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装修 Tabbar';

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 1, '移动端首页',
  '[{"title":"搜索","name":"search","disabled":1,"content":{},"styles":{}},{"title":"首页轮播图","name":"banner","content":{"enabled":1,"style":1,"bg_style":1,"data":[{"is_show":1,"image":"","bg":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{}},{"title":"导航菜单","name":"nav","content":{"enabled":1,"style":2,"per_line":5,"show_line":2,"data":[{"is_show":1,"image":"","name":"资讯中心","link":{"target_type":"shop","target":"news"}}]},"styles":{}},{"title":"首页中部轮播图","name":"middle-banner","content":{"enabled":1,"data":[{"is_show":1,"image":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{}},{"title":"资讯","name":"news","disabled":1,"content":{},"styles":{}}]',
  '[{"title":"页面设置","name":"page-meta","content":{"title":"首页","title_type":1,"title_img":"","bg_type":1,"bg_color":"#2F80ED","bg_image":"","text_color":1},"styles":{}}]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=1);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 2, '个人中心',
  '[{"title":"用户信息","name":"user-info","disabled":1,"content":{},"styles":{}},{"title":"我的服务","name":"my-service","content":{"enabled":1,"style":1,"title":"我的服务","data":[{"is_show":1,"image":"","name":"我的收藏","link":{"target_type":"shop","target":"favorites"}}]},"styles":{}},{"title":"个人中心广告图","name":"user-banner","content":{"enabled":1,"data":[{"is_show":1,"image":"","name":"","link":{"target_type":"shop","target":"profile"}}]},"styles":{}}]',
  '[{"title":"页面设置","name":"page-meta","content":{"title":"个人中心","title_type":1,"title_img":"","bg_type":1,"bg_color":"#2F80ED","bg_image":"","text_color":1},"styles":{}}]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=2);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 3, '客服设置',
  '[{"title":"客服设置","name":"customer-service","content":{"title":"添加客服二维码","time":"9:30 - 19:00","mobile":"","qrcode":"","remark":"长按添加客服或拨打客服热线"},"styles":{}}]',
  '[]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=3);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 4, 'PC 首页',
  '[{"title":"首页轮播图","name":"pc-banner","content":{"enabled":1,"data":[{"image":"","name":"","link":{"target_type":"shop","target":"home"}}]},"styles":{"position":"absolute","left":"40px","top":"75px","width":"750px","height":"340px"}}]',
  '[]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=4);

INSERT INTO `pa_decorate_page` (`type`,`name`,`data`,`meta`,`create_time`,`update_time`)
SELECT 5, '系统风格',
  '{"themeColorId":3,"topTextColor":"white","navigationBarColor":"#A74BFD","themeColor1":"#A74BFD","themeColor2":"#CB60FF","buttonColor":"white"}',
  '[]', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_page` WHERE `type`=5);

INSERT INTO `pa_decorate_tabbar` (`position`,`name`,`selected`,`unselected`,`link`,`is_show`,`create_time`,`update_time`)
SELECT 0,'首页','','','{"target_type":"shop","target":"home"}',1,0,0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_tabbar` WHERE `position`=0);
INSERT INTO `pa_decorate_tabbar` (`position`,`name`,`selected`,`unselected`,`link`,`is_show`,`create_time`,`update_time`)
SELECT 1,'资讯','','','{"target_type":"shop","target":"news"}',1,0,0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_tabbar` WHERE `position`=1);
INSERT INTO `pa_decorate_tabbar` (`position`,`name`,`selected`,`unselected`,`link`,`is_show`,`create_time`,`update_time`)
SELECT 2,'我的','','','{"target_type":"shop","target":"profile"}',1,0,0
WHERE NOT EXISTS (SELECT 1 FROM `pa_decorate_tabbar` WHERE `position`=2);

INSERT INTO `pa_config` (`type`,`name`,`value`)
SELECT 'tabbar','style','{"default_color":"#666666","selected_color":"#2F80ED"}'
WHERE NOT EXISTS (SELECT 1 FROM `pa_config` WHERE `type`='tabbar' AND `name`='style');

-- 旧五键装修配置退出运行时，不保留双读写或兼容层。
DELETE FROM `pa_config` WHERE `type`='decorate';

SET @pa_decoration_root_id = (
  SELECT `id` FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/decoration' ORDER BY `id` LIMIT 1
);
INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT 0,'M','装修管理','icon-palette',30,'','/decoration','',0,1,0
WHERE @pa_decoration_root_id IS NULL;
SET @pa_decoration_root_id = (
  SELECT `id` FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/decoration' ORDER BY `id` LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_decoration_root_id,'C',`seed`.`name`,'',`seed`.`sort`,'',`seed`.`paths`,`seed`.`component`,0,1,0
FROM (
  SELECT '移动端装修' AS `name`,30 AS `sort`,'/decoration/mobile' AS `paths`,'decoration/mobile/index' AS `component`
  UNION ALL SELECT 'Tabbar 装修',20,'/decoration/tabbar','decoration/tabbar/index'
  UNION ALL SELECT 'PC 装修',10,'/decoration/pc','decoration/pc/index'
) AS `seed`
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `paths`=`seed`.`paths`);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT `parent`.`id`,'A',`seed`.`name`,'',0,`seed`.`perms`,'','',0,1,0
FROM (
  SELECT '/decoration/mobile' AS `parent_path`,'移动装修列表' AS `name`,'decoration/mobile/page/lists' AS `perms`
  UNION ALL SELECT '/decoration/mobile','移动装修查看','decoration/mobile/page/detail'
  UNION ALL SELECT '/decoration/mobile','移动装修保存','decoration/mobile/page/save'
  UNION ALL SELECT '/decoration/mobile','装修文章选择','decoration/mobile/article'
  UNION ALL SELECT '/decoration/tabbar','Tabbar 查看','decoration/tabbar/detail'
  UNION ALL SELECT '/decoration/tabbar','Tabbar 保存','decoration/tabbar/save'
  UNION ALL SELECT '/decoration/pc','PC 装修列表','decoration/pc/page/lists'
  UNION ALL SELECT '/decoration/pc','PC 装修查看','decoration/pc/page/detail'
  UNION ALL SELECT '/decoration/pc','PC 装修保存','decoration/pc/page/save'
) AS `seed`
JOIN `pa_system_menu` AS `parent` ON `parent`.`paths`=`seed`.`parent_path` AND `parent`.`type`='C'
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `perms`=`seed`.`perms`);
