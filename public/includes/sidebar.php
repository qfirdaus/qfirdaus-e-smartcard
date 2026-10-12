<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *//**
 * Sidebar Navigation Component
 * 
 * Displays user profile, modules, and menu items based on user access.
 * Uses SidebarController for business logic and implements security validations.
 * 
 * @package e-prestasi
 * @author UPNM, Seksyen Aplikasi Digital, BTMK
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../controllers/SidebarController.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../setting/function.php'; // untuk base_path(), base_url()
require_once __DIR__ . '/../setting/helper/config_helper.php';
require_once __DIR__ . '/../setting/helper/access_helper.php';
require_once __DIR__ . '/../includes/functions-db.php';

/**
 * Validate icon class against whitelist
 * 
 * @param string $icon Icon class name
 * @return string Validated icon class (default: 'ri-folder-fill')
 */
function validate_sidebar_icon(string $icon): string {
    $icon = trim($icon);
    if ($icon !== '' && preg_match('/^ri-[a-z0-9-]+$/', $icon) === 1) {
        return $icon;
    }
    $allowed = SystemConfigConstants::ALLOWED_SIDEBAR_ICONS;
    return in_array($icon, $allowed, true) ? $icon : 'ri-folder-fill';
}

/**
 * Sanitize menu path to prevent path traversal
 * 
 * @param string $path Menu path from database
 * @return string|null Sanitized path or null if invalid
 */
function sanitize_menu_path(string $path): ?string {
    // Remove any path traversal attempts
    if (str_contains($path, '..') || str_contains($path, '//')) {
        return null;
    }
    
    // Remove leading/trailing slashes and whitespace
    $path = trim($path);
    $path = ltrim($path, '/');
    
    // Only allow alphanumeric, dash, underscore, dot, and forward slash
    if (!preg_match('/^[a-zA-Z0-9_\-.\/]+$/', $path)) {
        return null;
    }
    
    // Limit path length
    if (strlen($path) > 255) {
        return null;
    }
    
    return $path;
}

/**
 * Detect if a menu item is active based on current page path
 * 
 * @param string $currentPath Current page relative path (e.g., 'pages/dashboard.php')
 * @param string $menuPath Menu path from database
 * @return bool True if menu is active, false otherwise
 */
function normalize_sidebar_current_path(string $currentPath): string {
    $currentPath = trim(str_replace('\\', '/', $currentPath));
    if ($currentPath === '') {
        return '';
    }

    $currentPath = ltrim($currentPath, '/');
    $publicPos = stripos($currentPath, 'public/');
    if ($publicPos !== false) {
        $currentPath = substr($currentPath, $publicPos + 7);
    }

    return strtolower($currentPath);
}

function sidebar_path_match_variants(string $path, bool $defaultToPages = false): array {
    $normalized = normalize_sidebar_current_path($path);
    if ($normalized === '') {
        return [];
    }

    $variants = [$normalized];
    if ($defaultToPages && !preg_match('#^(pages|ajax|actions)/#', $normalized)) {
        $variants[] = 'pages/' . ltrim($normalized, '/');
    }

    $expanded = [];
    foreach ($variants as $variant) {
        $variant = rtrim($variant, '/');
        if ($variant === '') {
            continue;
        }

        $expanded[] = $variant;

        if (str_ends_with($variant, '/index.php')) {
            $expanded[] = substr($variant, 0, -10);
        } elseif (!str_ends_with($variant, '.php')) {
            $expanded[] = $variant . '/index.php';
        }
    }

    return array_values(array_unique(array_filter($expanded, static fn($item) => $item !== '')));
}

function is_menu_active(string $currentPath, string $menuPath): bool {
    $sanitizedPath = sanitize_menu_path($menuPath);
    if (!$sanitizedPath) {
        return false;
    }

    $currentCandidates = sidebar_path_match_variants($currentPath, false);
    $menuCandidates = sidebar_path_match_variants($sanitizedPath, true);

    foreach ($currentCandidates as $candidate) {
        if (in_array($candidate, $menuCandidates, true)) {
            return true;
        }
    }
    
    return false;
}

function sidebar_link_active_class(string $currentPath, string $menuPath): string {
    return is_menu_active($currentPath, $menuPath) ? ' active' : '';
}

function sanitize_sidebar_user_image(?string $path): string {
    $default = 'assets/images/small/small-5.jpg';
    $path = trim((string)$path);

    if ($path === '') {
        return $default;
    }

    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    $path = preg_replace('#^public/#i', '', $path);

    if (!preg_match('#^assets/images/small/[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp|gif)$#i', $path)) {
        return $default;
    }

    $fullPath = realpath(__DIR__ . '/../' . $path);
    $baseDir = realpath(__DIR__ . '/../assets/images/small');

    if (!$fullPath || !$baseDir || strncmp($fullPath, $baseDir, strlen($baseDir)) !== 0 || !is_file($fullPath)) {
        return $default;
    }

    return str_replace('\\', '/', $path);
}

function sidebar_safe_dom_id(string $prefix, int|string $id): string {
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$id) ?? '';
    $safe = trim($safe, '-');
    return $prefix . ($safe !== '' ? $safe : 'item');
}

