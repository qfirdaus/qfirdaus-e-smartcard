<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/NotificationPublisher.php';

final class NotificationAdminService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getGroups(): array
    {
        $stmt = $this->pdo->query("
            SELECT f_groupID, f_groupKod, f_groupName, f_categoryUser
            FROM tbl_m_group
            ORDER BY f_groupName ASC, f_groupKod ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getTemplates(): array
    {
        $stmt = $this->pdo->query("
            SELECT
              f_templateID,
              f_templateCode,
              f_eventCode,
              f_moduleCode,
              f_type,
              f_category,
              f_severity,
              f_priority,
              f_title_ms,
              f_title_en,
              f_body_ms,
              f_body_en,
              f_actionLabel_ms,
              f_actionLabel_en,
              f_icon,
              f_requiresAction
            FROM tbl_notification_template
            WHERE f_status = 1
            ORDER BY f_templateCode ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getRecentNotifications(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
              n.f_notificationID,
              n.f_eventCode,
              n.f_moduleCode,
              n.f_type,
              n.f_category,
              n.f_severity,
              n.f_priority,
              n.f_title_ms,
              n.f_title_en,
              n.f_requiresAction,
              n.f_dueAt,
              n.f_dedupeKey,
              n.f_isBroadcast,
              n.f_status,
              n.f_insertBy,
              n.f_insertdt,
              COUNT(a.f_audienceID) AS audience_count,
              GROUP_CONCAT(
                CONCAT(a.f_targetType, ':', COALESCE(a.f_targetValue, ''))
                ORDER BY a.f_audienceID ASC
                SEPARATOR '|'
              ) AS audience_summary_raw
            FROM tbl_notification n
            LEFT JOIN tbl_notification_audience a
              ON a.f_notificationID = n.f_notificationID
            GROUP BY
              n.f_notificationID,
              n.f_eventCode,
              n.f_moduleCode,
              n.f_type,
              n.f_category,
              n.f_severity,
              n.f_priority,
              n.f_title_ms,
              n.f_title_en,
              n.f_requiresAction,
              n.f_dueAt,
              n.f_dedupeKey,
              n.f_isBroadcast,
              n.f_status,
              n.f_insertBy,
              n.f_insertdt
            ORDER BY n.f_insertdt DESC, n.f_notificationID DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['audience_summary'] = $this->formatAudienceSummary((string)($row['audience_summary_raw'] ?? ''), (int)($row['audience_count'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    /** @param array<string,mixed> $options @return array{total:int,filtered:int,items:array<int,array<string,mixed>>} */
    public function getAdminList(array $options = []): array
    {
        $limit = max(5, min(100, (int)($options['limit'] ?? 10)));
        $offset = max(0, min(100000, (int)($options['offset'] ?? 0)));
        $rawSearch = trim((string)($options['search'] ?? ''));
        $search = function_exists('mb_substr') ? mb_substr($rawSearch, 0, 100) : substr($rawSearch, 0, 100);
        $type = strtolower(trim((string)($options['type'] ?? '')));
        $priority = strtolower(trim((string)($options['priority'] ?? '')));
        $status = strtolower(trim((string)($options['status'] ?? 'all')));
        $where = [];
        if ($search !== '') {
            $where[] = '(n.f_title_ms LIKE :search_title OR n.f_title_en LIKE :search_title_en OR n.f_eventCode LIKE :search_event OR n.f_moduleCode LIKE :search_module)';
        }
        if (in_array($type, ['announcement', 'reminder', 'event', 'workflow'], true)) $where[] = 'n.f_type = :type';
        if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) $where[] = 'n.f_priority = :priority';
        if ($status === 'active') $where[] = 'n.f_status = 1';
        if ($status === 'inactive') $where[] = 'n.f_status = 0';
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $total = (int)$this->pdo->query('SELECT COUNT(*) FROM tbl_notification')->fetchColumn();
        $filtered = $total;
        if ($where !== []) {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tbl_notification n {$whereSql}");
            $this->bindAdminListFilters($countStmt, $search, $type, $priority);
            $countStmt->execute();
            $filtered = (int)$countStmt->fetchColumn();
        }

        $fromSql = "FROM tbl_notification n LEFT JOIN tbl_notification_audience a ON a.f_notificationID = n.f_notificationID {$whereSql}";
        $stmt = $this->pdo->prepare("SELECT n.f_notificationID,n.f_eventCode,n.f_moduleCode,n.f_type,n.f_category,n.f_severity,n.f_priority,n.f_title_ms,n.f_title_en,n.f_requiresAction,n.f_dueAt,n.f_dedupeKey,n.f_isBroadcast,n.f_status,n.f_insertBy,n.f_insertdt,
              COUNT(a.f_audienceID) AS audience_count,
              GROUP_CONCAT(CONCAT(a.f_targetType, ':', COALESCE(a.f_targetValue, '')) ORDER BY a.f_audienceID SEPARATOR '|') AS audience_summary_raw
            {$fromSql}
            GROUP BY n.f_notificationID,n.f_eventCode,n.f_moduleCode,n.f_type,n.f_category,n.f_severity,n.f_priority,n.f_title_ms,n.f_title_en,n.f_requiresAction,n.f_dueAt,n.f_dedupeKey,n.f_isBroadcast,n.f_status,n.f_insertBy,n.f_insertdt
            ORDER BY n.f_insertdt DESC,n.f_notificationID DESC LIMIT :limit OFFSET :offset");
        $this->bindAdminListFilters($stmt, $search, $type, $priority);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) $row['audience_summary'] = $this->formatAudienceSummary((string)($row['audience_summary_raw'] ?? ''), (int)($row['audience_count'] ?? 0));
        unset($row);
        return ['total' => $total, 'filtered' => $filtered, 'items' => $rows];
    }

    private function bindAdminListFilters(PDOStatement $stmt, string $search, string $type, string $priority): void
    {
        if ($search !== '') {
            $like = '%' . $search . '%';
            foreach ([':search_title', ':search_title_en', ':search_event', ':search_module'] as $parameter) $stmt->bindValue($parameter, $like);
        }
        if (in_array($type, ['announcement', 'reminder', 'event', 'workflow'], true)) $stmt->bindValue(':type', $type);
        if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) $stmt->bindValue(':priority', $priority);
    }

    /**
     * @return array<string,int>
     */
    public function getSummary(): array
    {
        $row = $this->pdo->query("
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN f_status = 1 THEN 1 ELSE 0 END) AS active,
              SUM(CASE WHEN f_requiresAction = 1 THEN 1 ELSE 0 END) AS action_required,
              SUM(CASE WHEN f_isBroadcast = 1 THEN 1 ELSE 0 END) AS broadcast
            FROM tbl_notification
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'action_required' => (int)($row['action_required'] ?? 0),
            'broadcast' => (int)($row['broadcast'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{notification_id:int,message:string}
     */
    public function publishFromAdminInput(array $input, string $createdBy): array
    {
        $payload = $this->buildPayload($input, $createdBy);
        $options = [
            'dedupe' => (string)($input['dedupe_behavior'] ?? 'update'),
        ];

        if (!empty($input['resolve_to_login_ids'])) {
            $options['audience_context'] = ['resolve_to_login_ids' => true];
        }

        $publisher = new NotificationPublisher($this->pdo, new NotificationAudienceResolver($this->pdo));
        $notificationId = $publisher->publish($payload, $options);

        return [
            'notification_id' => $notificationId,
            'message' => 'Notification published.',
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function buildPayload(array $input, string $createdBy): array
    {
        $titleMs = trim((string)($input['title_ms'] ?? ''));
        if ($titleMs === '') {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_title') ?: 'Title is required.'));
        }
        $textLength = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($textLength($titleMs) > 255) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_title_length') ?: 'Title must not exceed 255 characters.'));
        }
        foreach (['body_ms', 'body_en'] as $bodyField) {
            if ($textLength((string)($input[$bodyField] ?? '')) > 10000) {
                throw new InvalidArgumentException((string)(__('notification_admin_validation_body_length') ?: 'Notification content must not exceed 10,000 characters.'));
            }
        }

        $eventCode = trim((string)($input['event_code'] ?? ''));
        if ($eventCode === '') {
            $eventCode = 'admin.notification.' . date('YmdHis');
        }

        $audience = $this->buildAudience($input);
        if ($audience === []) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_audience') ?: 'Audience is required.'));
        }

        $startsAt = trim((string)($input['starts_at'] ?? ''));
        $expiresAt = trim((string)($input['expires_at'] ?? ''));
        $dueAt = trim((string)($input['due_at'] ?? ''));
        $startsTs = $startsAt !== '' ? strtotime($startsAt) : false;
        $expiresTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
        $dueTs = $dueAt !== '' ? strtotime($dueAt) : false;
        if (($startsAt !== '' && $startsTs === false) || ($expiresAt !== '' && $expiresTs === false) || ($dueAt !== '' && $dueTs === false)) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_datetime') ?: 'One or more date values are invalid.'));
        }
        if ($startsTs !== false && $expiresTs !== false && $startsTs >= $expiresTs) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_schedule') ?: 'Expiry must be later than the start date.'));
        }
        if ($dueTs !== false && empty($input['requires_action'])) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_due_action') ?: 'Enable user action when a due date is supplied.'));
        }
        if ($dueTs !== false && $expiresTs !== false && $dueTs > $expiresTs) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_due_expiry') ?: 'Due date cannot be later than expiry.'));
        }
        $actionUrl = trim((string)($input['action_url'] ?? ''));
        if ($actionUrl !== '' && (preg_match('/^(?:(?:https?:)?\/\/|javascript:|data:)/i', $actionUrl)
            || str_contains($actionUrl, '..') || str_contains($actionUrl, '\\') || preg_match('/[\x00-\x1F\x7F]/', $actionUrl))) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_action_url') ?: 'Action URL must be a safe internal application path.'));
        }

        return [
            'event_code' => $eventCode,
            'template_code' => trim((string)($input['template_code'] ?? '')) ?: null,
            'module_code' => trim((string)($input['module_code'] ?? 'CORE')) ?: 'CORE',
            'type' => trim((string)($input['type'] ?? 'announcement')) ?: 'announcement',
            'category' => trim((string)($input['category'] ?? 'system')) ?: 'system',
            'severity' => trim((string)($input['severity'] ?? 'info')) ?: 'info',
            'priority' => trim((string)($input['priority'] ?? 'normal')) ?: 'normal',
            'title_ms' => $titleMs,
            'title_en' => trim((string)($input['title_en'] ?? '')),
            'body_ms' => trim((string)($input['body_ms'] ?? '')),
            'body_en' => trim((string)($input['body_en'] ?? '')),
            'action_url' => trim((string)($input['action_url'] ?? '')),
            'action_label_ms' => trim((string)($input['action_label_ms'] ?? '')),
            'action_label_en' => trim((string)($input['action_label_en'] ?? '')),
            'icon' => trim((string)($input['icon'] ?? 'ri-notification-3-line')) ?: 'ri-notification-3-line',
            'source_type' => trim((string)($input['source_type'] ?? 'admin_notification')) ?: 'admin_notification',
            'source_id' => trim((string)($input['source_id'] ?? '')),
            'requires_action' => !empty($input['requires_action']) ? 1 : 0,
            'due_at' => trim((string)($input['due_at'] ?? '')),
            'dedupe_key' => trim((string)($input['dedupe_key'] ?? '')),
            'is_broadcast' => (($input['audience_type'] ?? '') === 'ALL') ? 1 : 0,
            'starts_at' => trim((string)($input['starts_at'] ?? '')),
            'expires_at' => trim((string)($input['expires_at'] ?? '')),
            'created_by_type' => 'admin',
            'created_by_login_id' => $createdBy,
            'insert_by' => $createdBy,
            'audience' => $audience,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function buildAudience(array $input): array
    {
        $type = strtoupper(trim((string)($input['audience_type'] ?? 'ALL')));
        $rawValue = (string)($input['audience_value'] ?? '');

        return match ($type) {
            'ALL' => ['all' => true],
            'LOGIN_ID' => ['login_ids' => $this->splitValues($rawValue)],
            'RESOLVED_LOGIN_ID' => ['resolved_login_ids' => $this->splitValues($rawValue)],
            'GROUP_ID' => ['group_ids' => $this->splitValues($rawValue)],
            'CATEGORY_USER' => ['category_users' => array_map('strtoupper', $this->splitValues($rawValue))],
            'ROLE_ID' => ['role_ids' => $this->splitValues($rawValue)],
            'DEPARTMENT_ID' => ['department_ids' => $this->splitValues($rawValue)],
            'PERMISSION' => ['permission_codes' => $this->splitValues($rawValue)],
            default => [],
        };
    }

    /**
     * @return array<int,string>
     */
    private function splitValues(string $value): array
    {
        $parts = preg_split('/[\r\n,;]+/', $value) ?: [];
        $values = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $values[] = $part;
            }
        }
        $values = array_values(array_unique($values));
        if (count($values) > 500) {
            throw new InvalidArgumentException((string)(__('notification_admin_validation_audience_limit') ?: 'Audience is limited to 500 values per publication.'));
        }
        foreach ($values as $item) {
            if ((function_exists('mb_strlen') ? mb_strlen($item) : strlen($item)) > 150) {
                throw new InvalidArgumentException((string)(__('notification_admin_validation_audience_value') ?: 'An audience value is too long.'));
            }
        }
        return $values;
    }

    private function formatAudienceSummary(string $raw, int $count): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return (string)(__('notification_admin_no_audience') ?: 'No audience');
        }

        $items = array_values(array_filter(explode('|', $raw), static fn($item) => trim((string)$item) !== ''));
        if ($items === []) {
            return (string)(__('notification_admin_no_audience') ?: 'No audience');
        }

        $labels = [];
        foreach ($items as $item) {
            [$type, $value] = array_pad(explode(':', $item, 2), 2, '');
            $type = strtoupper(trim($type));
            $value = trim($value);

            $labels[] = match ($type) {
                'ALL' => (string)(__('notification_admin_audience_all_users') ?: 'All users'),
                'LOGIN_ID' => 'Login ID: ' . $value,
                'RESOLVED_LOGIN_ID' => 'Resolved user: ' . $value,
                'GROUP_ID' => 'Group ID: ' . $value,
                'CATEGORY_USER' => 'Category: ' . $value,
                'ROLE_ID' => 'Role ID: ' . $value,
                'DEPARTMENT_ID' => 'Department ID: ' . $value,
                'PERMISSION' => 'Permission: ' . $value,
                default => $type . ($value !== '' ? ': ' . $value : ''),
            };
        }

        $labels = array_values(array_unique($labels));
        $visible = array_slice($labels, 0, 2);
        $summary = implode(', ', $visible);
        $remaining = count($labels) - count($visible);

        if ($remaining > 0) {
            $summary .= ' +' . $remaining . ' ' . (string)(__('notification_admin_more') ?: 'more');
        }

        if ($count > count($labels)) {
            $summary .= ' (' . $count . ' ' . (string)(__('notification_admin_rules') ?: 'rules') . ')';
        }

        return $summary;
    }
}
