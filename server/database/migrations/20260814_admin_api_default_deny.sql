-- Register every historically valid Tenant Admin API route before default-deny is enabled.
-- Each exact URI is its own permission node. Existing role grants are inherited only from
-- the nearest existing capability; no runtime URI alias can enlarge authorization.

DROP TEMPORARY TABLE IF EXISTS `pa_admin_permission_seed`;
CREATE TEMPORARY TABLE `pa_admin_permission_seed` (
  `perms` VARCHAR(100) NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `parent_permission` VARCHAR(100) NOT NULL DEFAULT '',
  `parent_path` VARCHAR(200) NOT NULL DEFAULT '',
  `inherit_permission` VARCHAR(100) NOT NULL DEFAULT '',
  `inherit_path` VARCHAR(200) NOT NULL DEFAULT '',
  `type` CHAR(1) NOT NULL DEFAULT 'A',
  `paths` VARCHAR(200) NOT NULL DEFAULT '',
  `component` VARCHAR(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`perms`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pa_admin_permission_seed`
  (`perms`,`name`,`parent_permission`,`parent_path`,`inherit_permission`,`inherit_path`,`type`,`paths`,`component`)
VALUES
  ('menu/all','菜单选择','menu/lists','','menu/lists','','A','',''),
  ('menu/detail','菜单详情','menu/lists','','menu/lists','','A','',''),
  ('menu/status','菜单状态','menu/lists','','menu/edit','','A','',''),
  ('role/all','角色选择','role/lists','','role/lists','','A','',''),
  ('role/detail','角色详情','role/lists','','role/lists','','A','',''),
  ('admin/detail','管理员详情','admin/lists','','admin/lists','','A','',''),
  ('admin/status','管理员状态','admin/lists','','admin/edit','','A','',''),
  ('dept/all','部门选择','dept/lists','','dept/lists','','A','',''),
  ('dept/leaderdept','负责人部门','dept/lists','','dept/lists','','A','',''),
  ('dept/detail','部门详情','dept/lists','','dept/lists','','A','',''),
  ('dept/status','部门状态','dept/lists','','dept/edit','','A','',''),
  ('jobs/all','岗位选择','jobs/lists','','jobs/lists','','A','',''),
  ('jobs/detail','岗位详情','jobs/lists','','jobs/lists','','A','',''),
  ('jobs/status','岗位状态','jobs/lists','','jobs/edit','','A','',''),
  ('log/export/status','导出状态','log/lists','','log/export','','A','',''),
  ('log/export/download','导出下载','log/lists','','log/export','','A','',''),
  ('article.articlecate/all','文章分类选择','article.articlecate/lists','','article.articlecate/lists','','A','',''),
  ('finance/account-log/lists','余额明细 REST 列表','finance.account_log/lists','','finance.account_log/lists','','A','',''),
  ('finance/account-log/change-types','余额变动类型','finance.account_log/lists','','finance.account_log/lists','','A','',''),
  ('finance.account_log/getumchangetype','余额变动类型兼容接口','finance.account_log/lists','','finance.account_log/lists','','A','',''),
  ('setting/transaction/config','交易设置','', '/app-setting','', '/app-setting','C','/app-setting/transaction','app-setting/transaction/index'),
  ('setting/transaction/save','交易设置保存','setting/transaction/config','','','/app-setting','A','',''),
  ('finance/recharge/lists','充值记录 REST 列表','recharge.recharge/lists','','recharge.recharge/lists','','A','',''),
  ('finance/recharge/refund','充值退款 REST 接口','recharge.recharge/lists','','recharge.recharge/refund','','A','',''),
  ('finance/recharge/refundagain','重新退款 REST 接口','finance.refund/record','','recharge.recharge/refundagain','','A','',''),
  ('finance/refund/stat','退款统计 REST 接口','finance.refund/record','','finance.refund/record','','A','',''),
  ('finance/refund/record','退款记录 REST 接口','finance.refund/record','','finance.refund/record','','A','',''),
  ('finance/refund/log','退款日志 REST 接口','finance.refund/record','','finance.refund/log','','A','',''),
  ('finance.refund/stat','退款统计兼容接口','finance.refund/record','','finance.refund/record','','A','','');

INSERT INTO `pa_system_menu`
  (`pid`,`type`,`name`,`icon`,`sort`,`perms`,`paths`,`component`,`is_cache`,`is_show`,`is_disable`)
SELECT
  COALESCE(
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE LOWER(p.`perms`)=LOWER(seed.`parent_permission`)), 0),
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE p.`paths`=seed.`parent_path`), 0)
  ),
  seed.`type`, seed.`name`, '', 0, seed.`perms`, seed.`paths`, seed.`component`, 0, 1, 0
FROM `pa_admin_permission_seed` seed
WHERE COALESCE(
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE LOWER(p.`perms`)=LOWER(seed.`parent_permission`)), 0),
    NULLIF((SELECT MIN(p.`id`) FROM `pa_system_menu` p WHERE p.`paths`=seed.`parent_path`), 0)
  ) IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `pa_system_menu` existing WHERE LOWER(existing.`perms`)=LOWER(seed.`perms`)
  );

-- Preserve only grants that already authorized the corresponding capability.
INSERT IGNORE INTO `pa_system_role_menu` (`tenant_id`,`role_id`,`menu_id`)
SELECT DISTINCT inherited.`tenant_id`, inherited.`role_id`, registered.`id`
FROM `pa_admin_permission_seed` seed
JOIN `pa_system_menu` registered ON LOWER(registered.`perms`)=LOWER(seed.`perms`)
JOIN `pa_system_menu` source ON (
  (seed.`inherit_permission`<>'' AND LOWER(source.`perms`)=LOWER(seed.`inherit_permission`))
  OR (seed.`inherit_path`<>'' AND source.`paths`=seed.`inherit_path`)
)
JOIN `pa_system_role_menu` inherited ON inherited.`menu_id`=source.`id`;

SET @pa_admin_permission_missing_nodes = (
  SELECT COUNT(*)
  FROM `pa_admin_permission_seed` seed
  LEFT JOIN `pa_system_menu` registered
    ON LOWER(registered.`perms`)=LOWER(seed.`perms`) AND registered.`is_disable`=0
  WHERE registered.`id` IS NULL
);
SET @pa_admin_permission_sql = IF(
  @pa_admin_permission_missing_nodes=0,
  'SELECT 1',
  'SELECT * FROM `pa_admin_api_permission_registration_failed`'
);
PREPARE pa_admin_permission_stmt FROM @pa_admin_permission_sql;
EXECUTE pa_admin_permission_stmt;
DEALLOCATE PREPARE pa_admin_permission_stmt;

DROP TEMPORARY TABLE `pa_admin_permission_seed`;
