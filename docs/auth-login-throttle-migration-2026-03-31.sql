CREATE TABLE `tbl_auth_login_throttle` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `f_scope_type` VARCHAR(32) NOT NULL,
    `f_scope_key` VARCHAR(255) NOT NULL,
    `f_failed_attempts` INT NOT NULL DEFAULT 0,
    `f_locked_until` DATETIME NULL DEFAULT NULL,
    `f_last_failed_at` DATETIME NULL DEFAULT NULL,
    `f_last_success_at` DATETIME NULL DEFAULT NULL,
    `f_last_ip` VARCHAR(45) NULL DEFAULT NULL,
    `f_user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `f_insertdt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `f_updatedt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tbl_auth_login_throttle_scope` (`f_scope_type`, `f_scope_key`),
    KEY `idx_tbl_auth_login_throttle_locked_until` (`f_locked_until`),
    KEY `idx_tbl_auth_login_throttle_last_failed` (`f_last_failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
