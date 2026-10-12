<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

require_once __DIR__ . '/Group.php';
require_once __DIR__ . '/Modul.php';

final class AiChatbotSystemContext
{
    private const MAX_MODULES = 8;
    private const MAX_MENUS_PER_MODULE = 8;
    private const MAX_TOTAL_MENUS = 40;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function build(array $profile, array $actor = []): array
    {
        $groupId = (int)($actor['active_group_id'] ?? $_SESSION['group_active_id'] ?? ($profile['f_groupID'] ?? 0));
        if ($groupId <= 0) {
            return [];
        }

        $lang = $this->safeLang((string)($actor['lang'] ?? $_SESSION['lang'] ?? 'ms'));
        $currentPagePath = $this->safePath((string)($actor['current_page_path'] ?? ''));

        try {
            $groupModel = new Group($this->pdo);
            $modulModel = new Modul($this->pdo);
            $access = $groupModel->getAccessByGroupId($groupId);
            $moduleIds = $this->csvToIds((string)($access['f_modulAccess'] ?? ''));
            $menuIds = $this->csvToIds((string)($access['f_menuAccess'] ?? ''));

            if ($moduleIds === []) {
                return [];
            }

            $modules = $modulModel->getAllModulByGroup($moduleIds, $lang);
            $menusByModule = $modulModel->getAllMenusByModulIDs($moduleIds, $menuIds, $lang);

            return $this->shapeContext($modules, $menusByModule, $currentPagePath);
        } catch (Throwable $e) {
            error_log('[ai-chatbot-system-context] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $modules
     * @param array<int,array<int,array<string,mixed>>> $menusByModule
     * @return array<string,mixed>
     */
    private function shapeContext(array $modules, array $menusByModule, string $currentPagePath): array
    {
        $visibleModules = [];
        $totalMenus = 0;
        $currentPageMenu = null;

        foreach ($modules as $module) {
            if (count($visibleModules) >= self::MAX_MODULES) {
                break;
            }

            $moduleId = (int)($module['f_modulID'] ?? 0);
            if ($moduleId <= 0) {
                continue;
            }

            $visibleMenus = [];
            foreach (($menusByModule[$moduleId] ?? []) as $menu) {
                if (count($visibleMenus) >= self::MAX_MENUS_PER_MODULE || $totalMenus >= self::MAX_TOTAL_MENUS) {
                    break;
                }

                $menuName = $this->safeLabel((string)($menu['menuName'] ?? ''));
                $menuPath = $this->safePath((string)($menu['f_path'] ?? ''));
                if ($menuName === '') {
                    continue;
                }

                $menuRow = [
                    'name' => $menuName,
                    'path' => $menuPath,
                ];
                $visibleMenus[] = $menuRow;
                $totalMenus++;

                if ($currentPageMenu === null && $menuPath !== '' && $this->pathMatches($currentPagePath, $menuPath)) {
                    $currentPageMenu = [
                        'module' => $this->safeLabel((string)($module['modulName'] ?? '')),
                        'menu' => $menuName,
                        'path' => $menuPath,
                    ];
                }
            }

            $visibleModules[] = [
                'name' => $this->safeLabel((string)($module['modulName'] ?? '')),
                'menus' => $visibleMenus,
            ];
        }

        if ($visibleModules === []) {
            return [];
        }

        return [
            'source' => 'allowed_group_menu',
            'limits' => [
                'max_modules' => self::MAX_MODULES,
                'max_menus_per_module' => self::MAX_MENUS_PER_MODULE,
                'max_total_menus' => self::MAX_TOTAL_MENUS,
            ],
            'totals' => [
                'modules_in_prompt' => count($visibleModules),
                'menus_in_prompt' => $totalMenus,
            ],
            'current_page_menu' => $currentPageMenu,
            'visible_modules' => $visibleModules,
        ];
    }

    /**
     * @return array<int,int>
     */
    private function csvToIds(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/\s*,\s*/', $csv) ?: [] as $value) {
            if (is_numeric($value) && (int)$value > 0) {
                $ids[] = (int)$value;
            }
        }

        return array_values(array_unique($ids));
    }

    private function pathMatches(string $currentPath, string $menuPath): bool
    {
        if ($currentPath === '' || $menuPath === '') {
            return false;
        }

        $current = ltrim($currentPath, '/');
        $menu = ltrim($menuPath, '/');

        return $current === $menu
            || basename($current) === basename($menu)
            || $current === 'pages/' . basename($menu);
    }

    private function safeLang(string $lang): string
    {
        return in_array($lang, ['ms', 'en', 'zh', 'ta'], true) ? $lang : 'ms';
    }

    private function safeLabel(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        return mb_substr(trim((string)$value), 0, 120, 'UTF-8');
    }

    private function safePath(string $value): string
    {
        $path = parse_url(trim($value), PHP_URL_PATH);
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = trim((string)$path);

        if (str_contains($path, '..')) {
            return '';
        }

        return mb_substr($path, 0, 255, 'UTF-8');
    }
}
