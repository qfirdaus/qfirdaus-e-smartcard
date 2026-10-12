-- View As readiness migration.
-- Run this against the IQS Framework MySQL database.

-- 1) Add ATTEMPT to audit_event.outcome if it is an ENUM and does not already contain ATTEMPT.
-- This preserves the current enum values, NULL/NOT NULL, and default value.
SET @schema_name := DATABASE();

SELECT
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
INTO
  @outcome_column_type,
  @outcome_is_nullable,
  @outcome_default
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME = 'audit_event'
  AND COLUMN_NAME = 'outcome'
LIMIT 1;

SET @outcome_new_type := REPLACE(@outcome_column_type, ')', ',''ATTEMPT'')');
SET @outcome_null_sql := IF(@outcome_is_nullable = 'NO', ' NOT NULL', ' NULL');
SET @outcome_default_sql := CASE
  WHEN @outcome_default IS NULL AND @outcome_is_nullable = 'YES' THEN ' DEFAULT NULL'
  WHEN @outcome_default IS NULL THEN ''
  ELSE CONCAT(' DEFAULT ', QUOTE(@outcome_default))
END;

SET @outcome_alter_sql := IF(
  @outcome_column_type IS NULL,
  'SELECT ''audit_event.outcome column not found'' AS status',
  IF(
    @outcome_column_type NOT LIKE 'enum(%',
    'SELECT ''audit_event.outcome is not an ENUM column'' AS status',
    IF(
      @outcome_column_type LIKE '%''ATTEMPT''%',
      'SELECT ''ATTEMPT already exists in audit_event.outcome'' AS status',
      CONCAT('ALTER TABLE `audit_event` MODIFY `outcome` ', @outcome_new_type, @outcome_null_sql, @outcome_default_sql)
    )
  )
);

PREPARE outcome_stmt FROM @outcome_alter_sql;
EXECUTE outcome_stmt;
DEALLOCATE PREPARE outcome_stmt;

-- 2) Optional: seed the View As timeout DB override.
-- The code has a base default of 60 in settings.php, so this insert is optional.
-- Run it only if you want the same value stored explicitly in Tetapan Sistem.
--
-- INSERT INTO `tbl_m_config` (`f_group`, `f_key`, `f_value`)
-- VALUES ('app_settings', 'impersonation.timeout_minutes', '60')
-- ON DUPLICATE KEY UPDATE `f_value` = VALUES(`f_value`);
