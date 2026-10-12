<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

if (!function_exists('impersonation_state')) {
    function impersonation_state(): array
    {
        $state = $_SESSION['impersonation'] ?? [];
        return is_array($state) ? $state : [];
    }
}

if (!function_exists('impersonation_is_active')) {
    function impersonation_is_active(): bool
    {
        $state = impersonation_state();
        return !empty($state['active']) && !empty($state['actor']) && !empty($state['target']);
    }
}

if (!function_exists('impersonation_target')) {
    function impersonation_target(): array
    {
        $target = impersonation_state()['target'] ?? [];
        return is_array($target) ? $target : [];
    }
}

if (!function_exists('impersonation_mode')) {
    function impersonation_mode(): string
    {
        $mode = strtolower(trim((string)(impersonation_state()['mode'] ?? 'view_only')));
        return in_array($mode, ['view_only', 'support_action'], true) ? $mode : 'view_only';
    }
}

if (!function_exists('impersonation_actor')) {
    function impersonation_actor(): array
    {
        $actor = impersonation_state()['actor'] ?? [];
        return is_array($actor) ? $actor : [];
    }
}

if (!function_exists('impersonation_timeout_minutes')) {
    function impersonation_timeout_minutes(): int
    {
        $raw = function_exists('app_config')
            ? app_config('impersonation.timeout_minutes', 60)
            : 60;
        $minutes = (int)$raw;
        return max(5, min(240, $minutes));
    }
}

if (!function_exists('impersonation_current_actor_context')) {
    function impersonation_current_actor_context(): array
    {
        if (impersonation_is_active()) {
            return impersonation_actor();
        }

        return [
            'user_id' => (int)($_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? 0),
            'login_id' => (string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''),
            'staf_id' => (string)($_SESSION['f_stafID'] ?? $_SESSION['user']['f_stafID'] ?? ''),
            'nopekerja' => (string)($_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? ''),
            'name' => (string)($_SESSION['f_nama'] ?? $_SESSION['user']['f_nama'] ?? ''),
            'group_id' => (int)($_SESSION['group_active_id'] ?? $_SESSION['f_groupID'] ?? 0),
            'group_kod' => (string)($_SESSION['f_groupKod'] ?? $_SESSION['user']['f_groupKod'] ?? ''),
            'category' => (string)($_SESSION['f_categoryUser'] ?? $_SESSION['user']['f_categoryUser'] ?? ''),
            'department_code' => (string)($_SESSION['f_jabatanKod'] ?? $_SESSION['user']['f_jabatanKod'] ?? ''),
            'department_name' => (string)($_SESSION['f_namajabatan'] ?? $_SESSION['user']['f_namajabatan'] ?? ''),
        ];
    }
}

if (!function_exists('impersonation_current_effective_user_context')) {
    function impersonation_current_effective_user_context(): array
    {
        if (impersonation_is_active()) {
            return impersonation_target();
        }

        return [
            'user_id' => (int)($_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? 0),
            'login_id' => (string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''),
            'staf_id' => (string)($_SESSION['f_stafID'] ?? $_SESSION['user']['f_stafID'] ?? ''),
            'nopekerja' => (string)($_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? ''),
            'name' => (string)($_SESSION['f_nama'] ?? $_SESSION['user']['f_nama'] ?? ''),
            'group_id' => (int)($_SESSION['group_active_id'] ?? $_SESSION['f_groupID'] ?? 0),
            'group_kod' => (string)($_SESSION['f_groupKod'] ?? $_SESSION['user']['f_groupKod'] ?? ''),
            'category' => (string)($_SESSION['f_categoryUser'] ?? $_SESSION['user']['f_categoryUser'] ?? ''),
            'department_code' => (string)($_SESSION['f_jabatanKod'] ?? $_SESSION['user']['f_jabatanKod'] ?? ''),
            'department_name' => (string)($_SESSION['f_namajabatan'] ?? $_SESSION['user']['f_namajabatan'] ?? ''),
        ];
    }
}

