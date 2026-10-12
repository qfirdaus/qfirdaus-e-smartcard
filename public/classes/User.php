<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/User.php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

/**
 * ✅ Model untuk pengurusan pengguna sistem e-Prestasi (MySQL)
 */
class User extends BaseModel
{
    /**
     * Cache ringan untuk semakan kolum optional pada jadual auth.
     *
     * @var array<string,bool>
     */
    private static array $columnExistsCache = [];
    /** @var array<string,bool> */
    private static array $tableExistsCache = [];

    private function tableExists(string $table): bool
    {
        $cacheKey = strtolower($table);
        if (array_key_exists($cacheKey, self::$tableExistsCache)) {
            return self::$tableExistsCache[$cacheKey];
        }

        try {
            $databaseName = (string)$this->db->query('SELECT DATABASE()')->fetchColumn();
            if ($databaseName === '') {
                self::$tableExistsCache[$cacheKey] = false;
                return false;
            }

            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = :database
                   AND TABLE_NAME = :table'
            );
            $stmt->execute([
                ':database' => $databaseName,
                ':table' => $table,
            ]);
            self::$tableExistsCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            self::$tableExistsCache[$cacheKey] = false;
        }

        return self::$tableExistsCache[$cacheKey];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, self::$columnExistsCache)) {
            return self::$columnExistsCache[$cacheKey];
        }

        try {
            $databaseName = (string)$this->db->query('SELECT DATABASE()')->fetchColumn();
            if ($databaseName === '') {
                self::$columnExistsCache[$cacheKey] = false;
                return false;
            }

            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :database
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $stmt->execute([
                ':database' => $databaseName,
                ':table' => $table,
                ':column' => $column,
            ]);
            self::$columnExistsCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            self::$columnExistsCache[$cacheKey] = false;
        }

        return self::$columnExistsCache[$cacheKey];
    }

    public function authTableHasColumn(string $column): bool
    {
        return $this->tableHasColumn('tbl_m_user', $column);
    }

    /** Cari pengguna ikut f_loginID (auth identifier baharu) */
    public function findByLoginID(string $loginID): ?array
    {
        $loginID = function_exists('auth_normalize_login_id')
            ? auth_normalize_login_id($loginID)
            : trim($loginID);
        if ($loginID === '') {
            return null;
        }

        $fields = [
            'f_userID',
            'f_loginID',
            'f_email',
            'f_stafID',
            'f_password',
            'f_nama',
            'f_nickname',
            'f_nopekerja',
            'f_groupID',
            'f_groupKod',
            'f_flag',
            'f_categoryUser',
            'f_statusID',
            'f_status',
        ];

        foreach (['f_verified_at', 'f_must_change_password', 'f_password_changed_at', 'f_password_expires_at', 'f_identitySource'] as $optionalField) {
            if ($this->tableHasColumn('tbl_m_user', $optionalField)) {
                $fields[] = $optionalField;
            }
        }

        $sql = "SELECT " . implode(', ', $fields) . "
                FROM tbl_m_user
                WHERE TRIM(f_loginID) = :loginID
                  AND COALESCE(f_statusID, 0) != 9
                LIMIT 1";
        return $this->fetchOne($sql, [':loginID' => $loginID]);
    }

    /** ✅ Cari pengguna ikut f_stafID (diguna semasa login) */
    public function findByStafID(string $f_stafID): ?array
    {
        $sql = "SELECT f_stafID, f_password, f_nama, f_nickname, f_nopekerja, f_groupID, f_groupKod, f_flag
                FROM tbl_m_user
                WHERE f_stafID = :sid
                LIMIT 1";
        return $this->fetchOne($sql, [':sid' => $f_stafID]);
    }

    /** Ambil maklumat penuh pengguna berdasarkan f_loginID */
    public function getProfileByLoginID(string $loginID = ''): ?array
    {
        $resolvedLoginID = $loginID !== '' ? trim($loginID) : trim((string)($_SESSION['f_loginID'] ?? ''));
        if ($resolvedLoginID === '') {
            return null;
        }

        $sql = "SELECT 
                    u.f_userID,
                    u.f_loginID,
                    u.f_stafID,
                    u.f_nopekerja,
                    u.f_email,
                    u.f_categoryUser,
                    u.f_jabatanKod,
                    u.f_namajabatan,
                    u.f_nama,
                    u.f_nickname,
                    u.f_lang,
                    u.f_groupID,
                    u.f_groupKod,
                    u.f_themeSetting,
                    g.f_groupName
                FROM tbl_m_user u
                LEFT JOIN tbl_m_group g ON u.f_groupID = g.f_groupID
                WHERE TRIM(u.f_loginID) = :loginID
                  AND COALESCE(u.f_statusID, 0) != 9
                LIMIT 1";
        $profile = $this->fetchOne($sql, [':loginID' => $resolvedLoginID]);
        if (!$profile) {
            return null;
        }

        $avatarUrl = $this->resolveAvatarUrl($profile);
        $profile['avatar_url'] = $avatarUrl;
        $profile['avatar'] = $avatarUrl;

        return $profile;
    }

    /** ✅ Ambil maklumat penuh pengguna berdasarkan f_stafID */
    public function getProfile(string $f_stafID = ''): ?array
    {
        // Jika tak diberi, fallback ke session login
        $sid = $f_stafID !== '' ? $f_stafID : ($_SESSION['f_stafID'] ?? '');
        if ($sid === '') return null;

        $sql = "SELECT 
                    u.f_userID,
                    u.f_stafID,
                    u.f_nopekerja,
                    u.f_email,
                    u.f_categoryUser,
                    u.f_jabatanKod,
                    u.f_namajabatan,
                    u.f_nama,
                    u.f_nickname,
                    u.f_lang,
                    u.f_groupID,
                    u.f_groupKod,
                    u.f_themeSetting,
                    g.f_groupName
                FROM tbl_m_user u
                LEFT JOIN tbl_m_group g ON u.f_groupID = g.f_groupID
                WHERE u.f_stafID = :sid
                  AND COALESCE(u.f_statusID, 0) != 9
                LIMIT 1";
        $profile = $this->fetchOne($sql, [':sid' => $sid]);
        if (!$profile) {
            return null;
        }

        $avatarUrl = $this->resolveAvatarUrl($profile);
        $profile['avatar_url'] = $avatarUrl;
        $profile['avatar'] = $avatarUrl;

        return $profile;
    }

    public function updateLanguagePreference(string $f_stafID, string $lang): bool
    {
        $current = $this->fetchOne(
            "SELECT f_lang FROM tbl_m_user WHERE f_stafID = :sid LIMIT 1",
            [':sid' => $f_stafID]
        );
        if (($current['f_lang'] ?? null) === trim($lang)) {
            return true;
        }

        $sql = "UPDATE tbl_m_user SET f_lang = :lang WHERE f_stafID = :sid LIMIT 1";
        return $this->execute($sql, [
            ':lang' => trim($lang),
            ':sid' => $f_stafID,
        ]) > 0;
    }

    public function updateLanguagePreferenceByLoginID(string $loginID, string $lang): bool
    {
        $loginID = trim($loginID);
        if ($loginID === '') {
            return false;
        }

        $current = $this->fetchOne(
            "SELECT f_lang FROM tbl_m_user WHERE TRIM(f_loginID) = :loginID LIMIT 1",
            [':loginID' => $loginID]
        );
        if (($current['f_lang'] ?? null) === trim($lang)) {
            return true;
        }

        $sql = "UPDATE tbl_m_user SET f_lang = :lang WHERE TRIM(f_loginID) = :loginID LIMIT 1";
        return $this->execute($sql, [
            ':lang' => trim($lang),
            ':loginID' => $loginID,
        ]) > 0;
    }

    /** ✅ Jana URL avatar staf berdasarkan f_nopekerja (numeric) */
    public function getAvatarUrl(?string $f_nopekerja): string
    {
        return $this->getStaffAvatarUrl($f_nopekerja);
    }

    public function getStaffAvatarUrl(?string $f_nopekerja): string
    {
        $identifier = trim((string)$f_nopekerja);
        return $identifier !== ''
            ? "https://esmartcard.upnm.edu.my/img/staf/{$identifier}.jpg"
            : base_url('assets/images/no-image.jpg');
    }

    public function getStudentAvatarUrl(?string $matrik): string
    {
        $identifier = trim((string)$matrik);
        return $identifier !== ''
            ? "https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/{$identifier}.jpg"
            : base_url('assets/images/no-image.jpg');
    }

    /**
     * Resolve avatar URL by user category/profile shape.
     *
     * @param array<string,mixed> $profile
     */
    public function resolveAvatarUrl(array $profile): string
    {
        $category = strtoupper(trim((string)($profile['f_categoryUser'] ?? $profile['categoryUser'] ?? '')));

        if ($category === 'PELAJAR') {
            $matrikCandidates = [
                $profile['matrik'] ?? null,
                $profile['f_matrik'] ?? null,
                $profile['nomatrik'] ?? null,
                $profile['no_matrik'] ?? null,
                $profile['student_id'] ?? null,
                $profile['idpelajar'] ?? null,
                $profile['f_stafID'] ?? null,
            ];

            foreach ($matrikCandidates as $candidate) {
                $candidate = trim((string)$candidate);
                if ($candidate !== '') {
                    return $this->getStudentAvatarUrl($candidate);
                }
            }
        }

        $staffCandidates = [
            $profile['f_nopekerja'] ?? null,
            $profile['nopekerja'] ?? null,
            $profile['f_stafID'] ?? null,
        ];

        foreach ($staffCandidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '') {
                return $this->getStaffAvatarUrl($candidate);
            }
        }

        return base_url('assets/images/no-image.jpg');
    }
    

    /** ✅ Dapatkan semua pengguna dalam group tertentu (guna groupID sahaja) */
    public function getAllUsers(int $groupId = 0): array
    {
        if ($groupId <= 0) {
            return [];
        }

        $sql = "SELECT f_userID, f_stafID, f_nopekerja, f_nama, f_jawatan, f_groupID, f_groupKod, f_status
                FROM tbl_m_user
                WHERE f_groupID = :gid
                ORDER BY f_nama ASC, f_userID ASC";
        return $this->fetchAll($sql, [':gid' => $groupId]);
    }

    /** ✅ Label peranan user (guna f_groupKod) */
    public function getRoleLabel(?array $profile = null): string
    {
        $prof = $profile;
        if ($prof === null) {
            $fLoginID = trim((string)($_SESSION['f_loginID'] ?? ''));
            $prof = $fLoginID !== ''
                ? $this->getProfileByLoginID($fLoginID)
                : $this->getProfile();
        }
        return (string)($prof['f_groupKod'] ?? '');
    }

    /** (Opsyen) Update tema pengguna */
    public function updateTheme(string $f_stafID, array $theme): bool
    {
        $sql = "UPDATE tbl_m_user SET f_themeSetting = :t WHERE f_stafID = :sid LIMIT 1";
        return $this->execute($sql, [
            ':t'   => json_encode($theme, JSON_UNESCAPED_UNICODE),
            ':sid' => $f_stafID
        ]) > 0;
    }

    public function updateThemeByLoginID(string $loginID, array $theme): bool
    {
        $loginID = trim($loginID);
        if ($loginID === '') {
            return false;
        }

        $sql = "UPDATE tbl_m_user SET f_themeSetting = :t WHERE TRIM(f_loginID) = :loginID LIMIT 1";
        return $this->execute($sql, [
            ':t' => json_encode($theme, JSON_UNESCAPED_UNICODE),
            ':loginID' => $loginID,
        ]) > 0;
    }

    public function passwordHistoryTableExists(): bool
    {
        return $this->tableExists('tbl_auth_password_history');
    }

    public function addPasswordHistoryEntry(string $loginID, string $passwordHash, string $source = 'password_change'): bool
    {
        $loginID = trim($loginID);
        $passwordHash = trim($passwordHash);
        $source = trim($source);
        if ($loginID === '' || $passwordHash === '' || !$this->passwordHistoryTableExists()) {
            return false;
        }

        $fields = [
            'f_loginID',
            'f_password_hash',
            'f_source',
            'f_created_at',
        ];
        $placeholders = [
            ':loginID',
            ':passwordHash',
            ':source',
            'NOW()',
        ];
        $params = [
            ':loginID' => $loginID,
            ':passwordHash' => $passwordHash,
            ':source' => $source !== '' ? $source : 'password_change',
        ];

        if ($this->tableHasColumn('tbl_auth_password_history', 'f_insertdt')) {
            $fields[] = 'f_insertdt';
            $placeholders[] = 'NOW()';
        }
        if ($this->tableHasColumn('tbl_auth_password_history', 'f_updatedt')) {
            $fields[] = 'f_updatedt';
            $placeholders[] = 'NOW()';
        }

        $sql = "INSERT INTO tbl_auth_password_history (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        return $this->execute($sql, $params) > 0;
    }

    public function isPasswordReusedInHistory(string $loginID, string $plainPassword, int $limit = 5): bool
    {
        $loginID = trim($loginID);
        if ($loginID === '' || trim($plainPassword) === '' || !$this->passwordHistoryTableExists()) {
            return false;
        }

        $sql = "SELECT f_password_hash
                FROM tbl_auth_password_history
                WHERE TRIM(f_loginID) = :loginID
                ORDER BY id DESC
                LIMIT " . max(1, (int)$limit);
        $rows = $this->fetchAll($sql, [':loginID' => $loginID]);
        foreach ($rows as $row) {
            $hash = trim((string)($row['f_password_hash'] ?? ''));
            if ($hash !== '' && password_verify($plainPassword, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function updateManualPasswordByLoginID(
        string $loginID,
        string $passwordHash,
        ?string $expiresAt = null,
        ?int $expiresInDays = null,
        string $historySource = 'password_change'
    ): bool
    {
        $loginID = trim($loginID);
        if ($loginID === '' || trim($passwordHash) === '') {
            return false;
        }

        $currentUser = $this->findByLoginID($loginID);
        $currentPasswordHash = trim((string)($currentUser['f_password'] ?? ''));
        if ($currentPasswordHash !== '' && $this->passwordHistoryTableExists()) {
            $this->addPasswordHistoryEntry($loginID, $currentPasswordHash, $historySource);
        }

        $setParts = [
            'f_password = :password',
        ];
        $params = [
            ':loginID' => $loginID,
            ':password' => $passwordHash,
        ];

        if ($this->tableHasColumn('tbl_m_user', 'f_must_change_password')) {
            $setParts[] = 'f_must_change_password = 0';
        }
        if ($this->tableHasColumn('tbl_m_user', 'f_password_changed_at')) {
            $setParts[] = 'f_password_changed_at = NOW()';
        }
        if ($this->tableHasColumn('tbl_m_user', 'f_password_expires_at')) {
            if ($expiresInDays !== null && $expiresInDays > 0) {
                $setParts[] = 'f_password_expires_at = DATE_ADD(NOW(), INTERVAL ' . (int)$expiresInDays . ' DAY)';
            } elseif ($expiresAt !== null && trim($expiresAt) !== '') {
                $setParts[] = 'f_password_expires_at = :expiresAt';
                $params[':expiresAt'] = $expiresAt;
            } else {
                $setParts[] = 'f_password_expires_at = NULL';
            }
        }
        if ($this->tableHasColumn('tbl_m_user', 'f_verified_at')) {
            $setParts[] = 'f_verified_at = COALESCE(f_verified_at, NOW())';
        }
        if ($this->tableHasColumn('tbl_m_user', 'f_updatedt')) {
            $setParts[] = 'f_updatedt = NOW()';
        }

        $sql = "UPDATE tbl_m_user
                SET " . implode(', ', $setParts) . "
                WHERE TRIM(f_loginID) = :loginID
                  AND COALESCE(f_statusID, 0) != 9
                LIMIT 1";

        return $this->execute($sql, $params) > 0;
    }

    public function passwordResetTableExists(): bool
    {
        return $this->tableExists('tbl_auth_password_reset');
    }

    public function loginLockoutTableExists(): bool
    {
        return $this->tableExists('tbl_auth_login_lockout');
    }

    public function loginThrottleTableExists(): bool
    {
        return $this->tableExists('tbl_auth_login_throttle');
    }

    public function getLoginLockoutState(string $loginID, int $maxAttempts = 3): array
    {
        $loginID = function_exists('auth_normalize_login_id')
            ? auth_normalize_login_id($loginID)
            : trim($loginID);
        $maxAttempts = max(1, $maxAttempts);

        $defaultState = [
            'login_id' => $loginID,
            'failed_attempts' => 0,
            'attempts_remaining' => $maxAttempts,
            'locked_until' => null,
            'is_locked' => false,
        ];

        if ($loginID === '' || !$this->loginLockoutTableExists()) {
            return $defaultState;
        }

        $row = $this->fetchOne(
            "SELECT f_loginID, f_failed_attempts, f_locked_until
             FROM tbl_auth_login_lockout
             WHERE TRIM(f_loginID) = :loginID
             LIMIT 1",
            [':loginID' => $loginID]
        );
        if (!$row) {
            return $defaultState;
        }

        $lockedUntil = trim((string)($row['f_locked_until'] ?? ''));
        $lockedUntilTs = $lockedUntil !== '' ? strtotime($lockedUntil) : false;
        $failedAttempts = max(0, (int)($row['f_failed_attempts'] ?? 0));

        return [
            'login_id' => trim((string)($row['f_loginID'] ?? $loginID)),
            'failed_attempts' => $failedAttempts,
            'attempts_remaining' => max(0, $maxAttempts - $failedAttempts),
            'locked_until' => $lockedUntil !== '' ? $lockedUntil : null,
            'is_locked' => $lockedUntilTs !== false && $lockedUntilTs > time(),
        ];
    }

    public function clearLoginLockout(string $loginID, ?string $ip = null, ?string $userAgent = null): bool
    {
        $loginID = function_exists('auth_normalize_login_id')
            ? auth_normalize_login_id($loginID)
            : trim($loginID);
        if ($loginID === '' || !$this->loginLockoutTableExists()) {
            return false;
        }

        $setParts = [
            'f_failed_attempts = 0',
            'f_locked_until = NULL',
            'f_last_success_at = NOW()',
        ];
        $params = [
            ':loginID' => $loginID,
        ];

        if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_last_ip')) {
            $setParts[] = 'f_last_ip = :ip';
            $params[':ip'] = $ip !== null && trim($ip) !== '' ? trim($ip) : null;
        }
        if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_user_agent')) {
            $setParts[] = 'f_user_agent = :userAgent';
            $params[':userAgent'] = $userAgent !== null && trim($userAgent) !== '' ? trim($userAgent) : null;
        }
        if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_updatedt')) {
            $setParts[] = 'f_updatedt = NOW()';
        }

        $sql = "UPDATE tbl_auth_login_lockout
                SET " . implode(', ', $setParts) . "
                WHERE TRIM(f_loginID) = :loginID";

        return $this->execute($sql, $params) > 0;
    }

    public function recordFailedLoginAttempt(
        string $loginID,
        int $maxAttempts = 3,
        int $lockSeconds = 60,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $loginID = function_exists('auth_normalize_login_id')
            ? auth_normalize_login_id($loginID)
            : trim($loginID);
        $maxAttempts = max(1, $maxAttempts);
        $lockSeconds = max(30, $lockSeconds);

        $defaultResult = [
            'failed_attempts' => 0,
            'attempts_remaining' => $maxAttempts,
            'is_locked' => false,
            'locked_until' => null,
        ];

        if ($loginID === '' || !$this->loginLockoutTableExists()) {
            return $defaultResult;
        }

        return $this->transaction(function () use ($loginID, $maxAttempts, $lockSeconds, $ip, $userAgent): array {
            $selectSql = "SELECT id, f_failed_attempts, f_locked_until
                          FROM tbl_auth_login_lockout
                          WHERE TRIM(f_loginID) = :loginID
                          LIMIT 1
                          FOR UPDATE";
            $row = $this->fetchOne($selectSql, [':loginID' => $loginID]);

            $failedAttempts = 0;
            $lockedUntil = null;
            if ($row) {
                $failedAttempts = max(0, (int)($row['f_failed_attempts'] ?? 0));
                $lockedUntilValue = trim((string)($row['f_locked_until'] ?? ''));
                $lockedUntilTs = $lockedUntilValue !== '' ? strtotime($lockedUntilValue) : false;
                if ($lockedUntilTs !== false && $lockedUntilTs <= time()) {
                    $failedAttempts = 0;
                } elseif ($lockedUntilValue !== '') {
                    $lockedUntil = $lockedUntilValue;
                }
            }

            $failedAttempts++;
            $shouldLock = $failedAttempts >= $maxAttempts;

            if ($shouldLock) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $lockSeconds);
            } else {
                $lockedUntil = null;
            }

            $fields = [
                'f_loginID',
                'f_failed_attempts',
                'f_locked_until',
                'f_last_failed_at',
            ];
            $placeholders = [
                ':loginID',
                ':failedAttempts',
                ':lockedUntil',
                'NOW()',
            ];
            $params = [
                ':loginID' => $loginID,
                ':failedAttempts' => $failedAttempts,
                ':lockedUntil' => $lockedUntil,
            ];

            if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_last_ip')) {
                $fields[] = 'f_last_ip';
                $placeholders[] = ':ip';
                $params[':ip'] = $ip !== null && trim($ip) !== '' ? trim($ip) : null;
            }
            if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_user_agent')) {
                $fields[] = 'f_user_agent';
                $placeholders[] = ':userAgent';
                $params[':userAgent'] = $userAgent !== null && trim($userAgent) !== '' ? trim($userAgent) : null;
            }
            if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_insertdt')) {
                $fields[] = 'f_insertdt';
                $placeholders[] = 'NOW()';
            }
            if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_updatedt')) {
                $fields[] = 'f_updatedt';
                $placeholders[] = 'NOW()';
            }

            $updateParts = [
                'f_failed_attempts = VALUES(f_failed_attempts)',
                'f_locked_until = VALUES(f_locked_until)',
                'f_last_failed_at = NOW()',
            ];
            if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_last_ip')) {
                $updateParts[] = 'f_last_ip = VALUES(f_last_ip)';
            }
            if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_user_agent')) {
                $updateParts[] = 'f_user_agent = VALUES(f_user_agent)';
            }
            if ($this->tableHasColumn('tbl_auth_login_lockout', 'f_updatedt')) {
                $updateParts[] = 'f_updatedt = NOW()';
            }

            $sql = "INSERT INTO tbl_auth_login_lockout (" . implode(', ', $fields) . ")
                    VALUES (" . implode(', ', $placeholders) . ")
                    ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
            $this->execute($sql, $params);

            return [
                'failed_attempts' => $failedAttempts,
                'attempts_remaining' => max(0, $maxAttempts - $failedAttempts),
                'is_locked' => $shouldLock,
                'locked_until' => $lockedUntil,
            ];
        });
    }

    public function getLoginThrottleState(string $scopeType, string $scopeKey, int $maxAttempts): array
    {
        $scopeType = strtoupper(trim($scopeType));
        $scopeKey = trim($scopeKey);
        if ($scopeType === 'LOGIN_IP') {
            $parts = explode('|', $scopeKey, 2);
            $scopeKey = (function_exists('auth_normalize_login_id') ? auth_normalize_login_id($parts[0] ?? '') : trim((string)($parts[0] ?? '')))
                . (isset($parts[1]) && trim((string)$parts[1]) !== '' ? '|' . trim((string)$parts[1]) : '');
        }
        $maxAttempts = max(1, $maxAttempts);

        $defaultState = [
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'failed_attempts' => 0,
            'attempts_remaining' => $maxAttempts,
            'locked_until' => null,
            'is_locked' => false,
        ];

        if ($scopeType === '' || $scopeKey === '' || !$this->loginThrottleTableExists()) {
            return $defaultState;
        }

        $row = $this->fetchOne(
            "SELECT f_scope_type, f_scope_key, f_failed_attempts, f_locked_until
             FROM tbl_auth_login_throttle
             WHERE f_scope_type = :scopeType
               AND f_scope_key = :scopeKey
             LIMIT 1",
            [
                ':scopeType' => $scopeType,
                ':scopeKey' => $scopeKey,
            ]
        );
        if (!$row) {
            return $defaultState;
        }

        $lockedUntil = trim((string)($row['f_locked_until'] ?? ''));
        $lockedUntilTs = $lockedUntil !== '' ? strtotime($lockedUntil) : false;
        $failedAttempts = max(0, (int)($row['f_failed_attempts'] ?? 0));

        return [
            'scope_type' => trim((string)($row['f_scope_type'] ?? $scopeType)),
            'scope_key' => trim((string)($row['f_scope_key'] ?? $scopeKey)),
            'failed_attempts' => $failedAttempts,
            'attempts_remaining' => max(0, $maxAttempts - $failedAttempts),
            'locked_until' => $lockedUntil !== '' ? $lockedUntil : null,
            'is_locked' => $lockedUntilTs !== false && $lockedUntilTs > time(),
        ];
    }

    public function clearLoginThrottle(string $scopeType, string $scopeKey, ?string $ip = null, ?string $userAgent = null): bool
    {
        $scopeType = strtoupper(trim($scopeType));
        $scopeKey = trim($scopeKey);
        if ($scopeType === 'LOGIN_IP') {
            $parts = explode('|', $scopeKey, 2);
            $scopeKey = (function_exists('auth_normalize_login_id') ? auth_normalize_login_id($parts[0] ?? '') : trim((string)($parts[0] ?? '')))
                . (isset($parts[1]) && trim((string)$parts[1]) !== '' ? '|' . trim((string)$parts[1]) : '');
        }
        if ($scopeType === '' || $scopeKey === '' || !$this->loginThrottleTableExists()) {
            return false;
        }

        $setParts = [
            'f_failed_attempts = 0',
            'f_locked_until = NULL',
            'f_last_success_at = NOW()',
        ];
        $params = [
            ':scopeType' => $scopeType,
            ':scopeKey' => $scopeKey,
        ];

        if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_last_ip')) {
            $setParts[] = 'f_last_ip = :ip';
            $params[':ip'] = $ip !== null && trim($ip) !== '' ? trim($ip) : null;
        }
        if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_user_agent')) {
            $setParts[] = 'f_user_agent = :userAgent';
            $params[':userAgent'] = $userAgent !== null && trim($userAgent) !== '' ? trim($userAgent) : null;
        }
        if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_updatedt')) {
            $setParts[] = 'f_updatedt = NOW()';
        }

        $sql = "UPDATE tbl_auth_login_throttle
                SET " . implode(', ', $setParts) . "
                WHERE f_scope_type = :scopeType
                  AND f_scope_key = :scopeKey";

        return $this->execute($sql, $params) > 0;
    }

    public function recordFailedLoginThrottle(
        string $scopeType,
        string $scopeKey,
        int $maxAttempts,
        int $lockSeconds,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $scopeType = strtoupper(trim($scopeType));
        $scopeKey = trim($scopeKey);
        if ($scopeType === 'LOGIN_IP') {
            $parts = explode('|', $scopeKey, 2);
            $scopeKey = (function_exists('auth_normalize_login_id') ? auth_normalize_login_id($parts[0] ?? '') : trim((string)($parts[0] ?? '')))
                . (isset($parts[1]) && trim((string)$parts[1]) !== '' ? '|' . trim((string)$parts[1]) : '');
        }
        $maxAttempts = max(1, $maxAttempts);
        $lockSeconds = max(30, $lockSeconds);

        $defaultResult = [
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'failed_attempts' => 0,
            'attempts_remaining' => $maxAttempts,
            'is_locked' => false,
            'locked_until' => null,
        ];

        if ($scopeType === '' || $scopeKey === '' || !$this->loginThrottleTableExists()) {
            return $defaultResult;
        }

        return $this->transaction(function () use ($scopeType, $scopeKey, $maxAttempts, $lockSeconds, $ip, $userAgent): array {
            $selectSql = "SELECT id, f_failed_attempts, f_locked_until
                          FROM tbl_auth_login_throttle
                          WHERE f_scope_type = :scopeType
                            AND f_scope_key = :scopeKey
                          LIMIT 1
                          FOR UPDATE";
            $row = $this->fetchOne($selectSql, [
                ':scopeType' => $scopeType,
                ':scopeKey' => $scopeKey,
            ]);

            $failedAttempts = 0;
            $lockedUntil = null;
            if ($row) {
                $failedAttempts = max(0, (int)($row['f_failed_attempts'] ?? 0));
                $lockedUntilValue = trim((string)($row['f_locked_until'] ?? ''));
                $lockedUntilTs = $lockedUntilValue !== '' ? strtotime($lockedUntilValue) : false;
                if ($lockedUntilTs !== false && $lockedUntilTs <= time()) {
                    $failedAttempts = 0;
                } elseif ($lockedUntilValue !== '') {
                    $lockedUntil = $lockedUntilValue;
                }
            }

            $failedAttempts++;
            $shouldLock = $failedAttempts >= $maxAttempts;
            $lockedUntil = $shouldLock ? date('Y-m-d H:i:s', time() + $lockSeconds) : null;

            $fields = [
                'f_scope_type',
                'f_scope_key',
                'f_failed_attempts',
                'f_locked_until',
                'f_last_failed_at',
            ];
            $placeholders = [
                ':scopeType',
                ':scopeKey',
                ':failedAttempts',
                ':lockedUntil',
                'NOW()',
            ];
            $params = [
                ':scopeType' => $scopeType,
                ':scopeKey' => $scopeKey,
                ':failedAttempts' => $failedAttempts,
                ':lockedUntil' => $lockedUntil,
            ];

            if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_last_ip')) {
                $fields[] = 'f_last_ip';
                $placeholders[] = ':ip';
                $params[':ip'] = $ip !== null && trim($ip) !== '' ? trim($ip) : null;
            }
            if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_user_agent')) {
                $fields[] = 'f_user_agent';
                $placeholders[] = ':userAgent';
                $params[':userAgent'] = $userAgent !== null && trim($userAgent) !== '' ? trim($userAgent) : null;
            }
            if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_insertdt')) {
                $fields[] = 'f_insertdt';
                $placeholders[] = 'NOW()';
            }
            if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_updatedt')) {
                $fields[] = 'f_updatedt';
                $placeholders[] = 'NOW()';
            }

            $updateParts = [
                'f_failed_attempts = VALUES(f_failed_attempts)',
                'f_locked_until = VALUES(f_locked_until)',
                'f_last_failed_at = NOW()',
            ];
            if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_last_ip')) {
                $updateParts[] = 'f_last_ip = VALUES(f_last_ip)';
            }
            if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_user_agent')) {
                $updateParts[] = 'f_user_agent = VALUES(f_user_agent)';
            }
            if ($this->tableHasColumn('tbl_auth_login_throttle', 'f_updatedt')) {
                $updateParts[] = 'f_updatedt = NOW()';
            }

            $sql = "INSERT INTO tbl_auth_login_throttle (" . implode(', ', $fields) . ")
                    VALUES (" . implode(', ', $placeholders) . ")
                    ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
            $this->execute($sql, $params);

            return [
                'scope_type' => $scopeType,
                'scope_key' => $scopeKey,
                'failed_attempts' => $failedAttempts,
                'attempts_remaining' => max(0, $maxAttempts - $failedAttempts),
                'is_locked' => $shouldLock,
                'locked_until' => $lockedUntil,
            ];
        });
    }

    public function findPasswordResetCandidate(string $loginID): ?array
    {
        $resolution = $this->resolvePasswordResetCandidate($loginID);
        return is_array($resolution['candidate'] ?? null) ? $resolution['candidate'] : null;
    }

    /**
     * Resolve identifier awam secara deterministik tanpa memilih emel duplikat.
     *
     * @return array{candidate:?array,ambiguous:bool,match_type:string}
     */
    public function resolvePasswordResetCandidate(string $identifier): array
    {
        $loginID = trim($identifier);
        $emptyResult = ['candidate' => null, 'ambiguous' => false, 'match_type' => 'none'];
        $loginID = trim($loginID);
        if ($loginID === '') {
            return $emptyResult;
        }

        $sql = "SELECT
                    f_userID,
                    f_loginID,
                    f_email,
                    f_nama,
                    f_nickname,
                    f_password,
                    f_categoryUser,
                    f_flag,
                    f_statusID,
                    f_stafID,
                    f_nopekerja,
                    f_groupID,
                    f_groupKod,
                    CASE
                        WHEN TRIM(f_loginID) = :exactLogin THEN 1
                        WHEN TRIM(COALESCE(f_stafID, '')) = :exactStaff THEN 2
                        WHEN TRIM(COALESCE(f_nopekerja, '')) = :exactEmployee THEN 3
                        WHEN LOWER(TRIM(COALESCE(f_email, ''))) = LOWER(:exactEmail) THEN 4
                        ELSE 9
                    END AS reset_match_priority
                FROM tbl_m_user
                WHERE (
                    TRIM(f_loginID) = :whereLogin
                    OR TRIM(COALESCE(f_stafID, '')) = :whereStaff
                    OR TRIM(COALESCE(f_nopekerja, '')) = :whereEmployee
                    OR LOWER(TRIM(COALESCE(f_email, ''))) = LOWER(:whereEmail)
                )
                  AND COALESCE(f_statusID, 0) != 9
                ORDER BY reset_match_priority ASC, f_userID DESC
                LIMIT 3";

        $rows = $this->fetchAll($sql, [
            ':exactLogin' => $loginID,
            ':exactStaff' => $loginID,
            ':exactEmployee' => $loginID,
            ':exactEmail' => $loginID,
            ':whereLogin' => $loginID,
            ':whereStaff' => $loginID,
            ':whereEmployee' => $loginID,
            ':whereEmail' => $loginID,
        ]);
        if ($rows === []) {
            return $emptyResult;
        }

        $priority = (int)($rows[0]['reset_match_priority'] ?? 9);
        $samePriority = array_values(array_filter($rows, static fn(array $row): bool => (int)($row['reset_match_priority'] ?? 9) === $priority));
        if (count($samePriority) > 1) {
            return [
                'candidate' => null,
                'ambiguous' => true,
                'match_type' => $priority === 4 ? 'email' : 'identifier',
            ];
        }

        $candidate = $rows[0];
        unset($candidate['reset_match_priority']);
        $matchTypes = [1 => 'login_id', 2 => 'staff_id', 3 => 'employee_no', 4 => 'email'];
        return [
            'candidate' => $candidate,
            'ambiguous' => false,
            'match_type' => $matchTypes[$priority] ?? 'identifier',
        ];
    }

    public function invalidatePasswordResetTokensByLoginID(string $loginID, ?string $exceptTokenHash = null): void
    {
        $loginID = trim($loginID);
        if ($loginID === '' || !$this->passwordResetTableExists()) {
            return;
        }

        $setParts = [
            'f_used_at = COALESCE(f_used_at, NOW())',
        ];
        if ($this->tableHasColumn('tbl_auth_password_reset', 'f_updatedt')) {
            $setParts[] = 'f_updatedt = NOW()';
        }

        $sql = "UPDATE tbl_auth_password_reset
                SET " . implode(', ', $setParts) . "
                WHERE TRIM(f_loginID) = :loginID
                  AND f_used_at IS NULL";
        $params = [':loginID' => $loginID];
        if ($exceptTokenHash !== null && trim($exceptTokenHash) !== '') {
            $sql .= ' AND f_token_hash != :exceptTokenHash';
            $params[':exceptTokenHash'] = trim($exceptTokenHash);
        }
        $this->execute($sql, $params);
    }

    public function createPasswordResetToken(
        string $loginID,
        string $email,
        string $tokenHash,
        ?string $expiresAt,
        ?string $requestedIp = null,
        ?string $userAgent = null,
        ?int $expiresInMinutes = null,
        bool $invalidateExisting = true
    ): bool {
        $loginID = trim($loginID);
        $email = trim($email);
        $tokenHash = trim($tokenHash);

        if (
            $loginID === ''
            || $email === ''
            || $tokenHash === ''
            || ($expiresAt === null && ($expiresInMinutes === null || $expiresInMinutes <= 0))
            || !$this->passwordResetTableExists()
        ) {
            return false;
        }

        if ($invalidateExisting) {
            $this->invalidatePasswordResetTokensByLoginID($loginID);
        }

        $fields = [
            'f_loginID',
            'f_email',
            'f_token_hash',
            'f_requested_at',
            'f_expires_at',
        ];
        $placeholders = [
            ':loginID',
            ':email',
            ':tokenHash',
            'NOW()',
        ];
        $params = [
            ':loginID' => $loginID,
            ':email' => $email,
            ':tokenHash' => $tokenHash,
        ];

        if ($expiresInMinutes !== null && $expiresInMinutes > 0) {
            $placeholders[] = 'DATE_ADD(NOW(), INTERVAL ' . (int)$expiresInMinutes . ' MINUTE)';
        } else {
            $placeholders[] = ':expiresAt';
            $params[':expiresAt'] = (string)$expiresAt;
        }

        if ($this->tableHasColumn('tbl_auth_password_reset', 'f_requested_ip')) {
            $fields[] = 'f_requested_ip';
            $placeholders[] = ':requestedIp';
            $params[':requestedIp'] = $requestedIp !== null && trim($requestedIp) !== '' ? trim($requestedIp) : null;
        }
        if ($this->tableHasColumn('tbl_auth_password_reset', 'f_user_agent')) {
            $fields[] = 'f_user_agent';
            $placeholders[] = ':userAgent';
            $params[':userAgent'] = $userAgent !== null && trim($userAgent) !== '' ? trim($userAgent) : null;
        }
        if ($this->tableHasColumn('tbl_auth_password_reset', 'f_insertdt')) {
            $fields[] = 'f_insertdt';
            $placeholders[] = 'NOW()';
        }
        if ($this->tableHasColumn('tbl_auth_password_reset', 'f_updatedt')) {
            $fields[] = 'f_updatedt';
            $placeholders[] = 'NOW()';
        }

        $sql = "INSERT INTO tbl_auth_password_reset (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        return $this->execute($sql, $params) > 0;
    }

    public function findActivePasswordResetToken(string $tokenHash): ?array
    {
        $tokenHash = trim($tokenHash);
        if ($tokenHash === '' || !$this->passwordResetTableExists()) {
            return null;
        }

        $sql = "SELECT
                    pr.id,
                    pr.f_loginID,
                    pr.f_email,
                    pr.f_requested_at,
                    pr.f_expires_at,
                    pr.f_used_at,
                    u.f_userID,
                    u.f_password,
                    u.f_nama,
                    u.f_nickname,
                    u.f_flag,
                    u.f_categoryUser,
                    u.f_statusID,
                    u.f_stafID,
                    u.f_nopekerja,
                    u.f_groupID,
                    u.f_groupKod
                FROM tbl_auth_password_reset pr
                INNER JOIN tbl_m_user u
                    ON TRIM(u.f_loginID) = TRIM(pr.f_loginID)
                WHERE pr.f_token_hash = :tokenHash
                  AND pr.f_used_at IS NULL
                  AND pr.f_expires_at >= NOW()
                  AND COALESCE(u.f_statusID, 0) != 9
                ORDER BY pr.id DESC
                LIMIT 1";

        return $this->fetchOne($sql, [':tokenHash' => $tokenHash]);
    }

    public function markPasswordResetTokenUsed(int $tokenId, ?string $consumedIp = null): bool
    {
        if ($tokenId <= 0 || !$this->passwordResetTableExists()) {
            return false;
        }

        $setParts = [
            'f_used_at = NOW()',
        ];
        $params = [
            ':tokenId' => $tokenId,
        ];

        if ($this->tableHasColumn('tbl_auth_password_reset', 'f_consumed_ip')) {
            $setParts[] = 'f_consumed_ip = :consumedIp';
            $params[':consumedIp'] = $consumedIp !== null && trim($consumedIp) !== '' ? trim($consumedIp) : null;
        }
        if ($this->tableHasColumn('tbl_auth_password_reset', 'f_updatedt')) {
            $setParts[] = 'f_updatedt = NOW()';
        }

        $sql = "UPDATE tbl_auth_password_reset
                SET " . implode(', ', $setParts) . "
                WHERE id = :tokenId
                  AND f_used_at IS NULL";

        return $this->execute($sql, $params) > 0;
    }

    public function invalidatePasswordResetTokenByHash(string $tokenHash): bool
    {
        $tokenHash = trim($tokenHash);
        if ($tokenHash === '' || !$this->passwordResetTableExists()) {
            return false;
        }

        return $this->execute(
            "UPDATE tbl_auth_password_reset
             SET f_used_at = COALESCE(f_used_at, NOW())
             WHERE f_token_hash = :tokenHash
               AND f_used_at IS NULL",
            [':tokenHash' => $tokenHash]
        ) > 0;
    }

    public function completePasswordReset(
        int $tokenId,
        string $loginID,
        string $passwordHash,
        int $expiresInDays,
        ?string $consumedIp = null
    ): bool {
        if ($tokenId <= 0 || trim($loginID) === '' || trim($passwordHash) === '') {
            return false;
        }

        try {
            return (bool)$this->transaction(function () use ($tokenId, $loginID, $passwordHash, $expiresInDays, $consumedIp): bool {
                $updated = $this->updateManualPasswordByLoginID(
                    $loginID,
                    $passwordHash,
                    null,
                    $expiresInDays,
                    'reset_password'
                );
                if (!$updated || !$this->markPasswordResetTokenUsed($tokenId, $consumedIp)) {
                    throw new RuntimeException('Password reset transaction could not be completed.');
                }
                $this->invalidatePasswordResetTokensByLoginID($loginID);
                return true;
            });
        } catch (Throwable $e) {
            error_log('[User] completePasswordReset failed: ' . $e->getMessage());
            return false;
        }
    }
}
