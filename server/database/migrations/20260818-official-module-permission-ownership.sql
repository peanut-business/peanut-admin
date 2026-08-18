-- Move canonical permission ownership without replacing permission IDs or role grants.
UPDATE `pa_permission`
SET `module_key` = CASE
  WHEN `key` LIKE 'file/%' OR `key` LIKE 'upload/%' THEN 'official.file'
  WHEN `key` LIKE 'notice/%' THEN 'official.notification'
  WHEN `key` LIKE 'setting/web-page/%'
    OR `key` LIKE 'setting/mini-program/%'
    OR `key` LIKE 'setting/official-account/%'
    OR `key` LIKE 'setting/open-platform/%' THEN 'official.oauth'
  WHEN `key` LIKE 'setting/pay/%'
    OR `key` LIKE 'setting/recharge/%'
    OR `key` LIKE 'finance/recharge/%'
    OR `key` LIKE 'recharge.recharge/%'
    OR `key` LIKE 'finance/refund/%'
    OR `key` LIKE 'finance.refund/%' THEN 'official.payment'
  WHEN `key` LIKE 'member/%'
    OR `key` LIKE 'user.user/%'
    OR `key` LIKE 'finance/account-log/%'
    OR `key` LIKE 'finance.account_log/%' THEN 'official.member'
  WHEN `key` LIKE 'crontab/%' THEN 'official.task'
  WHEN `key` IN ('log/export', 'log/export/status', 'log/export/download') THEN 'official.import-export'
  ELSE `module_key`
END,
`updated_at` = TIMESTAMP('2026-08-18 00:00:00.000')
WHERE `module_key` = 'peanut.admin'
  AND (
    `key` LIKE 'file/%' OR `key` LIKE 'upload/%' OR `key` LIKE 'notice/%'
    OR `key` LIKE 'setting/web-page/%' OR `key` LIKE 'setting/mini-program/%'
    OR `key` LIKE 'setting/official-account/%' OR `key` LIKE 'setting/open-platform/%'
    OR `key` LIKE 'setting/pay/%' OR `key` LIKE 'setting/recharge/%'
    OR `key` LIKE 'finance/recharge/%' OR `key` LIKE 'recharge.recharge/%'
    OR `key` LIKE 'finance/refund/%' OR `key` LIKE 'finance.refund/%'
    OR `key` LIKE 'member/%' OR `key` LIKE 'user.user/%'
    OR `key` LIKE 'finance/account-log/%' OR `key` LIKE 'finance.account_log/%'
    OR `key` LIKE 'crontab/%'
    OR `key` IN ('log/export', 'log/export/status', 'log/export/download')
  );
