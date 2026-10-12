<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class NotificationService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<string,mixed>
     */
    public function resolveCurrentActor(): array
    {
        $loginId = trim((string)($_SESSION['f_loginID'] ?? ''));
        if ($loginId === '') {
            throw new RuntimeException('Missing session login ID.');
        }

        $stmt = $this->pdo->prepare("
            SELECT
              u.f_userID,
              u.f_loginID,
              u.f_stafID,
              u.f_nopekerja,
              u.f_categoryUser,
              u.f_groupID,
              u.f_jabatanKod,
              g.f_groupKod
            FROM tbl_m_user u
            LEFT JOIN tbl_m_group g
              ON g.f_groupID = u.f_groupID
            WHERE u.f_loginID = :loginID
            LIMIT 1
        ");
        $stmt->execute([':loginID' => $loginId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('Authenticated user record not found.');
        }

        $activeGroupId = (int)($_SESSION['group_active_id'] ?? 0);
        if ($activeGroupId <= 0) {
            $activeGroupId = (int)($row['f_groupID'] ?? 0);
        }
        $activeGroupKod = strtoupper((string)($row['f_groupKod'] ?? ''));
        if ($activeGroupId > 0 && $activeGroupId !== (int)($row['f_groupID'] ?? 0)) {
            $stmtGroup = $this->pdo->prepare('SELECT f_groupKod FROM tbl_m_group WHERE f_groupID = :group_id LIMIT 1');
            $stmtGroup->execute([':group_id' => $activeGroupId]);
            $activeGroupKod = strtoupper((string)($stmtGroup->fetchColumn() ?: $activeGroupKod));
        }

        return [
            'user_id' => (int)($row['f_userID'] ?? 0),
            'login_id' => (string)($row['f_loginID'] ?? ''),
            'staf_id' => (string)($row['f_stafID'] ?? ''),
            'nopekerja' => (string)($row['f_nopekerja'] ?? ''),
            'category_user' => strtoupper((string)($row['f_categoryUser'] ?? '')),
            'group_id' => $activeGroupId,
            'group_kod' => $activeGroupKod,
            'department_id' => strtoupper((string)($row['f_jabatanKod'] ?? '')),
        ];
    }

    /**
     * @return array{unread:int,items:array<int,array<string,mixed>>}
     */
    public function getTopbarPayload(string $lang = 'ms', int $limit = 10): array
    {
        $actor = $this->resolveCurrentActor();

        return [
            'unread' => $this->countUnread($actor),
            'items' => $this->listNotifications($actor, [
                'lang' => $lang,
                'limit' => $limit,
                'mode' => 'topbar',
                'filter' => 'all',
            ]),
        ];
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function countUnread(array $actor): int
    {
        $sql = "
            SELECT COUNT(DISTINCT n.f_notificationID)
            FROM tbl_notification n
            JOIN tbl_notification_audience a
              ON a.f_notificationID = n.f_notificationID
            LEFT JOIN tbl_notification_user_state s
              ON s.f_notificationID = n.f_notificationID
             AND s.f_loginID = :login_id
            WHERE {$this->visibleWhereSql()}
              AND COALESCE(s.f_isRead, 0) = 0
        ";

        $stmt = $this->pdo->prepare($sql);
        $this->bindActor($stmt, $actor);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $options
     * @return array<int,array<string,mixed>>
     */
    public function listNotifications(array $actor, array $options = []): array
    {
        $lang = $this->normalizeLang((string)($options['lang'] ?? 'ms'));
        $limit = max(1, min(100, (int)($options['limit'] ?? 10)));
        $offset = max(0, min(100000, (int)($options['offset'] ?? 0)));
        $filter = strtolower((string)($options['filter'] ?? 'all'));
        $search = mb_substr(trim((string)($options['search'] ?? '')), 0, 100);

        $filterSql = '';
        if ($filter === 'unread') {
            $filterSql = ' AND COALESCE(s.f_isRead, 0) = 0';
        } elseif ($filter === 'read') {
            $filterSql = ' AND COALESCE(s.f_isRead, 0) = 1';
        } elseif ($filter === 'action_required') {
            $filterSql = " AND n.f_requiresAction = 1 AND COALESCE(s.f_actionStatus, 'pending') = 'pending'";
        } elseif ($filter === 'overdue') {
            $filterSql = " AND n.f_requiresAction = 1 AND COALESCE(s.f_actionStatus, 'pending') = 'pending' AND n.f_dueAt IS NOT NULL AND n.f_dueAt < NOW()";
        }
        $searchSql = $search !== ''
            ? " AND (n.f_title_ms LIKE :search_title_ms OR n.f_title_en LIKE :search_title_en OR n.f_body_ms LIKE :search_body_ms OR n.f_body_en LIKE :search_body_en)"
            : '';

        $sql = "
            SELECT DISTINCT
              n.f_notificationID,
              n.f_eventCode,
              n.f_moduleCode,
              n.f_type,
              n.f_category,
              n.f_severity,
              n.f_priority,
              n.f_requiresAction,
              n.f_dueAt,
              CASE
                WHEN :lang_title = 'en' AND COALESCE(NULLIF(n.f_title_en, ''), '') <> '' THEN n.f_title_en
                ELSE n.f_title_ms
              END AS title,
              CASE
                WHEN :lang_body = 'en' AND COALESCE(NULLIF(n.f_body_en, ''), '') <> '' THEN n.f_body_en
                ELSE n.f_body_ms
              END AS body,
              n.f_actionURL,
              CASE
                WHEN :lang_action_label = 'en' AND COALESCE(NULLIF(n.f_actionLabel_en, ''), '') <> '' THEN n.f_actionLabel_en
                ELSE n.f_actionLabel_ms
              END AS action_label,
              n.f_icon,
              n.f_sourceType,
              n.f_sourceID,
              n.f_insertdt,
              COALESCE(s.f_isRead, 0) AS is_read,
              COALESCE(s.f_actionStatus, CASE WHEN n.f_requiresAction = 1 THEN 'pending' ELSE 'none' END) AS action_status
            FROM tbl_notification n
            JOIN tbl_notification_audience a
              ON a.f_notificationID = n.f_notificationID
            LEFT JOIN tbl_notification_user_state s
              ON s.f_notificationID = n.f_notificationID
             AND s.f_loginID = :login_id
            WHERE {$this->visibleWhereSql()}
              {$filterSql}
              {$searchSql}
            ORDER BY COALESCE(s.f_isRead, 0) ASC, n.f_insertdt DESC, n.f_notificationID DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lang_title', $lang);
        $stmt->bindValue(':lang_body', $lang);
        $stmt->bindValue(':lang_action_label', $lang);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt->bindValue(':search_title_ms', $like);
            $stmt->bindValue(':search_title_en', $like);
            $stmt->bindValue(':search_body_ms', $like);
            $stmt->bindValue(':search_body_en', $like);
        }
        $this->bindActor($stmt, $actor);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = $this->normalizeRow($row, $lang);
        }

        return $items;
    }

    /** @param array<string,mixed> $actor @param array<string,mixed> $options */
    public function countNotifications(array $actor, array $options = []): int
    {
        $filter = strtolower((string)($options['filter'] ?? 'all'));
        $search = mb_substr(trim((string)($options['search'] ?? '')), 0, 100);
        $filterSql = match ($filter) {
            'unread' => ' AND COALESCE(s.f_isRead, 0) = 0',
            'read' => ' AND COALESCE(s.f_isRead, 0) = 1',
            'action_required' => " AND n.f_requiresAction = 1 AND COALESCE(s.f_actionStatus, 'pending') = 'pending'",
            'overdue' => " AND n.f_requiresAction = 1 AND COALESCE(s.f_actionStatus, 'pending') = 'pending' AND n.f_dueAt IS NOT NULL AND n.f_dueAt < NOW()",
            default => '',
        };
        $searchSql = $search !== ''
            ? " AND (n.f_title_ms LIKE :search_title_ms OR n.f_title_en LIKE :search_title_en OR n.f_body_ms LIKE :search_body_ms OR n.f_body_en LIKE :search_body_en)"
            : '';
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT n.f_notificationID)
            FROM tbl_notification n
            JOIN tbl_notification_audience a ON a.f_notificationID = n.f_notificationID
            LEFT JOIN tbl_notification_user_state s ON s.f_notificationID = n.f_notificationID AND s.f_loginID = :login_id
            WHERE {$this->visibleWhereSql()} {$filterSql} {$searchSql}");
        if ($search !== '') {
            $like = '%' . $search . '%';
            foreach ([':search_title_ms', ':search_title_en', ':search_body_ms', ':search_body_en'] as $param) {
                $stmt->bindValue($param, $like);
            }
        }
        $this->bindActor($stmt, $actor);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /** @param array<string,mixed> $actor @return array{total:int,unread:int,action_required:int,overdue:int} */
    public function getNotificationSummary(array $actor): array
    {
        $stmt = $this->pdo->prepare("SELECT
              COUNT(*) AS total,
              COALESCE(SUM(x.is_read = 0), 0) AS unread,
              COALESCE(SUM(x.requires_action = 1 AND x.action_status = 'pending'), 0) AS action_required,
              COALESCE(SUM(x.requires_action = 1 AND x.action_status = 'pending' AND x.due_at IS NOT NULL AND x.due_at < NOW()), 0) AS overdue
            FROM (
              SELECT DISTINCT n.f_notificationID,
                COALESCE(s.f_isRead, 0) AS is_read,
                n.f_requiresAction AS requires_action,
                COALESCE(s.f_actionStatus, CASE WHEN n.f_requiresAction = 1 THEN 'pending' ELSE 'none' END) AS action_status,
                n.f_dueAt AS due_at
              FROM tbl_notification n
              JOIN tbl_notification_audience a ON a.f_notificationID = n.f_notificationID
              LEFT JOIN tbl_notification_user_state s ON s.f_notificationID = n.f_notificationID AND s.f_loginID = :login_id
              WHERE {$this->visibleWhereSql()}
            ) x");
        $this->bindActor($stmt, $actor);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int)($row['total'] ?? 0),
            'unread' => (int)($row['unread'] ?? 0),
            'action_required' => (int)($row['action_required'] ?? 0),
            'overdue' => (int)($row['overdue'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function markAsRead(int $notificationId, array $actor): bool
    {
        if ($notificationId <= 0 || !$this->isVisibleToActor($notificationId, $actor)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_notification_user_state
              (f_notificationID, f_loginID, f_categoryUser, f_isRead, f_readAt, f_actionStatus, f_insertdt, f_updatedt)
            VALUES
              (:notification_id, :login_id, :category_user, 1, NOW(), :action_status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              f_isRead = 1,
              f_readAt = COALESCE(f_readAt, NOW()),
              f_updatedt = NOW()
        ");

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':login_id' => (string)$actor['login_id'],
            ':category_user' => $this->normalizeCategoryForState((string)($actor['category_user'] ?? '')),
            ':action_status' => $this->defaultActionStatus($notificationId),
        ]);
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function markAllAsRead(array $actor, int $limit = 100): int
    {
        $unreadBefore = $this->countUnread($actor);
        if ($unreadBefore === 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare("INSERT INTO tbl_notification_user_state
              (f_notificationID, f_loginID, f_categoryUser, f_isRead, f_readAt, f_actionStatus, f_insertdt, f_updatedt)
            SELECT DISTINCT n.f_notificationID, :state_login_id, :state_category, 1, NOW(),
              CASE WHEN n.f_requiresAction = 1 THEN 'pending' ELSE 'none' END, NOW(), NOW()
            FROM tbl_notification n
            JOIN tbl_notification_audience a ON a.f_notificationID = n.f_notificationID
            LEFT JOIN tbl_notification_user_state s ON s.f_notificationID = n.f_notificationID AND s.f_loginID = :login_id
            WHERE {$this->visibleWhereSql()} AND COALESCE(s.f_isRead, 0) = 0
            ON DUPLICATE KEY UPDATE f_isRead = 1, f_readAt = COALESCE(f_readAt, NOW()), f_updatedt = NOW()");
        $stmt->bindValue(':state_login_id', (string)$actor['login_id']);
        $stmt->bindValue(':state_category', $this->normalizeCategoryForState((string)($actor['category_user'] ?? '')));
        $this->bindActor($stmt, $actor);
        $stmt->execute();
        return $unreadBefore;
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function markActionCompleted(int $notificationId, array $actor): bool
    {
        return $this->markActionStatus($notificationId, $actor, 'completed');
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function markActionCancelled(int $notificationId, array $actor): bool
    {
        return $this->markActionStatus($notificationId, $actor, 'cancelled');
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function markActionExpired(int $notificationId, array $actor): bool
    {
        return $this->markActionStatus($notificationId, $actor, 'expired');
    }

    public function completeBySource(string $sourceType, string $sourceId, ?string $eventCode = null): int
    {
        return $this->updateActionStatusBySource($sourceType, $sourceId, 'completed', $eventCode);
    }

    public function cancelBySource(string $sourceType, string $sourceId, ?string $eventCode = null): int
    {
        return $this->updateActionStatusBySource($sourceType, $sourceId, 'cancelled', $eventCode);
    }

    public function expireBySource(string $sourceType, string $sourceId, ?string $eventCode = null): int
    {
        return $this->updateActionStatusBySource($sourceType, $sourceId, 'expired', $eventCode);
    }

    public function expireOverdueTasks(int $limit = 500): int
    {
        $limit = max(1, min(5000, $limit));
        $stmt = $this->pdo->prepare("
            SELECT f_notificationID
            FROM tbl_notification
            WHERE f_requiresAction = 1
              AND f_status = 1
              AND f_dueAt IS NOT NULL
              AND f_dueAt < NOW()
            ORDER BY f_dueAt ASC, f_notificationID ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $updated = 0;
        foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $notificationId) {
            $updated += $this->upsertActionStatusForNotification($notificationId, 'expired');
        }

        return $updated;
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function touchClicked(int $notificationId, array $actor): bool
    {
        if ($notificationId <= 0 || !$this->isVisibleToActor($notificationId, $actor)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_notification_user_state
              (f_notificationID, f_loginID, f_categoryUser, f_isRead, f_readAt, f_clickedAt, f_actionStatus, f_insertdt, f_updatedt)
            VALUES
              (:notification_id, :login_id, :category_user, 1, NOW(), NOW(), :action_status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              f_isRead = 1,
              f_readAt = COALESCE(f_readAt, NOW()),
              f_clickedAt = NOW(),
              f_updatedt = NOW()
        ");

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':login_id' => (string)$actor['login_id'],
            ':category_user' => $this->normalizeCategoryForState((string)($actor['category_user'] ?? '')),
            ':action_status' => $this->defaultActionStatus($notificationId),
        ]);
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function dismiss(int $notificationId, array $actor): bool
    {
        if ($notificationId <= 0 || !$this->isVisibleToActor($notificationId, $actor)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_notification_user_state
              (f_notificationID, f_loginID, f_categoryUser, f_isRead, f_readAt, f_isDismissed, f_dismissedAt, f_actionStatus, f_insertdt, f_updatedt)
            VALUES
              (:notification_id, :login_id, :category_user, 1, NOW(), 1, NOW(), :action_status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              f_isRead = 1,
              f_readAt = COALESCE(f_readAt, NOW()),
              f_isDismissed = 1,
              f_dismissedAt = NOW(),
              f_updatedt = NOW()
        ");

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':login_id' => (string)$actor['login_id'],
            ':category_user' => $this->normalizeCategoryForState((string)($actor['category_user'] ?? '')),
            ':action_status' => $this->defaultActionStatus($notificationId),
        ]);
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function isVisibleToActor(int $notificationId, array $actor): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM tbl_notification n
            JOIN tbl_notification_audience a
              ON a.f_notificationID = n.f_notificationID
            LEFT JOIN tbl_notification_user_state s
              ON s.f_notificationID = n.f_notificationID
             AND s.f_loginID = :login_id
            WHERE n.f_notificationID = :notification_id
              AND {$this->visibleWhereSql()}
            LIMIT 1
        ");
        $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
        $this->bindActor($stmt, $actor);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();
    }

    private function visibleWhereSql(): string
    {
        return "
            n.f_status = 1
            AND (n.f_startsAt IS NULL OR n.f_startsAt <= NOW())
            AND (n.f_expiresAt IS NULL OR n.f_expiresAt >= NOW())
            AND COALESCE(s.f_isDismissed, 0) = 0
            AND (
              a.f_targetType = 'ALL'
              OR (a.f_targetType = 'LOGIN_ID' AND a.f_targetValue = :login_id_target)
              OR (a.f_targetType = 'RESOLVED_LOGIN_ID' AND a.f_targetValue = :login_id_resolved_target)
              OR a.f_resolvedLoginID = :login_id_resolved
              OR (a.f_targetType = 'GROUP_ID' AND a.f_targetValue = :group_id)
              OR (a.f_targetType = 'ROLE_ID' AND (a.f_targetValue = :role_id OR a.f_targetValue = :group_id_role))
              OR (a.f_targetType = 'DEPARTMENT_ID' AND a.f_targetValue = :department_id)
              OR (a.f_targetType = 'CATEGORY_USER' AND a.f_targetValue = :category_user)
            )
        ";
    }

    /**
     * @param array<string,mixed> $actor
     */
    private function bindActor(PDOStatement $stmt, array $actor): void
    {
        $loginId = (string)($actor['login_id'] ?? '');
        $stmt->bindValue(':login_id', $loginId);
        $stmt->bindValue(':login_id_target', $loginId);
        $stmt->bindValue(':login_id_resolved_target', $loginId);
        $stmt->bindValue(':login_id_resolved', $loginId);
        $stmt->bindValue(':group_id', (string)((int)($actor['group_id'] ?? 0)));
        $stmt->bindValue(':role_id', strtoupper((string)($actor['group_kod'] ?? '')));
        $stmt->bindValue(':group_id_role', (string)((int)($actor['group_id'] ?? 0)));
        $stmt->bindValue(':department_id', strtoupper((string)($actor['department_id'] ?? '')));
        $stmt->bindValue(':category_user', strtoupper((string)($actor['category_user'] ?? '')));
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeRow(array $row, string $lang): array
    {
        $createdAt = (string)($row['f_insertdt'] ?? '');
        $requiresAction = (int)($row['f_requiresAction'] ?? 0) === 1;
        $dueAt = $row['f_dueAt'] ?? null;
        $actionStatus = (string)($row['action_status'] ?? ($requiresAction ? 'pending' : 'none'));
        $isOverdue = $this->isOverdue($dueAt, $requiresAction, $actionStatus);

        return [
            'id' => (int)($row['f_notificationID'] ?? 0),
            'event_code' => (string)($row['f_eventCode'] ?? ''),
            'module_code' => (string)($row['f_moduleCode'] ?? ''),
            'type' => (string)($row['f_type'] ?? 'announcement'),
            'category' => (string)($row['f_category'] ?? 'system'),
            'severity' => (string)($row['f_severity'] ?? 'info'),
            'priority' => (string)($row['f_priority'] ?? 'normal'),
            'title' => (string)($row['title'] ?? ''),
            'body' => (string)($row['body'] ?? ''),
            'action_url' => $this->safeActionUrl((string)($row['f_actionURL'] ?? '')),
            'action_label' => (string)($row['action_label'] ?? ''),
            'icon' => (string)($row['f_icon'] ?? 'ri-notification-3-line'),
            'is_read' => (int)($row['is_read'] ?? 0) === 1,
            'requires_action' => $requiresAction,
            'action_status' => $actionStatus,
            'due_at' => $dueAt,
            'is_overdue' => $isOverdue,
            'due_label' => $this->formatDueLabel($dueAt, $lang),
            'source_type' => (string)($row['f_sourceType'] ?? ''),
            'source_id' => (string)($row['f_sourceID'] ?? ''),
            'created_at' => $createdAt,
            'time_ago' => $this->formatTimeAgo($createdAt, $lang),
        ];
    }

    private function defaultActionStatus(int $notificationId): string
    {
        $stmt = $this->pdo->prepare('SELECT f_requiresAction FROM tbl_notification WHERE f_notificationID = :id LIMIT 1');
        $stmt->execute([':id' => $notificationId]);
        return ((int)$stmt->fetchColumn() === 1) ? 'pending' : 'none';
    }

    /**
     * @param array<string,mixed> $actor
     */
    private function markActionStatus(int $notificationId, array $actor, string $status): bool
    {
        if (!in_array($status, ['completed', 'cancelled', 'expired'], true)) {
            throw new InvalidArgumentException('Invalid notification action status.');
        }
        if ($notificationId <= 0 || !$this->isVisibleToActor($notificationId, $actor)) {
            return false;
        }

        $timestampColumn = match ($status) {
            'completed' => 'f_completedAt',
            'cancelled' => 'f_cancelledAt',
            default => 'f_actedAt',
        };

        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_notification_user_state
              (f_notificationID, f_loginID, f_categoryUser, f_isRead, f_readAt, f_actionStatus, f_actedAt, {$timestampColumn}, f_insertdt, f_updatedt)
            VALUES
              (:notification_id, :login_id, :category_user, 1, NOW(), :action_status, NOW(), NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              f_isRead = 1,
              f_readAt = COALESCE(f_readAt, NOW()),
              f_actionStatus = VALUES(f_actionStatus),
              f_actedAt = NOW(),
              {$timestampColumn} = NOW(),
              f_updatedt = NOW()
        ");

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':login_id' => (string)$actor['login_id'],
            ':category_user' => $this->normalizeCategoryForState((string)($actor['category_user'] ?? '')),
            ':action_status' => $status,
        ]);
    }

    private function updateActionStatusBySource(string $sourceType, string $sourceId, string $status, ?string $eventCode = null): int
    {
        $sourceType = trim($sourceType);
        $sourceId = trim($sourceId);
        if ($sourceType === '' || $sourceId === '') {
            return 0;
        }
        if (!in_array($status, ['completed', 'cancelled', 'expired'], true)) {
            throw new InvalidArgumentException('Invalid notification action status.');
        }

        $eventSql = '';
        $params = [
            ':source_type' => $sourceType,
            ':source_id' => $sourceId,
        ];
        if ($eventCode !== null && trim($eventCode) !== '') {
            $eventSql = ' AND f_eventCode = :event_code';
            $params[':event_code'] = trim($eventCode);
        }

        $stmt = $this->pdo->prepare("
            SELECT f_notificationID
            FROM tbl_notification
            WHERE f_sourceType = :source_type
              AND f_sourceID = :source_id
              AND f_requiresAction = 1
              AND f_status = 1
              {$eventSql}
            ORDER BY f_notificationID ASC
        ");
        $stmt->execute($params);
        $notificationIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $updated = 0;
        foreach ($notificationIds as $notificationId) {
            $updated += $this->upsertActionStatusForNotification($notificationId, $status);
        }

        return $updated;
    }

    private function upsertActionStatusForNotification(int $notificationId, string $status): int
    {
        $loginIds = $this->resolveAudienceLoginIds($notificationId);
        $timestampColumn = match ($status) {
            'completed' => 'f_completedAt',
            'cancelled' => 'f_cancelledAt',
            default => 'f_actedAt',
        };

        $updated = 0;
        if ($loginIds !== []) {
            $stmt = $this->pdo->prepare("
                INSERT INTO tbl_notification_user_state
                  (f_notificationID, f_loginID, f_categoryUser, f_isRead, f_actionStatus, f_actedAt, {$timestampColumn}, f_insertdt, f_updatedt)
                VALUES
                  (:notification_id, :login_id, :category_user, 1, :action_status, NOW(), NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                  f_isRead = IF(f_actionStatus = 'pending', 1, f_isRead),
                  f_actedAt = IF(f_actionStatus = 'pending', NOW(), f_actedAt),
                  {$timestampColumn} = IF(f_actionStatus = 'pending', NOW(), {$timestampColumn}),
                  f_actionStatus = IF(f_actionStatus = 'pending', VALUES(f_actionStatus), f_actionStatus),
                  f_updatedt = NOW()
            ");

            foreach ($loginIds as $loginId => $categoryUser) {
                $stmt->execute([
                    ':notification_id' => $notificationId,
                    ':login_id' => $loginId,
                    ':category_user' => $this->normalizeCategoryForState((string)$categoryUser),
                    ':action_status' => $status,
                ]);
                $updated++;
            }

            return $updated;
        }

        $stmt = $this->pdo->prepare("
            UPDATE tbl_notification_user_state
            SET f_isRead = 1,
                f_actionStatus = :action_status,
                f_actedAt = NOW(),
                {$timestampColumn} = NOW(),
                f_updatedt = NOW()
            WHERE f_notificationID = :notification_id
              AND f_actionStatus = 'pending'
        ");
        $stmt->execute([
            ':notification_id' => $notificationId,
            ':action_status' => $status,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @return array<string,string|null> login ID mapped to category snapshot
     */
    private function resolveAudienceLoginIds(int $notificationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f_targetType, f_targetValue, f_resolvedLoginID
            FROM tbl_notification_audience
            WHERE f_notificationID = :notification_id
        ");
        $stmt->execute([':notification_id' => $notificationId]);
        $audienceRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $loginIds = [];
        foreach ($audienceRows as $row) {
            $type = strtoupper((string)($row['f_targetType'] ?? ''));
            $targetValue = trim((string)($row['f_targetValue'] ?? ''));
            $resolvedLoginId = trim((string)($row['f_resolvedLoginID'] ?? ''));

            if ($resolvedLoginId !== '') {
                $loginIds[$resolvedLoginId] = null;
                continue;
            }
            if (in_array($type, ['LOGIN_ID', 'RESOLVED_LOGIN_ID'], true) && $targetValue !== '') {
                $loginIds[$targetValue] = null;
                continue;
            }
            if ($type === 'GROUP_ID' && $targetValue !== '') {
                foreach ($this->loadLoginIdsByGroup((int)$targetValue) as $loginId => $categoryUser) {
                    $loginIds[$loginId] = $categoryUser;
                }
                continue;
            }
            if ($type === 'CATEGORY_USER' && $targetValue !== '') {
                foreach ($this->loadLoginIdsByCategory($targetValue) as $loginId => $categoryUser) {
                    $loginIds[$loginId] = $categoryUser;
                }
                continue;
            }
            if ($type === 'ALL') {
                foreach ($this->loadAllLoginIds() as $loginId => $categoryUser) {
                    $loginIds[$loginId] = $categoryUser;
                }
                continue;
            }
            if ($type === 'ROLE_ID' && $targetValue !== '') {
                foreach ($this->loadLoginIdsByRole($targetValue) as $loginId => $categoryUser) {
                    $loginIds[$loginId] = $categoryUser;
                }
                continue;
            }
            if ($type === 'DEPARTMENT_ID' && $targetValue !== '') {
                foreach ($this->loadLoginIdsByDepartment($targetValue) as $loginId => $categoryUser) {
                    $loginIds[$loginId] = $categoryUser;
                }
            }
        }

        return $this->hydrateCategorySnapshots($loginIds);
    }

    /**
     * @return array<string,string|null>
     */
    private function loadLoginIdsByGroup(int $groupId): array
    {
        if ($groupId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.f_loginID, u.f_categoryUser
            FROM tbl_m_user u
            LEFT JOIN tbl_ref_access a
              ON a.f_userID = u.f_userID
             AND a.f_status = 1
            WHERE TRIM(COALESCE(u.f_loginID, '')) <> ''
              AND (u.f_groupID = :group_id_user OR a.f_groupID = :group_id_access)
              AND COALESCE(u.f_flag, 1) = 1
        ");
        $stmt->execute([
            ':group_id_user' => $groupId,
            ':group_id_access' => $groupId,
        ]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loginId = trim((string)($row['f_loginID'] ?? ''));
            if ($loginId !== '') {
                $rows[$loginId] = $this->normalizeCategoryForState((string)($row['f_categoryUser'] ?? ''));
            }
        }
        return $rows;
    }

    /**
     * @return array<string,string|null>
     */
    private function loadAllLoginIds(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT f_loginID, f_categoryUser
            FROM tbl_m_user
            WHERE TRIM(COALESCE(f_loginID, '')) <> ''
              AND COALESCE(f_flag, 1) = 1
        ");

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loginId = trim((string)($row['f_loginID'] ?? ''));
            if ($loginId !== '') {
                $rows[$loginId] = $this->normalizeCategoryForState((string)($row['f_categoryUser'] ?? ''));
            }
        }
        return $rows;
    }

    /**
     * @return array<string,string|null>
     */
    private function loadLoginIdsByRole(string $roleId): array
    {
        $roleId = strtoupper(trim($roleId));
        if ($roleId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.f_loginID, u.f_categoryUser
            FROM tbl_m_user u
            LEFT JOIN tbl_m_group g
              ON g.f_groupID = u.f_groupID
            LEFT JOIN tbl_ref_access a
              ON a.f_userID = u.f_userID
             AND a.f_status = 1
            LEFT JOIN tbl_m_group ag
              ON ag.f_groupID = a.f_groupID
            WHERE TRIM(COALESCE(u.f_loginID, '')) <> ''
              AND COALESCE(u.f_flag, 1) = 1
              AND (
                CAST(u.f_groupID AS CHAR) = :role_id_user
                OR UPPER(COALESCE(g.f_groupKod, '')) = :role_id_group
                OR CAST(a.f_groupID AS CHAR) = :role_id_access
                OR UPPER(COALESCE(ag.f_groupKod, '')) = :role_id_access_group
              )
        ");
        $stmt->execute([
            ':role_id_user' => $roleId,
            ':role_id_group' => $roleId,
            ':role_id_access' => $roleId,
            ':role_id_access_group' => $roleId,
        ]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loginId = trim((string)($row['f_loginID'] ?? ''));
            if ($loginId !== '') {
                $rows[$loginId] = $this->normalizeCategoryForState((string)($row['f_categoryUser'] ?? ''));
            }
        }
        return $rows;
    }

    /**
     * @return array<string,string|null>
     */
    private function loadLoginIdsByDepartment(string $departmentId): array
    {
        $departmentId = strtoupper(trim($departmentId));
        if ($departmentId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT f_loginID, f_categoryUser
            FROM tbl_m_user
            WHERE TRIM(COALESCE(f_loginID, '')) <> ''
              AND COALESCE(f_flag, 1) = 1
              AND (
                UPPER(TRIM(COALESCE(f_jabatanKod, ''))) = :department_id_code
                OR UPPER(TRIM(COALESCE(f_namajabatan, ''))) = :department_id_name
              )
        ");
        $stmt->execute([
            ':department_id_code' => $departmentId,
            ':department_id_name' => $departmentId,
        ]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loginId = trim((string)($row['f_loginID'] ?? ''));
            if ($loginId !== '') {
                $rows[$loginId] = $this->normalizeCategoryForState((string)($row['f_categoryUser'] ?? ''));
            }
        }
        return $rows;
    }

    /**
     * @return array<string,string|null>
     */
    private function loadLoginIdsByCategory(string $categoryUser): array
    {
        $categoryUser = strtoupper(trim($categoryUser));
        if (!in_array($categoryUser, ['STAF', 'PELAJAR', 'UMUM'], true)) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT f_loginID, f_categoryUser
            FROM tbl_m_user
            WHERE TRIM(COALESCE(f_loginID, '')) <> ''
              AND TRIM(COALESCE(f_categoryUser, '')) = :category_user
              AND COALESCE(f_flag, 1) = 1
        ");
        $stmt->execute([':category_user' => $categoryUser]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loginId = trim((string)($row['f_loginID'] ?? ''));
            if ($loginId !== '') {
                $rows[$loginId] = $this->normalizeCategoryForState((string)($row['f_categoryUser'] ?? ''));
            }
        }
        return $rows;
    }

    /**
     * @param array<string,string|null> $loginIds
     * @return array<string,string|null>
     */
    private function hydrateCategorySnapshots(array $loginIds): array
    {
        $missing = array_keys(array_filter($loginIds, static fn($category) => $category === null));
        if ($missing === []) {
            return $loginIds;
        }

        $placeholders = [];
        $params = [];
        foreach ($missing as $index => $loginId) {
            $key = ':login_' . $index;
            $placeholders[] = $key;
            $params[$key] = $loginId;
        }

        $stmt = $this->pdo->prepare("
            SELECT f_loginID, f_categoryUser
            FROM tbl_m_user
            WHERE f_loginID IN (" . implode(',', $placeholders) . ")
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loginId = trim((string)($row['f_loginID'] ?? ''));
            if ($loginId !== '' && array_key_exists($loginId, $loginIds)) {
                $loginIds[$loginId] = $this->normalizeCategoryForState((string)($row['f_categoryUser'] ?? ''));
            }
        }

        return $loginIds;
    }

    private function normalizeCategoryForState(string $categoryUser): ?string
    {
        $categoryUser = strtoupper(trim($categoryUser));
        return in_array($categoryUser, ['STAF', 'PELAJAR', 'UMUM'], true) ? $categoryUser : null;
    }

    private function safeActionUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^(https?:)?\/\//i', $url)) {
            return '';
        }
        if (preg_match('/^(?:javascript|data):/i', $url) || str_contains($url, '..') || str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }
        $url = ltrim($url, '/');
        return preg_match('/^[A-Za-z0-9_.\/-]+(?:\?[A-Za-z0-9_=&%+.,:@\/-]*)?(?:#[A-Za-z0-9_.-]*)?$/', $url) ? $url : '';
    }

    private function normalizeLang(string $lang): string
    {
        return strtolower($lang) === 'en' ? 'en' : 'ms';
    }

    private function formatTimeAgo(string $datetime, string $lang): string
    {
        if ($datetime === '') {
            return '';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }

        $diff = max(0, time() - $timestamp);
        if ($diff < 60) {
            return $lang === 'en' ? 'just now' : 'baru sahaja';
        }

        $units = [
            31536000 => ['en' => 'year', 'ms' => 'tahun'],
            2592000 => ['en' => 'month', 'ms' => 'bulan'],
            86400 => ['en' => 'day', 'ms' => 'hari'],
            3600 => ['en' => 'hour', 'ms' => 'jam'],
            60 => ['en' => 'min', 'ms' => 'min'],
        ];

        foreach ($units as $seconds => $labels) {
            $value = intdiv($diff, $seconds);
            if ($value > 0) {
                if ($lang === 'en') {
                    $label = $labels['en'] . ($value > 1 && $labels['en'] !== 'min' ? 's' : '');
                    return $value . ' ' . $label . ' ago';
                }
                return $value . ' ' . $labels['ms'] . ' lalu';
            }
        }

        return $datetime;
    }

    private function isOverdue(mixed $dueAt, bool $requiresAction, string $actionStatus): bool
    {
        if (!$requiresAction || $actionStatus !== 'pending' || $dueAt === null || trim((string)$dueAt) === '') {
            return false;
        }
        $timestamp = strtotime((string)$dueAt);
        return $timestamp !== false && $timestamp < time();
    }

    private function formatDueLabel(mixed $dueAt, string $lang): string
    {
        if ($dueAt === null || trim((string)$dueAt) === '') {
            return '';
        }
        $timestamp = strtotime((string)$dueAt);
        if ($timestamp === false) {
            return (string)$dueAt;
        }

        $diff = $timestamp - time();
        $abs = abs($diff);
        if ($abs < 60) {
            return $diff < 0
                ? ($lang === 'en' ? 'due just now' : 'tamat tempoh sebentar tadi')
                : ($lang === 'en' ? 'due soon' : 'hampir tamat tempoh');
        }

        $units = [
            86400 => ['en' => 'day', 'ms' => 'hari'],
            3600 => ['en' => 'hour', 'ms' => 'jam'],
            60 => ['en' => 'min', 'ms' => 'min'],
        ];

        foreach ($units as $seconds => $labels) {
            $value = intdiv($abs, $seconds);
            if ($value > 0) {
                if ($lang === 'en') {
                    $label = $labels['en'] . ($value > 1 && $labels['en'] !== 'min' ? 's' : '');
                    return $diff < 0 ? 'overdue by ' . $value . ' ' . $label : 'due in ' . $value . ' ' . $label;
                }
                return $diff < 0 ? 'lewat ' . $value . ' ' . $labels['ms'] : 'tamat dalam ' . $value . ' ' . $labels['ms'];
            }
        }

        return date('Y-m-d H:i', $timestamp);
    }
}
