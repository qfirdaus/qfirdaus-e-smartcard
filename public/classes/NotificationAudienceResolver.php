<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class NotificationAudienceResolver
{
    private const VALID_TARGET_TYPES = [
        'ALL',
        'LOGIN_ID',
        'GROUP_ID',
        'CATEGORY_USER',
        'ROLE_ID',
        'DEPARTMENT_ID',
        'PERMISSION',
        'RESOLVED_LOGIN_ID',
    ];

    public function __construct(private PDO $pdo) {}

    /**
     * @param array<int|string,mixed> $audience
     * @param array<string,mixed> $context
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    public function resolve(array $audience, array $context = []): array
    {
        $rows = [];
        $resolveToLoginIds = !empty($context['resolve_to_login_ids']);

        if ($audience === [] || !empty($audience['all'])) {
            $rows[] = $this->row('ALL', null);
        }

        foreach ($this->arrayValues($audience['login_ids'] ?? []) as $loginId) {
            $rows[] = $this->row('LOGIN_ID', $loginId);
        }

        foreach ($this->arrayValues($audience['resolved_login_ids'] ?? []) as $loginId) {
            $rows[] = $this->row('RESOLVED_LOGIN_ID', $loginId, $loginId);
        }

        foreach ($this->arrayValues($audience['group_ids'] ?? []) as $groupId) {
            if ($resolveToLoginIds) {
                foreach ($this->resolveGroup((string)$groupId) as $row) {
                    $rows[] = $row;
                }
            } else {
                $rows[] = $this->row('GROUP_ID', (string)((int)$groupId));
            }
        }

        foreach ($this->arrayValues($audience['category_users'] ?? []) as $categoryUser) {
            $category = strtoupper(trim((string)$categoryUser));
            if ($resolveToLoginIds) {
                foreach ($this->resolveCategory($category) as $row) {
                    $rows[] = $row;
                }
            } else {
                $rows[] = $this->row('CATEGORY_USER', $category);
            }
        }

        foreach ($this->arrayValues($audience['role_ids'] ?? $audience['roles'] ?? []) as $roleId) {
            foreach ($this->resolveRole((string)$roleId, $context) as $row) {
                $rows[] = $row;
            }
        }

        foreach ($this->arrayValues($audience['department_ids'] ?? $audience['departments'] ?? []) as $departmentId) {
            foreach ($this->resolveDepartment((string)$departmentId, $context) as $row) {
                $rows[] = $row;
            }
        }

        foreach ($this->arrayValues($audience['permission_codes'] ?? $audience['permissions'] ?? []) as $permissionCode) {
            foreach ($this->resolvePermission((string)$permissionCode, $context) as $row) {
                $rows[] = $row;
            }
        }

        foreach ($audience as $key => $value) {
            if (!is_int($key)) {
                continue;
            }

            if (is_string($value)) {
                $rows[] = strtoupper($value) === 'ALL'
                    ? $this->row('ALL', null)
                    : $this->row('LOGIN_ID', $value);
                continue;
            }

            if (is_array($value)) {
                $rows[] = $this->normalizeRow($value);
            }
        }

        return $this->dedupeRows(array_values(array_filter($rows)));
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    public function resolveRole(string $roleId, array $context = []): array
    {
        $roleId = trim($roleId);
        if ($roleId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT f_groupID
            FROM tbl_m_group
            WHERE CAST(f_groupID AS CHAR) = :role_id
               OR f_groupKod = :role_id
            LIMIT 1
        ");
        $stmt->execute([':role_id' => $roleId]);
        $groupId = (int)$stmt->fetchColumn();
        if ($groupId > 0) {
            return !empty($context['resolve_to_login_ids'])
                ? $this->resolveGroup((string)$groupId)
                : [$this->row('GROUP_ID', (string)$groupId)];
        }

        return [$this->row('ROLE_ID', strtoupper($roleId))];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    public function resolveDepartment(string $departmentId, array $context = []): array
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '') {
            return [];
        }

        if (empty($context['resolve_to_login_ids'])) {
            return [$this->row('DEPARTMENT_ID', strtoupper($departmentId))];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT f_loginID
            FROM tbl_m_user
            WHERE TRIM(COALESCE(f_loginID, '')) <> ''
              AND (
                TRIM(COALESCE(f_jabatanKod, '')) = :department_id
                OR TRIM(COALESCE(f_namajabatan, '')) = :department_id
              )
              AND COALESCE(f_flag, 1) = 1
            ORDER BY f_loginID ASC
        ");
        $stmt->execute([':department_id' => $departmentId]);

        return $this->loginRows($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    public function resolvePermission(string $permissionCode, array $context = []): array
    {
        $permissionCode = trim($permissionCode);
        if ($permissionCode === '') {
            return [];
        }

        return [$this->row('PERMISSION', $permissionCode)];
    }

    /**
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    public function resolveGroup(string $groupId): array
    {
        $groupId = (string)((int)$groupId);
        if ($groupId === '0') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.f_loginID
            FROM tbl_m_user u
            LEFT JOIN tbl_ref_access a
              ON a.f_userID = u.f_userID
             AND a.f_status = 1
            WHERE TRIM(COALESCE(u.f_loginID, '')) <> ''
              AND (u.f_groupID = :group_id_user OR a.f_groupID = :group_id_access)
              AND COALESCE(u.f_flag, 1) = 1
            ORDER BY u.f_loginID ASC
        ");
        $stmt->execute([
            ':group_id_user' => (int)$groupId,
            ':group_id_access' => (int)$groupId,
        ]);

        return $this->loginRows($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    public function resolveCategory(string $categoryUser): array
    {
        $categoryUser = strtoupper(trim($categoryUser));
        if (!in_array($categoryUser, ['STAF', 'PELAJAR', 'UMUM'], true)) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT f_loginID
            FROM tbl_m_user
            WHERE TRIM(COALESCE(f_loginID, '')) <> ''
              AND TRIM(COALESCE(f_categoryUser, '')) = :category_user
              AND COALESCE(f_flag, 1) = 1
            ORDER BY f_loginID ASC
        ");
        $stmt->execute([':category_user' => $categoryUser]);

        return $this->loginRows($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{type:string,value:?string,resolved_login_id:?string}
     */
    private function normalizeRow(array $row): array
    {
        $type = strtoupper(trim((string)($row['type'] ?? $row['target_type'] ?? '')));
        if (!in_array($type, self::VALID_TARGET_TYPES, true)) {
            throw new InvalidArgumentException('Invalid notification audience target type.');
        }

        $value = $row['value'] ?? $row['target_value'] ?? null;
        $resolvedLoginId = $row['resolved_login_id'] ?? $row['resolvedLoginID'] ?? null;

        if ($type === 'ALL') {
            return $this->row('ALL', null);
        }

        $value = trim((string)$value);
        if ($value === '') {
            throw new InvalidArgumentException('Notification audience target value is required.');
        }

        if (in_array($type, ['CATEGORY_USER', 'ROLE_ID', 'DEPARTMENT_ID', 'PERMISSION'], true)) {
            $value = strtoupper($value);
        } elseif ($type === 'GROUP_ID') {
            $value = (string)((int)$value);
        }

        return $this->row($type, $value, $resolvedLoginId !== null ? trim((string)$resolvedLoginId) : null);
    }

    /**
     * @return array{type:string,value:?string,resolved_login_id:?string}
     */
    private function row(string $type, ?string $value, ?string $resolvedLoginId = null): array
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::VALID_TARGET_TYPES, true)) {
            throw new InvalidArgumentException('Invalid notification audience target type.');
        }

        $value = $value !== null ? trim($value) : null;
        $resolvedLoginId = $resolvedLoginId !== null ? trim($resolvedLoginId) : null;

        return [
            'type' => $type,
            'value' => $type === 'ALL' ? null : ($value !== '' ? $value : null),
            'resolved_login_id' => $resolvedLoginId !== '' ? $resolvedLoginId : null,
        ];
    }

    /**
     * @param mixed $value
     * @return array<int,mixed>
     */
    private function arrayValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            return [$value];
        }
        return array_values($value);
    }

    /**
     * @param array<int,mixed> $loginIds
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    private function loginRows(array $loginIds): array
    {
        $rows = [];
        foreach ($loginIds as $loginId) {
            $loginId = trim((string)$loginId);
            if ($loginId !== '') {
                $rows[] = $this->row('RESOLVED_LOGIN_ID', $loginId, $loginId);
            }
        }
        return $rows;
    }

    /**
     * @param array<int,array{type:string,value:?string,resolved_login_id:?string}> $rows
     * @return array<int,array{type:string,value:?string,resolved_login_id:?string}>
     */
    private function dedupeRows(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = $row['type'] . '|' . (string)($row['value'] ?? '') . '|' . (string)($row['resolved_login_id'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }
        return $unique;
    }
}
