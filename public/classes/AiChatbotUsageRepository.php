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

final class AiChatbotUsageRepository
{
    private const TABLE_NAME = 'tbl_ai_chat_usage';

    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function isAvailable(): bool
    {
        return $this->tableExists(self::TABLE_NAME);
    }

    public function countToday(?string $loginId = null): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                FROM " . self::TABLE_NAME . "
                WHERE f_createdDt >= CURRENT_DATE()
                  AND f_outcome IN ('success','failed','timeout')";
        $params = [];

        if ($loginId !== null && trim($loginId) !== '') {
            $sql .= " AND f_loginID = :login_id";
            $params[':login_id'] = trim($loginId);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $row
     */
    public function record(array $row): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $usage = is_array($row['usage'] ?? null) ? $row['usage'] : [];
        $promptTokens = $this->intOrNull($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? null);
        $completionTokens = $this->intOrNull($usage['completion_tokens'] ?? $usage['completionTokens'] ?? null);
        $totalTokens = $this->intOrNull($usage['total_tokens'] ?? $usage['totalTokens'] ?? null);

        $metaJson = null;
        if (isset($row['request_meta']) && is_array($row['request_meta'])) {
            $encoded = json_encode($row['request_meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $metaJson = is_string($encoded) ? $encoded : null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE_NAME . " (
                f_aiChatSessionID,
                f_aiChatMessageID,
                f_userID,
                f_loginID,
                f_provider,
                f_model,
                f_requestType,
                f_promptTokens,
                f_completionTokens,
                f_totalTokens,
                f_estimatedCost,
                f_currency,
                f_latencyMs,
                f_httpStatus,
                f_outcome,
                f_errorCode,
                f_errorMessage,
                f_requestMetaJson,
                f_createdDt
            ) VALUES (
                :session_id,
                :message_id,
                :user_id,
                :login_id,
                :provider,
                :model,
                :request_type,
                :prompt_tokens,
                :completion_tokens,
                :total_tokens,
                NULL,
                NULL,
                :latency_ms,
                :http_status,
                :outcome,
                :error_code,
                :error_message,
                :request_meta,
                NOW()
            )
        ");

        $stmt->execute([
            ':session_id' => $this->intOrNull($row['session_id'] ?? $row['ai_chat_session_id'] ?? null),
            ':message_id' => $this->intOrNull($row['message_id'] ?? $row['ai_chat_message_id'] ?? null),
            ':user_id' => $this->intOrNull($row['user_id'] ?? null),
            ':login_id' => $this->stringOrNull($row['login_id'] ?? null, 191),
            ':provider' => $this->stringOrDefault($row['provider'] ?? null, 'unknown', 64),
            ':model' => $this->stringOrDefault($row['model'] ?? null, 'unknown', 191),
            ':request_type' => $this->stringOrDefault($row['request_type'] ?? null, 'chat', 64),
            ':prompt_tokens' => $promptTokens,
            ':completion_tokens' => $completionTokens,
            ':total_tokens' => $totalTokens,
            ':latency_ms' => $this->intOrNull($row['latency_ms'] ?? null),
            ':http_status' => $this->intOrNull($row['http_status'] ?? null),
            ':outcome' => $this->normalizeOutcome((string)($row['outcome'] ?? 'success')),
            ':error_code' => $this->stringOrNull($row['error_code'] ?? null, 100),
            ':error_message' => $this->stringOrNull($row['error_message'] ?? null, 500),
            ':request_meta' => $metaJson,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ');
            $stmt->execute([':table_name' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function normalizeOutcome(string $outcome): string
    {
        $outcome = strtolower(trim($outcome));
        return in_array($outcome, ['success', 'failed', 'rate_limited', 'blocked', 'timeout'], true)
            ? $outcome
            : 'failed';
    }

    private function intOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    private function stringOrNull($value, int $maxLength): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function stringOrDefault($value, string $default, int $maxLength): string
    {
        return $this->stringOrNull($value, $maxLength) ?? $default;
    }
}