if (!function_exists('impersonation_should_mask_sensitive_data')) {
    function impersonation_should_mask_sensitive_data(string $field = ''): bool
    {
        if (!impersonation_is_active()) {
            return false;
        }

        $field = strtolower(trim($field));
        if ($field === '') {
            return impersonation_mode() === 'view_only';
        }

        $sensitive = [
            'password', 'pass', 'token', 'secret', 'api_key', 'apikey',
            'client_secret', 'private_key', 'recovery_code', 'otp', 'mfa',
        ];
        foreach ($sensitive as $needle) {
            if (str_contains($field, $needle)) {
                return true;
            }
        }

        return impersonation_mode() === 'view_only' && in_array($field, ['nokp', 'ic', 'nric'], true);
    }
}

if (!function_exists('impersonation_enforce_timeout')) {
    function impersonation_enforce_timeout(PDO $pdo): void
    {
        if (!impersonation_is_active()) {
            return;
        }

        $state = impersonation_state();
        $startedAt = strtotime((string)($state['started_at'] ?? ''));
        if ($startedAt <= 0) {
            return;
        }

        $ttl = impersonation_timeout_minutes() * 60;
        if ((time() - $startedAt) < $ttl) {
            return;
        }

        try {
            impersonation_stop($pdo, 'timeout');
        } catch (Throwable $e) {
            unset($_SESSION['impersonation']);
            error_log('[impersonation_timeout] ' . $e->getMessage());
        }

        $message = (string)(__('impersonation_timeout_message') ?: 'View As session expired. Please start View As again if needed.');
        if (function_exists('request_is_ajax_like') && request_is_ajax_like()) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            http_response_code(440);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => true, 'message' => $message, 'impersonation_timeout' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (function_exists('set_alert')) {
            set_alert([
                'type' => 'sweet',
                'icon' => 'info',
                'title' => 'View As',
                'text' => $message,
                'confirm' => true,
                'position' => 'center',
            ]);
        }
    }
}

if (!function_exists('impersonation_safe_session_snapshot')) {
    function impersonation_safe_session_snapshot(): array
    {
        $keys = [
            'f_userID', 'f_loginID', 'f_stafID', 'f_nopekerja', 'f_nama', 'f_nickname',
            'f_email', 'f_categoryUser', 'f_jabatanKod', 'f_namajabatan',
            'f_groupID', 'f_groupKod', 'group_default_id', 'group_active_id',
            'auth_login_method', 'user', 'lang',
            'theme.menu', 'theme.topbar', 'theme.layout', 'theme.sidebar',
        ];

        $snapshot = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $snapshot[$key] = $_SESSION[$key];
            }
        }

        return $snapshot;
    }
}

