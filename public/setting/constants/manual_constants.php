<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *//**
 * Constants and helpers for User Manual management flow.
 *
 * Scope:
 * - Access checks for manual management pages and AJAX endpoints
 * - Shared role-code source of truth for manual admin permissions
 */

if (defined('MANUAL_CONSTANTS_INCLUDED')) {
    return;
}
define('MANUAL_CONSTANTS_INCLUDED', true);

if (!defined('MANUAL_ROLE_KOD_ADM_SA')) {
    $manualRoleSa = $_ENV['MANUAL_ROLE_KOD_ADM_SA'] ?? getenv('MANUAL_ROLE_KOD_ADM_SA');
    define('MANUAL_ROLE_KOD_ADM_SA', (string)(is_string($manualRoleSa) && trim($manualRoleSa) !== '' ? trim($manualRoleSa) : 'ADM-SA'));
}

if (!defined('MANUAL_ROLE_KOD_ADM_PE')) {
    $manualRolePe = $_ENV['MANUAL_ROLE_KOD_ADM_PE'] ?? getenv('MANUAL_ROLE_KOD_ADM_PE');
    define('MANUAL_ROLE_KOD_ADM_PE', (string)(is_string($manualRolePe) && trim($manualRolePe) !== '' ? trim($manualRolePe) : 'ADM-PE'));
}

if (!defined('MANUAL_ALLOWED_ADMIN_ROLE_CODES')) {
    define('MANUAL_ALLOWED_ADMIN_ROLE_CODES', [
        MANUAL_ROLE_KOD_ADM_SA,
        MANUAL_ROLE_KOD_ADM_PE,
    ]);
}

if (!function_exists('manual_allowed_admin_roles')) {
    function manual_allowed_admin_roles(): array
    {
        return array_values(array_filter(array_map('strval', MANUAL_ALLOWED_ADMIN_ROLE_CODES)));
    }
}

if (!function_exists('manual_is_admin_role')) {
    function manual_is_admin_role(?string $roleKod): bool
    {
        $roleKod = trim((string)$roleKod);
        if ($roleKod === '') {
            return false;
        }

        return in_array($roleKod, manual_allowed_admin_roles(), true);
    }
}

if (!function_exists('manual_active_role_code')) {
    function manual_active_role_code(PDO $pdo): string
    {
        $activeGroupId = (int)($_SESSION['group_active_id'] ?? 0);
        if ($activeGroupId <= 0) {
            return '';
        }
        $stmt = $pdo->prepare('SELECT f_groupKod FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1');
        $stmt->execute([':gid' => $activeGroupId]);
        return trim((string)($stmt->fetchColumn() ?: ''));
    }
}

if (!function_exists('manual_can_manage')) {
    function manual_can_manage(PDO $pdo): bool
    {
        return manual_is_admin_role(manual_active_role_code($pdo));
    }
}
