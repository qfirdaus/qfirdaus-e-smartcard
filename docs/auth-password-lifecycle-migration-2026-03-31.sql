-- Auth password lifecycle migration
-- Date: 2026-03-31
-- Target table: tbl_m_user
--
-- Note:
-- Some legacy MySQL servers used by this project do not support
-- ADD COLUMN IF NOT EXISTS / ADD INDEX IF NOT EXISTS.
-- Run the statements below only if the column or index does not already exist.

ALTER TABLE `tbl_m_user`
    ADD COLUMN `f_verified_at` DATETIME NULL DEFAULT NULL AFTER `f_password`;

ALTER TABLE `tbl_m_user`
    ADD COLUMN `f_must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `f_verified_at`;

ALTER TABLE `tbl_m_user`
    ADD COLUMN `f_password_changed_at` DATETIME NULL DEFAULT NULL AFTER `f_must_change_password`;

ALTER TABLE `tbl_m_user`
    ADD COLUMN `f_password_expires_at` DATETIME NULL DEFAULT NULL AFTER `f_password_changed_at`;

ALTER TABLE `tbl_m_user`
    ADD INDEX `idx_tbl_m_user_verified_at` (`f_verified_at`);

ALTER TABLE `tbl_m_user`
    ADD INDEX `idx_tbl_m_user_password_expires_at` (`f_password_expires_at`);

-- Optional backfill for existing active users.
-- Review before execution if your data needs a stricter rollout.
UPDATE `tbl_m_user`
SET `f_verified_at` = COALESCE(`f_verified_at`, NOW()),
    `f_must_change_password` = COALESCE(`f_must_change_password`, 0),
    `f_password_changed_at` = COALESCE(`f_password_changed_at`, NOW())
WHERE COALESCE(`f_statusID`, 0) != 9;

-- Optional targeted rollout examples:
-- Force specific manual-login accounts to change password on next login:
-- UPDATE `tbl_m_user`
-- SET `f_must_change_password` = 1,
--     `f_password_expires_at` = NOW()
-- WHERE TRIM(`f_loginID`) IN ('user1', 'user2');
