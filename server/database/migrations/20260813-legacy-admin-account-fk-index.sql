ALTER TABLE `pa_legacy_admin_tenant_map`
    ADD KEY `idx_legacy_admin_account_fk` (`account_id`),
    DROP INDEX `uk_legacy_admin_account`,
    ADD UNIQUE KEY `uk_legacy_admin_account` (`tenant_id`, `account_id`);
