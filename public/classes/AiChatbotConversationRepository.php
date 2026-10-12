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

final class AiChatbotConversationRepository
{
    private const SESSION_TABLE = 'tbl_ai_chat_session';
    private const MESSAGE_TABLE = 'tbl_ai_chat_message';

    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function isAvailable(): bool
    {
        return $this->tableExists(self::SESSION_TABLE) && $this->tableExists(self::MESSAGE_TABLE);
    }

    /**
     * @param array<string,mixed> $context
     * @return array{id:int,public_id:string}
     */
    public function ensureSession(array $context): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('AI Chatbot conversation tables are not available.');
        }

        $publicId = trim((string)($_SESSION['ai_chatbot_session_public_id'] ?? ''));
        if ($publicId !== '') {
            $existing = $this->findSessionByPublicId($publicId);
            if ($existing) {
                $this->touchSession((int)$existing['f_aiChatSessionID'], $context);
                return [
                    'id' => (int)$existing['f_aiChatSessionID'],
                    'public_id' => (string)$existing['f_sessionPublicID'],
                ];
            }
        }

        $publicId = self::uuidV4();
        $_SESSION['ai_chatbot_session_public_id'] = $publicId;

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::SESSION_TABLE . " (
              f_sessionPublicID,
              f_userID,
              f_loginID,
              f_stafID,
              f_groupID,
              f_groupKod,
              f_provider,
              f_model,
              f_accessMode,
              f_title,
              f_status,
              f_startedAt,
              f_lastMessageAt,
              f_createdBy,
              f_createdDt,
              f_updatedBy,
              f_updatedDt
            ) VALUES (
              :public_id,
              :user_id,
              :login_id,
              :staf_id,
              :group_id,
              :group_kod,
              :provider,
              :model,
              :access_mode,
              :title,
              'active',
              NOW(),
              NOW(),
              :created_by,
              NOW(),
              :updated_by,
              NOW()
            )
        ");
        $stmt->execute([
            ':public_id' => $publicId,
            ':user_id' => $this->intOrNull($context['user_id'] ?? null),
            ':login_id' => $this->stringOrNull($context['login_id'] ?? null, 191),
            ':staf_id' => $this->stringOrNull($context['staf_id'] ?? null, 64),
            ':group_id' => $this->intOrNull($context['group_id'] ?? null),
            ':group_kod' => $this->stringOrNull($context['group_kod'] ?? null, 64),
            ':provider' => $this->stringOrDefault($context['provider'] ?? null, 'unknown', 64),
            ':model' => $this->stringOrDefault($context['model'] ?? null, 'unknown', 191),
            ':access_mode' => $this->stringOrDefault($context['access_mode'] ?? null, 'super_admin_only', 64),
            ':title' => $this->stringOrNull($context['title'] ?? null, 255),
            ':created_by' => $this->stringOrNull($context['actor'] ?? null, 191),
            ':updated_by' => $this->stringOrNull($context['actor'] ?? null, 191),
        ]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'public_id' => $publicId,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function recordMessage(int $sessionId, array $data, bool $storeContent): int
    {
        if ($sessionId <= 0 || !$this->isAvailable()) {
            return 0;
        }

        $content = (string)($data['content'] ?? '');
        $role = $this->choice((string)($data['role'] ?? 'user'), ['system', 'user', 'assistant', 'tool', 'error'], 'user');
        $status = $this->choice((string)($data['status'] ?? 'completed'), ['queued', 'sent', 'completed', 'failed', 'blocked'], 'completed');
        $metaJson = null;
        if (isset($data['meta']) && is_array($data['meta'])) {
            $encoded = json_encode($data['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $metaJson = is_string($encoded) ? $encoded : null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::MESSAGE_TABLE . " (
              f_aiChatSessionID,
              f_messagePublicID,
              f_role,
              f_provider,
              f_model,
              f_contentStored,
              f_content,
              f_contentSha256,
              f_contentLength,
              f_sensitiveRedacted,
              f_latencyMs,
              f_status,
              f_errorCode,
              f_errorMessage,
              f_metaJson,
              f_createdBy,
              f_createdDt
            ) VALUES (
              :session_id,
              :public_id,
              :role,
              :provider,
              :model,
              :content_stored,
              :content,
              :content_sha256,
              :content_length,
              :sensitive_redacted,
              :latency_ms,
              :status,
              :error_code,
              :error_message,
              :meta_json,
              :created_by,
              NOW()
            )
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':public_id' => self::uuidV4(),
            ':role' => $role,
            ':provider' => $this->stringOrNull($data['provider'] ?? null, 64),
            ':model' => $this->stringOrNull($data['model'] ?? null, 191),
            ':content_stored' => $storeContent ? 1 : 0,
            ':content' => $storeContent ? $content : null,
            ':content_sha256' => $content !== '' ? hash('sha256', $content) : null,
            ':content_length' => mb_strlen($content, 'UTF-8'),
            ':sensitive_redacted' => $storeContent ? 0 : 1,
            ':latency_ms' => $this->intOrNull($data['latency_ms'] ?? null),
            ':status' => $status,
            ':error_code' => $this->stringOrNull($data['error_code'] ?? null, 100),
            ':error_message' => $this->stringOrNull($data['error_message'] ?? null, 500),
            ':meta_json' => $metaJson,
            ':created_by' => $this->stringOrNull($data['actor'] ?? null, 191),
        ]);

        $messageId = (int)$this->pdo->lastInsertId();
        $this->markSessionMessageAt($sessionId);

        return $messageId;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findSessionByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM " . self::SESSION_TABLE . "
            WHERE f_sessionPublicID = :public_id
              AND f_status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function touchSession(int $sessionId, array $context): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE " . self::SESSION_TABLE . "
            SET f_provider = :provider,
                f_model = :model,
                f_accessMode = :access_mode,
                f_groupID = :group_id,
                f_groupKod = :group_kod,
                f_lastMessageAt = NOW(),
                f_updatedBy = :updated_by,
                f_updatedDt = NOW()
            WHERE f_aiChatSessionID = :session_id
            LIMIT 1
        ");
        $stmt->execute([
            ':provider' => $this->stringOrDefault($context['provider'] ?? null, 'unknown', 64),
            ':model' => $this->stringOrDefault($context['model'] ?? null, 'unknown', 191),
            ':access_mode' => $this->stringOrDefault($context['access_mode'] ?? null, 'super_admin_only', 64),
            ':group_id' => $this->intOrNull($context['group_id'] ?? null),
            ':group_kod' => $this->stringOrNull($context['group_kod'] ?? null, 64),
            ':updated_by' => $this->stringOrNull($context['actor'] ?? null, 191),
            ':session_id' => $sessionId,
        ]);
    }

    private function markSessionMessageAt(int $sessionId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE " . self::SESSION_TABLE . "
            SET f_lastMessageAt = NOW(),
                f_updatedDt = NOW()
            WHERE f_aiChatSessionID = :session_id
            LIMIT 1
        ");
        $stmt->execute([':session_id' => $sessionId]);
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

    /**
     * @param array<int,string> $choices
     */
    private function choice(string $value, array $choices, string $fallback): string
    {
        $value = strtolower(trim($value));
        return in_array($value, $choices, true) ? $value : $fallback;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    private function stringOrNull(mixed $value, int $maxLength): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function stringOrDefault(mixed $value, string $default, int $maxLength): string
    {
        return $this->stringOrNull($value, $maxLength) ?? $default;
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