function sidebar_group_menus_by_subgroup(array $menus): array {
    $directMenus = [];
    $subgroups = [];
    $sequence = 0;

    foreach ($menus as $menu) {
        $sequence++;
        $subgroupId = (int)($menu['f_subgroupID'] ?? $menu['subgroupID'] ?? 0);
        $subgroupName = trim((string)($menu['subgroupName'] ?? $menu['subgroup_name'] ?? $menu['f_subgroupName_ms'] ?? $menu['f_subgroupName_en'] ?? ''));

        if ($subgroupId <= 0) {
            $menu['_sidebar_order'] = (int)($menu['f_order'] ?? 99999);
            $menu['_sidebar_sequence'] = $sequence;
            $directMenus[] = $menu;
            continue;
        }

        if ($subgroupName === '') {
            $subgroupName = 'Subgroup ' . $subgroupId;
        }

        if (!isset($subgroups[$subgroupId])) {
            $subgroups[$subgroupId] = [
                'id' => $subgroupId,
                'name' => $subgroupName,
                'icon' => validate_sidebar_icon((string)($menu['subgroupIcon'] ?? $menu['subgroup_icon'] ?? 'ri-folder-2-line')),
                'order' => (int)($menu['subgroupOrder'] ?? $menu['subgroup_order'] ?? 0),
                'sequence' => $sequence,
                'menus' => [],
            ];
        }

        $subgroups[$subgroupId]['menus'][] = $menu;
    }

    uasort($subgroups, static function (array $a, array $b): int {
        $orderCompare = ($a['order'] <=> $b['order']);
        return $orderCompare !== 0 ? $orderCompare : ($a['id'] <=> $b['id']);
    });

    $items = [];
    foreach ($directMenus as $menu) {
        $items[] = [
            'type' => 'menu',
            'order' => (int)($menu['_sidebar_order'] ?? 99999),
            'sequence' => (int)($menu['_sidebar_sequence'] ?? 0),
            'menu' => $menu,
        ];
    }
    foreach ($subgroups as $subgroup) {
        $menuOrders = array_map(
            static fn(array $menu): int => (int)($menu['f_order'] ?? 99999),
            is_array($subgroup['menus'] ?? null) ? $subgroup['menus'] : []
        );
        $fallbackOrder = $menuOrders !== [] ? min($menuOrders) : 99999;
        $items[] = [
            'type' => 'subgroup',
            'order' => (int)($subgroup['order'] ?: $fallbackOrder),
            'sequence' => (int)($subgroup['sequence'] ?? 0),
            'subgroup' => $subgroup,
        ];
    }

    usort($items, static function (array $a, array $b): int {
        $orderCompare = ((int)$a['order'] <=> (int)$b['order']);
        return $orderCompare !== 0 ? $orderCompare : ((int)$a['sequence'] <=> (int)$b['sequence']);
    });

    return [
        'direct' => $directMenus,
        'subgroups' => array_values($subgroups),
        'items' => $items,
    ];
}

function sidebar_enrich_menus_with_subgroups(array $menus, string $lang = 'ms', ?int $modulID = null): array {
    $menuIds = [];
    $menuPaths = [];
    foreach ($menus as $menu) {
        $menuId = (int)($menu['f_menuID'] ?? $menu['menuID'] ?? $menu['id'] ?? 0);
        if ($menuId > 0) {
            $menuIds[$menuId] = true;
            continue;
        }

        $menuPath = sanitize_menu_path((string)($menu['f_path'] ?? $menu['path'] ?? ''));
        if ($menuPath !== null && $menuPath !== '') {
            $menuPaths[$menuPath] = true;
        }
    }

    if ($menuIds === [] && ($modulID === null || $modulID <= 0 || $menuPaths === [])) {
        return $menus;
    }

    try {
        $pdo = Database::getInstance('mysql')->getConnection();
        $ids = array_keys($menuIds);
        $placeholders = [];
        $params = [];
        foreach ($ids as $idx => $id) {
            $key = ':menu_' . $idx;
            $placeholders[] = $key;
            $params[$key] = (int)$id;
        }

        $pathPlaceholders = [];
        if ($menuIds === [] && $modulID !== null && $modulID > 0) {
            foreach (array_keys($menuPaths) as $idx => $path) {
                $key = ':path_' . $idx;
                $pathPlaceholders[] = $key;
                $params[$key] = $path;
            }
            $params[':modulID'] = (int)$modulID;
        }

        $nameField = strtolower($lang) === 'en' ? 'sg.f_subgroupName_en' : 'sg.f_subgroupName_ms';
        $whereSql = $placeholders !== []
            ? 'm.f_menuID IN (' . implode(',', $placeholders) . ')'
            : 'm.f_modulID = :modulID AND m.f_path IN (' . implode(',', $pathPlaceholders) . ')';
        $sql = "
            SELECT
                m.f_menuID,
                m.f_path,
                COALESCE(m.f_subgroupID, 0) AS f_subgroupID,
                COALESCE(NULLIF($nameField, ''), NULLIF(sg.f_subgroupName_ms, ''), NULLIF(sg.f_subgroupName_en, ''), CONCAT('Subgroup ', m.f_subgroupID)) AS subgroupName,
                COALESCE(sg.f_icon, 'ri-folder-2-line') AS subgroupIcon,
                COALESCE(sg.f_order, 0) AS subgroupOrder
            FROM tbl_m_menu m
            LEFT JOIN tbl_m_menu_subgroup sg
                ON sg.f_subgroupID = m.f_subgroupID
               AND sg.f_modulID = m.f_modulID
               AND COALESCE(sg.f_status, 1) = 1
            WHERE {$whereSql}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $menuId = (int)($row['f_menuID'] ?? 0);
            if ($menuId > 0) {
                $map[$menuId] = $row;
            }

            $rowPath = sanitize_menu_path((string)($row['f_path'] ?? ''));
            if ($rowPath !== null && $rowPath !== '') {
                $map['path:' . $rowPath] = $row;
            }
        }

        foreach ($menus as &$menu) {
            $menuId = (int)($menu['f_menuID'] ?? $menu['menuID'] ?? $menu['id'] ?? 0);
            $menuPath = sanitize_menu_path((string)($menu['f_path'] ?? $menu['path'] ?? ''));
            $mapKey = $menuId > 0 ? $menuId : ($menuPath !== null && $menuPath !== '' ? 'path:' . $menuPath : null);
            if ($mapKey === null || !isset($map[$mapKey])) {
                continue;
            }

            $row = $map[$mapKey];
            $menu['f_subgroupID'] = (int)($row['f_subgroupID'] ?? 0);
            $menu['subgroupName'] = (string)($row['subgroupName'] ?? '');
            $menu['subgroupIcon'] = (string)($row['subgroupIcon'] ?? 'ri-folder-2-line');
            $menu['subgroupOrder'] = (int)($row['subgroupOrder'] ?? 0);
        }
        unset($menu);
    } catch (Throwable $e) {
        error_log('[sidebar] Failed to enrich subgroup metadata: ' . $e->getMessage());
    }

    return $menus;
}

