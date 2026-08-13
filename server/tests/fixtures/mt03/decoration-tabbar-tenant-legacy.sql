CREATE TABLE pa_tenant (
  id BIGINT UNSIGNED NOT NULL, code VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_default_tenant_bootstrap (
  id TINYINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, status VARCHAR(16) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE TABLE pa_config (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, type VARCHAR(30) NOT NULL,
  name VARCHAR(60) NOT NULL, value TEXT NULL,
  create_time INT UNSIGNED NULL, update_time INT UNSIGNED NULL,
  PRIMARY KEY (id), UNIQUE KEY uk_type_name (type,name)
) ENGINE=InnoDB;
CREATE TABLE pa_decorate_tabbar (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  position TINYINT UNSIGNED NOT NULL,
  name VARCHAR(20) NOT NULL DEFAULT '',
  selected VARCHAR(2048) NOT NULL DEFAULT '',
  unselected VARCHAR(2048) NOT NULL DEFAULT '',
  link VARCHAR(1000) NOT NULL DEFAULT '{}',
  is_show TINYINT UNSIGNED NOT NULL DEFAULT 1,
  create_time INT UNSIGNED NOT NULL DEFAULT 0,
  update_time INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uk_decorate_tabbar_position (position)
) ENGINE=InnoDB;

INSERT INTO pa_tenant (id,code,status) VALUES (101,'alpha','active'),(202,'beta','active');
INSERT INTO pa_default_tenant_bootstrap (id,tenant_id,status) VALUES (1,101,'completed');
INSERT INTO pa_config (type,name,value) VALUES
('tabbar','style','{"default_color":"#111111","selected_color":"#2277EE"}'),
('website','shop_name','Peanut Admin');
INSERT INTO pa_decorate_tabbar (id,position,name,selected,unselected,link,is_show) VALUES
(11,0,'Alpha Home','','','{"target_type":"shop","target":"home"}',1),
(12,1,'Alpha Hidden','','','{"target_type":"shop","target":"profile"}',0);
