SET NAMES utf8mb4;

SET @pa_system_root_id = (
  SELECT `id` FROM `pa_system_menu` WHERE `type`='M' AND `paths`='/system' ORDER BY `id` LIMIT 1
);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT @pa_system_root_id,'C',`seed`.`name`,`seed`.`icon`,`seed`.`sort`,'',`seed`.`paths`,`seed`.`component`,0,1,0
FROM (
  SELECT '字典管理' AS `name`,'icon-book' AS `icon`,40 AS `sort`,'/system/dict' AS `paths`,'system/dict/index' AS `component`
  UNION ALL SELECT '定时任务','icon-clock-circle',30,'/system/crontab','system/crontab/index'
  UNION ALL SELECT '操作日志','icon-file',20,'/system/log','system/log/index'
  UNION ALL SELECT '系统维护','icon-tool',10,'/system/maintenance','system/maintenance/index'
) AS `seed`
WHERE @pa_system_root_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE `paths`=`seed`.`paths`);

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT `parent`.`id`,'A',`seed`.`name`,'',0,`seed`.`perms`,'','',0,1,0
FROM (
  SELECT '/system/dict' AS `parent_path`,'字典类型列表' AS `name`,'dict/type/lists' AS `perms`
  UNION ALL SELECT '/system/dict','字典类型选择','dict/type/all'
  UNION ALL SELECT '/system/dict','字典类型详情','dict/type/detail'
  UNION ALL SELECT '/system/dict','字典类型新增','dict/type/add'
  UNION ALL SELECT '/system/dict','字典类型编辑','dict/type/edit'
  UNION ALL SELECT '/system/dict','字典类型删除','dict/type/delete'
  UNION ALL SELECT '/system/dict','字典类型状态','dict/type/status'
  UNION ALL SELECT '/system/dict','字典数据列表','dict/data/lists'
  UNION ALL SELECT '/system/dict','字典数据选择','dict/data/bytype'
  UNION ALL SELECT '/system/dict','字典数据详情','dict/data/detail'
  UNION ALL SELECT '/system/dict','字典数据新增','dict/data/add'
  UNION ALL SELECT '/system/dict','字典数据编辑','dict/data/edit'
  UNION ALL SELECT '/system/dict','字典数据删除','dict/data/delete'
  UNION ALL SELECT '/system/dict','字典数据状态','dict/data/status'
  UNION ALL SELECT '/system/crontab','定时任务列表','crontab/lists'
  UNION ALL SELECT '/system/crontab','定时任务详情','crontab/detail'
  UNION ALL SELECT '/system/crontab','Cron 预览','crontab/expression'
  UNION ALL SELECT '/system/crontab','定时任务新增','crontab/add'
  UNION ALL SELECT '/system/crontab','定时任务编辑','crontab/edit'
  UNION ALL SELECT '/system/crontab','定时任务删除','crontab/delete'
  UNION ALL SELECT '/system/crontab','定时任务启停','crontab/operate'
  UNION ALL SELECT '/system/log','操作日志列表','log/lists'
  UNION ALL SELECT '/system/log','操作日志清空','log/clear'
  UNION ALL SELECT '/system/maintenance','系统环境信息','system/info'
  UNION ALL SELECT '/system/maintenance','系统缓存清理','system/clearcache'
) AS `seed`
JOIN `pa_system_menu` AS `parent` ON `parent`.`paths`=`seed`.`parent_path` AND `parent`.`type`='C'
WHERE NOT EXISTS (SELECT 1 FROM `pa_system_menu` WHERE LOWER(`perms`)=LOWER(`seed`.`perms`));
