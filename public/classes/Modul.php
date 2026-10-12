<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/Modul.php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

/**
 * ✅ Model Modul untuk sistem e-Prestasi (MySQL)
 * - Papar menu jika f_flag = 1
 * - Susun ikut COALESCE(f_order, 99999), f_*ID
 * - Jika $menuIDs kosong, ambil SEMUA menu aktif di modul tersebut
 */
class Modul extends BaseModel
{
    /** @var array<string,bool> */
    private static array $schemaCache = [];

    private function getModulNameField(string $lang): string
    {
        $supported = ['ms', 'en', 'zh', 'ta'];
        return in_array($lang, $supported, true) ? "f_modulName_{$lang}" : "f_modulName_ms";
    }

    private function getMenuNameField(string $lang): string
    {
        $supported = ['ms', 'en', 'zh', 'ta'];
        return in_array($lang, $supported, true) ? "f_menuName_{$lang}" : "f_menuName_ms";
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        $cacheKey = 'table:' . $table;
        if (array_key_exists($cacheKey, self::$schemaCache)) {
            return self::$schemaCache[$cacheKey];
        }

        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE :table_name');
            $stmt->execute([':table_name' => $table]);
            return self::$schemaCache[$cacheKey] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return self::$schemaCache[$cacheKey] = false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }

