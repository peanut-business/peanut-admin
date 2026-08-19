CREATE TABLE `pa_system_dict_type` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(100) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT '',
  `is_disable` TINYINT(1) NOT NULL DEFAULT 0,
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_dict_type_code` (`code`),
  KEY `idx_system_dict_type_status` (`is_disable`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统只读字典类型';

CREATE TABLE `pa_system_dict_data` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_code` VARCHAR(100) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT '',
  `value` VARCHAR(255) NOT NULL DEFAULT '',
  `sort` SMALLINT NOT NULL DEFAULT 0,
  `is_disable` TINYINT(1) NOT NULL DEFAULT 0,
  `remark` VARCHAR(255) NOT NULL DEFAULT '',
  `create_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_dict_data_type_value` (`type_code`, `value`),
  KEY `idx_system_dict_data_lookup` (`type_code`, `is_disable`, `sort`, `id`),
  CONSTRAINT `fk_system_dict_data_type`
    FOREIGN KEY (`type_code`) REFERENCES `pa_system_dict_type` (`code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统只读字典数据';

INSERT INTO `pa_system_dict_type` (`code`, `name`, `remark`)
VALUES
  ('member_sex', '会员性别', '系统固定枚举，租户不可修改'),
  ('member_status', '会员状态', '系统固定枚举，租户不可修改'),
  ('member_channel', '会员注册渠道', '系统固定枚举，租户不可修改'),
  ('payment_status', '支付状态', '系统固定枚举，租户不可修改'),
  ('refund_status', '退款状态', '系统固定枚举，租户不可修改');

INSERT INTO `pa_system_dict_data` (`type_code`, `name`, `value`, `sort`, `remark`)
VALUES
  ('member_sex', '未知', '0', 30, 'unknown'),
  ('member_sex', '男', '1', 20, 'male'),
  ('member_sex', '女', '2', 10, 'female'),
  ('member_status', '正常', '1', 20, 'active'),
  ('member_status', '禁用', '0', 10, 'disabled'),
  ('member_channel', '微信小程序', '1', 60, 'wechat_mmp'),
  ('member_channel', '微信公众号', '2', 50, 'wechat_oa'),
  ('member_channel', 'H5', '3', 40, 'h5'),
  ('member_channel', 'PC', '4', 30, 'pc'),
  ('member_channel', 'iOS', '5', 20, 'ios'),
  ('member_channel', 'Android', '6', 10, 'android'),
  ('payment_status', '待支付', '0', 20, 'unpaid'),
  ('payment_status', '已支付', '1', 10, 'paid'),
  ('refund_status', '处理中', '0', 30, 'processing'),
  ('refund_status', '成功', '1', 20, 'success'),
  ('refund_status', '失败', '2', 10, 'failed');
