SET NAMES utf8mb4;

-- PB07 旧 Channel CRUD 已退出；删除不再消费的重复凭据和未实现的 AES 资料。
DELETE FROM `pa_config`
WHERE (`type` = 'channel' AND `name` IN (
  'wechat_open_status', 'wechat_open_appid', 'wechat_open_secret',
  'wechat_oa_status', 'wechat_oa_appid', 'wechat_oa_secret',
  'qq_status', 'qq_appid', 'qq_secret'
)) OR (`type` = 'oa_setting' AND `name` IN ('encoding_aes_key', 'encryption_type'));
