-- User SSO provisioning and identity metadata migration
-- Date: 2026-09-03
-- Target table: tbl_m_user
-- Compatible with MySQL 8 and safe to run repeatedly.

SET @schema_name = DATABASE();

SET @add_auto_provisioned = IF(
    EXISTS(
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'tbl_m_user'
          AND COLUMN_NAME = 'f_isAutoProvisioned'
    ),
    'DO 0',
    'ALTER TABLE `tbl_m_user` ADD COLUMN `f_isAutoProvisioned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `f_remarks`'
);
PREPARE stmt FROM @add_auto_provisioned;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_identity_source = IF(
    EXISTS(
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'tbl_m_user'
          AND COLUMN_NAME = 'f_identitySource'
    ),
    'DO 0',
    'ALTER TABLE `tbl_m_user` ADD COLUMN `f_identitySource` VARCHAR(20) NULL DEFAULT NULL AFTER `f_isAutoProvisioned`'
);
PREPARE stmt FROM @add_identity_source;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill only records carrying explicit evidence that the account was created
-- by the framework SSO auto-provisioning flow. Existing manually-created users
-- are marked with f_identitySource = SSO on their next successful OneID login.
UPDATE `tbl_m_user`
SET `f_isAutoProvisioned` = 1,
    `f_identitySource` = 'SSO'
WHERE UPPER(TRIM(COALESCE(`f_updateby`, ''))) = 'SSO-AUTO'
   OR UPPER(TRIM(COALESCE(`f_remarks`, ''))) LIKE 'AUTO PROVISIONED VIA SSO%';
