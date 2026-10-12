<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/role-switch.php
// Switch active role for current session (group_active_id)
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('role_switch_trace_log')) {
    function role_switch_trace_log(string $message): void
    {
        $enabled = $_ENV['ACCESS_TRACE_LOG_ENABLED'] ?? getenv('ACCESS_TRACE_LOG_ENABLED');
        if (!is_string($enabled) || !in_array(strtolower(trim($enabled)), ['1', 'true', 'yes', 'on'], true)) {
            return;
        }

        $logDir = realpath(__DIR__ . '/../log') ?: (__DIR__ . '/../log');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $traceFile = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'access-trace.log';
        @file_put_contents($traceFile, '[' . date('Y-m-d H:i:s') . '] ' . trim($message) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/User.php';

    if (!isValidCsrfToken()) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => (string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Data tidak sah.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $groupID = (int)($data['groupID'] ?? 0);
    $requestedCurrentPath = prestasi_normalize_menu_path((string)($data['currentPath'] ?? ''));
    role_switch_trace_log(sprintf(
        '[role_switch] request group_id=%d session_active_before=%s session_default=%s login_id=%s staf_id=%s',
        $groupID,
        (string)($_SESSION['group_active_id'] ?? ''),
        (string)($_SESSION['group_default_id'] ?? ''),
        (string)($_SESSION['f_loginID'] ?? ''),
        (string)($_SESSION['f_stafID'] ?? '')
    ));

    if ($groupID <= 0) {
        http_response_code(422);
        echo json_encode(['error' => true, 'message' => 'Peranan tidak sah.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    $userModel = new User($pdo);
    $profile = [];
    $sessionLoginID = trim((string)($_SESSION['f_loginID'] ?? ''));
    $sessionStafID = trim((string)($_SESSION['f_stafID'] ?? ''));
    if ($sessionLoginID !== '') {
        $profile = $userModel->getProfileByLoginID($sessionLoginID) ?: [];
    }
    if (!$profile && $sessionStafID !== '') {
        $profile = $userModel->getProfile($sessionStafID) ?: [];
    }

    if (!$profile) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => 'Akses ditolak.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userID = (int)($profile['f_userID'] ?? $_SESSION['f_userID'] ?? 0);
    $stafID = (string)($profile['f_stafID'] ?? $_SESSION['f_stafID'] ?? '');
    $stafRaw = trim($stafID);
    $stafNorm = str_replace('-', '', $stafRaw);
    if ($userID <= 0 && $stafRaw === '' && $stafNorm === '') {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => 'Akses ditolak.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $defaultGroupId = (int)($profile['f_groupID'] ?? 0);
    if (!isset($_SESSION['group_default_id']) && $defaultGroupId > 0) {
        $_SESSION['group_default_id'] = $defaultGroupId;
    }
    // SECURITY CRITICAL – DO NOT MODIFY: role switch validation must enforce allowed roles
    // Allow switch to default role without tbl_ref_access
    if ($groupID !== $defaultGroupId) {
        // Validate role exists in tbl_ref_access for this user
        $stmt = $pdo->prepare("
            SELECT a.f_groupID
            FROM tbl_ref_access a
            WHERE a.f_status = 1
              AND a.f_groupID = :gid
              AND (
                a.f_userID = :uid
                OR (
                  :uid_zero = 0
                  AND (TRIM(a.f_stafID) = :staf OR REPLACE(TRIM(a.f_stafID), '-', '') = :staf_norm)
                )
              )
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userID, ':uid_zero' => $userID, ':staf' => $stafRaw, ':staf_norm' => $stafNorm, ':gid' => $groupID]);
        $ok = $stmt->fetchColumn();
        if (!$ok) {
            role_switch_trace_log(sprintf('[role_switch] denied requested_group=%d user_id=%d staf_id=%s', $groupID, $userID, $stafRaw));
            http_response_code(403);
            echo json_encode(['error' => true, 'message' => 'Peranan tidak dibenarkan untuk pengguna ini.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    $oldGroupId = (int)($_SESSION['group_active_id'] ?? 0);

    // SECURITY CRITICAL – DO NOT MODIFY: session active role drives access decisions
    $_SESSION['group_active_id'] = $groupID;
    role_switch_trace_log(sprintf(
        '[role_switch] applied old_group=%d new_group=%d session_active_after=%s default_group=%d',
        $oldGroupId,
        $groupID,
        (string)($_SESSION['group_active_id'] ?? ''),
        $defaultGroupId
    ));

    // Ensure subsequent partial UI refreshes read fresh access/sidebar state.
    clearSidebarNavigationCaches();
    unset($_SESSION['page_access_map_' . $oldGroupId], $_SESSION['page_access_map_' . $groupID]);

    $newGroupName = '';
    try {
        $stmtName = $pdo->prepare("SELECT f_groupName FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
        $stmtName->execute([':gid' => $groupID]);
        $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
        $newGroupName = (string)($rowName['f_groupName'] ?? '');
    } catch (Throwable $e) {
        $newGroupName = '';
    }

    $currentPagePath = $requestedCurrentPath !== '' ? $requestedCurrentPath : prestasi_current_page_relative_path();
    $currentPageAllowed = true;
    if ($currentPagePath !== '' && str_starts_with($currentPagePath, 'pages/')) {
        $policy = prestasi_resolve_page_access_policy($currentPagePath, $pdo);
        $isSuperAdmin = is_user_super_admin($profile, $pdo);
        if ($policy === 'super_admin_only') {
            $currentPageAllowed = $isSuperAdmin;
        } elseif ($policy === 'custom_guard') {
            $currentPageAllowed = false;
        } elseif ($policy === 'group_menu_based') {
            if ($isSuperAdmin) {
                $currentPageAllowed = true;
            } else {
                $allowedPaths = prestasi_load_allowed_page_paths($pdo, $groupID);
                $basename = basename($currentPagePath);
                $currentPageAllowed = isset($allowedPaths[$currentPagePath])
                    || ($basename !== '' && isset($allowedPaths[$basename]));
            }
        }
    }

    $redirectUrl = $currentPageAllowed
        ? null
        : base_url(app_config('site.default_home', 'pages/dashboard.php'));

    if ($currentPageAllowed) {
        unset($_SESSION['role_switch_success']);
    } else {
        $_SESSION['role_switch_success'] = [
            'group_id' => $groupID,
            'group_name' => $newGroupName,
        ];
    }

    // GOVERNANCE CRITICAL – DO NOT MODIFY: role switch must be auditable
    // Audit: Log role switch (session-only change)
    try {
        if (!function_exists('audit_event')) {
            $auditHelperPath = __DIR__ . '/../setting/helper/audit_helper.php';
            if (file_exists($auditHelperPath)) {
                require_once $auditHelperPath;
            }
        }
        if (function_exists('audit_event')) {
            $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;

            // Actor label
            $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
            $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
            $actorLabel = null;
            if (function_exists('audit_format_actor_label')) {
                $actorLabel = audit_format_actor_label($nama, $nostaf);
            } else {
                $actorLabel = $nama;
            }

            $oldGroupName = '';
            if ($oldGroupId > 0) {
                try {
                    $stmtOld = $pdo->prepare("SELECT f_groupName FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
                    $stmtOld->execute([':gid' => $oldGroupId]);
                    $rowOld = $stmtOld->fetch(PDO::FETCH_ASSOC);
                    $oldGroupName = (string)($rowOld['f_groupName'] ?? '');
                } catch (Throwable $e) {}
            }

            $message = function_exists('audit_format_message')
                ? audit_format_message('Role switched', $actorLabel)
                : ('Role switched' . ($actorLabel ? (' by ' . $actorLabel) : ''));

            audit_event([
                'event_type'  => 'UPDATE',
                'severity'    => 'INFO',
                'outcome'     => 'SUCCESS',
                'target_type' => 'role_switch',
                'target_id'   => (string)($_SESSION['f_stafID'] ?? ''),
                'target_label' => 'Role switch: ' . ($newGroupName !== '' ? $newGroupName : $groupID),
                'message'     => $message,
                'request_id'  => $requestId,
                'session_id'  => session_id() ?: null,
                'user_id'     => !empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja']) ? (int)$_SESSION['f_nopekerja'] : (!empty($_SESSION['user']['f_nopekerja']) && is_numeric($_SESSION['user']['f_nopekerja']) ? (int)$_SESSION['user']['f_nopekerja'] : null),
                'actor_label' => $actorLabel,
                'meta'        => [
                    'old_group_id' => $oldGroupId,
                    'old_group_name' => $oldGroupName,
                    'new_group_id' => $groupID,
                    'new_group_name' => $newGroupName,
                    'default_group_id' => $defaultGroupId
                ]
            ]);
        }
    } catch (Throwable $e) {
        error_log('[role-switch] Audit logging failed: ' . $e->getMessage());
    }

    $ui = buildAccessUiPayload($pdo, [
        'activeGroupId' => $groupID,
        'roleName' => $newGroupName,
        'currentFile' => $currentPagePath,
        'currentPagePath' => $currentPagePath,
        'currentPageAllowed' => $currentPageAllowed,
        'redirectUrl' => $redirectUrl,
        'includeSidebar' => $currentPageAllowed,
    ]);

    echo json_encode([
        'error' => false,
        'message' => 'Peranan berjaya dikemas kini.',
        'ui' => $ui,
        'active_group_id' => $groupID,
        'group_name' => $newGroupName,
        'current_page' => $currentPagePath,
        'current_page_allowed' => $currentPageAllowed,
        'redirect_url' => $redirectUrl,
        'html' => $ui['sidebar']['html'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    role_switch_trace_log('[role_switch] exception ' . $e->getMessage());
    error_log('[role-switch] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Ralat sistem semasa menukar peranan.'], JSON_UNESCAPED_UNICODE);
}