        $cacheKey = 'column:' . $table . ':' . $column;
        if (array_key_exists($cacheKey, self::$schemaCache)) {
            return self::$schemaCache[$cacheKey];
        }

        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
            $stmt->execute([':column_name' => $column]);
            return self::$schemaCache[$cacheKey] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return self::$schemaCache[$cacheKey] = false;
        }
    }

    private function getSubgroupNameField(string $lang): string
    {
        $supported = ['ms', 'en', 'zh', 'ta'];
        $candidates = [];
        if (in_array($lang, $supported, true)) {
            $candidates[] = "f_subgroupName_{$lang}";
        }
        $candidates[] = 'f_subgroupName_ms';
        $candidates[] = 'f_subgroupName_en';

        foreach ($candidates as $candidate) {
            if ($this->columnExists('tbl_m_menu_subgroup', $candidate)) {
                return $candidate;
            }
        }

        return 'f_subgroupName_ms';
    }

    /**
     * Sidebar subgroup schema is optional during rollout.
     * When the schema is absent, menu queries keep returning direct-menu rows.
     *
     * @return array{select:string,join:string,order:string}
     */
    private function getMenuSubgroupSqlParts(string $lang): array
    {
        $hasMenuSubgroupColumn = $this->columnExists('tbl_m_menu', 'f_subgroupID');
        $hasSubgroupTable = $this->tableExists('tbl_m_menu_subgroup');

        if (!$hasMenuSubgroupColumn || !$hasSubgroupTable) {
            return [
                'select' => "
                    NULL AS f_subgroupID,
                    NULL AS subgroupName,
                    NULL AS subgroupIcon,
                    NULL AS subgroupOrder,
                    NULL AS subgroupStatus",
                'join' => '',
                'order' => 'COALESCE(m.f_order, 99999), m.f_menuID',
            ];
        }

        $subgroupNameField = $this->getSubgroupNameField($lang);

        return [
            'select' => "
                    m.f_subgroupID,
                    COALESCE(NULLIF(sg.{$subgroupNameField}, ''), NULLIF(sg.f_subgroupName_ms, ''), NULLIF(sg.f_subgroupName_en, '')) AS subgroupName,
                    COALESCE(sg.f_icon, 'ri-folder-2-line') AS subgroupIcon,
                    sg.f_order AS subgroupOrder,
                    COALESCE(sg.f_status, 1) AS subgroupStatus",
            'join' => 'LEFT JOIN tbl_m_menu_subgroup sg ON sg.f_subgroupID = m.f_subgroupID AND sg.f_modulID = m.f_modulID AND COALESCE(sg.f_status, 1) = 1',
            'order' => 'COALESCE(sg.f_order, 0), COALESCE(m.f_order, 99999), m.f_menuID',
        ];
    }

    /** ✅ Dapatkan semua modul */
    public function getAllModul(string $lang = 'ms'): array
    {
        $nameField = $this->getModulNameField($lang);
        $sql = "SELECT 
                    f_modulID, 
                    {$nameField} AS modulName, 
                    COALESCE(f_icon,'ri-folder-fill') AS f_icon, 
                    f_order
                FROM tbl_m_modul
                ORDER BY COALESCE(f_order, 99999), f_modulID";
        return $this->fetchAll($sql);
    }

    /** ✅ Dapatkan semua menu anak bagi satu modul (flag=1 sahaja) */
    public function getChildMenu(int $modulID, string $lang = 'ms'): array
    {
        $nameField = $this->getMenuNameField($lang);
        $subgroupSql = $this->getMenuSubgroupSqlParts($lang);
        $sql = "SELECT 
                    m.f_menuID,
                    m.{$nameField} AS menuName,
                    m.f_path,
                    {$subgroupSql['select']},
                    m.f_flag,
                    m.f_order
                FROM tbl_m_menu m
                {$subgroupSql['join']}
                WHERE m.f_modulID = :modulID
                  AND m.f_flag = 1
                ORDER BY {$subgroupSql['order']}";
        return $this->fetchAll($sql, [':modulID' => $modulID]);
    }

    /** ✅ Dapatkan modul yang dibenarkan oleh group */
    public function getAllModulByGroup(array $modulIDs, string $lang = 'ms'): array
    {
        if (empty($modulIDs)) return [];
        [$ph, $bind] = $this->inClause('mid', array_map('intval', $modulIDs));

        $nameField = $this->getModulNameField($lang);
        $sql = "SELECT 
                    f_modulID, 
                    {$nameField} AS modulName, 
                    COALESCE(f_icon,'ri-folder-fill') AS f_icon, 
                    f_order
                FROM tbl_m_modul
                WHERE f_modulID IN ({$ph})
                ORDER BY COALESCE(f_order, 99999), f_modulID";
        return $this->fetchAll($sql, $bind);
    }

    /**
     * ✅ Dapatkan menu anak mengikut senarai menuID (akses group)
     * - Jika $menuIDs kosong → ambil SEMUA menu modul yang aktif (flag=1)
     * - Jika $menuIDs ada → tapis IN (...) + flag=1
     */
    public function getChildMenuByIDs(int $modulID, array $menuIDs, string $lang = 'ms'): array
    {
        $nameField = $this->getMenuNameField($lang);
        $subgroupSql = $this->getMenuSubgroupSqlParts($lang);

        $where = "WHERE m.f_modulID = :modulID AND m.f_flag = 1";
        $bind  = [':modulID' => $modulID];

        if (!empty($menuIDs)) {
            [$ph, $inBind] = $this->inClause('menu', array_map('intval', $menuIDs));
            $where .= " AND m.f_menuID IN ({$ph})";
            $bind   = array_merge($bind, $inBind);
        }

        $sql = "SELECT 
                    m.f_menuID,
                    m.f_path,
                    m.{$nameField} AS menuName,
                    {$subgroupSql['select']},
                    m.f_flag,
                    m.f_order
                FROM tbl_m_menu m
                {$subgroupSql['join']}
                {$where}
                ORDER BY {$subgroupSql['order']}";

        return $this->fetchAll($sql, $bind);
    }

    /**
     * ✅ Batch load semua menus untuk multiple moduls (fix N+1 query problem)
     * - Load semua menus untuk semua modulIDs sekali gus
     * - Return array grouped by modulID: [modulID => [menus...]]
     * 
     * @param array $modulIDs Array of modul IDs
     * @param array $menuIDs Array of allowed menu IDs (empty = all active menus)
     * @param string $lang Language code
     * @return array Associative array: [modulID => [menu1, menu2, ...]]
     */
    public function getAllMenusByModulIDs(array $modulIDs, array $menuIDs, string $lang = 'ms'): array
    {
        if (empty($modulIDs)) return [];

        $nameField = $this->getMenuNameField($lang);
        $subgroupSql = $this->getMenuSubgroupSqlParts($lang);
        
        // Build WHERE clause for modulIDs
        [$modulPh, $modulBind] = $this->inClause('mod', array_map('intval', $modulIDs));
        
        $where = "WHERE m.f_modulID IN ({$modulPh}) AND m.f_flag = 1";
        $bind  = $modulBind;

        // Add menuIDs filter if provided
        if (!empty($menuIDs)) {
            [$menuPh, $menuBind] = $this->inClause('menu', array_map('intval', $menuIDs));
            $where .= " AND m.f_menuID IN ({$menuPh})";
            $bind = array_merge($bind, $menuBind);
        }

        $sql = "SELECT 
                    m.f_modulID,
                    m.f_menuID,
                    m.f_path,
                    m.{$nameField} AS menuName,
                    COALESCE(m.f_domain, 'SHARED') AS f_domain,
                    COALESCE(m.f_show_staff_only, 1) AS f_show_staff_only,
                    {$subgroupSql['select']},
                    m.f_flag,
                    m.f_order
                FROM tbl_m_menu m
                {$subgroupSql['join']}
                {$where}
                ORDER BY m.f_modulID, {$subgroupSql['order']}";

        $allMenus = $this->fetchAll($sql, $bind);
        
        // Group by modulID
        $grouped = [];
        foreach ($allMenus as $menu) {
            $modulID = (int)$menu['f_modulID'];
            if (!isset($grouped[$modulID])) {
                $grouped[$modulID] = [];
            }
            // Remove f_modulID from menu array (not needed in result)
            unset($menu['f_modulID']);
            $grouped[$modulID][] = $menu;
        }

        return $grouped;
    }
}
