CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL, code VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_default_tenant_bootstrap (
  id TINYINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, status VARCHAR(16) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_default_bootstrap_tenant (tenant_id)
) ENGINE=InnoDB;
CREATE TABLE pa_member (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL,
  sn VARCHAR(20) NOT NULL DEFAULT '', account VARCHAR(50) NOT NULL DEFAULT '', password VARCHAR(100) NOT NULL DEFAULT '',
  nickname VARCHAR(50) NOT NULL DEFAULT '', avatar VARCHAR(255) NOT NULL DEFAULT '', mobile VARCHAR(20) NOT NULL DEFAULT '',
  status TINYINT NOT NULL DEFAULT 1, user_money DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  total_recharge_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NOT NULL DEFAULT 0, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_member_tenant_id (tenant_id,id)
) ENGINE=InnoDB;
CREATE TABLE pa_recharge_order (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, sn VARCHAR(64) NOT NULL, user_id INT UNSIGNED NOT NULL,
  pay_sn VARCHAR(255) NULL, pay_way TINYINT NOT NULL DEFAULT 2, pay_status TINYINT NOT NULL DEFAULT 0,
  pay_time INT UNSIGNED NULL, order_amount DECIMAL(10,2) NOT NULL DEFAULT 0, order_terminal TINYINT NULL DEFAULT 1,
  transaction_id VARCHAR(128) NULL, refund_status TINYINT NOT NULL DEFAULT 0,
  refund_transaction_id VARCHAR(255) NULL, create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn), UNIQUE KEY uk_pay_sn (pay_sn), UNIQUE KEY uk_transaction_id (transaction_id)
) ENGINE=InnoDB;
CREATE TABLE pa_member_balance_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL,
  sn VARCHAR(32) NOT NULL, member_id INT UNSIGNED NOT NULL, change_object TINYINT UNSIGNED NOT NULL DEFAULT 1,
  change_type SMALLINT UNSIGNED NOT NULL DEFAULT 0, action TINYINT UNSIGNED NOT NULL DEFAULT 1,
  change_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0, left_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
  source_type TINYINT NOT NULL DEFAULT 0,
  source_sn VARCHAR(255) NULL, remark VARCHAR(255) NULL, extra TEXT NULL, admin_id INT UNSIGNED NOT NULL DEFAULT 0,
  create_time INT UNSIGNED NOT NULL DEFAULT 0, update_time INT UNSIGNED NULL, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_member_balance_log_tenant_id (tenant_id,id),
  UNIQUE KEY uk_member_balance_log_tenant_sn (tenant_id,sn),
  UNIQUE KEY uk_member_balance_log_tenant_source (tenant_id,source_sn),
  KEY idx_member_balance_log_tenant_member_time (tenant_id,member_id,create_time,id)
) ENGINE=InnoDB;
CREATE TABLE pa_refund_record (
  id INT NOT NULL AUTO_INCREMENT, sn VARCHAR(32) NOT NULL, user_id INT NOT NULL, order_id INT NOT NULL,
  order_sn VARCHAR(64) NOT NULL, order_type VARCHAR(255) NULL DEFAULT 'order', order_amount DECIMAL(10,2) UNSIGNED NOT NULL,
  refund_amount DECIMAL(10,2) UNSIGNED NOT NULL, transaction_id VARCHAR(255) NULL, refund_way TINYINT NOT NULL DEFAULT 1,
  refund_type TINYINT NOT NULL DEFAULT 1, refund_status TINYINT UNSIGNED NOT NULL DEFAULT 0, refund_msg TEXT NULL,
  create_time INT UNSIGNED NULL DEFAULT 0, update_time INT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn), UNIQUE KEY uk_order_type_order_id (order_type,order_id)
) ENGINE=InnoDB;
CREATE TABLE pa_refund_log (
  id INT NOT NULL AUTO_INCREMENT, sn VARCHAR(32) NULL, record_id INT NOT NULL, user_id INT NOT NULL, handle_id INT NOT NULL DEFAULT 0,
  order_amount DECIMAL(10,2) UNSIGNED NOT NULL, refund_amount DECIMAL(10,2) UNSIGNED NOT NULL,
  refund_status TINYINT UNSIGNED NOT NULL DEFAULT 0, refund_msg TEXT NULL,
  create_time INT UNSIGNED NULL DEFAULT 0, update_time INT NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_sn (sn)
) ENGINE=InnoDB;

INSERT INTO pa_tenant (id,code,status) VALUES (101,'default','active');
INSERT INTO pa_default_tenant_bootstrap (id,tenant_id,status) VALUES (1,101,'completed');
INSERT INTO pa_member (id,tenant_id,sn,account,nickname,user_money,total_recharge_amount)
VALUES (11,101,'M-ALPHA','alpha','Alpha',100.00,10.00);
INSERT INTO pa_recharge_order
  (id,sn,user_id,pay_sn,pay_way,pay_status,pay_time,order_amount,order_terminal,transaction_id,refund_status,create_time)
VALUES (21,'RC-LEGACY-ALPHA',11,'PY-LEGACY-ALPHA',2,1,1700000000,10.00,3,'TX-LEGACY-ALPHA',1,1700000000);
INSERT INTO pa_refund_record
  (id,sn,user_id,order_id,order_sn,order_type,order_amount,refund_amount,transaction_id,refund_way,refund_type,refund_status,refund_msg)
VALUES (31,'RF-LEGACY-ALPHA',11,21,'RC-LEGACY-ALPHA','recharge',10.00,10.00,'TX-LEGACY-ALPHA',1,1,2,'');
INSERT INTO pa_refund_log
  (id,sn,record_id,user_id,handle_id,order_amount,refund_amount,refund_status,refund_msg)
VALUES (41,'RL-LEGACY-ALPHA',31,11,1,10.00,10.00,2,'');
