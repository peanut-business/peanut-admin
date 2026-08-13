SET NAMES utf8mb4;

SET @pa_org_tenant_mismatch_count = (
  SELECT
    (SELECT COUNT(*) FROM `pa_admin_role` x LEFT JOIN `pa_admin` a ON a.`tenant_id` = x.`tenant_id` AND a.`id` = x.`admin_id` LEFT JOIN `pa_system_role` r ON r.`tenant_id` = x.`tenant_id` AND r.`id` = x.`role_id` WHERE a.`id` IS NULL OR r.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_admin_dept` x LEFT JOIN `pa_admin` a ON a.`tenant_id` = x.`tenant_id` AND a.`id` = x.`admin_id` LEFT JOIN `pa_dept` d ON d.`tenant_id` = x.`tenant_id` AND d.`id` = x.`dept_id` WHERE a.`id` IS NULL OR d.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_admin_jobs` x LEFT JOIN `pa_admin` a ON a.`tenant_id` = x.`tenant_id` AND a.`id` = x.`admin_id` LEFT JOIN `pa_jobs` j ON j.`tenant_id` = x.`tenant_id` AND j.`id` = x.`jobs_id` WHERE a.`id` IS NULL OR j.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_system_role_menu` x LEFT JOIN `pa_system_role` r ON r.`tenant_id` = x.`tenant_id` AND r.`id` = x.`role_id` LEFT JOIN `pa_system_menu` m ON m.`id` = x.`menu_id` WHERE r.`id` IS NULL OR m.`id` IS NULL)
    + (SELECT COUNT(*) FROM `pa_dept` d LEFT JOIN `pa_dept` p ON p.`tenant_id` = d.`tenant_id` AND p.`id` = d.`pid` WHERE d.`pid` <> 0 AND p.`id` IS NULL)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_admin` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `username` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_admin` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `nickname` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_system_role` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `name` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_dept` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `name` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_jobs` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `name` HAVING COUNT(*) > 1) duplicates)
    + (SELECT COUNT(*) FROM (SELECT 1 FROM `pa_jobs` WHERE `delete_time` IS NULL GROUP BY `tenant_id`, `code` HAVING COUNT(*) > 1) duplicates)
);
SET @pa_sql = IF(
  @pa_org_tenant_mismatch_count = 0,
  'SELECT 1',
  'SELECT * FROM `MT02_ORG_TENANT_RELATION_MISMATCH`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_admin`
  ADD COLUMN `active_username` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `username`, NULL)) STORED AFTER `username`,
  ADD COLUMN `active_nickname` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `nickname`, NULL)) STORED AFTER `nickname`,
  ADD UNIQUE KEY `uk_admin_tenant_active_username` (`tenant_id`, `active_username`),
  ADD UNIQUE KEY `uk_admin_tenant_active_nickname` (`tenant_id`, `active_nickname`);
ALTER TABLE `pa_system_role`
  ADD COLUMN `active_name` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `name`, NULL)) STORED AFTER `name`,
  ADD UNIQUE KEY `uk_system_role_tenant_active_name` (`tenant_id`, `active_name`);
ALTER TABLE `pa_dept`
  ADD COLUMN `active_name` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `name`, NULL)) STORED AFTER `name`,
  ADD UNIQUE KEY `uk_dept_tenant_active_name` (`tenant_id`, `active_name`);
ALTER TABLE `pa_jobs`
  ADD COLUMN `active_name` VARCHAR(50)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `name`, NULL)) STORED AFTER `name`,
  ADD COLUMN `active_code` VARCHAR(64)
    GENERATED ALWAYS AS (IF(`delete_time` IS NULL, `code`, NULL)) STORED AFTER `code`,
  ADD UNIQUE KEY `uk_jobs_tenant_active_name` (`tenant_id`, `active_name`),
  ADD UNIQUE KEY `uk_jobs_tenant_active_code` (`tenant_id`, `active_code`);

ALTER TABLE `pa_admin_role`
  ADD CONSTRAINT `fk_admin_role_admin_owner`
    FOREIGN KEY (`tenant_id`, `admin_id`) REFERENCES `pa_admin` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_admin_role_role_owner`
    FOREIGN KEY (`tenant_id`, `role_id`) REFERENCES `pa_system_role` (`tenant_id`, `id`) ON DELETE RESTRICT;
ALTER TABLE `pa_admin_dept`
  ADD CONSTRAINT `fk_admin_dept_admin_owner`
    FOREIGN KEY (`tenant_id`, `admin_id`) REFERENCES `pa_admin` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_admin_dept_dept_owner`
    FOREIGN KEY (`tenant_id`, `dept_id`) REFERENCES `pa_dept` (`tenant_id`, `id`) ON DELETE RESTRICT;
ALTER TABLE `pa_admin_jobs`
  ADD CONSTRAINT `fk_admin_jobs_admin_owner`
    FOREIGN KEY (`tenant_id`, `admin_id`) REFERENCES `pa_admin` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_admin_jobs_jobs_owner`
    FOREIGN KEY (`tenant_id`, `jobs_id`) REFERENCES `pa_jobs` (`tenant_id`, `id`) ON DELETE RESTRICT;
ALTER TABLE `pa_system_role_menu`
  ADD CONSTRAINT `fk_role_menu_role_owner`
    FOREIGN KEY (`tenant_id`, `role_id`) REFERENCES `pa_system_role` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_role_menu_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `pa_system_menu` (`id`) ON DELETE RESTRICT;
