<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// controllers/LoginController.php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Config.php';
require_once __DIR__ . '/../includes/functions-db.php';

// Helper audit (audit_event, audit_request_bind_user, dll) di-autoload melalui init.php

class LoginController
{
    private User $userModel;
    private PDO $pdo;
    /** @var array<string,bool> */
    private array $auditColumnExistsCache = [];
    /** @var array<string,bool> */
    private array $groupColumnExistsCache = [];

    public function __construct()
    {
        $this->pdo = Database::getInstance('mysql')->getConnection();
        $this->userModel = new User($this->pdo);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, $this->auditColumnExistsCache)) {
            return $this->auditColumnExistsCache[$cacheKey];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);
            $this->auditColumnExistsCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $this->auditColumnExistsCache[$cacheKey] = false;
        }

        return $this->auditColumnExistsCache[$cacheKey];
    }

    /** Sahkan pengguna berdasarkan f_loginID + (optional) kata laluan
     *  If $password is null/empty, treat as SSO-authenticated flow (no local password check).
     */
    public function authenticate(string $loginID, ?string $password = null): bool
    {
        $attemptedMethod = $this->normalizeAuthLoginMethod(($password !== null && $password !== '') ? 'MANUAL' : 'SSO');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['pending_password_change']);

        // Normalisasi input
        $loginID = function_exists('auth_normalize_login_id')
            ? auth_normalize_login_id($loginID)
            : trim($loginID);
        if ($loginID === '') {
            $this->auditLoginFail($loginID, 'empty_input', null, $attemptedMethod);
            return false;
        }

        if ($attemptedMethod === 'SSO') {
            $handoff = is_array($_SESSION['sso_auth_handoff'] ?? null) ? $_SESSION['sso_auth_handoff'] : [];
            $handoffLoginId = function_exists('auth_normalize_login_id')
                ? auth_normalize_login_id((string)($handoff['resolved_login_id'] ?? ''))
                : trim((string)($handoff['resolved_login_id'] ?? ''));
            if (empty($handoff['valid_token']) || $handoffLoginId === '' || $handoffLoginId !== $loginID) {
                $this->auditLoginFail($loginID, 'sso_handoff_invalid', null, $attemptedMethod);
                throw new \RuntimeException('SSO_LOGIN_NOT_ALLOWED');
            }
        }

        // Cari user
        $user = $this->userModel->findByLoginID($loginID);
        if (!$user && $attemptedMethod === 'SSO') {
            $user = $this->attemptSsoAutoProvision($loginID);
        }
        if (!$user) {
            $this->auditLoginFail($loginID, 'user_not_found', null, $attemptedMethod);
            return false;
        }

        $policyDecision = $this->evaluateLoginPolicy($user, $attemptedMethod);
        if (!$policyDecision['allowed']) {
            $this->auditLoginFail($loginID, (string)$policyDecision['reason'], $user, $attemptedMethod);
            throw new \RuntimeException((string)$policyDecision['exception']);
        }
        if ($attemptedMethod === 'SSO') {
            $user = $this->reconcileExistingSsoUser($user, $loginID);
        }

        $prePasswordDecision = $this->evaluatePrePasswordCredentialLifecycle($user, $attemptedMethod);
        if (!$prePasswordDecision['allowed']) {
            $this->auditLoginFail($loginID, (string)$prePasswordDecision['reason'], $user, $attemptedMethod);
            throw new \RuntimeException((string)$prePasswordDecision['exception']);
        }

        // Jika password disediakan, semak password seperti biasa.
        // Jika tidak disediakan (SSO flow), skip password verification.
        if ($password !== null && $password !== '') {
            if (!password_verify($password, $user['f_password'])) {
                $this->auditLoginFail($loginID, 'wrong_password', $user, $attemptedMethod);
                return false;
            }
        }

        $postPasswordDecision = $this->evaluatePostPasswordCredentialLifecycle($user, $attemptedMethod);
        if (!$postPasswordDecision['allowed']) {
            $this->preparePendingPasswordChange($user, (string)$postPasswordDecision['reason']);
            $this->auditLoginFail($loginID, (string)$postPasswordDecision['reason'], $user, $attemptedMethod);
            throw new \RuntimeException((string)$postPasswordDecision['exception']);
        }

        // 🔒 Semak f_flag - jika 0, sekat akses
        $f_flag = (int)($user['f_flag'] ?? 1); // Default 1 jika NULL
        if ($f_flag !== 1) {
            $this->auditLoginFail($loginID, 'access_blocked', $user, $attemptedMethod);
            // Throw exception dengan specific message untuk access blocked
            throw new \RuntimeException('ACCESS_BLOCKED');
        }

        // 🔒 Kuatkan sesi
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_regenerate_id(true);

        // Dapatkan user_id dari f_nopekerja (no staf) untuk audit
        $nopekerja = $user['f_nopekerja'] ?? null;
        $resolvedLoginID = trim((string)($user['f_loginID'] ?? $loginID));
        
        // ✅ FIX: Jika f_nopekerja tidak lengkap (cth: "530" bukan "0530-09"), guna f_stafID sebagai fallback
        // f_stafID biasanya dalam format lengkap "0530-09" yang betul untuk audit
        $f_stafID = $user['f_stafID'] ?? null;
        if ($nopekerja && !preg_match('/^\d{4}-\d{2}$/', $nopekerja) && $f_stafID && preg_match('/^\d{4}-\d{2}$/', $f_stafID)) {
            // f_nopekerja tidak lengkap, guna f_stafID yang lengkap
            $nopekerja = $f_stafID;
        }
        
        // Derive short numeric user_id from possible formats like "0530-09" or "530"
        $userId = null;
        if ($nopekerja && preg_match('/^(\\d+)/', $nopekerja, $m)) {
            // cast leading digits to int ("0530" -> 530)
            $userId = (int)$m[1];
        }

        // 👤 Simpan maklumat asas user (minimal & selamat)
        $_SESSION['f_loginID']   = $resolvedLoginID;
        $_SESSION['f_stafID']    = $f_stafID;
        // ✅ FIX: Simpan f_nopekerja yang lengkap (atau f_stafID jika f_nopekerja tidak lengkap)
        $_SESSION['f_nopekerja'] = $nopekerja;
        $_SESSION['f_nama']      = $user['f_nama'] ?? ($user['f_nickname'] ?? '');
        $_SESSION['f_nickname']  = $user['f_nickname'] ?? '';
        $_SESSION['f_groupID']   = (int)($user['f_groupID'] ?? 0);
        $_SESSION['f_groupKod']  = $user['f_groupKod'] ?? '';
        $_SESSION['auth_login_method'] = $attemptedMethod;

        // Tambah payload standard untuk kegunaan umum
        // Resolve persistent numeric user id for backward compatibility.
        $resolvedUserId = $this->resolveUserId($user);
        if ($resolvedUserId === 0 && $userId !== null) {
            // If DB row does not contain a numeric PK, fall back to derived numeric from staff no.
            $resolvedUserId = (int)$userId;
        }
        // Expose top-level f_userID for other code that expects it
        $_SESSION['f_userID'] = $resolvedUserId;

        $_SESSION['user'] = [
            'f_userID'     => $resolvedUserId,
            'f_loginID'    => $resolvedLoginID,
            'f_stafID'     => $f_stafID,
            'f_nopekerja'  => $nopekerja,
            'f_nama'       => $_SESSION['f_nama'],
            'f_nickname'   => $_SESSION['f_nickname'],
            'f_groupID'    => $_SESSION['f_groupID'],
            'f_groupKod'   => $_SESSION['f_groupKod'],
            'f_groupName'  => $user['f_groupName'] ?? null,
            'auth_login_method' => $attemptedMethod,
        ];

        // 🎯 Add-ons: theme/lang daripada profile — balut
        try {
            $profile = $this->userModel->getProfileByLoginID($resolvedLoginID) ?? [];
            if (!empty($profile['f_themeSetting'])) {
                $theme = json_decode($profile['f_themeSetting'], true);
                if (is_array($theme)) {
                    $_SESSION['theme.menu']   = $theme['sidebarColor'] ?? ($_SESSION['theme.menu'] ?? 'light');
                    $_SESSION['theme.topbar'] = $theme['topbarColor']  ?? ($_SESSION['theme.topbar'] ?? 'light');
                    $_SESSION['theme.layout'] = $theme['layoutMode']   ?? ($_SESSION['theme.layout'] ?? 'light');
                    $_SESSION['theme.sidebar'] = $_SESSION['theme.menu'];
                }
            }
            $config = new Config($this->pdo);
            $activeLanguages = $config->getBahasaAktif();
            $defaultLanguage = $config->getDefaultBahasa($activeLanguages[0] ?? 'ms');
            if (!empty($profile['f_lang']) && in_array($profile['f_lang'], $activeLanguages, true)) {
                $_SESSION['lang'] = $profile['f_lang'];
            } elseif (in_array($defaultLanguage, $activeLanguages, true)) {
                $_SESSION['lang'] = $defaultLanguage;
            }
        } catch (\Throwable $e) {
            error_log('AUTH PROFILE/THEME WARN: ' . $e->getMessage());
        }

        // 🕒 Last login — balut
        try {
            if (method_exists($this->userModel, 'touchLastLogin')) {
                $touchIdentity = $f_stafID ?: $resolvedLoginID;
                $this->userModel->touchLastLogin($touchIdentity);
            }
        } catch (\Throwable $e) {
            error_log('AUTH LASTLOGIN WARN: ' . $e->getMessage());
        }

        // 🧾 AUDIT: session + event LOGIN SUCCESS
        $this->auditLoginSuccess($user, $userId, $attemptedMethod, $resolvedLoginID);

        // 🔗 PAKSA BIND audit_request → user_id + route 'auth/login'
        try {
            $rid = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null; // set dalam init.php
            if (function_exists('audit_request_bind_identity')) {
                audit_request_bind_identity($userId, $resolvedLoginID, $rid);
            } elseif ($userId && function_exists('audit_request_bind_user')) {
                audit_request_bind_user($userId, $rid);
            }
            if (function_exists('audit_request_set_route')) {
                audit_request_set_route('auth/login', $rid);
            }
            // Hard fallback (kalau helper unavailable)
            if ($rid) {
                $assignments = ["route = COALESCE(NULLIF(route,''),'auth/login')"];
                $params = [':rid' => $rid];
                if ($userId !== null) {
                    $assignments[] = 'user_id = :uid';
                    $params[':uid'] = $userId;
                }
                if ($resolvedLoginID !== '' && $this->tableHasColumn('audit_request', 'login_id')) {
                    $assignments[] = 'login_id = :login_id';
                    $params[':login_id'] = $resolvedLoginID;
                }
                $stmt = $this->pdo->prepare("UPDATE audit_request SET " . implode(', ', $assignments) . " WHERE request_id = :rid");
                $stmt->execute($params);
            }
        } catch (\Throwable $e) {
            error_log('[LoginController] Audit bind error: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * @param array<string,mixed> $user
     * @return array{allowed:bool,reason:string,exception:string,category:string,method:string}
     */
    private function evaluateLoginPolicy(array $user, string $attemptedMethod): array
    {
        $category = $this->normalizeLoginCategory($user['f_categoryUser'] ?? null, $user);
        $attemptedMethod = strtoupper(trim($attemptedMethod));
        if ($attemptedMethod !== 'SSO') {
            $attemptedMethod = 'MANUAL';
        }

        $policy = function_exists('get_auth_policy_config') ? get_auth_policy_config() : [];
        if (!is_array($policy) || $policy === []) {
            return [
                'allowed' => true,
                'reason' => 'policy_unavailable',
                'exception' => '',
                'category' => $category,
                'method' => $attemptedMethod,
            ];
        }

        if ($this->isUserSuperAdmin($user)) {
            return [
                'allowed' => true,
                'reason' => 'super_admin_override',
                'exception' => '',
                'category' => $category,
                'method' => $attemptedMethod,
            ];
        }

        $maintenanceMode = !empty($policy['maintenance_mode']);
        if ($maintenanceMode) {
            return [
                'allowed' => false,
                'reason' => 'maintenance_mode',
                'exception' => 'MAINTENANCE_MODE',
                'category' => $category,
                'method' => $attemptedMethod,
            ];
        }

        if (!$this->isLoginCategoryEnabled($policy, $category)) {
            return [
                'allowed' => false,
                'reason' => 'category_disabled',
                'exception' => 'CATEGORY_DISABLED',
                'category' => $category,
                'method' => $attemptedMethod,
            ];
        }

        $allowedMethod = $this->resolveAllowedLoginMethod($policy, $category);
        if ($allowedMethod !== $attemptedMethod) {
            $reason = $attemptedMethod === 'MANUAL' ? 'manual_login_not_allowed' : 'sso_login_not_allowed';
            return [
                'allowed' => false,
                'reason' => $reason,
                'exception' => $attemptedMethod === 'MANUAL' ? 'MANUAL_LOGIN_NOT_ALLOWED' : 'SSO_LOGIN_NOT_ALLOWED',
                'category' => $category,
                'method' => $allowedMethod,
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'policy_allowed',
            'exception' => '',
            'category' => $category,
            'method' => $allowedMethod,
        ];
    }

    /**
     * @param array<string,mixed> $user
     * @return array{allowed:bool,reason:string,exception:string}
     */
    private function evaluatePrePasswordCredentialLifecycle(array $user, string $attemptedMethod): array
    {
        $attemptedMethod = $this->normalizeAuthLoginMethod($attemptedMethod);

        if (array_key_exists('f_verified_at', $user)) {
            $verifiedAt = trim((string)($user['f_verified_at'] ?? ''));
            if ($verifiedAt === '') {
                return [
                    'allowed' => false,
                    'reason' => 'account_not_verified',
                    'exception' => 'ACCOUNT_NOT_VERIFIED',
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => 'pre_password_credential_lifecycle_ok',
            'exception' => '',
        ];
    }

    /**
     * @param array<string,mixed> $user
     * @return array{allowed:bool,reason:string,exception:string}
     */
    private function evaluatePostPasswordCredentialLifecycle(array $user, string $attemptedMethod): array
    {
        $attemptedMethod = $this->normalizeAuthLoginMethod($attemptedMethod);

        if ($attemptedMethod === 'MANUAL' && array_key_exists('f_must_change_password', $user)) {
            $mustChange = (int)($user['f_must_change_password'] ?? 0);
            if ($mustChange === 1) {
                return [
                    'allowed' => false,
                    'reason' => 'password_change_required',
                    'exception' => 'PASSWORD_CHANGE_REQUIRED',
                ];
            }
        }

        if ($attemptedMethod === 'MANUAL' && array_key_exists('f_password_expires_at', $user)) {
            $expiresAt = trim((string)($user['f_password_expires_at'] ?? ''));
            if ($expiresAt !== '') {
                $expiresAtTs = strtotime($expiresAt);
                if ($expiresAtTs !== false && $expiresAtTs <= time()) {
                    return [
                        'allowed' => false,
                        'reason' => 'password_expired',
                        'exception' => 'PASSWORD_EXPIRED',
                    ];
                }
            }
        }

        return [
            'allowed' => true,
            'reason' => 'post_password_credential_lifecycle_ok',
            'exception' => '',
        ];
    }

    /**
     * @param array<string,mixed> $user
     */
    private function preparePendingPasswordChange(array $user, string $reason): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['pending_password_change'] = [
            'login_id' => trim((string)($user['f_loginID'] ?? '')),
            'user_id' => $this->resolveUserId($user),
            'staf_id' => trim((string)($user['f_stafID'] ?? '')),
            'reason' => $reason,
            'issued_at' => date('c'),
        ];
    }

    private function normalizeLoginCategory(mixed $category, mixed $loginID = null): string
    {
        $value = strtoupper(trim((string)$category));
        if ($value === 'PUBLIC') {
            return 'UMUM';
        }
        if ($value === 'STUDENT') {
            return 'PELAJAR';
        }
        if ($value === '') {
            $candidateLogin = '';
            $candidateEmail = '';
            $candidateStafId = '';

            if (is_array($loginID)) {
                $candidateLogin = trim((string)($loginID['f_loginID'] ?? $loginID['login_id'] ?? ''));
                $candidateEmail = trim((string)($loginID['f_email'] ?? $loginID['email'] ?? ''));
                $candidateStafId = trim((string)($loginID['f_stafID'] ?? $loginID['staf_id'] ?? ''));
            } else {
                $candidateLogin = trim((string)$loginID);
            }

            if (($candidateLogin !== '' && str_contains($candidateLogin, '@')) || ($candidateEmail !== '' && str_contains($candidateEmail, '@'))) {
                return 'UMUM';
            }

            if (
                ($candidateStafId !== '' && preg_match('/^\d{4}-\d{2}$/', $candidateStafId) === 1)
                || ($candidateLogin !== '' && preg_match('/^\d{4}-\d{2}$/', $candidateLogin) === 1)
            ) {
                return 'STAF';
            }

            if ($candidateLogin !== '' && preg_match('/^[A-Za-z0-9]{4,20}$/', $candidateLogin) === 1) {
                return 'PELAJAR';
            }

            return 'STAF';
        }
        return $value;
    }

    /**
     * @param array<string,mixed> $policy
     */
    private function isLoginCategoryEnabled(array $policy, string $category): bool
    {
        $categories = is_array($policy['categories'] ?? null) ? $policy['categories'] : [];
        return match ($category) {
            'PELAJAR' => !empty($categories['pelajar']),
            'UMUM' => !empty($categories['umum']),
            default => !empty($categories['staf']),
        };
    }

    /**
     * @param array<string,mixed> $policy
     */
    private function resolveAllowedLoginMethod(array $policy, string $category): string
    {
        $sso = is_array($policy['sso'] ?? null) ? $policy['sso'] : [];
        if (empty($sso['enabled'])) {
            return 'MANUAL';
        }

        $mode = strtoupper(trim((string)($sso['mode'] ?? 'MANUAL')));
        if ($mode === 'ALL') {
            return $category === 'UMUM' ? 'MANUAL' : 'SSO';
        }
        if ($mode === 'HYBRID') {
            $hybrid = is_array($sso['hybrid'] ?? null) ? $sso['hybrid'] : [];
            return match ($category) {
                'PELAJAR' => strtoupper(trim((string)($hybrid['pelajar'] ?? 'SSO'))),
                'UMUM' => strtoupper(trim((string)($hybrid['umum'] ?? 'MANUAL'))),
                default => strtoupper(trim((string)($hybrid['staf'] ?? 'SSO'))),
            };
        }

        return 'MANUAL';
    }

    /**
     * @param array<string,mixed> $user
     */
    private function isUserSuperAdmin(array $user): bool
    {
        $superAdminCode = function_exists('prestasi_super_admin_code')
            ? prestasi_super_admin_code()
            : 'ADM-SA';

        $groupKod = trim((string)($user['f_groupKod'] ?? ''));
        if ($groupKod === '' && !empty($user['f_groupID'])) {
            try {
                $stmt = $this->pdo->prepare("SELECT f_groupKod FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
                $stmt->execute([':gid' => (int)$user['f_groupID']]);
                $groupKod = (string)($stmt->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $groupKod = '';
            }
        }

        $groupKod = strtoupper(preg_replace('/[^A-Z0-9]+/', '', trim($groupKod)) ?? '');
        $wanted = strtoupper(preg_replace('/[^A-Z0-9]+/', '', trim((string)$superAdminCode)) ?? '');
        return $groupKod !== '' && $groupKod === $wanted;
    }

    /* ===========================
       AUDIT HELPERS (private)
       =========================== */

    /** Ambil user PK secara fleksibel daripada row $user */
    private function resolveUserId(array $user): int
    {
        foreach (['f_userID','user_id','id','uid','pk_user','id_user'] as $k) {
            if (isset($user[$k]) && is_numeric($user[$k])) {
                return (int)$user[$k];
            }
        }
        return 0; // tak jumpa
    }

    private function normalizeAuthLoginMethod(?string $method): string
    {
        return strtoupper(trim((string)$method)) === 'SSO' ? 'SSO' : 'MANUAL';
    }

    private function groupTableHasColumn(string $column): bool
    {
        $cacheKey = strtolower('tbl_m_group.' . $column);
        if (array_key_exists($cacheKey, $this->groupColumnExistsCache)) {
            return $this->groupColumnExistsCache[$cacheKey];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $stmt->execute([
                ':table' => 'tbl_m_group',
                ':column' => $column,
            ]);
            $this->groupColumnExistsCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $this->groupColumnExistsCache[$cacheKey] = false;
        }

        return $this->groupColumnExistsCache[$cacheKey];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function attemptSsoAutoProvision(string $loginID): ?array
    {
        $handoff = is_array($_SESSION['sso_auth_handoff'] ?? null) ? $_SESSION['sso_auth_handoff'] : [];
        if (empty($handoff['valid_token'])) {
            return null;
        }

        $resolvedLoginId = trim((string)($handoff['resolved_login_id'] ?? ''));
        if ($resolvedLoginId === '' || $resolvedLoginId !== trim($loginID)) {
            return null;
        }

        $category = $this->resolveSsoProvisionCategory($handoff, $resolvedLoginId);
        if (!in_array($category, ['STAF', 'PELAJAR'], true)) {
            return null;
        }

        try {
            $policy = function_exists('get_auth_policy_config') ? get_auth_policy_config() : [];
            if (!$this->isLoginCategoryEnabled($policy, $category)) {
                $this->auditAutoProvisionBlocked($resolvedLoginId, $category, 'category_disabled');
                throw new \RuntimeException('SSO_ACCOUNT_NOT_PROVISIONED');
            }

            $allowedMethod = $this->resolveAllowedLoginMethod($policy, $category);
            if ($allowedMethod !== 'SSO') {
                $this->auditAutoProvisionBlocked($resolvedLoginId, $category, 'sso_route_not_allowed');
                throw new \RuntimeException('SSO_ACCOUNT_NOT_PROVISIONED');
            }

            if (!$this->isSsoAutoProvisionEnabled($policy, $category)) {
                $this->auditAutoProvisionBlocked($resolvedLoginId, $category, 'auto_provision_disabled');
                throw new \RuntimeException('SSO_ACCOUNT_NOT_PROVISIONED');
            }

            $existingUser = $this->findProvisionableUserByIdentifier($resolvedLoginId, $category);
            if ($existingUser) {
                return $existingUser;
            }

            $defaultGroup = $this->resolveDefaultProvisionGroup($policy, $category);
            if (!$defaultGroup) {
                $this->auditAutoProvisionBlocked($resolvedLoginId, $category, 'default_group_invalid');
                throw new \RuntimeException('SSO_DEFAULT_GROUP_INVALID');
            }

            try {
                $sourceRecord = $category === 'PELAJAR'
                    ? $this->fetchStudentProvisioningRecord($resolvedLoginId)
                    : $this->fetchStaffProvisioningRecord($resolvedLoginId);
            } catch (\Throwable $e) {
                $this->auditAutoProvisionBlocked($resolvedLoginId, $category, 'source_unavailable');
                throw new \RuntimeException('SSO_SOURCE_UNAVAILABLE');
            }

            if (!$sourceRecord) {
                $this->auditAutoProvisionBlocked($resolvedLoginId, $category, 'source_record_not_found');
                throw new \RuntimeException('SSO_ACCOUNT_NOT_PROVISIONED');
            }

            $this->pdo->beginTransaction();

            $recheckUser = $this->findProvisionableUserByIdentifier($resolvedLoginId, $category);
            if ($recheckUser) {
                $this->pdo->commit();
                return $recheckUser;
            }

            $payload = $category === 'PELAJAR'
                ? $this->buildStudentProvisionPayload($resolvedLoginId, $sourceRecord, $defaultGroup)
                : $this->buildStaffProvisionPayload($resolvedLoginId, $sourceRecord, $defaultGroup);

            $newUserId = $this->insertProvisionedUser($payload);
            $this->pdo->commit();

            $this->auditAutoProvisionSuccess($resolvedLoginId, $category, $newUserId, (string)($defaultGroup['f_groupKod'] ?? ''));

            return $this->userModel->findByLoginID($resolvedLoginId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[LoginController] SSO auto provision failed: ' . $e->getMessage());
            if ($e instanceof \RuntimeException && in_array($e->getMessage(), ['SSO_ACCOUNT_NOT_PROVISIONED', 'SSO_AUTO_PROVISION_FAILED', 'SSO_DEFAULT_GROUP_INVALID', 'SSO_SOURCE_UNAVAILABLE', 'ACCESS_BLOCKED', 'SSO_ACCOUNT_CATEGORY_MISMATCH'], true)) {
                throw $e;
            }
            $this->auditAutoProvisionBlocked($resolvedLoginId, $category, 'provision_insert_failed');
            throw new \RuntimeException('SSO_AUTO_PROVISION_FAILED');
        }
    }

    private function resolveSsoProvisionCategory(array $handoff, string $loginID): string
    {
        $source = strtolower(trim((string)($handoff['resolved_source'] ?? '')));
        if ($source === 'data3') {
            return 'STAF';
        }
        if ($source === 'data4') {
            return 'PELAJAR';
        }
        if (!empty($handoff['data3_valid']) && empty($handoff['data4_valid'])) {
            return 'STAF';
        }
        if (!empty($handoff['data4_valid']) && empty($handoff['data3_valid'])) {
            return 'PELAJAR';
        }

        return $this->normalizeLoginCategory(null, $loginID);
    }

    private function isSsoAutoProvisionEnabled(array $policy, string $category): bool
    {
        $provisioning = is_array($policy['provisioning'] ?? null) ? $policy['provisioning'] : [];
        return match ($category) {
            'PELAJAR' => !empty($provisioning['pelajar_sso_enabled']),
            default => !empty($provisioning['staf_sso_enabled']),
        };
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveDefaultProvisionGroup(array $policy, string $category): ?array
    {
        $provisioning = is_array($policy['provisioning'] ?? null) ? $policy['provisioning'] : [];
        $defaults = function_exists('get_auth_policy_defaults') ? get_auth_policy_defaults() : [];
        $groupCode = $category === 'PELAJAR'
            ? auth_normalize_group_code($provisioning['default_group_student_code'] ?? null, (string)($defaults['default_group_student_code'] ?? 'ADM-STUDENT'))
            : auth_normalize_group_code($provisioning['default_group_staff_code'] ?? null, (string)($defaults['default_group_staff_code'] ?? 'ADM-STAF'));

        if ($groupCode === '') {
            return null;
        }

        $selectColumns = ['f_groupID', 'f_groupKod', 'f_groupName'];
        if ($this->groupTableHasColumn('f_categoryUser')) {
            $selectColumns[] = 'f_categoryUser';
        }
        $sql = "SELECT " . implode(', ', $selectColumns) . " FROM tbl_m_group WHERE TRIM(COALESCE(f_groupKod, '')) = :groupKod LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':groupKod' => $groupCode]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$group) {
            return null;
        }

        $groupCategory = strtoupper(trim((string)($group['f_categoryUser'] ?? '')));
        if ($groupCategory !== '' && $groupCategory !== $category) {
            return null;
        }

        return $group;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchStaffProvisioningRecord(string $staffId): ?array
    {
        $pdoSybase = Database::pdoSybaseStaff();
        if (!$pdoSybase) {
            throw new \RuntimeException('Staff provisioning source is unavailable.');
        }
        $staffId = $this->normalizeSsoIdentifier($staffId, 'STAF');
        $sql = "
            SELECT
                nopekerja,
                idpekerja,
                gelar_nama,
                nama,
                nokp,
                email,
                handphone,
                kdjwtsemasa,
                jawatansemasa,
                kdjenis,
                jenis,
                kdjbtnsemasa,
                jabatansemasa,
                kumpjwt,
                kodstatus,
                status
            FROM v630staf_service_skim_all
            WHERE LTRIM(RTRIM(CONVERT(VARCHAR(50), nopekerja))) = :nopekerja
              AND LTRIM(RTRIM(CONVERT(VARCHAR(20), kodstatus))) = '1'
        ";
        $stmt = $pdoSybase->prepare($sql);
        $stmt->execute([':nopekerja' => $staffId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchStudentProvisioningRecord(string $matrik): ?array
    {
        if (function_exists('is_student_mode_enabled') && !is_student_mode_enabled()) {
            throw new \RuntimeException('Student provisioning mode is disabled.');
        }

        $pdoSybase = Database::pdoSybaseStudent();
        if (!$pdoSybase) {
            throw new \RuntimeException('Student provisioning source is unavailable.');
        }
        $matrik = $this->normalizeSsoIdentifier($matrik, 'PELAJAR');

        $sql = "
            SELECT
                matrik,
                nama,
                nokp,
                email,
                hpno,
                telno,
                telno_terkini,
                notel_terkini,
                kdprogram,
                program,
                kdfakulti,
                fakulti,
                kdtahap,
                tahap_pengajian,
                kadet,
                kategori_kadet,
                status,
                statusketerangan,
                statuskategori
            FROM v210
            WHERE UPPER(LTRIM(RTRIM(CONVERT(VARCHAR(50), matrik)))) = :matrik
              AND UPPER(LTRIM(RTRIM(CONVERT(VARCHAR(20), statuskategori)))) = 'AKTIF'
        ";
        $stmt = $pdoSybase->prepare($sql);
        $stmt->execute([':matrik' => $matrik]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param array<string,mixed> $sourceRecord
     * @param array<string,mixed> $group
     * @return array<string,mixed>
     */
    private function buildStaffProvisionPayload(string $staffId, array $sourceRecord, array $group): array
    {
        $nokp = trim((string)($sourceRecord['nokp'] ?? ''));
        return [
            'f_loginID' => $staffId,
            'f_stafID' => $staffId,
            'f_categoryUser' => 'STAF',
            'f_nopekerja' => trim((string)($sourceRecord['idpekerja'] ?? '')) ?: null,
            'f_nama' => trim((string)($sourceRecord['gelar_nama'] ?? '')) ?: null,
            'f_nickname' => trim((string)($sourceRecord['nama'] ?? '')) ?: null,
            'f_nokp' => $nokp !== '' ? $nokp : null,
            'f_password' => $nokp !== '' ? password_hash($nokp, PASSWORD_DEFAULT) : '',
            'f_email' => trim((string)($sourceRecord['email'] ?? '')) ?: null,
            'f_handphone' => trim((string)($sourceRecord['handphone'] ?? '')) ?: null,
            'f_jawatanKod' => trim((string)($sourceRecord['kdjwtsemasa'] ?? '')) ?: null,
            'f_jawatan' => trim((string)($sourceRecord['jawatansemasa'] ?? '')) ?: null,
            'f_jenisID' => trim((string)($sourceRecord['kdjenis'] ?? '')) !== '' ? (int)$sourceRecord['kdjenis'] : null,
            'f_jenis' => trim((string)($sourceRecord['jenis'] ?? '')) ?: null,
            'f_jabatanKod' => trim((string)($sourceRecord['kdjbtnsemasa'] ?? '')) ?: null,
            'f_namajabatan' => trim((string)($sourceRecord['jabatansemasa'] ?? '')) ?: null,
            'f_kumpjawatan' => trim((string)($sourceRecord['kumpjwt'] ?? '')) ?: null,
            'f_verified_at' => date('Y-m-d H:i:s'),
            'f_must_change_password' => 1,
            'f_password_changed_at' => null,
            'f_password_expires_at' => null,
            'f_statusID' => trim((string)($sourceRecord['kodstatus'] ?? '')) !== '' ? (int)$sourceRecord['kodstatus'] : null,
            'f_status' => trim((string)($sourceRecord['status'] ?? '')) ?: null,
            'f_groupID' => (int)($group['f_groupID'] ?? 0),
            'f_groupKod' => trim((string)($group['f_groupKod'] ?? '')) ?: null,
            'f_flag' => 1,
            'f_updateby' => 'SSO-AUTO',
            'f_remarks' => 'Auto provisioned via SSO (v630staf_service_skim_all)',
            'f_isAutoProvisioned' => 1,
            'f_identitySource' => 'SSO',
        ];
    }

    /**
     * @param array<string,mixed> $sourceRecord
     * @param array<string,mixed> $group
     * @return array<string,mixed>
     */
    private function buildStudentProvisionPayload(string $matrik, array $sourceRecord, array $group): array
    {
        $nokp = trim((string)($sourceRecord['nokp'] ?? ''));
        $phoneCandidates = [
            trim((string)($sourceRecord['notel_terkini'] ?? '')),
            trim((string)($sourceRecord['hpno'] ?? '')),
            trim((string)($sourceRecord['telno_terkini'] ?? '')),
            trim((string)($sourceRecord['telno'] ?? '')),
        ];
        $resolvedPhone = null;
        foreach ($phoneCandidates as $candidate) {
            if ($candidate !== '') {
                $resolvedPhone = $candidate;
                break;
            }
        }

        return [
            'f_loginID' => $matrik,
            'f_stafID' => $matrik,
            'f_categoryUser' => 'PELAJAR',
            'f_nopekerja' => null,
            'f_nama' => trim((string)($sourceRecord['nama'] ?? '')) ?: null,
            'f_nickname' => trim((string)($sourceRecord['nama'] ?? '')) ?: null,
            'f_nokp' => $nokp !== '' ? $nokp : null,
            'f_password' => $nokp !== '' ? password_hash($nokp, PASSWORD_DEFAULT) : '',
            'f_email' => trim((string)($sourceRecord['email'] ?? '')) ?: null,
            'f_handphone' => $resolvedPhone,
            'f_jawatanKod' => trim((string)($sourceRecord['kdprogram'] ?? '')) ?: null,
            'f_jawatan' => trim((string)($sourceRecord['program'] ?? '')) ?: null,
            'f_jenisID' => trim((string)($sourceRecord['kdtahap'] ?? '')) !== '' ? (int)$sourceRecord['kdtahap'] : null,
            'f_jenis' => trim((string)($sourceRecord['tahap_pengajian'] ?? '')) ?: null,
            'f_jabatanKod' => trim((string)($sourceRecord['kdfakulti'] ?? '')) ?: null,
            'f_namajabatan' => trim((string)($sourceRecord['fakulti'] ?? '')) ?: null,
            'f_kumpjawatan' => trim((string)($sourceRecord['kadet'] ?? '')) ?: null,
            'f_verified_at' => date('Y-m-d H:i:s'),
            'f_must_change_password' => 1,
            'f_password_changed_at' => null,
            'f_password_expires_at' => null,
            'f_statusID' => null,
            'f_status' => trim((string)($sourceRecord['statuskategori'] ?? '')) ?: null,
            'f_groupID' => (int)($group['f_groupID'] ?? 0),
            'f_groupKod' => trim((string)($group['f_groupKod'] ?? '')) ?: null,
            'f_flag' => 1,
            'f_updateby' => 'SSO-AUTO',
            'f_remarks' => 'Auto provisioned via SSO (v210)',
            'f_isAutoProvisioned' => 1,
            'f_identitySource' => 'SSO',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function insertProvisionedUser(array $payload): int
    {
        $columns = [];
        $placeholders = [];
        $params = [];
        $allowedColumns = [
            'f_loginID',
            'f_stafID',
            'f_categoryUser',
            'f_nopekerja',
            'f_nama',
            'f_nickname',
            'f_nokp',
            'f_password',
            'f_email',
            'f_handphone',
            'f_jawatanKod',
            'f_jawatan',
            'f_jenisID',
            'f_jenis',
            'f_jabatanKod',
            'f_namajabatan',
            'f_kumpjawatan',
            'f_verified_at',
            'f_must_change_password',
            'f_password_changed_at',
            'f_password_expires_at',
            'f_statusID',
            'f_status',
            'f_groupID',
            'f_groupKod',
            'f_flag',
            'f_updateby',
            'f_remarks',
            'f_isAutoProvisioned',
            'f_identitySource',
        ];

        foreach ($allowedColumns as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            if (!$this->tableHasColumn('tbl_m_user', $column)) {
                continue;
            }
            $columns[] = $column;
            $placeholders[] = ':' . $column;
            $params[':' . $column] = $payload[$column];
        }

        if ($this->tableHasColumn('tbl_m_user', 'f_insertdt')) {
            $columns[] = 'f_insertdt';
            $placeholders[] = 'NOW()';
        }
        if ($this->tableHasColumn('tbl_m_user', 'f_updatedt')) {
            $columns[] = 'f_updatedt';
            $placeholders[] = 'NOW()';
        }

        if ($columns === []) {
            throw new \RuntimeException('No insertable columns available for auto provision.');
        }

        $sql = 'INSERT INTO tbl_m_user (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findProvisionableUserByIdentifier(string $identifier, string $expectedCategory): ?array
    {
        $identifier = $this->normalizeSsoIdentifier($identifier, $expectedCategory);
        $stmt = $this->pdo->prepare(
            "SELECT f_userID, TRIM(COALESCE(f_loginID, '')) AS f_loginID,
                    TRIM(COALESCE(f_stafID, '')) AS f_stafID,
                    TRIM(COALESCE(f_categoryUser, '')) AS f_categoryUser,
                    COALESCE(f_statusID, 0) AS f_statusID,
                    COALESCE(f_flag, 1) AS f_flag
             FROM tbl_m_user
             WHERE UPPER(TRIM(COALESCE(f_loginID, ''))) = :login_identifier
                OR UPPER(TRIM(COALESCE(f_stafID, ''))) = :staff_identifier
             LIMIT 1"
        );
        $stmt->execute([
            ':login_identifier' => strtoupper($identifier),
            ':staff_identifier' => strtoupper($identifier),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return null;
        }

        if ((int)($row['f_statusID'] ?? 0) === 9 || (int)($row['f_flag'] ?? 1) !== 1) {
            $this->auditAutoProvisionBlocked($identifier, $expectedCategory, 'existing_account_disabled');
            throw new \RuntimeException('ACCESS_BLOCKED');
        }

        $actualCategory = $this->normalizeLoginCategory($row['f_categoryUser'] ?? null, $row);
        if ($actualCategory !== $expectedCategory) {
            $this->auditAutoProvisionBlocked($identifier, $expectedCategory, 'existing_account_category_mismatch');
            throw new \RuntimeException('SSO_ACCOUNT_CATEGORY_MISMATCH');
        }

        $resolvedLoginId = trim((string)($row['f_loginID'] ?? ''));
        if ($resolvedLoginId === '') {
            $resolvedLoginId = trim((string)($row['f_stafID'] ?? ''));
        }

        $user = $resolvedLoginId !== '' ? $this->userModel->findByLoginID($resolvedLoginId) : null;
        return $user ? $this->reconcileExistingSsoUser($user, $identifier) : null;
    }

    private function normalizeSsoIdentifier(string $identifier, string $category): string
    {
        $identifier = trim($identifier);
        if ($category === 'STAF') {
            return preg_replace('/\s*-\s*/', '-', $identifier) ?? $identifier;
        }
        return strtoupper($identifier);
    }

    /**
     * A valid OneID response is sufficient to verify an active existing account,
     * but must never reactivate it or silently change its category/group.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function reconcileExistingSsoUser(array $user, string $loginID): array
    {
        $handoff = is_array($_SESSION['sso_auth_handoff'] ?? null) ? $_SESSION['sso_auth_handoff'] : [];
        $expectedCategory = $this->resolveSsoProvisionCategory($handoff, $loginID);
        $actualCategory = $this->normalizeLoginCategory($user['f_categoryUser'] ?? null, $user);

        if (in_array($expectedCategory, ['STAF', 'PELAJAR'], true) && $actualCategory !== $expectedCategory) {
            $this->auditAutoProvisionBlocked($loginID, $expectedCategory, 'existing_account_category_mismatch');
            throw new \RuntimeException('SSO_ACCOUNT_CATEGORY_MISMATCH');
        }
        if ((int)($user['f_statusID'] ?? 0) === 9 || (int)($user['f_flag'] ?? 1) !== 1) {
            $this->auditAutoProvisionBlocked($loginID, $actualCategory, 'existing_account_disabled');
            throw new \RuntimeException('ACCESS_BLOCKED');
        }

        $updates = [];
        if ($this->tableHasColumn('tbl_m_user', 'f_verified_at') && trim((string)($user['f_verified_at'] ?? '')) === '') {
            $updates['f_verified_at'] = date('Y-m-d H:i:s');
        }
        if ($this->tableHasColumn('tbl_m_user', 'f_identitySource') && strtoupper(trim((string)($user['f_identitySource'] ?? ''))) !== 'SSO') {
            $updates['f_identitySource'] = 'SSO';
        }
        if ($updates === []) {
            return $user;
        }

        $assignments = [];
        $params = [':user_id' => (int)($user['f_userID'] ?? 0)];
        foreach ($updates as $column => $value) {
            $assignments[] = $column . ' = :' . $column;
            $params[':' . $column] = $value;
        }
        if ($this->tableHasColumn('tbl_m_user', 'f_updatedt')) {
            $assignments[] = 'f_updatedt = NOW()';
        }
        if ($params[':user_id'] <= 0) {
            throw new \RuntimeException('SSO_AUTO_PROVISION_FAILED');
        }

        $stmt = $this->pdo->prepare('UPDATE tbl_m_user SET ' . implode(', ', $assignments) . ' WHERE f_userID = :user_id');
        $stmt->execute($params);
        return $this->userModel->findByLoginID((string)($user['f_loginID'] ?? $loginID)) ?? $user;
    }

    private function auditAutoProvisionSuccess(string $loginID, string $category, int $userId, string $groupCode): void
    {
        try {
            if (!function_exists('audit_event')) {
                return;
            }

            audit_event([
                'event_type' => 'CREATE',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'user',
                'target_id' => $loginID,
                'target_label' => 'Auto Provisioned User: ' . $loginID,
                'message' => 'User auto provisioned via SSO',
                'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                'session_id' => session_id() ?: null,
                'login_id' => $loginID,
                'actor_label' => $loginID,
                'meta' => [
                    'category' => $category,
                    'auth_method' => 'SSO',
                    'auth_flow' => 'sso_auto_provision',
                    'user_id' => $userId,
                    'group_code' => $groupCode,
                    'identity_source' => 'SSO',
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[LoginController] auditAutoProvisionSuccess failed: ' . $e->getMessage());
        }
    }

    private function auditAutoProvisionBlocked(string $loginID, string $category, string $reason): void
    {
        try {
            if (!function_exists('audit_event')) {
                return;
            }

            audit_event([
                'event_type' => 'LOGIN',
                'severity' => 'SECURITY',
                'outcome' => 'FAIL',
                'target_type' => 'auth',
                'target_id' => 'login',
                'message' => 'SSO auto provision request denied',
                'request_id' => $GLOBALS['__AUDIT_REQUEST_ID'] ?? null,
                'session_id' => session_id() ?: null,
                'login_id' => $loginID,
                'actor_label' => $loginID,
                'meta' => [
                    'category' => $category,
                    'auth_method' => 'SSO',
                    'auth_flow' => 'sso_auto_provision',
                    'reason' => $reason,
                    'reason_code' => $reason,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[LoginController] auditAutoProvisionBlocked failed: ' . $e->getMessage());
        }
    }

    private function auditLoginFail(string $loginID, string $reason, ?array $user = null, ?string $authMethod = null): void
    {
        try {
            if (!function_exists('audit_event')) return;

            $loginID = function_exists('auth_normalize_login_id')
                ? auth_normalize_login_id($loginID)
                : trim($loginID);
            $authMethod = $this->normalizeAuthLoginMethod($authMethod);

            $ipText = $_SERVER['HTTP_CF_CONNECTING_IP']
                ?? (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : null)
                ?? ($_SERVER['HTTP_X_REAL_IP'] ?? ($_SERVER['HTTP_CLIENT_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')));

            // Resolve request id
            $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;

            // Determine user_id and actor label
            $userId = null;
            $actorLabel = $loginID ?: 'Unknown';
            if (is_array($user) && !empty($user)) {
                $userId = $this->resolveUserId($user) ?: null;
                $name = trim((string)($user['f_nama'] ?? $user['f_nickname'] ?? '')) ?: null;
                $identifier = trim((string)($user['f_loginID'] ?? $user['f_stafID'] ?? $loginID));
                if (function_exists('audit_format_actor_label')) {
                    $actorLabel = audit_format_actor_label($name, $identifier);
                } else {
                    $actorLabel = $name ? ($name . ' (' . $identifier . ')') : $identifier;
                }
            } else {
                $labelFromHelper = null;
                if (function_exists('audit_format_actor_label')) {
                    $labelFromHelper = audit_format_actor_label(null, $loginID);
                }
                if ($labelFromHelper) $actorLabel = $labelFromHelper;
            }

            // ✅ FIX: Message dalam bahasa Inggeris with actor label
            $failMessage = function_exists('audit_format_message') ? audit_format_message('Login attempt failed', $actorLabel) : ('Login attempt failed by ' . $actorLabel);

            audit_event([
                'event_type'  => 'LOGIN',
                'severity'    => 'SECURITY',
                'outcome'     => 'FAIL',
                'target_type' => 'auth',
                'target_id'   => 'login',
                'message'     => $failMessage,
                'request_id'  => $requestId,
                'session_id'  => session_id(),
                'user_id'     => $userId,
                'login_id'    => $loginID !== '' ? $loginID : null,
                'actor_label' => $actorLabel,
                'ip'          => $ipText,
                'meta'        => [
                    'login_id'           => $loginID !== '' ? $loginID : null,
                    'attempted_login_id' => $loginID,
                    'reason'             => $reason,
                    'reason_code'        => $reason,
                    'auth_method'        => $authMethod,
                    'auth_flow'          => $authMethod === 'SSO' ? 'sso_login' : 'manual_login',
                    'ip_text'            => $ipText,
                    'user_agent'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'session_id'         => session_id(),
                    'request_id'         => $requestId,
                    'resolved_name'      => is_array($user) ? ($user['f_nama'] ?? $user['f_nickname'] ?? null) : null,
                    'resolved_login_id'  => is_array($user) ? ($user['f_loginID'] ?? null) : null,
                    'resolved_legacy_id' => is_array($user) ? ($user['f_stafID'] ?? null) : null,
                    'resolved_user_id'   => $userId,
                    'resolved_category'  => is_array($user)
                        ? $this->normalizeLoginCategory($user['f_categoryUser'] ?? null, $user)
                        : null,
                ],
            ]);
        } catch (\Throwable $e) {
            // diam
        }
    }

    private function auditLoginSuccess(array $user, ?int $userId, ?string $authMethod = null, ?string $loginId = null): void
    {
        $authMethod = $this->normalizeAuthLoginMethod($authMethod);
        $nopek = (string)($user['f_nopekerja'] ?? '');
        $nama  = $user['f_nama'] ?? ($user['f_nickname'] ?? null);
        $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
        $resolvedLoginId = trim((string)($loginId ?? ($user['f_loginID'] ?? '')));

        // 1) Rekod audit_session
        try {
            $columns = ['session_id', 'user_id', 'user_nopekerja', 'started_at', 'ip_address', 'user_agent'];
            $placeholders = [':sid', ':uid', ':no', 'NOW(6)', ':ip', ':ua'];
            $params = [
                ':sid' => session_id(),
                ':uid' => $userId,
                ':no'  => $nopek,
                ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ];
            if ($resolvedLoginId !== '' && $this->tableHasColumn('audit_session', 'login_id')) {
                array_splice($columns, 3, 0, ['login_id']);
                array_splice($placeholders, 3, 0, [':login_id']);
                $params[':login_id'] = $resolvedLoginId;
            }
            $ipBin = null;
            if (class_exists('AuditLogger') && method_exists('AuditLogger','clientIp')) {
                $ipBin = AuditLogger::ipToBinary(AuditLogger::clientIp());
            }
            $params[':ip'] = $ipBin;
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_session
                (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")
            ");
            $stmt->execute($params);
        } catch (\Throwable $e) {
            error_log('[LoginController] audit_session error: ' . $e->getMessage());
        }

        // 2) Event LOGIN SUCCESS
        try {
            if (!function_exists('audit_event')) return;
            
            // ✅ FIX: Gunakan nostaf dari session (yang sudah disimpan dengan betul) BUKAN dari $user array
            // Session sudah disimpan dengan betul di baris 64: $_SESSION['f_nopekerja'] = $nopekerja;
            // Gunakan nilai dari session untuk ensure konsisten dengan nilai yang digunakan dalam sistem
            $loginIdFromSession = $_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? null;
            $loginIdentifier = $loginIdFromSession ? (string)$loginIdFromSession : (string)($user['f_loginID'] ?? '');
            
            $actorLabel = null;
            if (function_exists('audit_format_actor_label')) {
                $actorLabel = audit_format_actor_label($nama, $loginIdentifier);
            } else {
                $actorLabel = $nama;
            }
            
            // ✅ FIX: Message dalam bahasa Inggeris dengan format: "[action] by [actor_label]"
            $message = audit_format_message('User login', $actorLabel);
            
            audit_event([
                'event_type'  => 'LOGIN',
                'severity'    => 'INFO',
                'outcome'     => 'SUCCESS',
                'target_type' => 'auth',
                'target_id'   => 'login',
                'message'     => $message,
                'request_id'  => $requestId,
                'session_id'  => session_id(),
                'user_id'     => $userId,
                'login_id'    => $resolvedLoginId !== '' ? $resolvedLoginId : null,
                'actor_label' => $actorLabel,
                'meta'        => [
                    'login_id'    => $resolvedLoginId !== '' ? $resolvedLoginId : null,
                    'f_loginID'   => $user['f_loginID'] ?? null,
                    'f_stafID'    => $user['f_stafID'] ?? null,
                    'f_nopekerja' => $user['f_nopekerja'] ?? null,
                    'group'       => $user['f_groupKod'] ?? null,
                    'auth_method' => $authMethod,
                    'auth_flow'   => $authMethod === 'SSO' ? 'sso_login' : 'manual_login',
                    'category'    => $this->normalizeLoginCategory($user['f_categoryUser'] ?? null, $user),
                    'resolved_user_id' => $userId,
                    'session_id'  => session_id(),
                    'request_id'  => $requestId,
                    'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[LoginController] audit_event error: ' . $e->getMessage());
        }
    }
}
