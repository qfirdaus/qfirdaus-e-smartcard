<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
/**
 * SidebarController
 * 
 * Handles business logic for sidebar navigation component.
 * Manages user profile, modules, menus, and active page detection.
 * 
 * @package e-prestasi
 * @author UPNM, Seksyen Aplikasi Digital, BTMK
 */

declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Modul.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
require_once __DIR__ . '/../includes/functions-db.php';

/**
 * SidebarController class
 * 
 * Provides methods to retrieve sidebar data including:
 * - User profile information
 * - Accessible modules and menus
 * - Active module detection
 * - Caching support
 */
class SidebarController
{
    private PDO $pdoMysql;
    private User $userModel;
    private Modul $modulModel;
    private string $lang;
    private ?string $groupKod;
    private ?int $groupId;
    private array $profile;
    private array $senaraiModul;
    private array $modulMenus;
    private ?int $modulAktifID;
    private string $operationalMode;

    /**
     * Constructor
     * 
     * @param PDO|null $pdoMysql Optional PDO connection (uses singleton if not provided)
     */
    public function __construct(?PDO $pdoMysql = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->pdoMysql = $pdoMysql ?: Database::getInstance()->getConnection();
        $this->userModel = new User($this->pdoMysql);
        $this->modulModel = new Modul($this->pdoMysql);
        $this->lang = $_SESSION['lang'] ?? SystemConfigConstants::DEFAULT_LANGUAGE;
        $this->groupKod = null;
        $this->groupId = null;
        $this->profile = [];
        $this->senaraiModul = [];
        $this->modulMenus = [];
        $this->modulAktifID = null;
        $this->operationalMode = function_exists('get_sybase_operational_mode')
            ? (string)get_sybase_operational_mode()
            : SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE;
    }

    /**
     * Get user profile data
     * 
     * @return array User profile array
     */
    public function getProfile(): array
    {
        return $this->profile;
    }

    /**
     * Get list of accessible modules
     * 
     * @return array Array of modules
     */
    public function getSenaraiModul(): array
    {
        return $this->senaraiModul;
    }

    /**
     * Get menus grouped by modulID
     * 
     * @return array Associative array: [modulID => [menus...]]
     */
    public function getModulMenus(): array
    {
        return $this->modulMenus;
    }

    /**
     * Get active modul ID
     * 
     * @return int|null Active modul ID or null if none
     */
    public function getModulAktifID(): ?int
    {
        return $this->modulAktifID;
    }

    /**
     * Get language code
     * 
     * @return string Language code (ms, en, ta, zh)
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * Get group code
     * 
     * @return string|null Group code or null
     */
    public function getGroupKod(): ?string
    {
        return $this->groupKod;
    }

    /**
     * Get group ID
     *
     * @return int|null Group ID or null
     */
    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    /**
     * Search menus already filtered for the active role and operational mode.
     *
     * @return array<int,array{name:string,module:string,subgroup:string,path:string,icon:string}>
     */
    public function searchAccessibleMenus(string $query, int $limit = 12): array
    {
        $query = trim($query);
        $limit = max(1, min($limit, 20));
        if ($query === '' || strlen($query) > 80) {
            return [];
        }

        $lower = static fn(string $value): string => function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
        $needle = $lower($query);
        $modulesById = [];
        foreach ($this->senaraiModul as $module) {
            $moduleId = (int)($module['f_modulID'] ?? 0);
            if ($moduleId > 0) {
                $modulesById[$moduleId] = $module;
            }
        }

        $matches = [];
        foreach ($this->modulMenus as $moduleId => $menus) {
            $module = $modulesById[(int)$moduleId] ?? [];
            $moduleName = trim((string)($module['modulName'] ?? ''));
            $moduleIcon = (string)($module['f_icon'] ?? 'ri-folder-fill');

            foreach ((array)$menus as $menu) {
                $menuName = trim((string)($menu['menuName'] ?? ''));
                $subgroupName = trim((string)($menu['subgroupName'] ?? ''));
                $menuPath = $this->sanitizeMenuPath((string)($menu['f_path'] ?? ''));
                if ($menuName === '' || $menuPath === null || $menuPath === '') {
                    continue;
                }

                $menuNameLower = $lower($menuName);
                $moduleNameLower = $lower($moduleName);
                $subgroupNameLower = $lower($subgroupName);
                $menuPosition = strpos($menuNameLower, $needle);
                $modulePosition = strpos($moduleNameLower, $needle);
                $subgroupPosition = strpos($subgroupNameLower, $needle);
                if ($menuPosition === false && $modulePosition === false && $subgroupPosition === false) {
                    continue;
                }

                $score = $menuPosition === 0 ? 0 : ($menuPosition !== false ? 1 : 2);
                $matches[] = [
                    'name' => $menuName,
                    'module' => $moduleName,
                    'subgroup' => $subgroupName,
                    'path' => $menuPath,
                    'icon' => preg_match('/^ri-[a-z0-9-]+$/', $moduleIcon) === 1
                        ? $moduleIcon
                        : 'ri-folder-fill',
                    '_score' => $score,
                ];
            }
        }

        usort($matches, static function (array $left, array $right): int {
            $scoreCompare = ((int)$left['_score'] <=> (int)$right['_score']);
            return $scoreCompare !== 0
                ? $scoreCompare
                : strcasecmp((string)$left['name'], (string)$right['name']);
        });

        return array_map(static function (array $match): array {
            unset($match['_score']);
            return $match;
        }, array_slice($matches, 0, $limit));
    }

