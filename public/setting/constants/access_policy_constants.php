<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

if (defined('ACCESS_POLICY_CONSTANTS_INCLUDED')) {
    return;
}
define('ACCESS_POLICY_CONSTANTS_INCLUDED', true);

/**
 * Logged-in pages that should remain accessible to any authenticated user.
 */
if (!defined('ACCESS_POLICY_PUBLIC_PAGE_ALLOWLIST')) {
    define('ACCESS_POLICY_PUBLIC_PAGE_ALLOWLIST', [
        'pages/dashboard.php',
        'pages/profile.php',
        'pages/soalan-lazim.php',
    ]);
}

/**
 * Sensitive pages that must remain super-admin-only even if another group
 * accidentally receives menu access to the same path.
 */
if (!defined('ACCESS_POLICY_SUPER_ADMIN_ONLY_PAGES')) {
    define('ACCESS_POLICY_SUPER_ADMIN_ONLY_PAGES', [
        'pages/access-matrix.php',
        'pages/audit-center.php',
        'pages/carian-pelajar.php',
        'pages/kumpulan-pengguna.php',
        'pages/senarai-pengguna.php',
        'pages/template-emel.php',
        'pages/template-generator.php',
        'pages/tetapan-sistem.php',
    ]);
}

/**
 * Pages with their own additional role/business guard inside the page/controller.
 * These pages still require menu access, but are documented separately so access
 * policy remains auditable and easier to extend later.
 */
if (!defined('ACCESS_POLICY_CUSTOM_GUARD_PAGES')) {
    define('ACCESS_POLICY_CUSTOM_GUARD_PAGES', [
        'pages/manage-manuals.php',
    ]);
}

/**
 * Authenticated AJAX endpoints that are safe for any logged-in user.
 */
if (!defined('ACCESS_POLICY_PUBLIC_LOGGED_IN_AJAX')) {
    define('ACCESS_POLICY_PUBLIC_LOGGED_IN_AJAX', [
        'ajax/profile-audit-event-meta.php',
        'ajax/profile-audit-events.php',
        'ajax/profile-kill-session.php',
        'ajax/profile-login-activity.php',
        'ajax/impersonation-stop.php',
        'ajax/role-switch-roles.php',
        'ajax/role-switch.php',
        'ajax/session-keepalive.php',
        'ajax/track-event.php',
    ]);
}

/**
 * Sensitive AJAX endpoints that must remain super-admin-only.
 */
if (!defined('ACCESS_POLICY_SUPER_ADMIN_ONLY_AJAX')) {
    define('ACCESS_POLICY_SUPER_ADMIN_ONLY_AJAX', [
        'ajax/audit-center-action.php',
        'ajax/audit-center-export.php',
        'ajax/audit-center-meta.php',
        'ajax/audit-center-panel.php',
        'ajax/email-template-preview.php',
        'ajax/email-template-test-send.php',
        'ajax/group-access.php',
        'ajax/group-create.php',
        'ajax/group-delete.php',
        'ajax/group-list.php',
        'ajax/group-perms-get.php',
        'ajax/group-perms-save.php',
        'ajax/impersonation-start.php',
        'ajax/menu-create.php',
        'ajax/menu-delete.php',
        'ajax/menu-flag-toggle.php',
        'ajax/menu-get.php',
        'ajax/menu-list.php',
        'ajax/menu-save.php',
        'ajax/menu-swap.php',
        'ajax/modul-list.php',
        'ajax/module-delete.php',
        'ajax/module-reorder.php',
        'ajax/module-update.php',
        'ajax/system-resources.php',
        'ajax/uji-emel.php',
        'ajax/user-add-public.php',
        'ajax/user-add-student.php',
        'ajax/user-add.php',
        'ajax/user-delete.php',
        'ajax/user-extra-roles.php',
        'ajax/user-list-rows.php',
        'ajax/user-list-staf-options.php',
        'ajax/user-list-student-options.php',
        'ajax/user-search-pelajar.php',
        'ajax/user-search-staf.php',
        'ajax/user-set-group.php',
        'ajax/user-sync-sybase.php',
        'ajax/user-update-public.php',
    ]);
}

/**
 * AJAX endpoints with their own additional business guard.
 */
if (!defined('ACCESS_POLICY_CUSTOM_GUARD_AJAX')) {
    define('ACCESS_POLICY_CUSTOM_GUARD_AJAX', [
        'ajax/manual-delete.php',
        'ajax/manual-sync-groups.php',
        'ajax/manual-upload.php',
        'ajax/manual-view.php',
    ]);
}

/**
 * Action endpoints that may be used by authenticated workflows outside admin pages.
 */
if (!defined('ACCESS_POLICY_AUTHENTICATED_ACTIONS')) {
    define('ACCESS_POLICY_AUTHENTICATED_ACTIONS', [
    ]);
}