/**
 * Check if profile data is empty or invalid
 * 
 * @param array $profile Profile data array
 * @return bool True if profile is empty/invalid, false otherwise
 */
function is_profile_empty(array $profile): bool {
    return empty($profile) || 
           (empty($profile['f_loginID']) && empty($profile['f_stafID']) && empty($profile['f_nopekerja']) && empty($profile['f_nama']));
}

// Initialize controller and load sidebar data
$currentFile = isset($currentFile) && is_string($currentFile) && $currentFile !== ''
    ? $currentFile
    : (function_exists('prestasi_current_page_relative_path')
        ? prestasi_current_page_relative_path()
        : ($_SERVER['PHP_SELF'] ?? ''));
$sidebarController = new SidebarController();
$sidebarController->loadSidebarData($currentFile);

// Get data from controller
$profile = $sidebarController->getProfile();
$senaraiModul = $sidebarController->getSenaraiModul();
$modulMenus = $sidebarController->getModulMenus();
$modulAktifID = $sidebarController->getModulAktifID();
$lang = $sidebarController->getLang();

// Extract profile data with fallbacks
$isProfileEmpty = is_profile_empty($profile);
$namaPendek = 'Pengguna';
$avatarUrl = base_url('assets/images/no-image.jpg');
$perananLabel = 'Pengguna';
$profileMessage = null;

// Active role (session-based)
$activeGroupId = (int)($_SESSION['group_active_id'] ?? 0);

if (!$isProfileEmpty) {
    // Extract nickname or first name
    $namaPendek = $profile['f_nickname'] ?? '';
    if (empty($namaPendek) && !empty($profile['f_nama'])) {
        $namaPendek = explode(' ', $profile['f_nama'])[0];
    }
    if (empty($namaPendek)) {
        $namaPendek = 'Pengguna';
    }
    
    $avatarUrl = $profile['avatar_url'] ?? $profile['avatar'] ?? base_url('assets/images/no-image.jpg');
    
    $perananLabel = $profile['f_groupName'] ?? 'Pengguna';
    if ($activeGroupId > 0) {
        try {
            $stmtAct = Database::getInstance()->getConnection()->prepare("SELECT f_groupName FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
            $stmtAct->execute([':gid' => $activeGroupId]);
            $rowAct = $stmtAct->fetch(PDO::FETCH_ASSOC);
            if (!empty($rowAct['f_groupName'])) {
                $perananLabel = (string)$rowAct['f_groupName'];
            }
        } catch (Throwable $e) {
            // keep default label
        }
    }
} else {
    // Profile is empty - set fallback message
    $profileMessage = __('sidebar_profile_empty') ?: 'Profil tidak ditemui';
}

// Get theme settings
$sidebarColor = $_SESSION['theme.menu']
    ?? $_SESSION['theme.sidebar']
    ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR;

// Get notification count (optional - hide badge if null)
$notificationCount = $sidebarController->getNotificationCount();
$defaultHome = app_config('site.default_home', 'pages/dashboard.php');
$sidebarLogo = app_config('branding.sidebar_logo', 'assets/images/new-logo.png');
$sidebarUserImage = sanitize_sidebar_user_image(app_config('branding.sidebar_user_image', 'assets/images/small/small-5.jpg'));
$pdo = Database::getInstance()->getConnection();
$isSidebarSuperAdmin = function_exists('is_user_super_admin') ? is_user_super_admin($profile, $pdo) : false;
$manualMenuUrl = null;
if ($activeGroupId > 0) {
    try {
        $manualStmt = $pdo->prepare(
            "SELECT f_file_path FROM tbl_m_usermanual WHERE f_groupID = :gid LIMIT 1"
        );
        $manualStmt->execute([':gid' => $activeGroupId]);
        $manualRow = $manualStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $manualPath = trim((string)($manualRow['f_file_path'] ?? ''));
        $manualFullPath = $manualPath !== '' ? realpath(__DIR__ . '/../' . ltrim($manualPath, '/\\')) : false;
        $manualBaseDir = realpath(__DIR__ . '/../uploads/manuals');
        if ($manualPath !== '' && $manualFullPath && $manualBaseDir && strncmp($manualFullPath, $manualBaseDir, strlen($manualBaseDir)) === 0 && is_file($manualFullPath)) {
            $manualMenuUrl = base_url('ajax/manual-view.php?group_id=' . $activeGroupId);
        }
    } catch (Throwable $e) {
        $manualMenuUrl = null;
    }
}
?>

<!-- ========== Left Sidebar Start ========== -->
<div class="leftside-menu" id="leftside-menu" data-menu-color="<?= $sidebarColor ?>" data-sidebar-loaded="true">
<style>
/* Sidebar Loading State */
#leftside-menu[data-sidebar-loaded="false"] .sidebar-loading-overlay {
    display: flex;
}
#leftside-menu[data-sidebar-loaded="true"] .sidebar-loading-overlay {
    display: none;
}
.sidebar-loading-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(2px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10;
    flex-direction: column;
    gap: 12px;
}
html[data-bs-theme="dark"] .sidebar-loading-overlay {
    background: rgba(0, 0, 0, 0.75);
}
.sidebar-loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(0, 0, 0, 0.1);
    border-top-color: #0d6efd;
    border-radius: 50%;
    animation: sidebar-spin 0.8s linear infinite;
}
html[data-bs-theme="dark"] .sidebar-loading-spinner {
    border-color: rgba(255, 255, 255, 0.1);
    border-top-color: #0d6efd;
}
.sidebar-loading-text {
    font-size: 0.875rem;
    color: #6c757d;
    opacity: 0.8;
}
html[data-bs-theme="dark"] .sidebar-loading-text {
    color: #adb5bd;
}
@keyframes sidebar-spin {
    to { transform: rotate(360deg); }
}
/* Sidebar Chart Icon (Dashboard) */
.side-nav .sidebar-chart-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: calc(var(--ct-menu-item-font-size) * 1.1);
    color: var(--ct-menu-item-color);
    opacity: 0.6;
    transition: opacity 0.2s ease;
}
.side-nav .sidebar-chart-icon:hover {
    opacity: 1;
}
.side-nav .sidebar-manual-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: calc(var(--ct-menu-item-font-size) * 1.1);
    color: var(--ct-menu-item-color);
    opacity: 0.6;
    transition: opacity 0.2s ease;
}
.side-nav .sidebar-manual-icon:hover {
    opacity: 1;
}
/* Sidebar Logout Icon */
.side-nav .sidebar-logout-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: calc(var(--ct-menu-item-font-size) * 1.1);
    color: var(--bs-danger);
    opacity: 0.7;
    transition: opacity 0.2s ease;
}
.side-nav .sidebar-logout-icon:hover {
    opacity: 1;
}
.side-nav .side-nav-primary-link {
    position: relative;
    display: flex;
    align-items: center;
    width: calc(100% - 12px);
    margin: 0 6px;
    padding: var(--ct-menu-item-padding-y) calc(var(--ct-menu-item-padding-x) * 3) var(--ct-menu-item-padding-y) var(--ct-menu-item-padding-x);
    border-radius: 10px;
    transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}
