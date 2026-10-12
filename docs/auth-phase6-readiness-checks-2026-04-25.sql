-- Auth Phase 6 Readiness Checks
-- Tarikh: 2026-04-25
-- Tujuan:
-- 1. Semak schema sebenar auth guardrail tables
-- 2. Semak index dan collation identifier utama
-- 3. Sediakan baseline verification sebelum sebarang migration susulan

SHOW CREATE TABLE tbl_m_user;
SHOW CREATE TABLE tbl_auth_login_lockout;
SHOW CREATE TABLE tbl_auth_login_throttle;

SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'tbl_m_user' AND COLUMN_NAME = 'f_loginID')
    OR (TABLE_NAME = 'tbl_auth_login_lockout' AND COLUMN_NAME = 'f_loginID')
    OR (TABLE_NAME = 'tbl_auth_login_throttle' AND COLUMN_NAME = 'f_scope_key')
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

SELECT
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX,
    COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('tbl_auth_login_lockout', 'tbl_auth_login_throttle')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT
    COUNT(*) AS active_lockouts,
    MIN(f_locked_until) AS first_locked_until,
    MAX(f_locked_until) AS last_locked_until
FROM tbl_auth_login_lockout
WHERE f_locked_until IS NOT NULL;

SELECT
    f_scope_type,
    COUNT(*) AS active_rows,
    MIN(f_locked_until) AS first_locked_until,
    MAX(f_locked_until) AS last_locked_until
FROM tbl_auth_login_throttle
WHERE f_locked_until IS NOT NULL
GROUP BY f_scope_type
ORDER BY f_scope_type;

-- Optional spot checks for canonicalization:
SELECT f_loginID
FROM tbl_auth_login_lockout
WHERE f_loginID <> TRIM(f_loginID)
LIMIT 20;

SELECT f_scope_key
FROM tbl_auth_login_throttle
WHERE f_scope_type = 'LOGIN_IP'
  AND f_scope_key <> TRIM(f_scope_key)
LIMIT 20;
