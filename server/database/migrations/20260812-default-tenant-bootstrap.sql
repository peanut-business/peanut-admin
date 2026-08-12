SET NAMES utf8mb4;

SET @pa_default_tenant_id = (
    SELECT `id` FROM `pa_tenant` WHERE `code` = 'default' AND `status` = 'active' LIMIT 1
);
SET @pa_tenant_ready = IF(@pa_default_tenant_id IS NULL, 0, 1);
SET @pa_sql = IF(
    @pa_tenant_ready = 1,
    'SELECT 1',
    'SELECT * FROM `MT02_DEFAULT_TENANT_REQUIRED`'
);
PREPARE pa_stmt FROM @pa_sql;
EXECUTE pa_stmt;
DEALLOCATE PREPARE pa_stmt;

ALTER TABLE `pa_admin` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_admin` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_admin_tenant_username` (`tenant_id`, `username`),
    ADD CONSTRAINT `fk_admin_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_system_role` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_system_role` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_system_role`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_system_role_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_system_role_tenant_name` (`tenant_id`, `name`),
    ADD CONSTRAINT `fk_system_role_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_dept` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_dept` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_dept`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_dept_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_dept_tenant_parent` (`tenant_id`, `pid`),
    ADD CONSTRAINT `fk_dept_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_jobs` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
UPDATE `pa_jobs` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_jobs`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_jobs_tenant_id` (`tenant_id`, `id`),
    ADD KEY `idx_jobs_tenant_code` (`tenant_id`, `code`),
    ADD CONSTRAINT `fk_jobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_admin_role` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_admin_role` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin_role`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_role_tenant` (`tenant_id`, `admin_id`, `role_id`),
    ADD CONSTRAINT `fk_admin_role_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_admin_dept` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_admin_dept` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin_dept`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_dept_tenant` (`tenant_id`, `admin_id`, `dept_id`),
    ADD CONSTRAINT `fk_admin_dept_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_admin_jobs` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_admin_jobs` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_admin_jobs`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_admin_jobs_tenant` (`tenant_id`, `admin_id`, `jobs_id`),
    ADD CONSTRAINT `fk_admin_jobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_system_role_menu` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL FIRST;
UPDATE `pa_system_role_menu` SET `tenant_id` = @pa_default_tenant_id WHERE `tenant_id` IS NULL;
ALTER TABLE `pa_system_role_menu`
    MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY `uk_role_menu_tenant` (`tenant_id`, `role_id`, `menu_id`),
    ADD CONSTRAINT `fk_role_menu_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

CREATE TABLE `pa_legacy_admin_tenant_map` (
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `legacy_admin_id` INT UNSIGNED NOT NULL,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `tenant_member_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`tenant_id`, `legacy_admin_id`),
    UNIQUE KEY `uk_legacy_admin_account` (`account_id`),
    UNIQUE KEY `uk_legacy_admin_member` (`tenant_id`, `tenant_member_id`),
    CONSTRAINT `fk_legacy_admin_source` FOREIGN KEY (`tenant_id`, `legacy_admin_id`) REFERENCES `pa_admin` (`tenant_id`, `id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_legacy_admin_account` FOREIGN KEY (`account_id`) REFERENCES `pa_account` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_legacy_admin_member` FOREIGN KEY (`tenant_id`, `tenant_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_legacy_role_tenant_map` (
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `legacy_role_id` INT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`tenant_id`, `legacy_role_id`),
    UNIQUE KEY `uk_legacy_role_core` (`tenant_id`, `role_id`),
    CONSTRAINT `fk_legacy_role_source` FOREIGN KEY (`tenant_id`, `legacy_role_id`) REFERENCES `pa_system_role` (`tenant_id`, `id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_legacy_role_core` FOREIGN KEY (`tenant_id`, `role_id`) REFERENCES `pa_role` (`tenant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_legacy_dept_tenant_map` (
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `legacy_dept_id` INT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`tenant_id`, `legacy_dept_id`),
    UNIQUE KEY `uk_legacy_dept_core` (`tenant_id`, `department_id`),
    CONSTRAINT `fk_legacy_dept_source` FOREIGN KEY (`tenant_id`, `legacy_dept_id`) REFERENCES `pa_dept` (`tenant_id`, `id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_legacy_dept_core` FOREIGN KEY (`tenant_id`, `department_id`) REFERENCES `pa_department` (`tenant_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pa_default_tenant_bootstrap` (
    `id` TINYINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `owner_account_id` BIGINT UNSIGNED NOT NULL,
    `owner_member_id` BIGINT UNSIGNED NOT NULL,
    `core_source_commit` CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `schema_digest` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `status` VARCHAR(16) NOT NULL,
    `completed_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_default_bootstrap_tenant` (`tenant_id`),
    CONSTRAINT `fk_default_bootstrap_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_default_bootstrap_account` FOREIGN KEY (`owner_account_id`) REFERENCES `pa_account` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_default_bootstrap_member` FOREIGN KEY (`tenant_id`, `owner_member_id`) REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_default_bootstrap_singleton` CHECK (`id` = 1),
    CONSTRAINT `chk_default_bootstrap_status` CHECK (`status` IN ('running', 'completed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