.side-nav .side-nav-primary-link > i:first-child {
    flex: 0 0 auto;
    opacity: 0.9;
    transition: color 0.18s ease, opacity 0.18s ease, transform 0.18s ease;
}
.side-nav .side-nav-primary-link > span {
    flex: 1 1 auto;
    padding-right: calc(var(--ct-menu-item-padding-x) * 0.6);
}
.side-nav .side-nav-toggle-btn {
    position: relative;
    display: flex;
    align-items: center;
    width: calc(100% - 12px);
    margin: 0 6px;
    padding: var(--ct-menu-item-padding-y) calc(var(--ct-menu-item-padding-x) * 3) var(--ct-menu-item-padding-y) var(--ct-menu-item-padding-x);
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--ct-menu-item-color);
    text-align: left;
    box-shadow: none;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
    transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}
.side-nav .side-nav-toggle-btn > i:first-child {
    flex: 0 0 auto;
    opacity: 0.9;
    transition: color 0.18s ease, opacity 0.18s ease, transform 0.18s ease;
}
.side-nav .side-nav-toggle-btn > span {
    flex: 1 1 auto;
    padding-right: calc(var(--ct-menu-item-padding-x) * 0.6);
}
.side-nav .sidebar-parent-arrow {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    text-align: center;
    font-size: calc(var(--ct-menu-item-font-size) * 1.1);
    color: inherit;
    opacity: 0.72;
    transition: transform 0.15s ease, opacity 0.15s ease;
}
.side-nav .side-nav-toggle-btn:hover .sidebar-parent-arrow,
.side-nav .side-nav-toggle-btn:focus-visible .sidebar-parent-arrow {
    opacity: 1;
}
.side-nav .side-nav-item > .side-nav-toggle-btn[aria-expanded="true"] > .sidebar-parent-arrow {
    transform: translateY(-50%) rotate(90deg);
    opacity: 1;
}
.side-nav .side-nav-toggle-btn:hover,
.side-nav .side-nav-toggle-btn:focus-visible {
    color: var(--ct-menu-item-hover-color);
    background-color: rgba(255, 255, 255, 0.055);
    transform: translateX(1px);
}
.side-nav .side-nav-toggle-btn:focus {
    outline: none;
    box-shadow: none;
}
.side-nav .side-nav-item > .side-nav-toggle-btn[aria-expanded="true"] {
    color: var(--ct-menu-item-active-color);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.075), rgba(255, 255, 255, 0.025));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
}
.side-nav .side-nav-item.menuitem-active > .side-nav-toggle-btn {
    color: var(--ct-menu-item-active-color);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.018));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.028);
    font-weight: 400;
}
.side-nav .side-nav-item > .side-nav-primary-link.active {
    color: var(--ct-menu-item-active-color);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.018));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.028);
    font-weight: 400;
}
.side-nav .side-nav-item.menuitem-active > .side-nav-primary-link {
    color: var(--ct-menu-item-active-color);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.018));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.028);
    font-weight: 400;
}
.side-nav .side-nav-primary-link:hover,
.side-nav .side-nav-primary-link:focus-visible {
    color: var(--ct-menu-item-hover-color);
    background-color: rgba(255, 255, 255, 0.055);
    transform: translateX(1px);
}
.side-nav .side-nav-primary-link:hover > i:first-child,
.side-nav .side-nav-primary-link:focus-visible > i:first-child,
.side-nav .side-nav-toggle-btn:hover > i:first-child,
.side-nav .side-nav-toggle-btn:focus-visible > i:first-child,
.side-nav .side-nav-item.menuitem-active > .side-nav-primary-link > i:first-child,
.side-nav .side-nav-item.menuitem-active > .side-nav-toggle-btn > i:first-child {
    opacity: 1;
    transform: translateX(1px);
}
.side-nav .side-nav-item.menuitem-active > .side-nav-toggle-btn .sidebar-parent-arrow {
    opacity: 1;
}
.side-nav .side-nav-second-level {
    position: relative;
    padding-left: calc(var(--ct-menu-item-icon-width) - 12px);
    margin-top: 0.28rem;
    padding-top: 0.18rem;
    padding-bottom: 0.22rem;
}
.side-nav .side-nav-second-level::before {
    content: "";
    position: absolute;
    left: 0.55rem;
    top: 0.15rem;
    bottom: 0.25rem;
    width: 1px;
    border-radius: 999px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.08));
    opacity: 0.55;
}
.side-nav .side-nav-second-level li > a {
    display: flex;
    align-items: center;
    margin: 0.08rem 0.55rem 0.08rem 0.2rem;
    min-height: 36px;
    padding-top: 0.34rem;
    padding-bottom: 0.34rem;
    padding-left: calc(var(--ct-menu-item-padding-x) * 0.9);
    line-height: 1;
    border-radius: 8px;
    transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
}
.side-nav .side-nav-second-level li > a:hover {
    color: var(--ct-menu-item-hover-color);
    background-color: rgba(255, 255, 255, 0.045);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.02);
    transform: translateX(2px);
}
.side-nav .side-nav-second-level li.menuitem-active > a,
.side-nav .side-nav-second-level li > a.active {
    color: var(--ct-menu-item-active-color);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.09), rgba(255, 255, 255, 0.028));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
    padding-left: calc(var(--ct-menu-item-padding-x) * 1.45);
    font-weight: 400;
}
.side-nav .side-nav-second-level li.menuitem-active > a::before,
.side-nav .side-nav-second-level li > a.active::before {
    left: 0.55rem;
    top: 0.5rem;
    bottom: 0.5rem;
}
.side-nav .sidebar-subgroup-toggle {
    position: relative;
    display: flex;
    align-items: center;
    width: calc(100% - 12px);
    margin: 0.1rem 6px;
    min-height: 36px;
    padding: 0.34rem calc(var(--ct-menu-item-padding-x) * 3) 0.34rem calc(var(--ct-menu-item-padding-x) * 0.9);
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--ct-menu-item-color);
    text-align: left;
    line-height: 1;
    box-shadow: none;
    outline: none;
    transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
}
.side-nav .sidebar-subgroup-toggle:hover,
.side-nav .sidebar-subgroup-toggle:focus-visible {
    color: var(--ct-menu-item-hover-color);
    background-color: rgba(255, 255, 255, 0.045);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.02);
    transform: translateX(2px);
}
.side-nav .sidebar-subgroup-toggle:focus {
    outline: none;
    box-shadow: none;
}
.side-nav .sidebar-subgroup-toggle > i:first-child {
    flex: 0 0 auto;
    margin-right: 0.45rem;
    opacity: 0.82;
}
.side-nav .sidebar-subgroup-toggle > span {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.side-nav .sidebar-subgroup-arrow {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    text-align: center;
    color: inherit;
    opacity: 0.68;
    transition: transform 0.15s ease, opacity 0.15s ease;
}
.side-nav .sidebar-subgroup-toggle[aria-expanded="true"] {
    color: var(--ct-menu-item-active-color);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.02));
}
.side-nav .sidebar-subgroup-toggle[aria-expanded="true"] .sidebar-subgroup-arrow {
    transform: translateY(-50%) rotate(90deg);
    opacity: 1;
}
.side-nav .sidebar-subgroup-item.menuitem-active > .sidebar-subgroup-toggle {
    color: var(--ct-menu-item-active-color);
}
.side-nav .side-nav-third-level {
    position: relative;
    margin: 0.05rem 0 0.18rem;
    padding-left: calc(var(--ct-menu-item-icon-width) - 2px);
    padding-bottom: 0.12rem;
}
.side-nav .side-nav-third-level::before {
    content: "";
    position: absolute;
    left: 1.18rem;
    top: 0.05rem;
    bottom: 0.18rem;
    width: 1px;
    border-radius: 999px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.06));
    opacity: 0.5;
}
.side-nav .side-nav-third-level li > a {
    display: flex;
    align-items: center;
    margin: 0.06rem 0.55rem 0.06rem 0.2rem;
    min-height: 32px;
    padding: 0.3rem 0.55rem 0.3rem calc(var(--ct-menu-item-padding-x) * 0.85);
    border-radius: 8px;
    line-height: 1.05;
}
.side-nav .side-nav-third-level li > a:hover {
    color: var(--ct-menu-item-hover-color);
    background-color: rgba(255, 255, 255, 0.04);
    transform: translateX(2px);
}
.side-nav .side-nav-third-level li.menuitem-active > a,
.side-nav .side-nav-third-level li > a.active {
    color: var(--ct-menu-item-active-color);
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.09), rgba(255, 255, 255, 0.025));
}
.sidebar-menu-search {
    position: relative;
    z-index: 6;
    padding: 12px 14px 4px;
}
.sidebar-menu-search-field {
    position: relative;
    display: flex;
    align-items: center;
}
.sidebar-menu-search-field > .sidebar-search-leading {
    position: absolute;
    left: 12px;
    z-index: 1;
    color: var(--ct-menu-item-color);
    pointer-events: none;
}
.sidebar-menu-search-input {
    width: 100%;
    height: 38px;
    padding: 7px 38px 7px 36px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 9px;
    outline: none;
    background: rgba(255, 255, 255, 0.07);
    color: var(--ct-menu-item-active-color);
    font-size: 13px;
    transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease;
}
.sidebar-menu-search-input::placeholder {
    color: var(--ct-menu-item-color);
    opacity: .8;
}
.sidebar-menu-search-input:focus {
    border-color: rgba(var(--ct-primary-rgb), .75);
    background: rgba(255, 255, 255, 0.11);
    box-shadow: 0 0 0 3px rgba(var(--ct-primary-rgb), .14);
}
.sidebar-menu-search-clear {
    position: absolute;
    right: 6px;
    display: inline-flex;
    width: 28px;
    height: 28px;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: var(--ct-menu-item-color);
}
.sidebar-menu-search-clear:hover,
.sidebar-menu-search-clear:focus-visible {
    background: rgba(255, 255, 255, 0.1);
    color: var(--ct-menu-item-active-color);
}
.sidebar-menu-search-clear[hidden],
.sidebar-menu-search-results[hidden] {
    display: none !important;
}
.sidebar-menu-search-results {
    position: absolute;
    top: calc(100% + 4px);
    left: 14px;
    right: 14px;
    z-index: 20;
    max-height: 310px;
    overflow-y: auto;
    padding: 6px;
    border: 1px solid rgba(255, 255, 255, 0.13);
    border-radius: 10px;
    background: var(--ct-menu-bg);
    box-shadow: 0 14px 30px rgba(0, 0, 0, .28);
}
.sidebar-search-result {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 9px;
    padding: 9px 8px;
    border-radius: 7px;
    color: var(--ct-menu-item-color);
}
.sidebar-search-result:hover,
.sidebar-search-result:focus-visible,
.sidebar-search-result.is-active {
    background: rgba(255, 255, 255, .09);
    color: var(--ct-menu-item-active-color);
}
.sidebar-search-result > i:first-child {
    flex: 0 0 auto;
    font-size: 17px;
}
.sidebar-search-result-copy {
    display: flex;
    min-width: 0;
    flex: 1 1 auto;
    flex-direction: column;
}
.sidebar-search-result-copy strong,
.sidebar-search-result-copy small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sidebar-search-result-copy strong {
    color: inherit;
    font-size: 13px;
    font-weight: 600;
}
.sidebar-search-result-copy small {
    opacity: .72;
    font-size: 11px;
}
.sidebar-search-result-arrow {
    flex: 0 0 auto;
    opacity: .55;
}
.sidebar-search-message {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 8px;
    color: var(--ct-menu-item-color);
    font-size: 12px;
}
.sidebar-search-message.is-loading i {
    animation: sidebar-spin .8s linear infinite;
}
.sidebar-search-message.is-error {
    color: var(--ct-danger);
}
.sidebar-menu-search-compact {
    display: none;
    width: 42px;
    height: 42px;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    padding: 0;
    border: 0;
    border-radius: 9px;
    background: transparent;
    color: var(--ct-menu-item-color);
    font-size: 20px;
}
.sidebar-menu-search-compact:hover,
.sidebar-menu-search-compact:focus-visible {
    background: rgba(255, 255, 255, .09);
    color: var(--ct-menu-item-active-color);
}
html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .sidebar-menu-search {
    padding-right: 0;
    padding-left: 0;
}
html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .sidebar-menu-search-expanded {
    display: none;
}
html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .sidebar-menu-search-compact {
    display: flex;
}
</style>
<div class="sidebar-loading-overlay">
    <div class="sidebar-loading-spinner"></div>
    <div class="sidebar-loading-text"><?= __('sidebar_loading') ?: 'Memuatkan...' ?></div>
