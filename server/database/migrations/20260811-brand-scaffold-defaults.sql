SET NAMES utf8mb4;

-- 只替换空值或旧 ThinkPHP favicon 占位；已配置的会员头像保持不变。
INSERT INTO `pa_config` (`type`,`name`,`value`,`create_time`,`update_time`)
SELECT 'default_image', 'user_avatar', 'brand/avatar-member.svg', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM `pa_config` WHERE `type`='default_image' AND `name`='user_avatar'
);

UPDATE `pa_config`
SET `value`='brand/avatar-member.svg', `update_time`=UNIX_TIMESTAMP()
WHERE `type`='default_image'
  AND `name`='user_avatar'
  AND (`value`='' OR `value`='favicon.ico');