if (!function_exists('impersonation_restore_session_snapshot')) {
    function impersonation_restore_session_snapshot(array $snapshot): void
    {
        foreach (['f_userID', 'f_loginID', 'f_stafID', 'f_nopekerja', 'f_nama', 'f_nickname', 'f_email', 'f_categoryUser', 'f_jabatanKod', 'f_namajabatan', 'f_groupID', 'f_groupKod', 'group_default_id', 'group_active_id', 'auth_login_method', 'user', 'lang', 'theme.menu', 'theme.topbar', 'theme.layout', 'theme.sidebar'] as $key) {
            unset($_SESSION[$key]);
        }

        foreach ($snapshot as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }
}

if (!function_exists('impersonation_apply_profile_to_session')) {
    function impersonation_apply_profile_to_session(array $profile, PDO $pdo): void
    {
        $loginId = trim((string)($profile['f_loginID'] ?? ''));
        $stafId = trim((string)($profile['f_stafID'] ?? ''));
        $groupId = (int)($profile['f_groupID'] ?? 0);

        $_SESSION['f_userID'] = (int)($profile['f_userID'] ?? 0);
        $_SESSION['f_loginID'] = $loginId;
        $_SESSION['f_stafID'] = $stafId;
        $_SESSION['f_nopekerja'] = (string)($profile['f_nopekerja'] ?? $stafId);
        $_SESSION['f_nama'] = (string)($profile['f_nama'] ?? ($profile['f_nickname'] ?? $loginId));
        $_SESSION['f_nickname'] = (string)($profile['f_nickname'] ?? '');
        $_SESSION['f_email'] = (string)($profile['f_email'] ?? '');
        $_SESSION['f_categoryUser'] = (string)($profile['f_categoryUser'] ?? '');
        $_SESSION['f_jabatanKod'] = (string)($profile['f_jabatanKod'] ?? '');
        $_SESSION['f_namajabatan'] = (string)($profile['f_namajabatan'] ?? '');
        $_SESSION['f_groupID'] = $groupId;
        $_SESSION['f_groupKod'] = (string)($profile['f_groupKod'] ?? '');
        $_SESSION['group_default_id'] = $groupId;
        $_SESSION['group_active_id'] = $groupId;
        $_SESSION['auth_login_method'] = 'IMPERSONATION';
        $_SESSION['user'] = [
            'f_userID' => $_SESSION['f_userID'],
            'f_loginID' => $_SESSION['f_loginID'],
            'f_stafID' => $_SESSION['f_stafID'],
            'f_nopekerja' => $_SESSION['f_nopekerja'],
            'f_nama' => $_SESSION['f_nama'],
            'f_nickname' => $_SESSION['f_nickname'],
            'f_email' => $_SESSION['f_email'],
            'f_categoryUser' => $_SESSION['f_categoryUser'],
            'f_jabatanKod' => $_SESSION['f_jabatanKod'],
            'f_namajabatan' => $_SESSION['f_namajabatan'],
            'f_groupID' => $_SESSION['f_groupID'],
            'f_groupKod' => $_SESSION['f_groupKod'],
            'f_groupName' => $profile['f_groupName'] ?? null,
            'auth_login_method' => 'IMPERSONATION',
        ];

        if (!empty($profile['f_lang'])) {
            $_SESSION['lang'] = (string)$profile['f_lang'];
        }

        $themeSetting = [];
        if (!empty($profile['f_themeSetting'])) {
            $decoded = json_decode((string)$profile['f_themeSetting'], true);
            if (is_array($decoded)) {
                $themeSetting = $decoded;
            }
        }

        if (!$themeSetting && class_exists('Config')) {
            try {
                $themeSetting = (new Config($pdo))->getTema();
            } catch (Throwable $e) {
                $themeSetting = [];
            }
        }

        $_SESSION['theme.menu'] = $themeSetting['sidebarColor'] ?? ($_SESSION['theme.menu'] ?? 'light');
        $_SESSION['theme.topbar'] = $themeSetting['topbarColor'] ?? ($_SESSION['theme.topbar'] ?? 'light');
        $_SESSION['theme.layout'] = $themeSetting['layoutMode'] ?? ($_SESSION['theme.layout'] ?? 'light');
        $_SESSION['theme.sidebar'] = $_SESSION['theme.menu'];
    }
}

if (!function_exists('impersonation_audit')) {
    function impersonation_audit(string $eventType, string $outcome, array $target, array $actor, array $meta = []): void
    {
        if (!function_exists('audit_event')) {
            return;
        }

        try {
            $actorLoginId = (string)($actor['login_id'] ?? $actor['f_loginID'] ?? '');
            $actorNoPekerja = $actor['nopekerja'] ?? $actor['f_nopekerja'] ?? null;
            $actorAuditUserId = null;
            if ($actorNoPekerja !== null && is_numeric((string)$actorNoPekerja)) {
                $actorAuditUserId = (int)$actorNoPekerja;
            } elseif (isset($actor['user_id']) && is_numeric((string)$actor['user_id'])) {
                $actorAuditUserId = (int)$actor['user_id'];
            } elseif (isset($actor['f_userID']) && is_numeric((string)$actor['f_userID'])) {
                $actorAuditUserId = (int)$actor['f_userID'];
            }

            audit_event([
                'event_type' => $eventType,
                'severity' => 'INFO',
                'outcome' => strtoupper($outcome),
                'target_type' => 'user_impersonation',
                'target_id' => (string)($target['login_id'] ?? $target['f_loginID'] ?? ''),
                'target_label' => (string)($target['name'] ?? $target['f_nama'] ?? ''),
                'message' => $eventType . ' by ' . (string)($actor['name'] ?? $actor['f_nama'] ?? $actor['login_id'] ?? 'admin'),
                'login_id' => $actorLoginId !== '' ? $actorLoginId : null,
                'meta' => array_merge([
                    'actor_login_id' => $actorLoginId !== '' ? $actorLoginId : null,
                    'actor_user_id' => $actor['user_id'] ?? $actor['f_userID'] ?? null,
                    'actor_nopekerja' => $actorNoPekerja,
                    'target_login_id' => $target['login_id'] ?? $target['f_loginID'] ?? null,
                    'target_user_id' => $target['user_id'] ?? $target['f_userID'] ?? null,
                    'target_nopekerja' => $target['nopekerja'] ?? $target['f_nopekerja'] ?? null,
                    'target_staf_id' => $target['staf_id'] ?? $target['f_stafID'] ?? null,
                    'mode' => impersonation_mode(),
                    'view_only' => impersonation_mode() === 'view_only',
                ], $meta),
                'session_id' => session_id() ?: null,
                'user_id' => $actorAuditUserId,
            ]);
        } catch (Throwable $e) {
            error_log('[impersonation_audit] ' . $e->getMessage());
        }
    }
}

if (!function_exists('impersonation_profile_is_super_admin')) {
    function impersonation_profile_is_super_admin(array $profile): bool
    {
        $legacyRoleId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
        if ($legacyRoleId > 0 && (int)($profile['f_groupID'] ?? 0) === $legacyRoleId) {
            return true;
        }

        $superAdminKod = function_exists('prestasi_super_admin_code') ? prestasi_super_admin_code() : 'ADM-SA';
        $groupKod = (string)($profile['f_groupKod'] ?? '');
        return function_exists('prestasi_group_code_equals')
            ? prestasi_group_code_equals($groupKod, $superAdminKod)
            : strtoupper(trim($groupKod)) === strtoupper(trim($superAdminKod));
    }
}

if (!function_exists('impersonation_start')) {
    function impersonation_start(PDO $pdo, string $targetLoginId, string $reason, string $mode = 'view_only'): array
    {
        if (impersonation_is_active()) {
            throw new RuntimeException('IMPERSONATION_ALREADY_ACTIVE');
        }

        $targetLoginId = trim($targetLoginId);
        $reason = trim($reason);
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['view_only', 'support_action'], true)) {
            $mode = 'view_only';
        }
        if ($targetLoginId === '' || $reason === '') {
            throw new RuntimeException('IMPERSONATION_REQUIRED');
        }

        require_once __DIR__ . '/../../classes/User.php';
        $userModel = new User($pdo);
        $actorLoginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
        $actorProfile = $actorLoginId !== '' ? ($userModel->getProfileByLoginID($actorLoginId) ?: []) : [];

        if (!$actorProfile || !function_exists('is_user_super_admin') || !is_user_super_admin($actorProfile, $pdo)) {
            throw new RuntimeException('IMPERSONATION_FORBIDDEN');
        }

        $targetAuth = $userModel->findByLoginID($targetLoginId);
        $targetProfile = $targetAuth ? ($userModel->getProfileByLoginID((string)$targetAuth['f_loginID']) ?: []) : [];
        if (!$targetProfile) {
            throw new RuntimeException('IMPERSONATION_TARGET_NOT_FOUND');
        }

        if ((int)($targetAuth['f_flag'] ?? 1) !== 1) {
            throw new RuntimeException('IMPERSONATION_TARGET_DISABLED');
        }

        if (trim((string)($targetProfile['f_loginID'] ?? '')) === $actorLoginId) {
            throw new RuntimeException('IMPERSONATION_SELF_DENIED');
        }

        if (impersonation_profile_is_super_admin($targetProfile)) {
            throw new RuntimeException('IMPERSONATION_SUPER_ADMIN_DENIED');
        }

        $actor = [
            'user_id' => (int)($actorProfile['f_userID'] ?? 0),
            'login_id' => (string)($actorProfile['f_loginID'] ?? $actorLoginId),
            'staf_id' => (string)($actorProfile['f_stafID'] ?? ''),
            'nopekerja' => (string)($actorProfile['f_nopekerja'] ?? ''),
            'name' => (string)($actorProfile['f_nama'] ?? $actorProfile['f_nickname'] ?? $actorLoginId),
            'group_id' => (int)($actorProfile['f_groupID'] ?? 0),
            'group_kod' => (string)($actorProfile['f_groupKod'] ?? ''),
            'category' => (string)($actorProfile['f_categoryUser'] ?? ''),
            'department_code' => (string)($actorProfile['f_jabatanKod'] ?? ''),
            'department_name' => (string)($actorProfile['f_namajabatan'] ?? ''),
        ];
        $target = [
            'user_id' => (int)($targetProfile['f_userID'] ?? 0),
            'login_id' => (string)($targetProfile['f_loginID'] ?? $targetLoginId),
            'staf_id' => (string)($targetProfile['f_stafID'] ?? ''),
            'nopekerja' => (string)($targetProfile['f_nopekerja'] ?? ''),
            'name' => (string)($targetProfile['f_nama'] ?? $targetProfile['f_nickname'] ?? $targetLoginId),
            'group_id' => (int)($targetProfile['f_groupID'] ?? 0),
            'group_kod' => (string)($targetProfile['f_groupKod'] ?? ''),
            'group_name' => (string)($targetProfile['f_groupName'] ?? ''),
            'category' => (string)($targetProfile['f_categoryUser'] ?? ''),
            'department_code' => (string)($targetProfile['f_jabatanKod'] ?? ''),
            'department_name' => (string)($targetProfile['f_namajabatan'] ?? ''),
        ];

        $_SESSION['impersonation'] = [
            'active' => true,
            'mode' => $mode,
            'reason' => $reason,
            'started_at' => date('Y-m-d H:i:s'),
            'actor' => $actor,
            'target' => $target,
            'actor_session' => impersonation_safe_session_snapshot(),
        ];

        impersonation_apply_profile_to_session($targetProfile, $pdo);
        impersonation_audit('IMPERSONATION_START', 'SUCCESS', $target, $actor, ['reason' => $reason, 'mode' => $mode]);

        return ['actor' => $actor, 'target' => $target];
    }
}

