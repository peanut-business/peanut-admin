ALTER TABLE `pa_legacy_admin_tenant_map`
    DROP INDEX `uk_legacy_admin_account`,
    ADD UNIQUE KEY `uk_legacy_admin_account` (`tenant_id`, `account_id`);
