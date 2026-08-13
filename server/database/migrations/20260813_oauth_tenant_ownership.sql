-- MT03 OAuth identity, browser state, and completion ticket Tenant ownership.
-- pa_config OAuth switches and channel credentials remain instance-owned.

SET @pa_mt03_oauth_required_table_count = (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
      'pa_tenant', 'pa_member', 'pa_oauth_principal', 'pa_oauth_identity',
      'pa_oauth_attempt', 'pa_oauth_completion_ticket'
    )
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_required_table_count = 6,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_requires_all_owned_tables_before_backfill`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

SET @pa_mt03_oauth_member_owner_ready = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND COLUMN_NAME = 'tenant_id' AND IS_NULLABLE = 'NO'
);
SET @pa_mt03_oauth_member_identity_ready = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pa_member'
    AND INDEX_NAME = 'uk_member_tenant_id' AND NON_UNIQUE = 0
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_member_owner_ready = 1 AND @pa_mt03_oauth_member_identity_ready = 2,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_requires_member_tenant_ownership_before_backfill`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

SET @pa_mt03_oauth_invalid_legacy_rows = (
  (SELECT COUNT(*) FROM `pa_oauth_principal` p
   LEFT JOIN `pa_member` m ON m.`id` = p.`member_id`
   WHERE m.`id` IS NULL)
  + (SELECT COUNT(*) FROM `pa_oauth_identity` i
     LEFT JOIN `pa_member` m ON m.`id` = i.`member_id`
     LEFT JOIN `pa_oauth_principal` p ON p.`id` = i.`principal_id`
     WHERE m.`id` IS NULL
       OR (i.`principal_id` IS NOT NULL AND (p.`id` IS NULL OR p.`member_id` <> i.`member_id`)))
  + (SELECT COUNT(*) FROM `pa_oauth_completion_ticket` c
     LEFT JOIN `pa_member` m ON m.`id` = c.`member_id`
     WHERE m.`id` IS NULL)
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_invalid_legacy_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_legacy_relationship_verification_failed`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

SET @pa_mt03_oauth_attempt_rows = (SELECT COUNT(*) FROM `pa_oauth_attempt`);
SET @pa_mt03_oauth_active_tenant_count = (SELECT COUNT(*) FROM `pa_tenant` WHERE `status` = 'active');
SET @pa_mt03_oauth_default_tenant_id = (SELECT `id` FROM `pa_tenant` WHERE `status` = 'active' LIMIT 1);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_attempt_rows = 0
    OR (@pa_mt03_oauth_active_tenant_count = 1 AND @pa_mt03_oauth_default_tenant_id > 0),
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_requires_exactly_one_active_tenant_for_legacy_attempts`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

ALTER TABLE `pa_oauth_principal` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_oauth_identity` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_oauth_attempt` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;
ALTER TABLE `pa_oauth_completion_ticket` ADD COLUMN `tenant_id` BIGINT UNSIGNED NULL AFTER `id`;

UPDATE `pa_oauth_principal` p
JOIN `pa_member` m ON m.`id` = p.`member_id`
SET p.`tenant_id` = m.`tenant_id`
WHERE p.`tenant_id` IS NULL;
UPDATE `pa_oauth_identity` i
JOIN `pa_member` m ON m.`id` = i.`member_id`
SET i.`tenant_id` = m.`tenant_id`
WHERE i.`tenant_id` IS NULL;
UPDATE `pa_oauth_completion_ticket` c
JOIN `pa_member` m ON m.`id` = c.`member_id`
SET c.`tenant_id` = m.`tenant_id`
WHERE c.`tenant_id` IS NULL;
UPDATE `pa_oauth_attempt`
SET `tenant_id` = @pa_mt03_oauth_default_tenant_id
WHERE `tenant_id` IS NULL;

SET @pa_mt03_oauth_invalid_rows = (
  (SELECT COUNT(*) FROM `pa_oauth_principal` p
   JOIN `pa_member` m ON m.`id` = p.`member_id`
   WHERE p.`tenant_id` IS NULL OR p.`tenant_id` = 0 OR p.`tenant_id` <> m.`tenant_id`)
  + (SELECT COUNT(*) FROM `pa_oauth_identity` i
     JOIN `pa_member` m ON m.`id` = i.`member_id`
     LEFT JOIN `pa_oauth_principal` p ON p.`id` = i.`principal_id`
     WHERE i.`tenant_id` IS NULL OR i.`tenant_id` = 0 OR i.`tenant_id` <> m.`tenant_id`
       OR (i.`principal_id` IS NOT NULL AND i.`tenant_id` <> p.`tenant_id`))
  + (SELECT COUNT(*) FROM `pa_oauth_attempt` WHERE `tenant_id` IS NULL OR `tenant_id` = 0)
  + (SELECT COUNT(*) FROM `pa_oauth_completion_ticket` c
     JOIN `pa_member` m ON m.`id` = c.`member_id`
     WHERE c.`tenant_id` IS NULL OR c.`tenant_id` = 0 OR c.`tenant_id` <> m.`tenant_id`)
);
SET @pa_mt03_oauth_sql = IF(
  @pa_mt03_oauth_invalid_rows = 0,
  'SELECT 1',
  'SELECT * FROM `pa_mt03_oauth_tenant_backfill_verification_failed`'
);
PREPARE pa_mt03_oauth_stmt FROM @pa_mt03_oauth_sql;
EXECUTE pa_mt03_oauth_stmt;
DEALLOCATE PREPARE pa_mt03_oauth_stmt;

ALTER TABLE `pa_oauth_principal`
  DROP INDEX `uk_provider_scope_union`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_principal_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_oauth_principal_tenant_union` (`tenant_id`, `provider`, `union_scope`, `union_id`),
  ADD KEY `idx_oauth_principal_tenant_member` (`tenant_id`, `member_id`),
  ADD CONSTRAINT `fk_oauth_principal_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_principal_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_oauth_identity`
  DROP INDEX `uk_provider_client_subject`,
  DROP INDEX `uk_member_provider_client`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_identity_tenant_id` (`tenant_id`, `id`),
  ADD UNIQUE KEY `uk_oauth_identity_tenant_subject` (`tenant_id`, `provider`, `client_key`, `subject`),
  ADD UNIQUE KEY `uk_oauth_identity_tenant_member_client` (`tenant_id`, `member_id`, `provider`, `client_key`),
  ADD KEY `idx_oauth_identity_tenant_member_terminal` (`tenant_id`, `member_id`, `terminal`),
  ADD KEY `idx_oauth_identity_tenant_principal` (`tenant_id`, `principal_id`),
  ADD CONSTRAINT `fk_oauth_identity_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_identity_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_identity_principal` FOREIGN KEY (`tenant_id`, `principal_id`) REFERENCES `pa_oauth_principal` (`tenant_id`, `id`) ON DELETE RESTRICT;

ALTER TABLE `pa_oauth_attempt`
  DROP INDEX `uk_state_hash`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_attempt_tenant_state` (`tenant_id`, `state_hash`),
  ADD KEY `idx_oauth_attempt_tenant_expires` (`tenant_id`, `expires_at`),
  ADD CONSTRAINT `fk_oauth_attempt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pa_oauth_completion_ticket`
  DROP INDEX `uk_token_hash`,
  MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE KEY `uk_oauth_ticket_tenant_token` (`tenant_id`, `token_hash`),
  ADD KEY `idx_oauth_ticket_tenant_member` (`tenant_id`, `member_id`),
  ADD KEY `idx_oauth_ticket_tenant_expires` (`tenant_id`, `expires_at`),
  ADD CONSTRAINT `fk_oauth_ticket_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_oauth_ticket_member` FOREIGN KEY (`tenant_id`, `member_id`) REFERENCES `pa_member` (`tenant_id`, `id`) ON DELETE RESTRICT;