if (!function_exists('impersonation_stop')) {
    function impersonation_stop(PDO $pdo, string $reason = 'manual_stop'): array
    {
        if (!impersonation_is_active()) {
            throw new RuntimeException('IMPERSONATION_NOT_ACTIVE');
        }

        $state = impersonation_state();
        $actor = impersonation_actor();
        $target = impersonation_target();
        impersonation_audit('IMPERSONATION_STOP', 'SUCCESS', $target, $actor, ['reason' => trim($reason) ?: 'manual_stop']);

        $snapshot = is_array($state['actor_session'] ?? null) ? $state['actor_session'] : [];
        unset($_SESSION['impersonation']);
        impersonation_restore_session_snapshot($snapshot);
        unset($_SESSION['page_access_map_' . (int)($_SESSION['group_active_id'] ?? 0)]);

        return ['actor' => $actor, 'target' => $target];
    }
}

if (!function_exists('impersonation_write_guard')) {
    function impersonation_write_guard(): void
    {
        if (!impersonation_is_active()) {
            return;
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_ends_with($script, '/ajax/impersonation-stop.php')) {
            return;
        }

        $state = impersonation_state();
        $actor = impersonation_actor();
        $target = impersonation_target();
        $route = ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $meta = [
            'method' => $method,
            'route' => $route,
            'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'mode' => impersonation_mode(),
            'reason' => (string)($state['reason'] ?? ''),
        ];

        if (impersonation_mode() === 'support_action') {
            impersonation_audit('IMPERSONATION_WRITE_REQUEST', 'ATTEMPT', $target, $actor, $meta);
            return;
        }

        impersonation_audit('IMPERSONATION_WRITE_BLOCKED', 'DENIED', $target, $actor, $meta);

        $message = (string)(__('impersonation_view_only_blocked') ?: 'View As mode is read-only. Stop View As before making changes.');
        if (function_exists('request_is_ajax_like') && request_is_ajax_like()) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (function_exists('set_alert')) {
            set_alert([
                'type' => 'sweet',
                'icon' => 'warning',
                'title' => 'View As',
                'text' => $message,
                'confirm' => true,
                'position' => 'center',
                'is_key' => false,
                'title_is_key' => false,
                'text_is_key' => false,
                'confirmText' => 'OK',
                'confirmText_is_key' => false,
            ]);
        }

        $fallback = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($fallback === '' || str_contains($fallback, '/ajax/')) {
            $fallback = function_exists('base_url') ? base_url('pages/dashboard.php') : '../pages/dashboard.php';
        }
        if (!headers_sent()) {
            header('Location: ' . $fallback, true, 302);
        }
        exit;
    }
}
