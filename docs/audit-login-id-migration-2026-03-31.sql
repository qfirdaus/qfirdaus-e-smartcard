ALTER TABLE `audit_event`
    ADD COLUMN `login_id` VARCHAR(191) NULL DEFAULT NULL AFTER `user_id`,
    ADD INDEX `idx_audit_event_login_id_occurred_at` (`login_id`, `occurred_at`);

ALTER TABLE `audit_session`
    ADD COLUMN `login_id` VARCHAR(191) NULL DEFAULT NULL AFTER `user_nopekerja`,
    ADD INDEX `idx_audit_session_login_id_started_at` (`login_id`, `started_at`);

ALTER TABLE `audit_request`
    ADD COLUMN `login_id` VARCHAR(191) NULL DEFAULT NULL AFTER `user_id`,
    ADD INDEX `idx_audit_request_login_id_started_at` (`login_id`, `started_at`);

UPDATE `audit_session` AS s
INNER JOIN `tbl_m_user` AS u
    ON (
        (`s`.`user_id` IS NOT NULL AND `u`.`f_userID` = `s`.`user_id`)
        OR (`s`.`user_id` IS NOT NULL AND TRIM(COALESCE(`u`.`f_stafID`, '')) REGEXP '^[0-9]+' AND CAST(SUBSTRING_INDEX(TRIM(`u`.`f_stafID`), '-', 1) AS UNSIGNED) = `s`.`user_id`)
        OR (`s`.`user_id` IS NOT NULL AND TRIM(COALESCE(`u`.`f_nopekerja`, '')) REGEXP '^[0-9]+' AND CAST(SUBSTRING_INDEX(TRIM(`u`.`f_nopekerja`), '-', 1) AS UNSIGNED) = `s`.`user_id`)
        OR (TRIM(COALESCE(`s`.`user_nopekerja`, '')) <> '' AND TRIM(COALESCE(`u`.`f_loginID`, '')) = TRIM(`s`.`user_nopekerja`))
        OR (TRIM(COALESCE(`s`.`user_nopekerja`, '')) <> '' AND TRIM(COALESCE(`u`.`f_stafID`, '')) = TRIM(`s`.`user_nopekerja`))
        OR (TRIM(COALESCE(`s`.`user_nopekerja`, '')) <> '' AND TRIM(COALESCE(`u`.`f_nopekerja`, '')) = TRIM(`s`.`user_nopekerja`))
    )
SET `s`.`login_id` = TRIM(`u`.`f_loginID`)
WHERE COALESCE(TRIM(`s`.`login_id`), '') = ''
  AND COALESCE(TRIM(`u`.`f_loginID`), '') <> '';

UPDATE `audit_event` AS e
INNER JOIN `tbl_m_user` AS u
    ON (
        (`e`.`user_id` IS NOT NULL AND `u`.`f_userID` = `e`.`user_id`)
        OR (`e`.`user_id` IS NOT NULL AND TRIM(COALESCE(`u`.`f_stafID`, '')) REGEXP '^[0-9]+' AND CAST(SUBSTRING_INDEX(TRIM(`u`.`f_stafID`), '-', 1) AS UNSIGNED) = `e`.`user_id`)
        OR (`e`.`user_id` IS NOT NULL AND TRIM(COALESCE(`u`.`f_nopekerja`, '')) REGEXP '^[0-9]+' AND CAST(SUBSTRING_INDEX(TRIM(`u`.`f_nopekerja`), '-', 1) AS UNSIGNED) = `e`.`user_id`)
    )
SET `e`.`login_id` = TRIM(`u`.`f_loginID`)
WHERE COALESCE(TRIM(`e`.`login_id`), '') = ''
  AND COALESCE(TRIM(`u`.`f_loginID`), '') <> '';

UPDATE `audit_request` AS r
INNER JOIN `tbl_m_user` AS u
    ON (
        (`r`.`user_id` IS NOT NULL AND `u`.`f_userID` = `r`.`user_id`)
        OR (`r`.`user_id` IS NOT NULL AND TRIM(COALESCE(`u`.`f_stafID`, '')) REGEXP '^[0-9]+' AND CAST(SUBSTRING_INDEX(TRIM(`u`.`f_stafID`), '-', 1) AS UNSIGNED) = `r`.`user_id`)
        OR (`r`.`user_id` IS NOT NULL AND TRIM(COALESCE(`u`.`f_nopekerja`, '')) REGEXP '^[0-9]+' AND CAST(SUBSTRING_INDEX(TRIM(`u`.`f_nopekerja`), '-', 1) AS UNSIGNED) = `r`.`user_id`)
    )
SET `r`.`login_id` = TRIM(`u`.`f_loginID`)
WHERE COALESCE(TRIM(`r`.`login_id`), '') = ''
  AND COALESCE(TRIM(`u`.`f_loginID`), '') <> '';
