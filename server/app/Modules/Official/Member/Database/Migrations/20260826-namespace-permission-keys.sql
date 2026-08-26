-- Collapse historical Member Admin URI permissions into the Module-key
-- namespace. Several legacy aliases map to one canonical permission, so one
-- deterministic keeper row is selected and both RBAC binding tables are
-- merged before the redundant permission rows are removed.

CREATE TEMPORARY TABLE `pa_official_member_permission_key_map` (
  `old_key` VARCHAR(160) NOT NULL,
  `new_key` VARCHAR(160) NOT NULL,
  PRIMARY KEY (`old_key`),
  KEY `idx_official_member_permission_new_key` (`new_key`)
);

INSERT INTO `pa_official_member_permission_key_map` (`old_key`, `new_key`) VALUES
  ('member/lists',                         'official.member.list'),
  ('member/detail',                        'official.member.detail'),
  ('user.user/detail',                     'official.member.detail'),
  ('member/add',                           'official.member.add'),
  ('member/edit',                          'official.member.edit'),
  ('member/profile/edit',                  'official.member.edit'),
  ('user.user/edit',                       'official.member.edit'),
  ('member/status',                        'official.member.update-status'),
  ('member/adjustbalance',                 'official.member.balance.adjust'),
  ('member/adjustmoney',                   'official.member.balance.adjust'),
  ('user.user/adjustmoney',                'official.member.balance.adjust'),
  ('member/tag/lists',                     'official.member.tag.list'),
  ('member/tag/add',                       'official.member.tag.add'),
  ('member/tag/edit',                      'official.member.tag.edit'),
  ('member/tag/delete',                    'official.member.tag.delete'),
  ('finance/account-log/lists',            'official.member.account-log.list'),
  ('finance.account_log/lists',            'official.member.account-log.list'),
  ('finance/account-log/change-types',     'official.member.account-log.change-types'),
  ('finance.account_log/getumchangetype',  'official.member.account-log.change-types');

CREATE TEMPORARY TABLE `pa_official_member_permission_key_assertion` (
  `phase` VARCHAR(16) NOT NULL,
  `mapped_count` INT NOT NULL,
  `bad_owner_count` INT NOT NULL,
  CONSTRAINT `chk_official_member_permission_key_assertion`
    CHECK (`mapped_count` = 12 AND `bad_owner_count` = 0)
);

INSERT INTO `pa_official_member_permission_key_assertion`
  (`phase`, `mapped_count`, `bad_owner_count`)
SELECT
  'before',
  SUM(EXISTS(
    SELECT 1
    FROM `pa_permission` permission
    LEFT JOIN `pa_official_member_permission_key_map` mapping
      ON LOWER(permission.`key`) = mapping.`old_key`
    WHERE BINARY permission.`key` = BINARY canonical.`new_key`
       OR BINARY mapping.`new_key` = BINARY canonical.`new_key`
  )),
  (
    SELECT COUNT(*)
    FROM `pa_permission` permission
    LEFT JOIN `pa_official_member_permission_key_map` mapping
      ON LOWER(permission.`key`) = mapping.`old_key`
    WHERE (mapping.`old_key` IS NOT NULL
        OR permission.`key` IN (
          SELECT `new_key` FROM `pa_official_member_permission_key_map`
        ))
      AND permission.`module_key` NOT IN ('peanut.admin', 'official.member')
  )
FROM (
  SELECT DISTINCT `new_key`
  FROM `pa_official_member_permission_key_map`
) canonical;

CREATE TEMPORARY TABLE `pa_official_member_permission_target` (
  `new_key` VARCHAR(160) NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`new_key`),
  UNIQUE KEY `uk_official_member_permission_target_id` (`permission_id`)
);

