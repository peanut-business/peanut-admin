CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL, code VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_member (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL,
  sn VARCHAR(20) NOT NULL DEFAULT '', account VARCHAR(50) NOT NULL DEFAULT '', password VARCHAR(100) NOT NULL DEFAULT '',
  nickname VARCHAR(50) NOT NULL DEFAULT '', avatar VARCHAR(255) NOT NULL DEFAULT '', mobile VARCHAR(20) NOT NULL DEFAULT '',
  status TINYINT NOT NULL DEFAULT 1, channel TINYINT NOT NULL DEFAULT 0,
  is_new_user TINYINT NOT NULL DEFAULT 0, login_time INT UNSIGNED NULL, login_ip VARCHAR(64) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_member_tenant_id (tenant_id,id)
) ENGINE=InnoDB;
CREATE TABLE pa_oauth_principal (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, provider VARCHAR(32) NOT NULL,
  union_scope VARCHAR(64) NOT NULL, union_id VARCHAR(191) NOT NULL, member_id INT UNSIGNED NOT NULL,
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_provider_scope_union (provider,union_scope,union_id), KEY idx_member_id (member_id)
) ENGINE=InnoDB;
CREATE TABLE pa_oauth_identity (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, provider VARCHAR(32) NOT NULL,
  client_key VARCHAR(64) NOT NULL, subject VARCHAR(191) NOT NULL,
  principal_id INT UNSIGNED NULL, member_id INT UNSIGNED NOT NULL, terminal TINYINT UNSIGNED NOT NULL,
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_provider_client_subject (provider,client_key,subject),
  UNIQUE KEY uk_member_provider_client (member_id,provider,client_key),
  KEY idx_member_terminal (member_id,terminal), KEY idx_principal_id (principal_id)
) ENGINE=InnoDB;
CREATE TABLE pa_oauth_attempt (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, state_hash CHAR(64) NOT NULL,
  scene VARCHAR(32) NOT NULL, return_path VARCHAR(500) NOT NULL DEFAULT '/',
  expires_at INT UNSIGNED NOT NULL, used_at INT UNSIGNED NULL,
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_state_hash (state_hash), KEY idx_expires_at (expires_at)
) ENGINE=InnoDB;
CREATE TABLE pa_oauth_completion_ticket (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, token_hash CHAR(64) NOT NULL,
  member_id INT UNSIGNED NOT NULL, need_profile TINYINT UNSIGNED NOT NULL DEFAULT 0,
  need_mobile TINYINT UNSIGNED NOT NULL DEFAULT 0, expires_at INT UNSIGNED NOT NULL,
  used_at INT UNSIGNED NULL, create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_token_hash (token_hash), KEY idx_member_id (member_id), KEY idx_expires_at (expires_at)
) ENGINE=InnoDB;

INSERT INTO pa_tenant (id,code,status) VALUES (101,'alpha','active'),(202,'beta','suspended');
INSERT INTO pa_member (id,tenant_id,sn,account,nickname,status)
VALUES (11,101,'M-ALPHA','alpha','Alpha',1),(22,202,'M-BETA','beta','Beta',1);
INSERT INTO pa_oauth_principal (id,provider,union_scope,union_id,member_id)
VALUES (31,'wechat','wechat_default','union-shared',11);
INSERT INTO pa_oauth_identity (id,provider,client_key,subject,principal_id,member_id,terminal)
VALUES (41,'wechat','mnp:app-instance','openid-shared',31,11,1);
INSERT INTO pa_oauth_attempt (id,state_hash,scene,return_path,expires_at)
VALUES (51,REPEAT('a',64),'oa','/alpha',2147483647);
INSERT INTO pa_oauth_completion_ticket (id,token_hash,member_id,need_profile,need_mobile,expires_at)
VALUES (61,REPEAT('b',64),11,1,0,2147483647);
