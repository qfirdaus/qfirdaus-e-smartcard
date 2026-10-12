<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Group.php';

/** Build the complete, read-only system access matrix in bulk. */
class AccessController
{
    public string $lang = 'ms';
    public array $profile = [];

    private PDO $pdo;
    private Group $groupModel;
    private array $matrix = ['roles' => [], 'modules' => [], 'totals' => []];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->lang = in_array(strtolower((string)($_SESSION['lang'] ?? 'ms')), ['ms', 'en'], true)
            ? strtolower((string)$_SESSION['lang'])
            : 'ms';
        $this->pdo = Database::getInstance('mysql')->getConnection();
        $this->groupModel = new Group($this->pdo);

        $staffId = $_SESSION['f_stafID'] ?? null;
        if ($staffId) {
            $this->profile = (new User($this->pdo))->getProfile($staffId) ?? [];
        }
        $themeSettings = json_decode((string)($this->profile['f_themeSetting'] ?? '{}'), true);
        if (!is_array($themeSettings)) {
            $themeSettings = [];
        }
        $_SESSION['theme.menu'] = $themeSettings['sidebarColor'] ?? ($_SESSION['theme.menu'] ?? 'light');
        $_SESSION['theme.topbar'] = $themeSettings['topbarColor'] ?? ($_SESSION['theme.topbar'] ?? 'light');
        $_SESSION['theme.layout'] = $themeSettings['layoutMode'] ?? ($_SESSION['theme.layout'] ?? 'light');

        $this->matrix = $this->buildMatrix();
        $this->auditView();
    }

    public function getMatrix(): array
    {
        return $this->matrix;
    }

    private function buildMatrix(): array
    {
        $groups = $this->groupModel->getAll();
        $roles = [];
        $groupAccess = [];

        foreach ($groups as $group) {
            $groupId = (int)($group['f_groupID'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }
            $code = trim((string)($group['f_groupKod'] ?? ''));
            $name = trim((string)($group['f_groupName'] ?? ''));
            $roles[] = [
                'id' => $groupId,
                'kod' => $code,
                'nama' => $name !== '' ? $name : $code,
            ];
            $groupAccess[$groupId] = [
                'modules' => array_fill_keys($this->csvToIds((string)($group['f_modulAccess'] ?? '')), true),
                'menus' => array_fill_keys($this->csvToIds((string)($group['f_menuAccess'] ?? '')), true),
                'explicit_menus' => trim((string)($group['f_menuAccess'] ?? '')) !== '',
            ];
        }

        $moduleName = $this->lang === 'en' ? 'f_modulName_en' : 'f_modulName_ms';
        $menuName = $this->lang === 'en' ? 'f_menuName_en' : 'f_menuName_ms';
        $modulesStmt = $this->pdo->query(
            "SELECT f_modulID AS id, COALESCE(NULLIF($moduleName, ''), f_modulName_ms) AS nama
             FROM tbl_m_modul
             ORDER BY f_order ASC, f_modulID ASC"
        );
        $menusStmt = $this->pdo->query(
             "SELECT f_menuID AS id, f_modulID AS modul_id,
                    COALESCE(NULLIF($menuName, ''), f_menuName_ms) AS nama, f_path AS path
             FROM tbl_m_menu
             WHERE COALESCE(f_flag, 1) = 1
             ORDER BY f_modulID ASC, f_order ASC, f_menuID ASC"
        );

        $modules = [];
        foreach ($modulesStmt->fetchAll(PDO::FETCH_ASSOC) as $module) {
            $moduleId = (int)$module['id'];
            $modules[$moduleId] = [
                'id' => $moduleId,
                'nama' => (string)$module['nama'],
                'menus' => [],
            ];
        }

        $permissionCount = 0;
        foreach ($menusStmt->fetchAll(PDO::FETCH_ASSOC) as $menu) {
            $moduleId = (int)($menu['modul_id'] ?? 0);
            if (!isset($modules[$moduleId])) {
                $modules[$moduleId] = ['id' => $moduleId, 'nama' => 'Module #' . $moduleId, 'menus' => []];
            }

            $menuId = (int)$menu['id'];
            $permissions = [];
            foreach ($roles as $role) {
                $roleId = (int)$role['id'];
                $access = $groupAccess[$roleId];
                $allowed = $access['explicit_menus']
                    ? isset($access['menus'][$menuId])
                    : isset($access['modules'][$moduleId]);
                $permissions[$roleId] = $allowed;
                if ($allowed) {
                    $permissionCount++;
                }
            }

            $modules[$moduleId]['menus'][] = [
                'id' => $menuId,
                'nama' => (string)$menu['nama'],
                'path' => (string)($menu['path'] ?? ''),
                'perms' => $permissions,
            ];
        }

        $modules = array_values(array_filter($modules, static fn(array $module): bool => $module['menus'] !== []));
        $menuCount = array_sum(array_map(static fn(array $module): int => count($module['menus']), $modules));

        return [
            'roles' => $roles,
            'modules' => $modules,
            'totals' => [
                'roles' => count($roles),
                'modules' => count($modules),
                'menus' => $menuCount,
                'permissions' => $permissionCount,
            ],
        ];
    }

    private function csvToIds(string $csv): array
    {
        $ids = [];
        foreach (explode(',', $csv) as $value) {
            $value = trim($value);
            if ($value !== '' && ctype_digit($value) && (int)$value > 0) {
                $ids[(int)$value] = (int)$value;
            }
        }
        return array_values($ids);
    }

    private function auditView(): void
    {
        try {
            require_once __DIR__ . '/../setting/helper/audit_helper.php';
            if (!function_exists('audit_event')) {
                return;
            }
            $totals = $this->matrix['totals'];
            audit_event([
                'event_type' => 'AUDIT_READ',
                'severity' => 'INFO',
                'outcome' => 'SUCCESS',
                'target_type' => 'access_matrix',
                'target_label' => 'System access matrix',
                'message' => 'System access matrix viewed',
                'user_id' => $_SESSION['f_stafID'] ?? null,
                'session_id' => session_id(),
                'meta' => [
                    'role_count' => $totals['roles'] ?? 0,
                    'module_count' => $totals['modules'] ?? 0,
                    'menu_count' => $totals['menus'] ?? 0,
                ],
            ]);
        } catch (Throwable $exception) {
            error_log('[AccessController] Audit error: ' . $exception->getMessage());
        }
    }
}