    /**
     * Load all sidebar data
     * 
     * This method loads user profile, modules, menus, and detects active module.
     * Uses caching to improve performance.
     * 
     * @param string $currentPath Current page relative path (e.g., 'pages/dashboard.php')
     * @return void
     */
    public function loadSidebarData(string $currentPath): void
    {
        try {
            // Load user profile
            $this->loadUserProfile();
            
            // Load group access
            $akses = $this->loadGroupAccess();
            
            // Extract access arrays
            $modulAccess = $this->parseCsvIds((string)($akses['f_modulAccess'] ?? ''));
            $menuAccess = $this->parseCsvIds((string)($akses['f_menuAccess'] ?? ''));
            
            // Load modules and menus (with caching)
            $this->loadModulesAndMenus($modulAccess, $menuAccess);
            
            // Detect active module
            $this->detectActiveModul($currentPath);
            
        } catch (Throwable $e) {
            error_log("SidebarController: Error loading sidebar data: " . $e->getMessage());
            // Continue with empty state
        }
    }

    /**
     * Load user profile from database
     * 
     * @return void
     */
    private function loadUserProfile(): void
    {
        $f_loginID = $_SESSION['f_loginID'] ?? null;
        $f_stafID = $_SESSION['f_stafID'] ?? null;
        
        if (!$f_loginID && !$f_stafID) {
            $this->profile = [];
            return;
        }

        try {
            if ($f_loginID) {
                $this->profile = $this->userModel->getProfileByLoginID((string)$f_loginID);
            } else {
                $this->profile = $this->userModel->getProfile((string)$f_stafID);
            }
            if (!is_array($this->profile)) {
                $this->profile = [];
            } else {
                if (!empty($this->profile['f_loginID']) && empty($_SESSION['f_loginID'])) {
                    $_SESSION['f_loginID'] = (string)$this->profile['f_loginID'];
                }
                $defaultGroupId = isset($this->profile['f_groupID']) ? (int)$this->profile['f_groupID'] : null;
                $activeGroupId = isset($_SESSION['group_active_id']) ? (int)$_SESSION['group_active_id'] : 0;
                $this->groupId = ($activeGroupId > 0) ? $activeGroupId : $defaultGroupId;
                $this->groupKod = $this->profile['f_groupKod'] ?? null; // display/logging only
            }
        } catch (Throwable $e) {
            $identityForLog = $f_loginID ?: $f_stafID;
            error_log("SidebarController: Failed to get user profile for identity={$identityForLog}: " . $e->getMessage());
            $this->profile = [];
        }
    }

