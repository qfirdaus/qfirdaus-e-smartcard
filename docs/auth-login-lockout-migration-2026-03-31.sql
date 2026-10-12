CREATE TABLE `tbl_auth_login_lockout` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `f_loginID` VARCHAR(191) NOT NULL,
    `f_failed_attempts` INT NOT NULL DEFAULT 0,
    `f_locked_until` DATETIME NULL DEFAULT NULL,
    `f_last_failed_at` DATETIME NULL DEFAULT NULL,
    `f_last_success_at` DATETIME NULL DEFAULT NULL,
    `f_last_ip` VARCHAR(45) NULL DEFAULT NULL,
    `f_user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `f_insertdt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `f_updatedt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tbl_auth_login_lockout_login` (`f_loginID`),
    KEY `idx_tbl_auth_login_lockout_locked_until` (`f_locked_until`),
    KEY `idx_tbl_auth_login_lockout_last_failed` (`f_last_failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
