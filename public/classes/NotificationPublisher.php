<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NotificationAudienceResolver.php';

final class NotificationPublisher
{
    private const DEDUPE_SKIP = 'skip';
    private const DEDUPE_UPDATE = 'update';
    private const DEDUPE_REPUBLISH = 'republish';

    public function __construct(
        private PDO $pdo,
        private NotificationAudienceResolver $resolver
    ) {}

    public static function default(): self
    {
        $pdo = Database::getInstance('mysql')->getConnection();
        return new self($pdo, new NotificationAudienceResolver($pdo));
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     */
    public function publish(array $payload, array $options = []): int
    {
        $payload = $this->validatePayload($payload);
        $audienceRows = $this->resolver->resolve(
            (array)$payload['audience'],
            (array)($options['audience_context'] ?? $payload['audience_context'] ?? [])
        );
        if ($audienceRows === []) {
            throw new InvalidArgumentException('Notification audience cannot be empty.');
        }

        $dedupeBehavior = $this->normalizeDedupeBehavior((string)($options['dedupe'] ?? $payload['dedupe'] ?? self::DEDUPE_SKIP));
        $existingId = $this->findExistingByDedupeKey((string)($payload['dedupe_key'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            if ($existingId > 0) {
                if ($dedupeBehavior === self::DEDUPE_SKIP) {
                    $this->pdo->commit();
                    return $existingId;
                }

                if ($dedupeBehavior === self::DEDUPE_UPDATE) {
                    $this->updateNotification($existingId, $payload);
                    $this->replaceAudience($existingId, $audienceRows);
                    $this->pdo->commit();
                    return $existingId;
                }

                $this->archiveExistingDedupe($existingId);
            }

            $notificationId = $this->insertNotification($payload);
            $this->insertAudience($notificationId, $audienceRows);
            $this->pdo->commit();

            return $notificationId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function publishFromTemplate(string $eventCode, array $variables, array $payload = [], array $options = []): int
    {
        $template = $this->loadTemplate($eventCode);
        if ($template !== null) {
            $defaults = [
                'template_code' => (string)$template['f_templateCode'],
                'event_code' => (string)$template['f_eventCode'],
                'module_code' => (string)($template['f_moduleCode'] ?? ''),
                'type' => (string)$template['f_type'],
                'category' => (string)$template['f_category'],
                'severity' => (string)$template['f_severity'],
                'priority' => (string)$template['f_priority'],
                'title_ms' => (string)$template['f_title_ms'],
                'title_en' => (string)($template['f_title_en'] ?? ''),
                'body_ms' => (string)($template['f_body_ms'] ?? ''),
                'body_en' => (string)($template['f_body_en'] ?? ''),
                'action_label_ms' => (string)($template['f_actionLabel_ms'] ?? ''),
                'action_label_en' => (string)($template['f_actionLabel_en'] ?? ''),
                'icon' => (string)($template['f_icon'] ?? 'ri-notification-3-line'),
                'requires_action' => (int)($template['f_requiresAction'] ?? 0),
            ];
            $payload = array_replace($defaults, $payload);
        }

        $payload['event_code'] = $payload['event_code'] ?? $eventCode;

        foreach (['title_ms', 'title_en', 'body_ms', 'body_en', 'action_label_ms', 'action_label_en', 'action_url'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = $this->renderVariables($payload[$field], $variables);
            }
        }

        return $this->publish($payload, $options);
    }

    private function loadTemplate(string $eventOrTemplateCode): ?array
    {
        $code = trim($eventOrTemplateCode);
        if ($code === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM tbl_notification_template
            WHERE f_status = 1
              AND (f_eventCode = :code_event OR f_templateCode = :code_template)
            ORDER BY CASE WHEN f_templateCode = :code_order THEN 0 ELSE 1 END, f_templateID ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':code_event' => $code,
            ':code_template' => $code,
            ':code_order' => $code,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function validatePayload(array $payload): array
    {
        $payload = $this->normalizePayloadAliases($payload);

        if (trim((string)($payload['title_ms'] ?? '')) === '') {
            throw new InvalidArgumentException('Notification title_ms is required.');
        }
        if (!isset($payload['audience']) || !is_array($payload['audience'])) {
            throw new InvalidArgumentException('Notification audience array is required.');
        }

        $payload['event_code'] = $this->cleanCode((string)($payload['event_code'] ?? 'system.notification'), 100);
        $payload['template_code'] = $this->nullableCleanCode($payload['template_code'] ?? null, 100);
        $payload['module_code'] = $this->nullableCleanCode($payload['module_code'] ?? null, 64);
        $payload['type'] = $this->cleanCode((string)($payload['type'] ?? 'announcement'), 32);
        $payload['category'] = $this->cleanCode((string)($payload['category'] ?? 'system'), 32);
        $payload['severity'] = $this->normalizeChoice((string)($payload['severity'] ?? 'info'), ['info', 'success', 'warning', 'danger', 'error'], 'info');
        $payload['priority'] = $this->normalizeChoice((string)($payload['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], 'normal');
        $payload['title_ms'] = $this->limitText((string)$payload['title_ms'], 255);
        $payload['title_en'] = $this->nullableLimitText($payload['title_en'] ?? null, 255);
        $payload['body_ms'] = $this->nullableText($payload['body_ms'] ?? null);
        $payload['body_en'] = $this->nullableText($payload['body_en'] ?? null);
        $payload['action_url'] = $this->safeActionUrl((string)($payload['action_url'] ?? ''));
        $payload['action_label_ms'] = $this->nullableLimitText($payload['action_label_ms'] ?? null, 100);
        $payload['action_label_en'] = $this->nullableLimitText($payload['action_label_en'] ?? null, 100);
        $payload['icon'] = $this->nullableCleanCode($payload['icon'] ?? 'ri-notification-3-line', 64);
        $payload['source_type'] = $this->nullableCleanCode($payload['source_type'] ?? null, 32);
        $payload['source_id'] = $this->nullableLimitText($payload['source_id'] ?? null, 64);
        $payload['requires_action'] = !empty($payload['requires_action']) ? 1 : 0;
        $payload['due_at'] = $this->nullableDateTime($payload['due_at'] ?? null);
        $payload['dedupe_key'] = $this->nullableLimitText($payload['dedupe_key'] ?? null, 190);
        $payload['is_broadcast'] = !empty($payload['is_broadcast']) ? 1 : 0;
        $payload['status'] = isset($payload['status']) ? (int)$payload['status'] : 1;
        $payload['starts_at'] = $this->nullableDateTime($payload['starts_at'] ?? null);
        $payload['expires_at'] = $this->nullableDateTime($payload['expires_at'] ?? null);
        $payload['created_by_type'] = $this->cleanCode((string)($payload['created_by_type'] ?? 'system'), 32);
        $payload['created_by_login_id'] = $this->nullableLimitText($payload['created_by_login_id'] ?? ($_SESSION['f_loginID'] ?? null), 150);
        $payload['insert_by'] = $this->nullableLimitText($payload['insert_by'] ?? ($_SESSION['f_loginID'] ?? null), 150);

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizePayloadAliases(array $payload): array
    {
        $aliases = [
            'eventCode' => 'event_code',
            'templateCode' => 'template_code',
            'moduleCode' => 'module_code',
            'title' => 'title_ms',
            'titleMs' => 'title_ms',
            'titleEn' => 'title_en',
            'body' => 'body_ms',
            'bodyMs' => 'body_ms',
            'bodyEn' => 'body_en',
            'actionUrl' => 'action_url',
            'actionLabelMs' => 'action_label_ms',
            'actionLabelEn' => 'action_label_en',
            'sourceType' => 'source_type',
            'sourceId' => 'source_id',
            'requiresAction' => 'requires_action',
            'dueAt' => 'due_at',
            'dedupeKey' => 'dedupe_key',
            'isBroadcast' => 'is_broadcast',
            'startsAt' => 'starts_at',
            'expiresAt' => 'expires_at',
            'createdByType' => 'created_by_type',
            'createdByLoginID' => 'created_by_login_id',
            'insertBy' => 'insert_by',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $payload) && !array_key_exists($to, $payload)) {
                $payload[$to] = $payload[$from];
            }
        }

        return $payload;
    }

    private function insertNotification(array $payload): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_notification (
              f_code,
              f_eventCode,
              f_templateCode,
              f_moduleCode,
              f_type,
              f_category,
              f_severity,
              f_priority,
              f_title_ms,
              f_title_en,
              f_body_ms,
              f_body_en,
              f_actionURL,
              f_actionLabel_ms,
              f_actionLabel_en,
              f_icon,
              f_sourceType,
              f_sourceID,
              f_requiresAction,
              f_dueAt,
              f_dedupeKey,
              f_isBroadcast,
              f_status,
              f_startsAt,
              f_expiresAt,
              f_createdByType,
              f_createdByLoginID,
              f_insertBy,
              f_insertdt,
              f_updatedt
            ) VALUES (
              :code,
              :event_code,
              :template_code,
              :module_code,
              :type,
              :category,
              :severity,
              :priority,
              :title_ms,
              :title_en,
              :body_ms,
              :body_en,
              :action_url,
              :action_label_ms,
              :action_label_en,
              :icon,
              :source_type,
              :source_id,
              :requires_action,
              :due_at,
              :dedupe_key,
              :is_broadcast,
              :status,
              :starts_at,
              :expires_at,
              :created_by_type,
              :created_by_login_id,
              :insert_by,
              NOW(),
              NOW()
            )
        ");

        $stmt->execute($this->notificationParams($payload, [
            ':code' => $this->generateCode($payload),
        ]));

        return (int)$this->pdo->lastInsertId();
    }

    private function updateNotification(int $notificationId, array $payload): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE tbl_notification
            SET f_eventCode = :event_code,
                f_templateCode = :template_code,
                f_moduleCode = :module_code,
                f_type = :type,
                f_category = :category,
                f_severity = :severity,
                f_priority = :priority,
                f_title_ms = :title_ms,
                f_title_en = :title_en,
                f_body_ms = :body_ms,
                f_body_en = :body_en,
                f_actionURL = :action_url,
                f_actionLabel_ms = :action_label_ms,
                f_actionLabel_en = :action_label_en,
                f_icon = :icon,
                f_sourceType = :source_type,
                f_sourceID = :source_id,
                f_requiresAction = :requires_action,
                f_dueAt = :due_at,
                f_dedupeKey = :dedupe_key,
                f_isBroadcast = :is_broadcast,
                f_status = :status,
                f_startsAt = :starts_at,
                f_expiresAt = :expires_at,
                f_createdByType = :created_by_type,
                f_createdByLoginID = :created_by_login_id,
                f_insertBy = :insert_by,
                f_updatedt = NOW()
            WHERE f_notificationID = :notification_id
            LIMIT 1
        ");

        $stmt->execute($this->notificationParams($payload, [
            ':notification_id' => $notificationId,
        ]));
    }

    /**
     * @param array<int,array{type:string,value:?string,resolved_login_id:?string}> $audienceRows
     */
    private function replaceAudience(int $notificationId, array $audienceRows): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tbl_notification_audience WHERE f_notificationID = :notification_id');
        $stmt->execute([':notification_id' => $notificationId]);
        $this->insertAudience($notificationId, $audienceRows);
    }

    /**
     * @param array<int,array{type:string,value:?string,resolved_login_id:?string}> $audienceRows
     */
    private function insertAudience(int $notificationId, array $audienceRows): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_notification_audience (
              f_notificationID,
              f_targetType,
              f_targetValue,
              f_resolvedLoginID,
              f_insertdt
            ) VALUES (
              :notification_id,
              :target_type,
              :target_value,
              :resolved_login_id,
              NOW()
            )
        ");

        foreach ($audienceRows as $row) {
            $stmt->execute([
                ':notification_id' => $notificationId,
                ':target_type' => $row['type'],
                ':target_value' => $row['value'],
                ':resolved_login_id' => $row['resolved_login_id'],
            ]);
        }
    }

    private function findExistingByDedupeKey(string $dedupeKey): int
    {
        $dedupeKey = trim($dedupeKey);
        if ($dedupeKey === '') {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT f_notificationID
            FROM tbl_notification
            WHERE f_dedupeKey = :dedupe_key
            LIMIT 1
        ");
        $stmt->execute([':dedupe_key' => $dedupeKey]);

        return (int)$stmt->fetchColumn();
    }

    private function archiveExistingDedupe(int $notificationId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE tbl_notification
            SET f_status = 0,
                f_dedupeKey = CONCAT(f_dedupeKey, ':archived:', f_notificationID),
                f_updatedt = NOW()
            WHERE f_notificationID = :notification_id
              AND f_dedupeKey IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([':notification_id' => $notificationId]);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function notificationParams(array $payload, array $extra = []): array
    {
        return $extra + [
            ':event_code' => $payload['event_code'],
            ':template_code' => $payload['template_code'],
            ':module_code' => $payload['module_code'],
            ':type' => $payload['type'],
            ':category' => $payload['category'],
            ':severity' => $payload['severity'],
            ':priority' => $payload['priority'],
            ':title_ms' => $payload['title_ms'],
            ':title_en' => $payload['title_en'],
            ':body_ms' => $payload['body_ms'],
            ':body_en' => $payload['body_en'],
            ':action_url' => $payload['action_url'] !== '' ? $payload['action_url'] : null,
            ':action_label_ms' => $payload['action_label_ms'],
            ':action_label_en' => $payload['action_label_en'],
            ':icon' => $payload['icon'],
            ':source_type' => $payload['source_type'],
            ':source_id' => $payload['source_id'],
            ':requires_action' => (int)$payload['requires_action'],
            ':due_at' => $payload['due_at'],
            ':dedupe_key' => $payload['dedupe_key'],
            ':is_broadcast' => (int)$payload['is_broadcast'],
            ':status' => (int)$payload['status'],
            ':starts_at' => $payload['starts_at'],
            ':expires_at' => $payload['expires_at'],
            ':created_by_type' => $payload['created_by_type'],
            ':created_by_login_id' => $payload['created_by_login_id'],
            ':insert_by' => $payload['insert_by'],
        ];
    }

    private function generateCode(array $payload): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', (string)$payload['event_code']) ?: 'NOTIFICATION');
        return substr($prefix, 0, 40) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
    }

    private function normalizeDedupeBehavior(string $behavior): string
    {
        $behavior = strtolower(trim($behavior));
        return in_array($behavior, [self::DEDUPE_SKIP, self::DEDUPE_UPDATE, self::DEDUPE_REPUBLISH], true)
            ? $behavior
            : self::DEDUPE_SKIP;
    }

    private function cleanCode(string $value, int $limit): string
    {
        $value = trim($value);
        $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $value) ?: '';
        return substr($value !== '' ? $value : 'notification', 0, $limit);
    }

    private function nullableCleanCode(mixed $value, int $limit): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $this->cleanCode($value, $limit);
    }

    private function normalizeChoice(string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim($value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function limitText(string $value, int $limit): string
    {
        $value = trim($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private function nullableLimitText(mixed $value, int $limit): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function nullableDateTime(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Invalid notification datetime value.');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function safeActionUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^(https?:)?\/\//i', $url)
            || preg_match('/^(?:javascript|data):/i', $url)
            || str_contains($url, '..')
            || str_contains($url, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }
        $url = ltrim($url, '/');
        return preg_match('/^[A-Za-z0-9_.\/-]+(?:\?[A-Za-z0-9_=&%+.,:@\/-]*)?(?:#[A-Za-z0-9_.-]*)?$/', $url) ? $url : '';
    }

    /**
     * @param array<string,mixed> $variables
     */
    private function renderVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $text = str_replace('{{' . $key . '}}', (string)$value, $text);
            }
        }
        return $text;
    }
}
