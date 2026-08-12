CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tenant_code (code)
) ENGINE=InnoDB;

CREATE TABLE pa_notice_scene (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL DEFAULT '',
  name VARCHAR(100) NOT NULL DEFAULT '',
  description VARCHAR(255) NOT NULL DEFAULT '',
  recipient VARCHAR(50) NOT NULL DEFAULT '用户',
  variables JSON NULL,
  sms_template_id VARCHAR(100) NOT NULL DEFAULT '',
  sms_content VARCHAR(500) NOT NULL DEFAULT '',
  sms_status TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_code (code),
  KEY idx_sms_status (sms_status)
) ENGINE=InnoDB;

CREATE TABLE pa_notice_template (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL DEFAULT '',
  code VARCHAR(50) NOT NULL DEFAULT '',
  channel TINYINT(2) NOT NULL DEFAULT 1,
  title VARCHAR(200) NOT NULL DEFAULT '',
  content TEXT,
  is_disable TINYINT(1) NOT NULL DEFAULT 0,
  remark VARCHAR(255) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  delete_time INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_code (code),
  KEY idx_channel (channel)
) ENGINE=InnoDB;

CREATE TABLE pa_notice_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_id INT UNSIGNED NOT NULL DEFAULT 0,
  scene_id INT UNSIGNED NOT NULL DEFAULT 0,
  channel TINYINT(2) NOT NULL DEFAULT 1,
  provider VARCHAR(20) NOT NULL DEFAULT '',
  receiver VARCHAR(200) NOT NULL DEFAULT '',
  title VARCHAR(200) NOT NULL DEFAULT '',
  content TEXT,
  verify_code_hash VARCHAR(255) NOT NULL DEFAULT '',
  is_verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  check_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  verified_time INT UNSIGNED NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 0,
  error VARCHAR(500) NOT NULL DEFAULT '',
  extra TEXT,
  send_time INT UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_template_id (template_id),
  KEY idx_scene_receiver (scene_id, receiver, status, send_time),
  KEY idx_channel (channel),
  KEY idx_status (status),
  KEY idx_send_time (send_time)
) ENGINE=InnoDB;

INSERT INTO pa_tenant (id, code, status) VALUES (101, 'default', 'active');
INSERT INTO pa_notice_scene
  (id, code, name, variables, sms_template_id, sms_content, sms_status, create_time, update_time)
VALUES
  (11, 'login_code', 'Default login', JSON_ARRAY('code'), 'provider-login', 'Code ${code}', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (12, 'bind_mobile', 'Default bind', JSON_ARRAY('code'), 'provider-bind', 'Code ${code}', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (13, 'change_mobile', 'Default change', JSON_ARRAY('code'), 'provider-change', 'Code ${code}', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (14, 'reset_password', 'Default reset', JSON_ARRAY('code'), 'provider-reset', 'Code ${code}', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO pa_notice_template
  (id, name, code, channel, title, content, create_time, update_time)
VALUES (21, 'Default template', 'member_notice', 1, '', 'Code ${code}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO pa_notice_log
  (id, template_id, scene_id, channel, provider, receiver, title, content, status, error, extra, send_time, create_time)
VALUES (31, 21, 11, 1, 'legacy', '13900000000', 'Legacy', 'redacted', 2, '', '{}', UNIX_TIMESTAMP() - 600, UNIX_TIMESTAMP() - 600);
