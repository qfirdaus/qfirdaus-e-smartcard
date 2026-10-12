<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-list-rows.php
// Return structured user rows for AJAX reload.
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    ob_start();
    require_once __DIR__ . '/../includes/init.php';
    $initOutput = ob_get_clean();
    require_once __DIR__ . '/_helpers.php';
    logAjaxUnexpectedOutput('user-list-rows:init.php', $initOutput);

    if (empty($_SESSION['f_stafID'])) {
        jsonErrorResponse((string)(__('unauthorized_access') ?: 'Sila log masuk terlebih dahulu.'), 401);
    }

    $_GET['manual_sync'] = true;

    ob_start();
    require_once __DIR__ . '/../controllers/UserListController.php';
    require_once __DIR__ . '/../classes/User.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../setting/constants/prestasi_constants.php';
    $requireOutput = ob_get_clean();
    logAjaxUnexpectedOutput('user-list-rows:requires', $requireOutput);

    ob_start();
    $controller = new UserListController();
    $controllerOutput = ob_get_clean();
    logAjaxUnexpectedOutput('user-list-rows:controller', $controllerOutput);
    
    $senaraiUser = $controller->senaraiUser ?? [];
    
    // User model untuk getAvatarUrl dan permission check
    $pdo = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($pdo);
    $userModel = new User($pdo);
    
    // Helper function format_stafid (h() already exists in html_helper.php)
    if (!function_exists('format_stafid')) {
        function format_stafid(?string $id): string {
            $id = trim((string)$id);
            $raw = str_replace('-', '', $id);
            if ($raw !== '' && ctype_digit($raw) && strlen($raw) === 6) {
                return substr($raw,0,4) . '-' . substr($raw,4,2);
            }
            return $id;
        }
    }

    if (!function_exists('normalize_identity_value')) {
        function normalize_identity_value(?string $value): string {
            return normalizeIdentityValue($value);
        }
    }

    if (!function_exists('is_current_logged_in_user_target')) {
        function is_current_logged_in_user_target(
            int $targetUserId,
            string $targetStafId,
            string $targetNoPekerja,
            int $currentUserId,
            string $currentUserStafIdNormalized,
            string $currentUserNoPekerjaNormalized
        ): bool {
            if ($currentUserId > 0 && $targetUserId > 0 && $targetUserId === $currentUserId) {
                return true;
            }

            $normalizedTargetStafId = normalize_identity_value($targetStafId);
            if ($currentUserStafIdNormalized !== '' && $normalizedTargetStafId !== '' && $normalizedTargetStafId === $currentUserStafIdNormalized) {
                return true;
            }

            $normalizedTargetNoPekerja = normalize_identity_value($targetNoPekerja);
            if ($currentUserNoPekerjaNormalized !== '' && $normalizedTargetNoPekerja !== '' && $normalizedTargetNoPekerja === $currentUserNoPekerjaNormalized) {
                return true;
            }

            return false;
        }
    }

    // Get current user's group for permission control
    $currentLoginID = $_SESSION['f_loginID'] ?? '';
    $currentStafID = $_SESSION['f_stafID'] ?? '';
    $currentProfile = $currentLoginID !== ''
        ? ($userModel->getProfileByLoginID((string)$currentLoginID) ?: [])
        : ($userModel->getProfile((string)$currentStafID) ?: []);
    $isADM_SA = $currentProfile && function_exists('is_user_super_admin') && is_user_super_admin($currentProfile, $pdo);
    $currentUserId = (int)($currentProfile['f_userID'] ?? 0);
    $currentUserStafIdNormalized = normalize_identity_value((string)($currentProfile['f_stafID'] ?? $currentStafID));
    $currentUserNoPekerjaNormalized = normalize_identity_value((string)($currentProfile['f_nopekerja'] ?? ''));

    // Group style map (data-driven): centralized helper
    $groupUiMaps = ['by_id' => [], 'by_code' => []];
    try {
        $groupUiMaps = prestasi_group_ui_load_maps($pdo);
    } catch (Throwable $e) {
        error_log('[user-list-rows] Group style map load failed: ' . $e->getMessage());
    }
    
    // Generate structured rows
    $rows = [];
    if (!empty($senaraiUser)) {
        foreach ($senaraiUser as $u) {
            $userID  = (int)($u['f_userID'] ?? 0);
            $nama    = (string)($u['f_nama'] ?? '');
            $loginID = trim((string)($u['f_loginID'] ?? ''));
            $stafID  = format_stafid((string)($u['f_stafID'] ?? ''));
            $nickname = trim((string)($u['f_nickname'] ?? ''));
            $email = trim((string)($u['f_email'] ?? ''));
            $phone = trim((string)($u['f_handphone'] ?? ''));
            $nokp = trim((string)($u['f_nokp'] ?? ''));
            $jabatan = (string)($u['f_namajabatan'] ?? '');
            $jawatan = (string)($u['f_jawatan'] ?? '');
            $gId     = (int)($u['f_groupID'] ?? 0);
            $gKod    = (string)($u['f_groupKod'] ?? '');
            $gName   = (string)($u['f_groupName'] ?? $gKod);
            $extraRoles = $u['extra_roles'] ?? [];
            if (!is_array($extraRoles)) $extraRoles = [];
            $extraCount = (int)($u['extra_roles_count'] ?? count($extraRoles));
            $f_flag  = (int)($u['f_flag'] ?? 1);
            $f_nopekerja = (string)($u['f_nopekerja'] ?? '');
            $avatarUrl = $userModel->resolveAvatarUrl($u);
            $isAutoProvisioned = (int)($u['f_isAutoProvisioned'] ?? 0) === 1;
            $identitySource = strtoupper(trim((string)($u['f_identitySource'] ?? '')));
            $isCurrentLoggedInUser = is_current_logged_in_user_target(
                $userID,
                $stafID,
                $f_nopekerja,
                $currentUserId,
                $currentUserStafIdNormalized,
                $currentUserNoPekerjaNormalized
            );
            $isProtectedAccount = isProtectedStaffAccount($stafID);
            $canManageProtectedSelf = canSelfManageProtectedStaffAccount($stafID);
            $isTargetSuperAdmin = strtoupper(trim($gKod)) === 'ADM-SA';
            $canEditGroup = function_exists('userListCanEditTargetUser')
                ? userListCanEditTargetUser($pdo, $u, $currentProfile)
                : ($isADM_SA && (!$isProtectedAccount || $canManageProtectedSelf));
            $canDeleteUser = (function_exists('userListCanDeleteTargetUser')
                ? userListCanDeleteTargetUser($pdo, $u, $currentProfile)
                : $isADM_SA) && !$isCurrentLoggedInUser && !$isProtectedAccount;
            $canViewAsUser = $isADM_SA && !$isCurrentLoggedInUser && !$isProtectedAccount && !$isTargetSuperAdmin && $f_flag === 1 && $loginID !== '';
            
            $style = prestasi_group_ui_resolve($groupUiMaps, $gId, $gKod);
            $badgeClass = (string)($style['badgeClass'] ?? 'bg-secondary');
            $rowClass = (string)($style['rowClass'] ?? '');
            $rowColor = (string)($style['rowColor'] ?? '');

            $rows[] = [
                'f_userID' => $userID,
                'f_nama' => $nama,
                'f_loginID' => $loginID,
                'f_stafID' => $stafID,
                'f_nickname' => $nickname,
                'f_email' => $email,
                'f_handphone' => $phone,
                'f_nokp' => $nokp,
                'f_categoryUser' => (string)($u['f_categoryUser'] ?? ''),
                'f_isAutoProvisioned' => $isAutoProvisioned ? 1 : 0,
                'f_identitySource' => $identitySource,
                'f_namajabatan' => $jabatan,
                'f_jawatan' => $jawatan,
                'f_groupID' => $gId,
                'f_groupKod' => $gKod,
                'f_groupName' => $gName,
                'f_badge_class' => $badgeClass,
                'f_row_class' => $rowClass,
                'f_row_color' => $rowColor,
                'extra_roles' => $extraRoles,
                'extra_roles_count' => $extraCount,
                'f_flag' => $f_flag,
                'f_nopekerja' => $f_nopekerja,
                'avatarUrl' => $avatarUrl,
                'is_current_logged_in_user' => $isCurrentLoggedInUser,
                'is_protected_account' => $isProtectedAccount,
                'can_edit_group' => $canEditGroup,
                'can_delete_user' => $canDeleteUser,
                'can_view_as_user' => $canViewAsUser
            ];
        }
    }
    
    jsonSuccessResponse([
        'rows' => $rows,
        'count' => count($senaraiUser)
    ]);
    
} catch (Throwable $e) {
    error_log("[user-list-rows] Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonErrorResponse((string)__('userList_ajax_system_error'), 500);
}
