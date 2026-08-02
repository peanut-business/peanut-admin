SET NAMES utf8mb4;

-- O03 角色契约增量：为角色启用软删除。
-- 关系表继续物理删除，角色删除操作由 RoleLogic 在同一事务中维护。
SET @pa_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_system_role' AND COLUMN_NAME = 'delete_time') = 0,
    'ALTER TABLE `pa_system_role` ADD COLUMN `delete_time` INT UNSIGNED NULL DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
    'SELECT 1'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;