    /**
     * Load group access permissions
     * 
     * @return array Access array with f_modulAccess and f_menuAccess
     */
    private function loadGroupAccess(): array
    {
        if (!$this->groupId || $this->groupId <= 0) {
            return [];
        }

        try {
            $stmt = $this->pdoMysql->prepare(
                "SELECT f_modulAccess, f_menuAccess 
                 FROM tbl_m_group 
                 WHERE f_groupID = :groupId 
                 LIMIT 1"
            );
            $stmt->execute(['groupId' => $this->groupId]);
            $akses = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (is_array($akses) && !empty($akses)) {
                return $akses;
            }

            // Fallback safety: if active role in session is stale/invalid, reset to user's default role.
            $defaultGroupId = isset($this->profile['f_groupID']) ? (int)$this->profile['f_groupID'] : 0;
            if ($defaultGroupId > 0 && $defaultGroupId !== $this->groupId) {
                $stmtDefault = $this->pdoMysql->prepare(
                    "SELECT f_modulAccess, f_menuAccess
                     FROM tbl_m_group
                     WHERE f_groupID = :groupId
                     LIMIT 1"
                );
                $stmtDefault->execute(['groupId' => $defaultGroupId]);
                $fallback = $stmtDefault->fetch(PDO::FETCH_ASSOC) ?: [];
                if (is_array($fallback) && !empty($fallback)) {
                    $_SESSION['group_active_id'] = $defaultGroupId;
                    $this->groupId = $defaultGroupId;
                    return $fallback;
                }
            }

            return [];
        } catch (PDOException $e) {
            error_log("SidebarController: Failed to get group access for groupId={$this->groupId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Load modules and menus with caching
     * 
     * @param array $modulAccess Array of allowed modul IDs
     * @param array $menuAccess Array of allowed menu IDs
     * @return void
     */
    private function loadModulesAndMenus(array $modulAccess, array $menuAccess): void
    {
        // Check cache
        $cacheKey = $this->getCacheKey();
        $cachedData = $this->getCache($cacheKey);
        
        if ($cachedData !== null && is_array($cachedData)) {
            $cachedModuls = $cachedData['moduls'] ?? [];
            $cachedMenus = $cachedData['menus'] ?? [];

            // Do not trust stale empty cache when access list is present.
            if (!(empty($cachedModuls) && !empty($modulAccess))) {
                $this->senaraiModul = $cachedModuls;
                $this->modulMenus = is_array($cachedMenus) ? $cachedMenus : [];
                return;
            }
        }

        // Load from database
        try {
            $this->senaraiModul = $this->modulModel->getAllModulByGroup($modulAccess, $this->lang);
            
            // Batch load all menus (fix N+1 query)
            if (!empty($this->senaraiModul)) {
                $modulIDs = array_column($this->senaraiModul, 'f_modulID');
                $this->modulMenus = $this->modulModel->getAllMenusByModulIDs($modulIDs, $menuAccess, $this->lang);
                $this->applyOperationalMenuFilter();
            } else {
                $this->modulMenus = [];
            }
            
            // Cache the results
            $this->setCache($cacheKey, [
                'moduls' => $this->senaraiModul,
                'menus' => $this->modulMenus
            ]);
            
        } catch (Throwable $e) {
            error_log("SidebarController: Failed to load modules/menus: " . $e->getMessage());
            $this->senaraiModul = [];
            $this->modulMenus = [];
        }
    }

    /**
     * Detect which module is currently active based on current file
     * 
     * @param string $currentPath Current page relative path
     * @return void
     */
    private function detectActiveModul(string $currentPath): void
    {
        $normalizedCurrentPath = $this->normalizeCurrentPath($currentPath);
        $currentBasename = basename($normalizedCurrentPath !== '' ? $normalizedCurrentPath : $currentPath);

        foreach ($this->senaraiModul as $modul) {
            $modulID = (int)$modul['f_modulID'];
            $childs = $this->modulMenus[$modulID] ?? [];
            
            foreach ($childs as $menu) {
                $menuPath = $this->sanitizeMenuPath($menu['f_path'] ?? '');
                if ($menuPath && $this->menuMatchesCurrentPath($normalizedCurrentPath, $currentBasename, $menuPath)) {
                    $this->modulAktifID = $modulID;
                    return; // Found, exit early
                }
            }
        }
    }

    /**
     * Get cache key for sidebar data
     * 
     * @return string Cache key
     */
    private function getCacheKey(): string
    {
        return 'sidebar:v3:' . md5((string)($this->groupId ?? 'guest') . '_' . $this->lang . '_' . $this->operationalMode);
    }

    /**
     * Apply runtime menu visibility policy based on operational mode.
     *
     * Current policy:
     * - When mode is `staff_only`, menus with f_show_staff_only = 0 are hidden.
     * - In other modes, menus follow normal access rules.
     *
     * @return void
     */
    private function applyOperationalMenuFilter(): void
    {
        if ($this->operationalMode !== 'staff_only') {
            return;
        }

        $filteredMenus = [];
        $allowedModulIds = [];

        foreach ($this->modulMenus as $modulID => $menus) {
            $menus = is_array($menus) ? $menus : [];
            $visibleMenus = array_values(array_filter($menus, static function ($menu): bool {
                $showStaffOnly = isset($menu['f_show_staff_only']) ? (int)$menu['f_show_staff_only'] : 1;
                return $showStaffOnly === 1;
            }));

            if (!empty($visibleMenus)) {
                $filteredMenus[$modulID] = $visibleMenus;
                $allowedModulIds[(int)$modulID] = true;
            }
        }

        $this->modulMenus = $filteredMenus;
        $this->senaraiModul = array_values(array_filter(
            $this->senaraiModul,
            static fn(array $modul): bool => isset($allowedModulIds[(int)($modul['f_modulID'] ?? 0)])
        ));
    }

    /**
     * Get cached sidebar data
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null
     */
    private function getCache(string $key)
    {
        // Try APCu first
        if (function_exists('apcu_fetch')) {
            try {
                $ok = false;
                $v = apcu_fetch($key, $ok);
                if ($ok) return $v;
            } catch (Throwable $e) {
                error_log("SidebarController: Cache fetch error: " . $e->getMessage());
            }
        }
        
        // Fallback to session cache
        if (isset($_SESSION['sidebar_cache'][$key])) {
            $cached = $_SESSION['sidebar_cache'][$key];
            if (is_array($cached) && isset($cached['ts'], $cached['data'])) {
                $ttl = SystemConfigConstants::CACHE_TTL_SIDEBAR;
                if ((time() - $cached['ts']) < $ttl) {
                    return $cached['data'];
                }
                unset($_SESSION['sidebar_cache'][$key]);
            }
        }
        
        return null;
    }

    /**
     * Set cache for sidebar data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @return void
     */
    private function setCache(string $key, mixed $data): void
    {
        $ttl = SystemConfigConstants::CACHE_TTL_SIDEBAR;
        
        // Try APCu first
        if (function_exists('apcu_store')) {
            try {
                apcu_store($key, $data, $ttl);
            } catch (Throwable $e) {
                error_log("SidebarController: Cache store error: " . $e->getMessage());
            }
        }
        
        // Fallback to session cache
        if (!isset($_SESSION['sidebar_cache'])) {
            $_SESSION['sidebar_cache'] = [];
        }
        $_SESSION['sidebar_cache'][$key] = [
            'ts' => time(),
            'data' => $data
        ];
    }

    /**
     * Sanitize menu path to prevent path traversal
     * 
     * @param string $path Menu path from database
     * @return string|null Sanitized path or null if invalid
     */
    private function sanitizeMenuPath(string $path): ?string
    {
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
     * Normalize request path into `pages/...` form for sidebar matching.
     */
    private function normalizeCurrentPath(string $currentPath): string
    {
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

    /**
     * Build equivalent path variants so `folder` and `folder/index.php` match.
     */
    private function buildPathVariants(string $path, bool $defaultToPages = false): array
    {
        $normalized = $this->normalizeCurrentPath($path);
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

    /**
     * Match a menu path against the current request with full-path priority.
     */
    private function menuMatchesCurrentPath(string $normalizedCurrentPath, string $currentBasename, string $menuPath): bool
    {
        $currentCandidates = $this->buildPathVariants($normalizedCurrentPath, false);
        $menuCandidates = $this->buildPathVariants($menuPath, true);
        if (empty($menuCandidates)) {
            return false;
        }

        foreach ($currentCandidates as $candidate) {
            if (in_array($candidate, $menuCandidates, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse CSV IDs safely.
     * - Empty string => []
     * - Ignores non-numeric/zero values
     * - Keeps unique positive integers
     */
    private function parseCsvIds(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }

        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $id = (int)$part;
            if ($id > 0 && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Get notification count for dashboard badge
     * 
     * Currently returns null (no notification system implemented).
     * Can be extended to query actual notification count.
     * 
     * @return int|null Notification count or null if not available
     */
    public function getNotificationCount(): ?int
    {
        // TODO: Implement actual notification count query
        // Example:
        // $stmt = $this->pdoMysql->prepare("SELECT COUNT(*) FROM tbl_notifications WHERE f_userID = :userID AND f_read = 0");
        // $stmt->execute(['userID' => $_SESSION['f_userID'] ?? null]);
        // return (int)$stmt->fetchColumn();
        
        return null; // No notifications for now
    }
}