</div>
<script>
// Hide sidebar loading overlay once sidebar is loaded
(function() {
    const sidebar = document.getElementById('leftside-menu');
    if (sidebar && sidebar.getAttribute('data-sidebar-loaded') === 'true') {
        // Sidebar already loaded, hide overlay immediately
        const overlay = sidebar.querySelector('.sidebar-loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }
})();
</script>

    <!-- ✅ Logo Sidebar -->
    <a href="<?= base_path($defaultHome) ?>" class="logo logo-dark">
        <span class="logo-lg"><img src="<?= base_url($sidebarLogo) ?>" alt="logo" style="width: 200px; height: auto;"></span>
        <span class="logo-sm"><img src="<?= base_url($sidebarLogo) ?>" alt="small logo" style="height: 30px; width: auto;"></span>
    </a>

    <a href="<?= base_path($defaultHome) ?>" class="logo logo-light">
        <span class="logo-lg"><img src="<?= base_url($sidebarLogo) ?>" alt="logo" style="width: 200px; height: auto;"></span>
        <span class="logo-sm"><img src="<?= base_url($sidebarLogo) ?>" alt="small logo" style="height: 30px; width: auto;"></span>
    </a>

    <div class="h-100" id="leftside-menu-container" data-simplebar>

        <!-- ✅ Paparan Pengguna -->
        <div class="leftbar-user p-3 text-white" style="background-image:url('<?= htmlspecialchars(base_url($sidebarUserImage), ENT_QUOTES, 'UTF-8') ?>');">
            <?php if ($isProfileEmpty): ?>
                <!-- Fallback: Profile tidak ditemui -->
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle shadow d-flex align-items-center justify-content-center bg-white bg-opacity-10" style="width: 42px; height: 42px;">
                            <i class="ri-user-line fs-18"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <span class="fw-semibold fs-15 d-block"><?= htmlspecialchars($namaPendek) ?></span>
                        <span class="fs-12 text-white-50"><?= htmlspecialchars($profileMessage) ?></span>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= base_path('pages/profile.php') ?>" class="d-flex align-items-center text-reset">
                    <div class="flex-shrink-0">
                        <img src="<?= $avatarUrl ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.jpg') ?>';" alt="user-image" height="42" class="rounded-circle shadow">
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <span class="fw-semibold fs-15 d-block"><?= htmlspecialchars($namaPendek) ?></span>
                        <span class="fs-13"><?= htmlspecialchars($perananLabel) ?></span>
                    </div>
                    <div class="ms-auto">
                        <i class="ri-arrow-right-s-fill fs-20"></i>
                    </div>
                </a>
            <?php endif; ?>
        </div>

        <div class="sidebar-menu-search" id="sidebar-menu-search">
            <div class="sidebar-menu-search-expanded">
                <label for="sidebar-menu-search-input" class="visually-hidden"><?= htmlspecialchars(__('sidebar_search_label') ?: 'Cari menu', ENT_QUOTES, 'UTF-8') ?></label>
                <div class="sidebar-menu-search-field">
                    <i class="ri-search-line sidebar-search-leading" aria-hidden="true"></i>
                    <input type="search"
                           id="sidebar-menu-search-input"
                           class="sidebar-menu-search-input"
                           maxlength="80"
                           autocomplete="off"
                           placeholder="<?= htmlspecialchars(__('sidebar_search_placeholder') ?: 'Cari menu...', ENT_QUOTES, 'UTF-8') ?>"
                           role="combobox"
                           aria-autocomplete="list"
                           aria-controls="sidebar-menu-search-results"
                           aria-expanded="false">
                    <button type="button"
                            class="sidebar-menu-search-clear"
                            id="sidebar-menu-search-clear"
                            aria-label="<?= htmlspecialchars(__('sidebar_search_clear') ?: 'Kosongkan carian menu', ENT_QUOTES, 'UTF-8') ?>"
                            hidden>
                        <i class="ri-close-line" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="sidebar-menu-search-results"
                     id="sidebar-menu-search-results"
                     role="listbox"
                     aria-label="<?= htmlspecialchars(__('sidebar_search_label') ?: 'Cari menu', ENT_QUOTES, 'UTF-8') ?>"
                     hidden></div>
            </div>
            <button type="button"
                    class="sidebar-menu-search-compact"
                    id="sidebar-menu-search-compact"
                    aria-label="<?= htmlspecialchars(__('sidebar_search_open') ?: 'Buka carian menu', ENT_QUOTES, 'UTF-8') ?>"
                    title="<?= htmlspecialchars(__('sidebar_search_open') ?: 'Buka carian menu', ENT_QUOTES, 'UTF-8') ?>">
                <i class="ri-search-line" aria-hidden="true"></i>
            </button>
        </div>
        <script>
        window.sidebarMenuSearchConfig = <?= json_encode([
            'url' => base_url('ajax/sidebar-menu-search.php'),
            'i18n' => [
                'loading' => __('sidebar_search_loading') ?: 'Mencari...',
                'empty' => __('sidebar_search_empty') ?: 'Tiada menu yang sepadan.',
                'error' => __('sidebar_search_error') ?: 'Carian menu tidak dapat dilakukan.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        </script>
        <script src="<?= base_url('assets/js/sidebar-menu-search.js') ?>?v=<?= htmlspecialchars((string)($_ENV['APP_ASSET_VER'] ?? '1'), ENT_QUOTES, 'UTF-8') ?>" defer></script>

        <!-- ✅ Menu Sidebar -->
        <ul class="side-nav" style="padding-bottom: 70px;">

            <!-- Dashboard -->
            <li class="side-nav-title mt-1"><?= __('sidebar_main') ?></li>
            <?php $dashboardActive = is_menu_active($currentFile, $defaultHome); ?>
            <li class="side-nav-item<?= $dashboardActive ? ' menuitem-active' : '' ?>">
                <a href="<?= base_path($defaultHome) ?>" class="side-nav-link side-nav-primary-link<?= $dashboardActive ? ' active' : '' ?>">
                    <i class="ri-dashboard-fill"></i>
                    <span><?= __('sidebar_dashboard') ?></span>
                    <i class="ri-bar-chart-line sidebar-chart-icon" title="<?= __('sidebar_dashboard_stats') ?: 'Statistik' ?>"></i>
                </a>
            </li>
            <?php if (!empty($manualMenuUrl)): ?>
            <li class="side-nav-item">
                <a href="<?= htmlspecialchars($manualMenuUrl, ENT_QUOTES, 'UTF-8') ?>" class="side-nav-link side-nav-primary-link" target="_blank" rel="noopener">
                    <i class="ri-book-open-line"></i>
                    <span><?= __('sidebar_user_manual') ?: 'Manual Pengguna' ?></span>
                    <i class="ri-external-link-line sidebar-manual-icon" title="<?= __('sidebar_user_manual') ?: 'Manual Pengguna' ?>"></i>
                </a>
            </li>
            <?php endif; ?>
            <!-- Modul Sistem -->
            <li class="side-nav-title mt-2"><?= __('sidebar_modul') ?></li>
            <?php foreach ($senaraiModul as $modul): 
                $modulID = (int)$modul['f_modulID'];
                $modulId = 'sidebarModul' . $modulID;
                
                // ✅ VALIDATE ICON CLASS
                $icon = validate_sidebar_icon($modul['f_icon'] ?? 'ri-folder-fill');
                
                $nama = htmlspecialchars($modul['modulName'] ?? '', ENT_QUOTES, 'UTF-8');
                
                // ✅ USE BATCH LOADED MENUS (no N+1 query)
                $childs = $modulMenus[$modulID] ?? [];
                if (empty($childs)) continue;
                $childs = sidebar_enrich_menus_with_subgroups($childs, $lang, $modulID);
                $menuGroups = sidebar_group_menus_by_subgroup($childs);

                $isActive     = ($modulID === $modulAktifID);
                $collapseCls  = $isActive ? 'collapse show' : 'collapse';
                $linkCls      = 'side-nav-link' . ($isActive ? '' : ' collapsed');
                $ariaExpanded = $isActive ? 'true' : 'false';
            ?>
                <li class="side-nav-item<?= $isActive ? ' menuitem-active' : '' ?>">
                    <button type="button"
                       data-sidebar-toggle="true"
                       data-sidebar-target="#<?= $modulId ?>"
                       class="<?= $linkCls ?> side-nav-toggle-btn<?= $isActive ? ' active' : '' ?>"
                       aria-expanded="<?= $ariaExpanded ?>"
                       aria-controls="<?= $modulId ?>">
                        <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
                        <span><?= $nama ?></span>
                        <i class="ri-arrow-right-s-line sidebar-parent-arrow" aria-hidden="true"></i>
                    </button>
                    <div class="<?= $collapseCls ?>" id="<?= $modulId ?>">
                        <ul class="side-nav-second-level">
                            <?php foreach ($menuGroups['items'] as $sidebarItem): ?>
                                <?php if (($sidebarItem['type'] ?? '') === 'menu'):
                                    $menu = is_array($sidebarItem['menu'] ?? null) ? $sidebarItem['menu'] : [];
                                    $menuPath = sanitize_menu_path($menu['f_path'] ?? '');
                                    if (!$menuPath) continue;

                                    $menuActive = is_menu_active($currentFile, $menuPath);
                                    $menuHref = base_path('pages/' . $menuPath);
                                    $menuName = htmlspecialchars($menu['menuName'] ?? '-', ENT_QUOTES, 'UTF-8');
                                ?>
                                    <li class="<?= $menuActive ? 'menuitem-active' : '' ?>">
                                        <a class="<?= $menuActive ? 'active' : '' ?>"
                                           href="<?= htmlspecialchars($menuHref, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $menuName ?>
                                        </a>
                                    </li>
                                <?php elseif (($sidebarItem['type'] ?? '') === 'subgroup'):
                                    $subgroup = is_array($sidebarItem['subgroup'] ?? null) ? $sidebarItem['subgroup'] : [];
                                    $subgroupMenus = is_array($subgroup['menus'] ?? null) ? $subgroup['menus'] : [];
                                    if (empty($subgroupMenus)) continue;

                                    $subgroupActive = false;
                                    foreach ($subgroupMenus as $subgroupMenu) {
                                        $subgroupMenuPath = sanitize_menu_path($subgroupMenu['f_path'] ?? '');
                                        if ($subgroupMenuPath && is_menu_active($currentFile, $subgroupMenuPath)) {
                                            $subgroupActive = true;
                                            break;
                                        }
                                    }

                                    $subgroupDomId = sidebar_safe_dom_id('sidebarSubgroup' . $modulID . '-', (int)$subgroup['id']);
                                    $subgroupCollapseCls = $subgroupActive ? 'collapse show' : 'collapse';
                                    $subgroupLinkCls = 'sidebar-subgroup-toggle' . ($subgroupActive ? '' : ' collapsed');
                                    $subgroupExpanded = $subgroupActive ? 'true' : 'false';
                                    $subgroupName = htmlspecialchars((string)$subgroup['name'], ENT_QUOTES, 'UTF-8');
                                    $subgroupIcon = validate_sidebar_icon((string)($subgroup['icon'] ?? 'ri-folder-2-line'));
                                ?>
                                    <li class="sidebar-subgroup-item<?= $subgroupActive ? ' menuitem-active' : '' ?>">
                                        <button type="button"
                                                data-sidebar-toggle="true"
                                                data-sidebar-level="subgroup"
                                                data-sidebar-target="#<?= htmlspecialchars($subgroupDomId, ENT_QUOTES, 'UTF-8') ?>"
                                                class="<?= htmlspecialchars($subgroupLinkCls, ENT_QUOTES, 'UTF-8') ?>"
                                                aria-expanded="<?= $subgroupExpanded ?>"
                                                aria-controls="<?= htmlspecialchars($subgroupDomId, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="<?= htmlspecialchars($subgroupIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                                            <span><?= $subgroupName ?></span>
                                            <i class="ri-arrow-right-s-line sidebar-subgroup-arrow" aria-hidden="true"></i>
                                        </button>
                                        <div class="<?= $subgroupCollapseCls ?>" id="<?= htmlspecialchars($subgroupDomId, ENT_QUOTES, 'UTF-8') ?>">
                                            <ul class="side-nav-third-level">
                                                <?php foreach ($subgroupMenus as $menu):
                                                    $menuPath = sanitize_menu_path($menu['f_path'] ?? '');
                                                    if (!$menuPath) continue;

                                                    $menuActive = is_menu_active($currentFile, $menuPath);
                                                    $menuHref = base_path('pages/' . $menuPath);
                                                    $menuName = htmlspecialchars($menu['menuName'] ?? '-', ENT_QUOTES, 'UTF-8');
                                                ?>
                                                    <li class="<?= $menuActive ? 'menuitem-active' : '' ?>">
                                                        <a class="<?= $menuActive ? 'active' : '' ?>"
                                                           href="<?= htmlspecialchars($menuHref, ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= $menuName ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach ?>
                                            </ul>
                                        </div>
                                    </li>
                                <?php endif ?>
                            <?php endforeach ?>
                        </ul>
                    </div>
                </li>
            <?php endforeach ?>

            <!-- Kawalan Sistem -->
            <li class="side-nav-title mt-2"><?= __('sidebar_kawalan') ?></li>
            <li class="side-nav-item">
                <a href="javascript:void(0);" onclick="return confirmLogout(event);" data-no-loader class="side-nav-link side-nav-primary-link text-danger">
                    <i class="ri-logout-box-r-fill"></i>
                    <span><?= __('sidebar_keluar') ?></span>
                    <i class="ri-logout-box-r-line sidebar-logout-icon" title="<?= __('sidebar_keluar') ?>"></i>
                </a>
            </li>

        </ul>
        <div class="clearfix"></div>
    </div>
</div>
<!-- ========== Left Sidebar End ========== -->
<script>
(function () {
    if (window.__SidebarCollapseFixInitialized) {
        return;
    }
    window.__SidebarCollapseFixInitialized = true;

    function syncSidebarToggleState(toggleEl, panelEl, isExpanded) {
        if (!toggleEl || !panelEl) {
            return;
        }

        toggleEl.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        toggleEl.classList.toggle('collapsed', !isExpanded);
        panelEl.classList.toggle('show', !!isExpanded);
    }

    function bindSidebarCollapseFix() {
        var sidebar = document.getElementById('leftside-menu');
        if (!sidebar) {
            return;
        }

        sidebar.addEventListener('click', function (event) {
            var toggleEl = event.target.closest(".side-nav li [data-sidebar-toggle='true']");
            if (!toggleEl || !sidebar.contains(toggleEl)) {
                return;
            }

            var targetSelector = toggleEl.getAttribute('data-sidebar-target') || '';
            if (!targetSelector || targetSelector.charAt(0) !== '#') {
                return;
            }

            var panelEl = document.querySelector(targetSelector);
            if (!panelEl) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var isExpanded = panelEl.classList.contains('show');

            if (isExpanded) {
                syncSidebarToggleState(toggleEl, panelEl, false);
                return;
            }

            var toggleLevel = toggleEl.getAttribute('data-sidebar-level') || 'module';
            var scopeSelector = toggleLevel === 'subgroup'
                ? '.side-nav-second-level'
                : '.side-nav';
            var containingList = toggleEl.closest(scopeSelector) || sidebar;
            var openPanels = containingList.querySelectorAll(':scope > li > .collapse.show');
            openPanels.forEach(function (openPanelEl) {
                if (openPanelEl === panelEl) {
                    return;
                }

                var openToggleEl = sidebar.querySelector(".side-nav li [data-sidebar-toggle='true'][data-sidebar-target='#" + openPanelEl.id + "']");
                syncSidebarToggleState(openToggleEl, openPanelEl, false);
            });

            syncSidebarToggleState(toggleEl, panelEl, true);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindSidebarCollapseFix, { once: true });
    } else {
        bindSidebarCollapseFix();
    }
})();
</script>
