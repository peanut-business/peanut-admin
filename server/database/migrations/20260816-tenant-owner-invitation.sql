CREATE TABLE `pa_tenant_owner_invitation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `email_normalized` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(120) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `delivery_status` VARCHAR(32) NOT NULL DEFAULT 'pending_delivery',
  `delivery_provider` VARCHAR(64) NULL,
  `delivery_message_id` VARCHAR(190) NULL,
  `delivery_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `delivery_error_code` VARCHAR(96) NULL,
  `last_delivery_at` DATETIME(3) NULL,
  `generation` INT UNSIGNED NOT NULL DEFAULT 1,
  `expires_at` DATETIME(3) NOT NULL,
  `accepted_at` DATETIME(3) NULL,
  `revoked_at` DATETIME(3) NULL,
  `accepted_account_id` BIGINT UNSIGNED NULL,
  `accepted_member_id` BIGINT UNSIGNED NULL,
  `invited_by_operator_id` BIGINT UNSIGNED NOT NULL,
  `revoked_by_operator_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  `pending_tenant_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN `status` = 'pending' THEN `tenant_id` ELSE NULL END
  ) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_owner_invitation_token_hash` (`token_hash`),
  UNIQUE KEY `uk_owner_invitation_pending_tenant` (`pending_tenant_id`),
  KEY `idx_owner_invitation_tenant_time` (`tenant_id`, `created_at`, `id`),
  KEY `idx_owner_invitation_status_expiry` (`status`, `expires_at`, `id`),
  CONSTRAINT `fk_owner_invitation_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `pa_tenant` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_owner_invitation_account` FOREIGN KEY (`accepted_account_id`)
    REFERENCES `pa_account` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_owner_invitation_member` FOREIGN KEY (`tenant_id`, `accepted_member_id`)
    REFERENCES `pa_tenant_member` (`tenant_id`, `id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_owner_invitation_inviter` FOREIGN KEY (`invited_by_operator_id`)
    REFERENCES `pa_platform_operator` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_owner_invitation_revoker` FOREIGN KEY (`revoked_by_operator_id`)
    REFERENCES `pa_platform_operator` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_owner_invitation_status` CHECK (
    `status` IN ('pending', 'accepted', 'revoked', 'expired')
  ),
  CONSTRAINT `chk_owner_invitation_delivery_status` CHECK (
    `delivery_status` IN ('pending_delivery', 'sent', 'failed')
  ),
  CONSTRAINT `chk_owner_invitation_accepted` CHECK (
    (`status` = 'accepted' AND `accepted_at` IS NOT NULL
      AND `accepted_account_id` IS NOT NULL AND `accepted_member_id` IS NOT NULL)
    OR (`status` <> 'accepted' AND `accepted_at` IS NULL
      AND `accepted_account_id` IS NULL AND `accepted_member_id` IS NULL)
  ),
  CONSTRAINT `chk_owner_invitation_revoked` CHECK (
    (`status` = 'revoked' AND `revoked_at` IS NOT NULL AND `revoked_by_operator_id` IS NOT NULL)
    OR (`status` <> 'revoked' AND `revoked_at` IS NULL AND `revoked_by_operator_id` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
