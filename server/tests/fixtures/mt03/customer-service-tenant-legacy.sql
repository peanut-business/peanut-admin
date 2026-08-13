CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL, code VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_config (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, type VARCHAR(30) NOT NULL,
  name VARCHAR(60) NOT NULL, value TEXT NULL,
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_type_name (type,name)
) ENGINE=InnoDB;
CREATE TABLE pa_file (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id BIGINT UNSIGNED NOT NULL,
  type TINYINT UNSIGNED NOT NULL DEFAULT 10, name VARCHAR(100) NOT NULL DEFAULT '',
  uri VARCHAR(500) NOT NULL DEFAULT '', storage VARCHAR(20) NOT NULL DEFAULT 'local',
  cid INT UNSIGNED NOT NULL DEFAULT 0, source VARCHAR(20) NOT NULL DEFAULT '',
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL, delete_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_file_tenant_id (tenant_id,id)
) ENGINE=InnoDB;

INSERT INTO pa_tenant (id,code,status) VALUES (101,'alpha','active'),(202,'beta','active');
INSERT INTO pa_file (id,tenant_id,name,uri,storage) VALUES
(11,101,'Alpha QR','storage/uploads/images/alpha-qr.png','local'),
(22,202,'Beta QR','storage/uploads/images/beta-qr.png','local');
INSERT INTO pa_config (type,name,value) VALUES
('customer_service','qr_code','storage/uploads/images/alpha-qr.png'),
('customer_service','wechat','peanut-support'),
('customer_service','phone','400-000-0000'),
('customer_service','service_time','09:00-18:00'),
('website','shop_name','Peanut Admin');
