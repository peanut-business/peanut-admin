SET NAMES utf8mb4;

-- PB07 不保留可重放验证码明文：部署时令历史未消费验证码失效并脱敏快照。
UPDATE `pa_notice_log`
SET `content` = CASE
        WHEN `verify_code` <> '' THEN REPLACE(`content`, `verify_code`, '****')
        ELSE `content`
    END,
    `verify_code` = ''
WHERE `verify_code` <> '';

ALTER TABLE `pa_notice_log`
  CHANGE COLUMN `verify_code` `verify_code_hash` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '验证码单向慢哈希';

-- 通用模板/邮件扩展没有产品触发器或 UI 消费，退出 Runtime；历史模板表留存只读数据。
DELETE FROM `pa_system_role_menu`
WHERE `menu_id` IN (
  SELECT `id` FROM `pa_system_menu`
  WHERE `perms` IN ('notice/template/add', 'notice/template/edit', 'notice/template/delete')
);

DELETE FROM `pa_system_menu`
WHERE `perms` IN ('notice/template/add', 'notice/template/edit', 'notice/template/delete');
