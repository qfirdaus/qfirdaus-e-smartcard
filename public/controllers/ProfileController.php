<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// controllers/ProfileController.php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Config.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';

class ProfileController
{
    public string $lang = 'ms';
    public array  $profile = [];

    private PDO  $pdoMysql;
    private User $userModel;
    private Config $configModel;

    public function __construct(?PDO $pdoMysql = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        $this->lang     = $_SESSION['lang'] ?? 'ms';
        $this->pdoMysql = $pdoMysql ?: Database::pdoMysql();
        $this->pdoMysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->userModel = new User($this->pdoMysql);
        $this->configModel = new Config($this->pdoMysql);
        $this->profile   = [];
    }

    public function getLang(): string { return $this->lang; }

    private function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $stmt = $this->pdoMysql->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $stmt->execute([':table' => $table, ':column' => $column]);
            $cache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $cache[$cacheKey] = false;
        }

        return $cache[$cacheKey];
    }

    public function handleRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = trim((string)($_POST['profile_action'] ?? ''));
        if ($action !== 'update_language') {
            return;
        }

        $csrfToken = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
            set_alert([
                'title' => 'profile_alert_security_title',
                'text' => 'profile_alert_security_text',
                'icon' => 'error',
                'confirm' => true,
            ]);
            header('Location: profile.php?tab=profil-pengguna');
            exit;
        }

        $loginID = trim((string)($_SESSION['f_loginID'] ?? ''));
        $stafID = trim((string)($_SESSION['f_stafID'] ?? ''));
        if ($loginID === '' && $stafID === '') {
            set_alert([
                'title' => 'profile_alert_access_title',
                'text' => 'profile_alert_access_text',
                'icon' => 'error',
                'confirm' => true,
            ]);
            header('Location: profile.php?tab=profil-pengguna');
            exit;
        }

        $lang = trim((string)($_POST['f_lang'] ?? ''));
        $activeLanguages = $this->getActiveLanguages();
        if ($lang === '' || !in_array($lang, $activeLanguages, true)) {
            set_alert([
                'title' => 'profile_alert_validation_title',
                'text' => 'profile_alert_validation_text',
                'icon' => 'error',
                'confirm' => true,
            ]);
            header('Location: profile.php?tab=profil-pengguna');
            exit;
        }

        try {
            $updated = $loginID !== ''
                ? $this->userModel->updateLanguagePreferenceByLoginID($loginID, $lang)
                : $this->userModel->updateLanguagePreference($stafID, $lang);
            if (!$updated) {
                set_alert([
                    'title' => 'profile_alert_save_title',
                    'text' => 'profile_alert_save_text',
                    'icon' => 'error',
                    'confirm' => true,
                ]);
                header('Location: profile.php?tab=profil-pengguna');
                exit;
            }

            $_SESSION['lang'] = $lang;
            $this->lang = $lang;

            set_alert([
                'title' => 'profile_lang_title',
                'text' => 'profile_lang_save_success',
                'icon' => 'success',
                'confirm' => true,
                'confirmText' => 'config_js_btn_tutup',
            ]);
        } catch (\Throwable $e) {
            error_log('[ProfileController] Failed to update language preference: ' . $e->getMessage());
            set_alert([
                'title' => 'profile_alert_system_title',
                'text' => 'profile_alert_system_text',
                'icon' => 'error',
                'confirm' => true,
            ]);
        }

        header('Location: profile.php?tab=profil-pengguna');
        exit;
    }

    public function getActiveLanguages(): array
    {
        try {
            return $this->configModel->getBahasaAktif();
        } catch (\Throwable $e) {
            error_log('[ProfileController] Failed to read active languages: ' . $e->getMessage());
            return ['ms', 'en'];
        }
    }

    /**
     * Profil ringkas untuk view
     * - Cari guna f_loginID daripada session, dengan fallback f_stafID
     * - Tiada JOIN / tiada filter status
     */
    public function getCurrentUserProfile(): array
    {
        $loginID = trim((string)($_SESSION['f_loginID'] ?? ''));
        $stafID = trim((string)($_SESSION['f_stafID'] ?? ''));
        if ($loginID === '' && $stafID === '') {
            return $this->profile = $this->emptyProfile($this->userModel->getAvatarUrl(null));
        }

        $identityColumn = $loginID !== '' ? 'TRIM(u.f_loginID)' : 'u.f_stafID';
        $identity = $loginID !== '' ? $loginID : $stafID;

        $sql = "
            SELECT
                u.f_userID,
                u.f_loginID,
                u.f_stafID,
                u.f_nopekerja,
                u.f_categoryUser,
                u.f_nama,
                u.f_nickname,
                u.f_email,
                u.f_handphone,
                u.f_lang,
                u.f_jawatan,
                u.f_kumpjawatan,
                u.f_namajabatan
            FROM tbl_m_user u
            WHERE {$identityColumn} = :id
            LIMIT 1
        ";

        $stmt = $this->pdoMysql->prepare($sql);
        $stmt->execute([':id' => $identity]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return $this->profile = $this->emptyProfile($this->userModel->getAvatarUrl(null));
        }

        $avatar  = $this->userModel->resolveAvatarUrl($row);
        $nama    = trim((string)($row['f_nama'] ?? ''));
        $nick    = trim((string)($row['f_nickname'] ?? ''));
        $display = $nama !== '' ? $nama : ($nick !== '' ? $nick : 'Pengguna');

        return $this->profile = [
            'stafID'     => (string)($row['f_stafID'] ?? ''),
            'loginID'    => (string)($row['f_loginID'] ?? ''),
            'nopekerja'  => (string)($row['f_nopekerja'] ?? ''), // still dipapar jika view perlukan
            'categoryUser' => (string)($row['f_categoryUser'] ?? ''),
            'nama_penuh' => (string)$display,
            'nickname'   => (string)($row['f_nickname'] ?? ''),
            'jawatan'    => (string)($row['f_jawatan'] ?? ''),
            'gred'       => (string)($row['f_kumpjawatan'] ?? ''),
            'jabatan'    => (string)($row['f_namajabatan'] ?? ''),
            'emel'       => (string)($row['f_email'] ?? ''),
            'lang'       => (string)($row['f_lang'] ?? ''),
            'avatar_url' => (string)($avatar ?: base_url('assets/images/no-image.jpg')),
        ];
    }

    private function emptyProfile(?string $avatar): array
    {
        return [
            'stafID'     => '',
            'loginID'    => '',
            'nopekerja'  => '',
            'categoryUser' => '',
            'nama_penuh' => 'Pengguna',
            'nickname'   => '',
            'jawatan'    => '',
            'gred'       => '',
            'jabatan'    => '',
            'emel'       => '',
            'lang'       => (string)($_SESSION['lang'] ?? SystemConfigConstants::DEFAULT_LANGUAGE),
            'avatar_url' => (string)($avatar ?: base_url('assets/images/no-image.jpg')),
        ];
    }

    /**
     * Get login activity from audit_session table
     * @param int $limit Maximum number of records to return
     * @return array
     */
    public function getLoginActivity(int $limit = 30): array
    {
        $stafID = trim((string)($_SESSION['f_stafID'] ?? ''));
        if ($stafID === '') {
            return [];
        }

        // Get login_id + legacy identifiers for matching
        $userProfile = $this->getCurrentUserProfile();
        $loginId = trim((string)($userProfile['loginID'] ?? $_SESSION['f_loginID'] ?? ''));
        $userNopekerja = $userProfile['nopekerja'] ?? '';
        $userId = null;
        
        // Query directly to get f_userID
        $sqlUser = "SELECT f_userID FROM tbl_m_user WHERE f_stafID = :stafID LIMIT 1";
        $stmtUser = $this->pdoMysql->prepare($sqlUser);
        $stmtUser->execute([':stafID' => $stafID]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($userRow && !empty($userRow['f_userID'])) {
            $userId = (int)$userRow['f_userID'];
        }

        try {
            // Query audit_session - match by user_nopekerja OR user_id
            $sql = "
                SELECT 
                    id,
                    session_id,
                    user_id,
                    user_nopekerja,
                    started_at,
                    ended_at,
                    ip_text as ip_address,
                    user_agent,
                    device_fingerprint,
                    TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW(6))) as duration_seconds
                FROM audit_session
                WHERE (" . ($loginId !== '' && $this->tableHasColumn('audit_session', 'login_id') ? "login_id = :login_id OR " : "") . "user_nopekerja = :nopek" . ($userId ? " OR user_id = :uid" : "") . ")
                ORDER BY started_at DESC
                LIMIT :limit
            ";

            $stmt = $this->pdoMysql->prepare($sql);
            if ($loginId !== '' && $this->tableHasColumn('audit_session', 'login_id')) {
                $stmt->bindValue(':login_id', $loginId, PDO::PARAM_STR);
            }
            $stmt->bindValue(':nopek', $userNopekerja ?: '', PDO::PARAM_STR);
            if ($userId) {
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'session_id' => (string)($row['session_id'] ?? ''),
                    'started_at' => (string)($row['started_at'] ?? ''),
                    'ended_at' => $row['ended_at'] ? (string)$row['ended_at'] : null,
                    'ip_address' => (string)($row['ip_address'] ?? ''),
                    'user_agent' => (string)($row['user_agent'] ?? ''),
                    'device_fingerprint' => (string)($row['device_fingerprint'] ?? ''),
                    'duration_seconds' => $row['ended_at'] ? (int)($row['duration_seconds'] ?? 0) : null,
                    'is_active' => $row['ended_at'] === null,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            error_log("[ProfileController] Failed to fetch login activity: " . $e->getMessage());
            return [];
        }
    }

    public function hasActiveSession(): bool
    {
        $stafID = trim((string)($_SESSION['f_stafID'] ?? ''));
        if ($stafID === '') {
            return false;
        }

        $userProfile = $this->getCurrentUserProfile();
        $loginId = trim((string)($userProfile['loginID'] ?? $_SESSION['f_loginID'] ?? ''));
        $userNopekerja = $userProfile['nopekerja'] ?? '';
        $userId = null;

        $sqlUser = "SELECT f_userID FROM tbl_m_user WHERE f_stafID = :stafID LIMIT 1";
        $stmtUser = $this->pdoMysql->prepare($sqlUser);
        $stmtUser->execute([':stafID' => $stafID]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($userRow && !empty($userRow['f_userID'])) {
            $userId = (int)$userRow['f_userID'];
        }

        try {
            $sql = "
                SELECT 1
                FROM audit_session
                WHERE ended_at IS NULL
                  AND (" . ($loginId !== '' && $this->tableHasColumn('audit_session', 'login_id') ? "login_id = :login_id OR " : "") . "user_nopekerja = :nopek" . ($userId ? " OR user_id = :uid" : "") . ")
                LIMIT 1
            ";

            $stmt = $this->pdoMysql->prepare($sql);
            if ($loginId !== '' && $this->tableHasColumn('audit_session', 'login_id')) {
                $stmt->bindValue(':login_id', $loginId, PDO::PARAM_STR);
            }
            $stmt->bindValue(':nopek', $userNopekerja ?: '', PDO::PARAM_STR);
            if ($userId) {
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            }
            $stmt->execute();

            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log("[ProfileController] Failed to determine active session: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit events from audit_event table
     * @param int $limit Maximum number of records to return
     * @return array
     */
    public function getAuditEvents(int $limit = 30, bool $includeChangeSets = false): array
    {
        $stafID = trim((string)($_SESSION['f_stafID'] ?? ''));
        $loginId = trim((string)($_SESSION['f_loginID'] ?? ''));
        if ($stafID === '') {
            return [];
        }

        // Get f_nopekerja (numeric) which is used as user_id in audit_event
        // user_id in audit_event is f_nopekerja (numeric), not f_userID
        $userId = null;
        
        // Try from session first
        if (!empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja'])) {
            $userId = (int)$_SESSION['f_nopekerja'];
        } elseif (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja'])) {
            $userId = (int)$_SESSION['user']['f_nopekerja'];
        }
        
        // If not in session, query from database
        if (!$userId) {
            $sqlUser = "SELECT f_nopekerja FROM tbl_m_user WHERE f_stafID = :stafID LIMIT 1";
            $stmtUser = $this->pdoMysql->prepare($sqlUser);
            $stmtUser->execute([':stafID' => $stafID]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($userRow && !empty($userRow['f_nopekerja']) && is_numeric($userRow['f_nopekerja'])) {
                $userId = (int)$userRow['f_nopekerja'];
            }
        }

        try {
            // Get current session_id for fallback matching
            $currentSessionId = session_id() ?: null;
            
            // Query audit_event - match by user_id OR session_id (if user_id is NULL)
            // This handles cases where some events might not have user_id set
            if ($userId && $currentSessionId) {
                $sql = "
                    SELECT 
                        id,
                        occurred_at,
                        request_id,
                        session_id,
                        user_id,
                        actor_label,
                        INET6_NTOA(ip_address) as ip_address,
                        event_type,
                        severity,
                        outcome,
                        target_type,
                        target_id,
                        target_label,
                        message,
                        meta
                    FROM audit_event
                    WHERE (" . ($loginId !== '' && $this->tableHasColumn('audit_event', 'login_id') ? "login_id = :login_id OR " : "") . "user_id = :uid OR (user_id IS NULL AND session_id = :sid))
                    ORDER BY occurred_at DESC
                    LIMIT :limit
                ";
                $stmt = $this->pdoMysql->prepare($sql);
                if ($loginId !== '' && $this->tableHasColumn('audit_event', 'login_id')) {
                    $stmt->bindValue(':login_id', $loginId, PDO::PARAM_STR);
                }
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':sid', $currentSessionId, PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            } elseif ($userId) {
                // Only match by user_id
                $sql = "
                    SELECT 
                        id,
                        occurred_at,
                        request_id,
                        session_id,
                        user_id,
                        actor_label,
                        INET6_NTOA(ip_address) as ip_address,
                        event_type,
                        severity,
                        outcome,
                        target_type,
                        target_id,
                        target_label,
                        message,
                        meta
                    FROM audit_event
                    WHERE " . ($loginId !== '' && $this->tableHasColumn('audit_event', 'login_id') ? "(login_id = :login_id OR user_id = :uid)" : "user_id = :uid") . "
                    ORDER BY occurred_at DESC
                    LIMIT :limit
                ";
                $stmt = $this->pdoMysql->prepare($sql);
                if ($loginId !== '' && $this->tableHasColumn('audit_event', 'login_id')) {
                    $stmt->bindValue(':login_id', $loginId, PDO::PARAM_STR);
                }
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            } elseif ($currentSessionId) {
                // Only match by session_id
                $sql = "
                    SELECT 
                        id,
                        occurred_at,
                        request_id,
                        session_id,
                        user_id,
                        actor_label,
                        INET6_NTOA(ip_address) as ip_address,
                        event_type,
                        severity,
                        outcome,
                        target_type,
                        target_id,
                        target_label,
                        message,
                        meta
                    FROM audit_event
                    WHERE " . ($loginId !== '' && $this->tableHasColumn('audit_event', 'login_id') ? "(login_id = :login_id OR session_id = :sid)" : "session_id = :sid") . "
                    ORDER BY occurred_at DESC
                    LIMIT :limit
                ";
                $stmt = $this->pdoMysql->prepare($sql);
                if ($loginId !== '' && $this->tableHasColumn('audit_event', 'login_id')) {
                    $stmt->bindValue(':login_id', $loginId, PDO::PARAM_STR);
                }
                $stmt->bindValue(':sid', $currentSessionId, PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            } else {
                // No way to match, return empty
                return [];
            }
            
            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $meta = null;
                if (!empty($row['meta'])) {
                    $meta = json_decode($row['meta'], true);
                }
                
                $eventId = (int)($row['id'] ?? 0);
                
                $changeSets = [];
                if ($includeChangeSets && $eventId > 0) {
                    try {
                        $sqlChangeSet = "
                            SELECT 
                                id,
                                target_type,
                                target_id,
                                change_reason,
                                meta
                            FROM audit_change_set
                            WHERE event_id = :eventId
                            ORDER BY id ASC
                        ";
                        $stmtChangeSet = $this->pdoMysql->prepare($sqlChangeSet);
                        $stmtChangeSet->execute([':eventId' => $eventId]);
                        
                        while ($changeSetRow = $stmtChangeSet->fetch(PDO::FETCH_ASSOC)) {
                            $changeSetId = (int)($changeSetRow['id'] ?? 0);
                            $changeSetMeta = null;
                            if (!empty($changeSetRow['meta'])) {
                                $changeSetMeta = json_decode($changeSetRow['meta'], true);
                            }
                            
                            // Fetch field changes for this change set
                            $fieldChanges = [];
                            if ($changeSetId > 0) {
                                $sqlFields = "
                                    SELECT 
                                        field,
                                        old_value,
                                        new_value,
                                        data_type,
                                        is_sensitive,
                                        diff_hint
                                    FROM audit_change_field
                                    WHERE change_set_id = :changeSetId
                                    ORDER BY id ASC
                                ";
                                $stmtFields = $this->pdoMysql->prepare($sqlFields);
                                $stmtFields->execute([':changeSetId' => $changeSetId]);
                                
                                while ($fieldRow = $stmtFields->fetch(PDO::FETCH_ASSOC)) {
                                    $fieldChanges[] = [
                                        'field' => (string)($fieldRow['field'] ?? ''),
                                        'old_value' => $fieldRow['old_value'],
                                        'new_value' => $fieldRow['new_value'],
                                        'data_type' => (string)($fieldRow['data_type'] ?? 'string'),
                                        'is_sensitive' => !empty($fieldRow['is_sensitive']),
                                        'diff_hint' => $fieldRow['diff_hint'] ? (string)$fieldRow['diff_hint'] : null,
                                    ];
                                }
                            }
                            
                            $changeSets[] = [
                                'id' => $changeSetId,
                                'target_type' => (string)($changeSetRow['target_type'] ?? ''),
                                'target_id' => (string)($changeSetRow['target_id'] ?? ''),
                                'change_reason' => $changeSetRow['change_reason'] ? (string)$changeSetRow['change_reason'] : null,
                                'meta' => $changeSetMeta,
                                'field_changes' => $fieldChanges,
                            ];
                        }
                    } catch (\Throwable $e) {
                        error_log("[ProfileController] Failed to fetch change sets: " . $e->getMessage());
                        // Continue without change sets
                    }
                }
                
                $results[] = [
                    'id' => $eventId,
                    'occurred_at' => (string)($row['occurred_at'] ?? ''),
                    'request_id' => (string)($row['request_id'] ?? ''),
                    'session_id' => (string)($row['session_id'] ?? ''),
                    'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
                    'actor_label' => (string)($row['actor_label'] ?? ''),
                    'ip_address' => (string)($row['ip_address'] ?? ''),
                    'event_type' => (string)($row['event_type'] ?? ''),
                    'severity' => (string)($row['severity'] ?? ''),
                    'outcome' => (string)($row['outcome'] ?? ''),
                    'target_type' => (string)($row['target_type'] ?? ''),
                    'target_id' => (string)($row['target_id'] ?? ''),
                    'target_label' => (string)($row['target_label'] ?? ''),
                    'message' => (string)($row['message'] ?? ''),
                    'meta' => $meta,
                    'change_sets' => $changeSets,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            error_log("[ProfileController] Failed to fetch audit events: " . $e->getMessage());
            return [];
        }
    }
}
