-- MT02 Article collection membership Tenant integrity.
-- Preflight every historical relationship before adding the composite member foreign key.

SET @pa_mt02_collect_member_required_tables = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('pa_member', 'pa_article', 'pa_article_collect')
);
SET @pa_mt02_collect_member_sql = IF(
  @pa_mt02_collect_member_required_tables = 3,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_collect_member_requires_owned_tables`'
);
PREPARE pa_mt02_collect_member_stmt FROM @pa_mt02_collect_member_sql;
EXECUTE pa_mt02_collect_member_stmt;
DEALLOCATE PREPARE pa_mt02_collect_member_stmt;

SET @pa_mt02_collect_member_required_columns = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND (
      (TABLE_NAME = 'pa_member' AND COLUMN_NAME IN ('id', 'tenant_id'))
      OR (TABLE_NAME = 'pa_article' AND COLUMN_NAME IN ('id', 'tenant_id'))
      OR (TABLE_NAME = 'pa_article_collect' AND COLUMN_NAME IN ('tenant_id', 'member_id', 'article_id'))
    )
);
SET @pa_mt02_collect_member_parent_indexes = (
  SELECT COUNT(*) FROM (
    SELECT TABLE_NAME, INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
        (TABLE_NAME = 'pa_member' AND INDEX_NAME = 'uk_member_tenant_id')
        OR (TABLE_NAME = 'pa_article' AND INDEX_NAME = 'uk_article_tenant_id')
      )
      AND NON_UNIQUE = 0
    GROUP BY TABLE_NAME, INDEX_NAME
    HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) = 'tenant_id,id'
  ) AS required_parent_indexes
);
SET @pa_mt02_collect_member_sql = IF(
  @pa_mt02_collect_member_required_columns = 7
    AND @pa_mt02_collect_member_parent_indexes = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_collect_member_requires_tenant_parent_keys`'
);
PREPARE pa_mt02_collect_member_stmt FROM @pa_mt02_collect_member_sql;
EXECUTE pa_mt02_collect_member_stmt;
DEALLOCATE PREPARE pa_mt02_collect_member_stmt;

SET @pa_mt02_collect_member_invalid_rows = (
  SELECT COUNT(*)
  FROM `pa_article_collect` collect
  LEFT JOIN `pa_member` member ON member.`id` = collect.`member_id`
  LEFT JOIN `pa_article` article ON article.`id` = collect.`article_id`
  WHERE collect.`tenant_id` IS NULL
    OR collect.`tenant_id` = 0
    OR member.`id` IS NULL
    OR article.`id` IS NULL
    OR member.`tenant_id` <> collect.`tenant_id`
    OR article.`tenant_id` <> collect.`tenant_id`
);
SET @pa_mt02_collect_member_sql = IF(
  @pa_mt02_collect_member_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt02_article_collect_membership_preflight_failed`'
);
PREPARE pa_mt02_collect_member_stmt FROM @pa_mt02_collect_member_sql;
EXECUTE pa_mt02_collect_member_stmt;
DEALLOCATE PREPARE pa_mt02_collect_member_stmt;

ALTER TABLE `pa_article_collect`
  ADD CONSTRAINT `fk_article_collect_tenant_member`
    FOREIGN KEY (`tenant_id`, `member_id`)
    REFERENCES `pa_member` (`tenant_id`, `id`)
    ON DELETE RESTRICT;
