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

INSERT INTO pa_tenant (id,code,status) VALUES (101,'alpha','active'),(202,'beta','active');
INSERT INTO pa_config (type,name,value) VALUES
('transaction','cancel_unpaid_orders','0'),
('transaction','cancel_unpaid_orders_times','45'),
('transaction','verification_orders','1'),
('transaction','verification_orders_times','36'),
('website','shop_name','Peanut Admin');
