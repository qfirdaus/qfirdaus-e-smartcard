<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

if (!function_exists('prestasi_group_ui_normalize_code')) {
    function prestasi_group_ui_normalize_code(?string $groupKod): string {
        if (function_exists('prestasi_normalize_group_code')) {
            return prestasi_normalize_group_code($groupKod);
        }
        return strtoupper((string)preg_replace('/[^A-Z0-9]+/', '', (string)$groupKod));
    }
}

if (!function_exists('prestasi_group_ui_default_row_class')) {
    function prestasi_group_ui_default_row_class(?string $groupKod): string {
        $code = strtolower(preg_replace('/[^a-z0-9]+/', '-', (string)$groupKod) ?? '');
        $code = trim($code, '-');
        return $code !== '' ? 'row-group-' . $code : '';
    }
}

if (!function_exists('prestasi_group_ui_load_maps')) {
    /**
     * Build group UI maps from either provided rows or DB table `tbl_m_group`.
     *
     * @param PDO $pdo
     * @param array<int, array<string, mixed>>|null $groupRows
     * @return array{by_id: array<string, array{badgeClass: string, rowClass: string, rowColor: string}>, by_code: array<string, array{badgeClass: string, rowClass: string, rowColor: string}>}
     */
    function prestasi_group_ui_load_maps(PDO $pdo, ?array $groupRows = null): array {
        static $cache = [];

        $cacheKey = null;
        if ($groupRows === null) {
            $cacheKey = 'pdo_' . spl_object_id($pdo);
            if (isset($cache[$cacheKey])) {
                return $cache[$cacheKey];
            }
        }

        if ($groupRows === null) {
            $groupRows = $pdo->query(
                "SELECT f_groupID, f_groupKod, f_badge_class, f_row_class, f_color FROM tbl_m_group"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $byId = [];
        $byCode = [];
        foreach ($groupRows as $g) {
            $groupId = (int)($g['f_groupID'] ?? 0);
            $groupKod = prestasi_group_ui_normalize_code((string)($g['f_groupKod'] ?? ''));
            $badgeClass = trim((string)($g['f_badge_class'] ?? ''));
            $rowClass = trim((string)($g['f_row_class'] ?? ''));
            if ($rowClass === '') {
                $rowClass = prestasi_group_ui_default_row_class((string)($g['f_groupKod'] ?? ''));
            }
            $rowColor = trim((string)($g['f_color'] ?? ''));
            $style = [
                'badgeClass' => ($badgeClass !== '' ? $badgeClass : 'bg-secondary'),
                'rowClass' => $rowClass,
                'rowColor' => $rowColor,
            ];

            if ($groupId > 0) {
                $byId[(string)$groupId] = $style;
            }
            if ($groupKod !== '') {
                $byCode[$groupKod] = $style;
            }
        }

        $maps = ['by_id' => $byId, 'by_code' => $byCode];
        if ($cacheKey !== null) {
            $cache[$cacheKey] = $maps;
        }
        return $maps;
    }
}

if (!function_exists('prestasi_group_ui_resolve')) {
    /**
     * Resolve final UI style by group ID first, then normalized group code.
     *
     * @param array{by_id?: array<string, array{badgeClass?: string, rowClass?: string, rowColor?: string}>, by_code?: array<string, array{badgeClass?: string, rowClass?: string, rowColor?: string}>} $maps
     * @return array{badgeClass: string, rowClass: string, rowColor: string}
     */
    function prestasi_group_ui_resolve(array $maps, int $groupId, ?string $groupKod = null): array {
        $idKey = (string)$groupId;
        $codeKey = prestasi_group_ui_normalize_code($groupKod);
        $style = [];

        if (isset($maps['by_id'][$idKey])) {
            $style = (array)$maps['by_id'][$idKey];
        } elseif ($codeKey !== '' && isset($maps['by_code'][$codeKey])) {
            $style = (array)$maps['by_code'][$codeKey];
        }

        $badgeClass = trim((string)($style['badgeClass'] ?? ''));
        $rowClass = trim((string)($style['rowClass'] ?? ''));
        if ($rowClass === '') {
            $rowClass = prestasi_group_ui_default_row_class($groupKod);
        }
        $rowColor = trim((string)($style['rowColor'] ?? ''));
        return [
            'badgeClass' => ($badgeClass !== '' ? $badgeClass : 'bg-secondary'),
            'rowClass' => $rowClass,
            'rowColor' => $rowColor,
        ];
    }
}
