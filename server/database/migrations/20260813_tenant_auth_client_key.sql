SET @pa_mt04_login_client_column = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pa_login_challenge'
    AND COLUMN_NAME = 'client_key'
);
SET @pa_mt04_login_client_sql = IF(
  @pa_mt04_login_client_column = 0,
  'ALTER TABLE `pa_login_challenge` ADD COLUMN `client_key` VARCHAR(64) NOT NULL DEFAULT ''admin-web'' AFTER `purpose`',
  'SELECT 1'
);
PREPARE pa_mt04_login_client_stmt FROM @pa_mt04_login_client_sql;
EXECUTE pa_mt04_login_client_stmt;
DEALLOCATE PREPARE pa_mt04_login_client_stmt;

SET @pa_mt04_login_client_check = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pa_login_challenge'
    AND CONSTRAINT_NAME = 'chk_login_challenge_client'
);
SET @pa_mt04_login_check_sql = IF(
  @pa_mt04_login_client_check = 0,
  'ALTER TABLE `pa_login_challenge` ADD CONSTRAINT `chk_login_challenge_client` CHECK (REGEXP_LIKE(`client_key`, ''^[a-z][a-z0-9-]{0,63}$'', ''c''))',
  'SELECT 1'
);
PREPARE pa_mt04_login_check_stmt FROM @pa_mt04_login_check_sql;
EXECUTE pa_mt04_login_check_stmt;
DEALLOCATE PREPARE pa_mt04_login_check_stmt;

ALTER TABLE `pa_login_challenge` ALTER COLUMN `client_key` DROP DEFAULT;
