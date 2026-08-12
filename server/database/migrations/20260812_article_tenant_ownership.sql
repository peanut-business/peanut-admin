-- MT02 Article tenant ownership: expand, backfill, verify, contract.
-- The bootstrap/RBAC slice owns pa_tenant creation. This migration refuses to guess a Tenant.

SET @pa_mt02_tenant_table_exists = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_tenant'
);
SET @pa_mt02_sql = IF(
  @pa_mt02_tenant_table_exists = 1,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_requires_pa_tenant_before_article_backfill`'
);
PREPARE pa_mt02_stmt FROM @pa_mt02_sql;
EXECUTE pa_mt02_stmt;
DEALLOCATE PREPARE pa_mt02_stmt;

SET @pa_mt02_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt02_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt02_sql = IF(
  @pa_mt02_active_tenant_count = 1 AND @pa_mt02_default_tenant_id > 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_requires_exactly_one_active_tenant_for_article_backfill`'
);
PREPARE pa_mt02_stmt FROM @pa_mt02_sql;
EXECUTE pa_mt02_stmt;
DEALLOCATE PREPARE pa_mt02_stmt;

ALTER TABLE `pa_article_cate` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_article` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_article_collect` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_article_cate` SET `tenant_id` = @pa_mt02_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_article` SET `tenant_id` = @pa_mt02_default_tenant_id WHERE `tenant_id` IS NULL;
UPDATE `pa_article_collect` SET `tenant_id` = @pa_mt02_default_tenant_id WHERE `tenant_id` IS NULL;

SET @pa_mt02_invalid_rows = (
  SELECT
    (SELECT COUNT(*) FROM `pa_article_cate` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_article` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_article_collect` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
    + (SELECT COUNT(*) FROM `pa_article` a
       JOIN `pa_article_cate` c ON c.`id` = a.`cid`
       WHERE a.`tenant_id` <> c.`tenant_id`)
    + (SELECT COUNT(*) FROM `pa_article_collect` ac
       JOIN `pa_article` a ON a.`id` = ac.`article_id`
       WHERE ac.`tenant_id` <> a.`tenant_id`)
);
SET @pa_mt02_sql = IF(
  @pa_mt02_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_tenant_backfill_verification_failed`'
);
PREPARE pa_mt02_stmt FROM @pa_mt02_sql;
EXECUTE pa_mt02_stmt;
DEALLOCATE PREPARE pa_mt02_stmt;

ALTER TABLE `pa_article_cate`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_article_cate_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_article_cate_tenant_visible` (`tenant_id`, `is_show`, `sort`, `id`),
  ADD CONSTRAINT `fk_article_cate_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_article`
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN `cid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文章分类',
  ADD UNIQUE KEY `uk_article_tenant_id` (`tenant_id`, `id`),
  ADD KEY `idx_article_tenant_visible_cate` (`tenant_id`, `is_show`, `cid`, `sort`, `id`),
  ADD CONSTRAINT `fk_article_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_article_tenant_cate` FOREIGN KEY (`tenant_id`, `cid`) REFERENCES `pa_article_cate` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_article_collect`
  DROP INDEX `uk_member_article`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_article_collect_tenant_member_article` (`tenant_id`, `member_id`, `article_id`),
  ADD KEY `idx_article_collect_tenant_member_status` (`tenant_id`, `member_id`, `status`, `id`),
  ADD CONSTRAINT `fk_article_collect_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_article_collect_tenant_article` FOREIGN KEY (`tenant_id`, `article_id`) REFERENCES `pa_article` (`tenant_id`, `id`) ON DELETE RESTRICT;
