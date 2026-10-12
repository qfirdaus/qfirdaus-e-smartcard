-- Audit user_id normalization check/fix.
-- Standard: audit_event.user_id and audit_request.user_id store tbl_m_user.f_nopekerja
-- as a numeric staff number, not tbl_m_user.f_userID.

-- 1) Find audit_event rows where user_id looks like tbl_m_user.f_userID
-- and can be safely mapped through matching login_id.
SELECT
  e.id,
  e.occurred_at,
  e.event_type,
  e.login_id,
  e.user_id AS current_user_id,
  u.f_userID AS matched_f_userID,
  u.f_nopekerja AS corrected_nopekerja
FROM audit_event e
JOIN tbl_m_user u
  ON TRIM(u.f_loginID) = TRIM(e.login_id)
 AND CAST(e.user_id AS UNSIGNED) = CAST(u.f_userID AS UNSIGNED)
WHERE e.login_id IS NOT NULL
  AND TRIM(e.login_id) <> ''
  AND e.user_id IS NOT NULL
  AND u.f_nopekerja IS NOT NULL
  AND TRIM(u.f_nopekerja) REGEXP '^[0-9]+$'
  AND CAST(e.user_id AS UNSIGNED) <> CAST(u.f_nopekerja AS UNSIGNED)
ORDER BY e.id DESC;

-- 2) Fix audit_event rows found by the query above.
UPDATE audit_event e
JOIN tbl_m_user u
  ON TRIM(u.f_loginID) = TRIM(e.login_id)
 AND CAST(e.user_id AS UNSIGNED) = CAST(u.f_userID AS UNSIGNED)
SET e.user_id = CAST(u.f_nopekerja AS UNSIGNED)
WHERE e.login_id IS NOT NULL
  AND TRIM(e.login_id) <> ''
  AND e.user_id IS NOT NULL
  AND u.f_nopekerja IS NOT NULL
  AND TRIM(u.f_nopekerja) REGEXP '^[0-9]+$'
  AND CAST(e.user_id AS UNSIGNED) <> CAST(u.f_nopekerja AS UNSIGNED);

-- 3) Find audit_request rows with the same issue.
SELECT
  r.id,
  r.started_at,
  r.route,
  r.login_id,
  r.user_id AS current_user_id,
  u.f_userID AS matched_f_userID,
  u.f_nopekerja AS corrected_nopekerja
FROM audit_request r
JOIN tbl_m_user u
  ON TRIM(u.f_loginID) = TRIM(r.login_id)
 AND CAST(r.user_id AS UNSIGNED) = CAST(u.f_userID AS UNSIGNED)
WHERE r.login_id IS NOT NULL
  AND TRIM(r.login_id) <> ''
  AND r.user_id IS NOT NULL
  AND u.f_nopekerja IS NOT NULL
  AND TRIM(u.f_nopekerja) REGEXP '^[0-9]+$'
  AND CAST(r.user_id AS UNSIGNED) <> CAST(u.f_nopekerja AS UNSIGNED)
ORDER BY r.id DESC;

-- 4) Fix audit_request rows found by the query above.
UPDATE audit_request r
JOIN tbl_m_user u
  ON TRIM(u.f_loginID) = TRIM(r.login_id)
 AND CAST(r.user_id AS UNSIGNED) = CAST(u.f_userID AS UNSIGNED)
SET r.user_id = CAST(u.f_nopekerja AS UNSIGNED)
WHERE r.login_id IS NOT NULL
  AND TRIM(r.login_id) <> ''
  AND r.user_id IS NOT NULL
  AND u.f_nopekerja IS NOT NULL
  AND TRIM(u.f_nopekerja) REGEXP '^[0-9]+$'
  AND CAST(r.user_id AS UNSIGNED) <> CAST(u.f_nopekerja AS UNSIGNED);
