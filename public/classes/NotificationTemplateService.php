<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class NotificationTemplateService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT
              t.*,
              (
                SELECT COUNT(DISTINCT n.f_notificationID)
                FROM tbl_notification n
                WHERE n.f_templateCode = t.f_templateCode
                   OR n.f_eventCode = t.f_eventCode
              ) AS usage_count
            FROM tbl_notification_template t
            ORDER BY t.f_status DESC, t.f_templateCode ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $options @return array{total:int,filtered:int,items:array<int,array<string,mixed>>} */
    public function getList(array $options = []): array
    {
        $limit = max(5, min(100, (int)($options['limit'] ?? 10)));
        $offset = max(0, min(100000, (int)($options['offset'] ?? 0)));
        $rawSearch = trim((string)($options['search'] ?? ''));
        $search = function_exists('mb_substr') ? mb_substr($rawSearch, 0, 100) : substr($rawSearch, 0, 100);
        $type = strtolower(trim((string)($options['type'] ?? '')));
        $priority = strtolower(trim((string)($options['priority'] ?? '')));
        $status = strtolower(trim((string)($options['status'] ?? 'all')));
        $where = [];
        if ($search !== '') $where[] = '(t.f_templateCode LIKE :search_code OR t.f_eventCode LIKE :search_event OR t.f_title_ms LIKE :search_title OR t.f_title_en LIKE :search_title_en)';
        if (in_array($type, ['event','announcement','reminder','workflow'], true)) $where[] = 't.f_type = :type';
        if (in_array($priority, ['low','normal','high','urgent'], true)) $where[] = 't.f_priority = :priority';
        if ($status === 'active') $where[] = 't.f_status = 1';
        if ($status === 'archived') $where[] = 't.f_status = 0';
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int)$this->pdo->query('SELECT COUNT(*) FROM tbl_notification_template')->fetchColumn();
        $filtered = $total;
        if ($where !== []) {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tbl_notification_template t {$whereSql}");
            $this->bindListFilters($countStmt, $search, $type, $priority);
            $countStmt->execute();
            $filtered = (int)$countStmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare("SELECT t.*,(SELECT COUNT(DISTINCT n.f_notificationID) FROM tbl_notification n WHERE n.f_templateCode=t.f_templateCode OR n.f_eventCode=t.f_eventCode) AS usage_count FROM tbl_notification_template t {$whereSql} ORDER BY t.f_status DESC,t.f_templateCode ASC LIMIT :limit OFFSET :offset");
        $this->bindListFilters($stmt, $search, $type, $priority); $stmt->bindValue(':limit',$limit,PDO::PARAM_INT); $stmt->bindValue(':offset',$offset,PDO::PARAM_INT); $stmt->execute();
        return ['total'=>$total,'filtered'=>$filtered,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    private function bindListFilters(PDOStatement $stmt, string $search, string $type, string $priority): void
    {
        if ($search !== '') { $like='%'.$search.'%'; foreach ([':search_code',':search_event',':search_title',':search_title_en'] as $key) $stmt->bindValue($key,$like); }
        if (in_array($type,['event','announcement','reminder','workflow'],true)) $stmt->bindValue(':type',$type);
        if (in_array($priority,['low','normal','high','urgent'],true)) $stmt->bindValue(':priority',$priority);
    }

    public function findById(int $templateId): ?array
    {
        if ($templateId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM tbl_notification_template
            WHERE f_templateID = :template_id
            LIMIT 1
        ");
        $stmt->execute([':template_id' => $templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $input
     */
    public function save(array $input, string $updateBy): int
    {
        $data = $this->validate($input);
        $templateId = (int)($input['template_id'] ?? $input['f_templateID'] ?? 0);
        if ($templateId > 0 && !$this->findById($templateId)) {
            throw new RuntimeException((string)(__('notification_template_not_found') ?: 'Notification template not found.'));
        }
        $this->assertUniqueCodes($data['template_code'], $data['event_code'], $templateId);

        if ($templateId > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE tbl_notification_template
                SET f_templateCode = :template_code,
                    f_eventCode = :event_code,
                    f_moduleCode = :module_code,
                    f_type = :type,
                    f_category = :category,
                    f_severity = :severity,
                    f_priority = :priority,
                    f_title_ms = :title_ms,
                    f_title_en = :title_en,
                    f_body_ms = :body_ms,
                    f_body_en = :body_en,
                    f_actionLabel_ms = :action_label_ms,
                    f_actionLabel_en = :action_label_en,
                    f_icon = :icon,
                    f_requiresAction = :requires_action,
                    f_placeholders = :placeholders,
                    f_status = :status,
                    f_insertBy = :insert_by,
                    f_updatedt = NOW()
                WHERE f_templateID = :template_id
                LIMIT 1
            ");
            $stmt->execute($this->params($data, [
                ':template_id' => $templateId,
                ':insert_by' => $updateBy,
            ]));
            return $templateId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_notification_template (
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
              f_requiresAction,
              f_placeholders,
              f_status,
              f_insertBy,
              f_insertdt,
              f_updatedt
            ) VALUES (
              :template_code,
              :event_code,
              :module_code,
              :type,
              :category,
              :severity,
              :priority,
              :title_ms,
              :title_en,
              :body_ms,
              :body_en,
              :action_label_ms,
              :action_label_en,
              :icon,
              :requires_action,
              :placeholders,
              :status,
              :insert_by,
              NOW(),
              NOW()
            )
        ");
        $stmt->execute($this->params($data, [':insert_by' => $updateBy]));

        return (int)$this->pdo->lastInsertId();
    }

    public function duplicate(int $templateId, string $updateBy): int
    {
        $row = $this->findById($templateId);
        if (!$row) {
            throw new RuntimeException('Notification template not found.');
        }

        $baseCode = (string)$row['f_templateCode'];
        $row['f_templateID'] = 0;
        $row['template_id'] = 0;
        $row['template_code'] = $this->nextDuplicateCode($baseCode);

        return $this->save($this->rowToInput($row), $updateBy);
    }

    public function archive(int $templateId, string $updateBy): void
    {
        $this->setStatus($templateId, 0, $updateBy);
    }

    public function restore(int $templateId, string $updateBy): void
    {
        $this->setStatus($templateId, 1, $updateBy);
    }

    public function delete(int $templateId): void
    {
        if ($templateId <= 0) {
            throw new RuntimeException('Notification template not found.');
        }

        $row = $this->findById($templateId);
        if (!$row) {
            throw new RuntimeException('Notification template not found.');
        }

        if ($this->isTemplateInUse((string)$row['f_templateCode'], (string)($row['f_eventCode'] ?? ''))) {
            throw new RuntimeException('Template is currently used by existing notifications and cannot be deleted. Archive it instead.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM tbl_notification_template WHERE f_templateID = :template_id LIMIT 1');
        $stmt->execute([':template_id' => $templateId]);
    }

    public function isTemplateInUse(string $templateCode, string $eventCode = ''): bool
    {
        $templateCode = trim($templateCode);
        $eventCode = trim($eventCode);
        if ($templateCode === '' && $eventCode === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM tbl_notification
            WHERE (:template_code <> "" AND f_templateCode = :template_code)
               OR (:event_code <> "" AND f_eventCode = :event_code)
            LIMIT 1
        ');
        $stmt->execute([
            ':template_code' => $templateCode,
            ':event_code' => $eventCode,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @return array<string,int>
     */
    public function summary(): array
    {
        $row = $this->pdo->query("
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN f_status = 1 THEN 1 ELSE 0 END) AS active,
              SUM(CASE WHEN f_status = 0 THEN 1 ELSE 0 END) AS archived,
              SUM(CASE WHEN f_requiresAction = 1 THEN 1 ELSE 0 END) AS action_required
            FROM tbl_notification_template
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'archived' => (int)($row['archived'] ?? 0),
            'action_required' => (int)($row['action_required'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function validate(array $input): array
    {
        $data = [
            'template_code' => strtoupper($this->cleanCode((string)($input['template_code'] ?? $input['f_templateCode'] ?? ''), 100)),
            'event_code' => $this->cleanCode((string)($input['event_code'] ?? $input['f_eventCode'] ?? ''), 100),
            'module_code' => $this->nullableCleanCode($input['module_code'] ?? $input['f_moduleCode'] ?? null, 64),
            'type' => $this->cleanCode((string)($input['type'] ?? $input['f_type'] ?? 'event'), 32),
            'category' => $this->cleanCode((string)($input['category'] ?? $input['f_category'] ?? 'system'), 32),
            'severity' => $this->choice((string)($input['severity'] ?? $input['f_severity'] ?? 'info'), ['info', 'success', 'warning', 'danger', 'error'], 'info'),
            'priority' => $this->choice((string)($input['priority'] ?? $input['f_priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], 'normal'),
            'title_ms' => trim((string)($input['title_ms'] ?? $input['f_title_ms'] ?? '')),
            'title_en' => $this->nullableText($input['title_en'] ?? $input['f_title_en'] ?? null, 255),
            'body_ms' => $this->nullableLongText($input['body_ms'] ?? $input['f_body_ms'] ?? null),
            'body_en' => $this->nullableLongText($input['body_en'] ?? $input['f_body_en'] ?? null),
            'action_label_ms' => $this->nullableText($input['action_label_ms'] ?? $input['f_actionLabel_ms'] ?? null, 100),
            'action_label_en' => $this->nullableText($input['action_label_en'] ?? $input['f_actionLabel_en'] ?? null, 100),
            'icon' => $this->nullableCleanCode($input['icon'] ?? $input['f_icon'] ?? 'ri-notification-3-line', 64),
            'requires_action' => !empty($input['requires_action'] ?? $input['f_requiresAction'] ?? 0) ? 1 : 0,
            'placeholders' => $this->normalizePlaceholders($input['placeholders'] ?? $input['f_placeholders'] ?? null),
            'status' => isset($input['status']) ? (int)$input['status'] : (int)($input['f_status'] ?? 1),
        ];

        if ($data['template_code'] === '' || $data['event_code'] === '' || $data['title_ms'] === '') {
            throw new InvalidArgumentException((string)(__('notification_template_validation_required') ?: 'Template code, event code, and MS title are required.'));
        }
        foreach (['body_ms','body_en'] as $field) {
            if (strlen((string)($data[$field] ?? '')) > 10000) throw new InvalidArgumentException((string)(__('notification_template_validation_body_length') ?: 'Template content must not exceed 10,000 characters.'));
        }

        $data['title_ms'] = $this->limit($data['title_ms'], 255);
        $data['status'] = $data['status'] === 1 ? 1 : 0;

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function params(array $data, array $extra = []): array
    {
        return $extra + [
            ':template_code' => $data['template_code'],
            ':event_code' => $data['event_code'],
            ':module_code' => $data['module_code'],
            ':type' => $data['type'],
            ':category' => $data['category'],
            ':severity' => $data['severity'],
            ':priority' => $data['priority'],
            ':title_ms' => $data['title_ms'],
            ':title_en' => $data['title_en'],
            ':body_ms' => $data['body_ms'],
            ':body_en' => $data['body_en'],
            ':action_label_ms' => $data['action_label_ms'],
            ':action_label_en' => $data['action_label_en'],
            ':icon' => $data['icon'],
            ':requires_action' => (int)$data['requires_action'],
            ':placeholders' => $data['placeholders'],
            ':status' => (int)$data['status'],
        ];
    }

    private function setStatus(int $templateId, int $status, string $updateBy): void
    {
        if ($templateId <= 0) {
            throw new RuntimeException('Notification template not found.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE tbl_notification_template
            SET f_status = :status,
                f_insertBy = :insert_by,
                f_updatedt = NOW()
            WHERE f_templateID = :template_id
            LIMIT 1
        ");
        $stmt->execute([
            ':template_id' => $templateId,
            ':status' => $status === 1 ? 1 : 0,
            ':insert_by' => $updateBy,
        ]);
        if ($stmt->rowCount() === 0 && !$this->findById($templateId)) throw new RuntimeException((string)(__('notification_template_not_found') ?: 'Notification template not found.'));
    }

    private function assertUniqueCodes(string $templateCode, string $eventCode, int $excludeId): void
    {
        $stmt=$this->pdo->prepare('SELECT f_templateID FROM tbl_notification_template WHERE (f_templateCode=:template_code OR f_eventCode=:event_code) AND f_templateID<>:exclude_id LIMIT 1');
        $stmt->execute([':template_code'=>$templateCode,':event_code'=>$eventCode,':exclude_id'=>$excludeId]);
        if ($stmt->fetchColumn()) throw new InvalidArgumentException((string)(__('notification_template_validation_unique') ?: 'Template code and event code must be unique.'));
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function rowToInput(array $row): array
    {
        return [
            'template_id' => (int)($row['f_templateID'] ?? $row['template_id'] ?? 0),
            'template_code' => (string)($row['template_code'] ?? $row['f_templateCode'] ?? ''),
            'event_code' => (string)($row['f_eventCode'] ?? ''),
            'module_code' => (string)($row['f_moduleCode'] ?? ''),
            'type' => (string)($row['f_type'] ?? 'event'),
            'category' => (string)($row['f_category'] ?? 'system'),
            'severity' => (string)($row['f_severity'] ?? 'info'),
            'priority' => (string)($row['f_priority'] ?? 'normal'),
            'title_ms' => (string)($row['f_title_ms'] ?? ''),
            'title_en' => (string)($row['f_title_en'] ?? ''),
            'body_ms' => (string)($row['f_body_ms'] ?? ''),
            'body_en' => (string)($row['f_body_en'] ?? ''),
            'action_label_ms' => (string)($row['f_actionLabel_ms'] ?? ''),
            'action_label_en' => (string)($row['f_actionLabel_en'] ?? ''),
            'icon' => (string)($row['f_icon'] ?? 'ri-notification-3-line'),
            'requires_action' => (int)($row['f_requiresAction'] ?? 0),
            'placeholders' => (string)($row['f_placeholders'] ?? ''),
            'status' => (int)($row['f_status'] ?? 1),
        ];
    }

    private function nextDuplicateCode(string $baseCode): string
    {
        $baseCode = preg_replace('/_COPY(_\d+)?$/', '', strtoupper(trim($baseCode))) ?: 'NOTIFICATION_TEMPLATE';
        for ($i = 1; $i <= 99; $i++) {
            $candidate = $baseCode . '_COPY' . ($i > 1 ? '_' . $i : '');
            $stmt = $this->pdo->prepare('SELECT 1 FROM tbl_notification_template WHERE f_templateCode = :code LIMIT 1');
            $stmt->execute([':code' => $candidate]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
        }
        throw new RuntimeException('Unable to generate duplicate template code.');
    }

    private function normalizePlaceholders(mixed $value): ?string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? null : $encoded;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException((string)(__('notification_template_validation_json') ?: 'Placeholders must be valid JSON.'));
        }
        $decoded=json_decode($value,true);
        if (!is_array($decoded) || array_is_list($decoded) || count($decoded)>100 || strlen($value)>10000) throw new InvalidArgumentException((string)(__('notification_template_validation_json_object') ?: 'Placeholders must be a JSON object with no more than 100 entries.'));

        return $value;
    }

    private function cleanCode(string $value, int $limit): string
    {
        $value = trim($value);
        $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $value) ?: '';
        return $this->limit($value, $limit);
    }

    private function nullableCleanCode(mixed $value, int $limit): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $this->cleanCode($value, $limit);
    }

    private function choice(string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim($value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function nullableText(mixed $value, int $limit): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $this->limit($value, $limit);
    }

    private function nullableLongText(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function limit(string $value, int $limit): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }
}
