CREATE TABLE `tbl_auth_password_reset` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `f_loginID` VARCHAR(150) NOT NULL,
    `f_email` VARCHAR(150) NOT NULL,
    `f_token_hash` CHAR(64) NOT NULL,
    `f_requested_at` DATETIME NOT NULL,
    `f_expires_at` DATETIME NOT NULL,
    `f_used_at` DATETIME NULL DEFAULT NULL,
    `f_requested_ip` VARCHAR(45) NULL DEFAULT NULL,
    `f_user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `f_consumed_ip` VARCHAR(45) NULL DEFAULT NULL,
    `f_insertdt` DATETIME NOT NULL,
    `f_updatedt` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tbl_auth_password_reset_token_hash` (`f_token_hash`),
    KEY `idx_tbl_auth_password_reset_login` (`f_loginID`),
    KEY `idx_tbl_auth_password_reset_expires` (`f_expires_at`),
    KEY `idx_tbl_auth_password_reset_used` (`f_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