INSERT INTO `pa_official_member_permission_target` (`new_key`, `permission_id`)
SELECT
  canonical.`new_key`,
  COALESCE(
    (
      SELECT MIN(permission.`id`)
      FROM `pa_permission` permission
      WHERE BINARY permission.`key` = BINARY canonical.`new_key`
    ),
    (
      SELECT MIN(permission.`id`)
      FROM `pa_permission` permission
      JOIN `pa_official_member_permission_key_map` mapping
        ON LOWER(permission.`key`) = mapping.`old_key`
      WHERE BINARY mapping.`new_key` = BINARY canonical.`new_key`
    )
  )
FROM (
  SELECT DISTINCT `new_key`
  FROM `pa_official_member_permission_key_map`
) canonical;

UPDATE `pa_system_menu` menu
JOIN `pa_official_member_permission_key_map` mapping
  ON LOWER(menu.`perms`) = mapping.`old_key`
SET menu.`perms` = mapping.`new_key`;

UPDATE `pa_permission` permission
JOIN `pa_official_member_permission_target` target
  ON target.`permission_id` = permission.`id`
SET permission.`key` = target.`new_key`,
    permission.`module_key` = 'official.member',
    permission.`status` = 'active',
    permission.`retired_at` = NULL,
    permission.`updated_at` = UTC_TIMESTAMP(3);

INSERT IGNORE INTO `pa_role_permission`
  (`tenant_id`, `role_id`, `permission_id`, `granted_by_member_id`, `granted_at`)
SELECT binding.`tenant_id`, binding.`role_id`, target.`permission_id`,
       binding.`granted_by_member_id`, binding.`granted_at`
FROM `pa_role_permission` binding
JOIN `pa_permission` old_permission
  ON old_permission.`id` = binding.`permission_id`
JOIN `pa_official_member_permission_key_map` mapping
  ON LOWER(old_permission.`key`) = mapping.`old_key`
JOIN `pa_official_member_permission_target` target
  ON BINARY target.`new_key` = BINARY mapping.`new_key`;

INSERT IGNORE INTO `pa_platform_role_permission`
  (`platform_role_id`, `permission_id`, `granted_at`)
SELECT binding.`platform_role_id`, target.`permission_id`, binding.`granted_at`
FROM `pa_platform_role_permission` binding
JOIN `pa_permission` old_permission
  ON old_permission.`id` = binding.`permission_id`
JOIN `pa_official_member_permission_key_map` mapping
  ON LOWER(old_permission.`key`) = mapping.`old_key`
JOIN `pa_official_member_permission_target` target
  ON BINARY target.`new_key` = BINARY mapping.`new_key`;

DELETE binding
FROM `pa_role_permission` binding
JOIN `pa_permission` old_permission
  ON old_permission.`id` = binding.`permission_id`
JOIN `pa_official_member_permission_key_map` mapping
  ON LOWER(old_permission.`key`) = mapping.`old_key`;

DELETE binding
FROM `pa_platform_role_permission` binding
JOIN `pa_permission` old_permission
  ON old_permission.`id` = binding.`permission_id`
JOIN `pa_official_member_permission_key_map` mapping
  ON LOWER(old_permission.`key`) = mapping.`old_key`;

DELETE old_permission
FROM `pa_permission` old_permission
JOIN `pa_official_member_permission_key_map` mapping
  ON LOWER(old_permission.`key`) = mapping.`old_key`;

UPDATE `pa_permission` permission
JOIN `pa_official_member_permission_target` target
  ON target.`permission_id` = permission.`id`
SET permission.`module_key` = 'official.member',
    permission.`status` = 'active',
    permission.`retired_at` = NULL,
    permission.`updated_at` = UTC_TIMESTAMP(3);

INSERT INTO `pa_official_member_permission_key_assertion`
  (`phase`, `mapped_count`, `bad_owner_count`)
SELECT
  'after',
  COUNT(*),
  SUM(permission.`module_key` <> 'official.member')
FROM `pa_permission` permission
JOIN `pa_official_member_permission_target` target
  ON BINARY permission.`key` = BINARY target.`new_key`;

DROP TEMPORARY TABLE `pa_official_member_permission_target`;
DROP TEMPORARY TABLE `pa_official_member_permission_key_assertion`;
DROP TEMPORARY TABLE `pa_official_member_permission_key_map`;
