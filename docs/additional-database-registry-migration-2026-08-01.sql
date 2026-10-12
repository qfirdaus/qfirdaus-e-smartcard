-- IQS Framework Additional Database registry
-- Source-of-truth migration date: 2026-08-01
-- Target: Main IQS MySQL database (MySQL 8.x)
--
-- Safety properties:
--   - Creates only the three Additional Database registry tables.
--   - Uses CREATE TABLE IF NOT EXISTS and is safe to run repeatedly.
--   - Does not drop, rename, truncate, update, or delete existing data.
--   - Does not seed or alter connection codes and credentials.
--   - Avoids foreign keys so downstream deployments with an older partial
--     registry are not blocked by constraint or parent-index differences.
--
-- Important:
--   CREATE TABLE IF NOT EXISTS does not repair an existing incompatible table.
--   Run the verification queries at the end and resolve reported schema drift
--   through a separately reviewed additive ALTER migration.

CREATE TABLE IF NOT EXISTS `tbl_m_db_connection` (
  `f_code` VARCHAR(100) NOT NULL,
  `f_name` VARCHAR(191) NOT NULL,
  `f_family` VARCHAR(20) NOT NULL,
  `f_category` VARCHAR(20) NOT NULL DEFAULT 'additional',
  `f_purpose` VARCHAR(100) NOT NULL DEFAULT 'reference',
  `f_driver_mode` VARCHAR(20) NOT NULL DEFAULT 'auto',
  `f_is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `f_supports_prod` TINYINT(1) NOT NULL DEFAULT 1,
  `f_supports_dev` TINYINT(1) NOT NULL DEFAULT 0,
  `f_notes` TEXT NULL,
  `f_created_by` VARCHAR(191) NULL DEFAULT NULL,
  `f_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_updated_by` VARCHAR(191) NULL DEFAULT NULL,
  `f_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`f_code`),
  KEY `idx_db_connection_category_family` (`f_category`, `f_family`),
  KEY `idx_db_connection_enabled` (`f_is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_m_db_connection_env` (
  `f_db_connection_env_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_connection_code` VARCHAR(100) NOT NULL,
  `f_environment` VARCHAR(20) NOT NULL,
  `f_os_family` VARCHAR(20) NOT NULL DEFAULT 'any',
  `f_driver` VARCHAR(20) NOT NULL,
  `f_host` VARCHAR(255) NULL DEFAULT NULL,
  `f_port` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `f_database_name` VARCHAR(255) NULL DEFAULT NULL,
  `f_dsn_name` VARCHAR(500) NULL DEFAULT NULL,
  `f_username` VARCHAR(255) NULL DEFAULT NULL,
  `f_password_ciphertext` TEXT NULL,
  `f_charset` VARCHAR(64) NULL DEFAULT NULL,
  `f_extra_json` JSON NULL,
  `f_is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `f_last_test_status` VARCHAR(20) NULL DEFAULT NULL,
  `f_last_test_message` TEXT NULL,
  `f_last_tested_at` DATETIME NULL DEFAULT NULL,
  `f_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `f_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`f_db_connection_env_id`),
  UNIQUE KEY `uk_db_connection_env_target` (`f_connection_code`, `f_environment`, `f_os_family`, `f_driver`),
  KEY `idx_db_connection_env_active` (`f_connection_code`, `f_environment`, `f_is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_a_db_connection_test` (
  `f_db_connection_test_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `f_connection_code` VARCHAR(100) NOT NULL,
  `f_environment` VARCHAR(20) NOT NULL,
  `f_os_family` VARCHAR(20) NOT NULL DEFAULT 'any',
  `f_driver` VARCHAR(20) NULL DEFAULT NULL,
  `f_status` VARCHAR(20) NOT NULL,
  `f_message` TEXT NULL,
  `f_tested_by` VARCHAR(191) NULL DEFAULT NULL,
  `f_tested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`f_db_connection_test_id`),
  KEY `idx_db_connection_test_code_time` (`f_connection_code`, `f_tested_at`),
  KEY `idx_db_connection_test_status_time` (`f_status`, `f_tested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Read-only verification: all required runtime columns should return one row.
SELECT
  `TABLE_NAME`,
  COUNT(*) AS `required_columns_found`
FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND (
    (`TABLE_NAME` = 'tbl_m_db_connection' AND `COLUMN_NAME` IN (
      'f_code', 'f_name', 'f_family', 'f_category', 'f_purpose',
      'f_driver_mode', 'f_is_enabled', 'f_supports_prod', 'f_supports_dev',
      'f_notes', 'f_created_by', 'f_updated_by'
    ))
    OR
    (`TABLE_NAME` = 'tbl_m_db_connection_env' AND `COLUMN_NAME` IN (
      'f_connection_code', 'f_environment', 'f_os_family', 'f_driver',
      'f_host', 'f_port', 'f_database_name', 'f_dsn_name', 'f_username',
      'f_password_ciphertext', 'f_charset', 'f_extra_json', 'f_is_active',
      'f_last_test_status', 'f_last_test_message', 'f_last_tested_at'
    ))
    OR
    (`TABLE_NAME` = 'tbl_a_db_connection_test' AND `COLUMN_NAME` IN (
      'f_connection_code', 'f_environment', 'f_os_family', 'f_driver',
      'f_status', 'f_message', 'f_tested_by', 'f_tested_at'
    ))
  )
GROUP BY `TABLE_NAME`
ORDER BY `TABLE_NAME`;

-- Expected counts:
--   tbl_m_db_connection      = 12
--   tbl_m_db_connection_env  = 16
--   tbl_a_db_connection_test = 8
