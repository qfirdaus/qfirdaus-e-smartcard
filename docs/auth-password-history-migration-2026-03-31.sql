CREATE TABLE `tbl_auth_password_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `f_loginID` VARCHAR(150) NOT NULL,
    `f_password_hash` VARCHAR(255) NOT NULL,
    `f_source` VARCHAR(50) NOT NULL DEFAULT 'password_change',
    `f_created_at` DATETIME NOT NULL,
    `f_insertdt` DATETIME NOT NULL,
    `f_updatedt` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tbl_auth_password_history_login` (`f_loginID`),
    KEY `idx_tbl_auth_password_history_created` (`f_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
