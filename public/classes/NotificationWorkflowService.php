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
require_once __DIR__ . '/NotificationPublisher.php';
require_once __DIR__ . '/NotificationService.php';

final class NotificationWorkflowService
{
    public function __construct(
        private NotificationPublisher $publisher,
        private NotificationService $service
    ) {}

    public static function default(): self
    {
        $pdo = Database::getInstance('mysql')->getConnection();
        return new self(
            new NotificationPublisher($pdo, new NotificationAudienceResolver($pdo)),
            new NotificationService($pdo)
        );
    }

    /**
     * Publish a standard workflow task notification.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     */
    public function publishTask(array $payload, array $options = []): int
    {
        $payload['type'] = $payload['type'] ?? 'workflow';
        $payload['requires_action'] = 1;
        $payload['priority'] = $payload['priority'] ?? 'high';
        $payload['severity'] = $payload['severity'] ?? 'warning';
        $payload['category'] = $payload['category'] ?? 'workflow';

        $options['dedupe'] = $options['dedupe'] ?? 'update';

        return $this->publisher->publish($payload, $options);
    }

    /**
     * Publish a workflow progress/final informational notification.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     */
    public function publishInfo(array $payload, array $options = []): int
    {
        $payload['type'] = $payload['type'] ?? 'event';
        $payload['requires_action'] = 0;
        $payload['priority'] = $payload['priority'] ?? 'normal';
        $payload['severity'] = $payload['severity'] ?? 'info';
        $payload['category'] = $payload['category'] ?? 'workflow';

        return $this->publisher->publish($payload, $options);
    }

    public function completeSourceStep(string $sourceType, string $sourceId, ?string $eventCode = null): int
    {
        return $this->service->completeBySource($sourceType, $sourceId, $eventCode);
    }

    public function cancelSource(string $sourceType, string $sourceId, ?string $eventCode = null): int
    {
        return $this->service->cancelBySource($sourceType, $sourceId, $eventCode);
    }

    public function expireSourceStep(string $sourceType, string $sourceId, ?string $eventCode = null): int
    {
        return $this->service->expireBySource($sourceType, $sourceId, $eventCode);
    }

    public function expireOverdueTasks(int $limit = 500): int
    {
        return $this->service->expireOverdueTasks($limit);
    }
}
