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

final class AiChatbotKnowledgeService
{
    private const TABLE_NAME = 'tbl_ai_chat_knowledge';
    private const ALLOWED_LANGUAGES = ['ms', 'en'];
    private const REVIEW_STATUSES = ['draft', 'reviewed', 'approved', 'needs_update'];

    public function __construct(private PDO $pdo)
    {
    }

    public function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ');
            $stmt->execute([':table_name' => self::TABLE_NAME]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAll(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $stmt = $this->pdo->query("
            SELECT *
            FROM " . self::TABLE_NAME . "
            ORDER BY
              CASE f_status
                WHEN 'active' THEN 1
                WHEN 'draft' THEN 2
                ELSE 3
              END,
              f_priority ASC,
              f_updatedDt DESC,
              f_createdDt DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByPublicId(string $publicId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM " . self::TABLE_NAME . "
            WHERE f_publicID = :public_id
            LIMIT 1
        ");
        $stmt->execute([':public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getGroupOptions(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT f_groupID, f_groupKod, f_groupName
                FROM tbl_m_group
                ORDER BY f_groupName ASC, f_groupKod ASC, f_groupID ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[ai-chatbot-knowledge-service] groups: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<string,int>
     */
    public function summary(): array
    {
        if (!$this->tableExists()) {
            return ['total' => 0, 'active' => 0, 'draft' => 0, 'archived' => 0];
        }

        $row = $this->pdo->query("
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN f_status = 'active' THEN 1 ELSE 0 END) AS active,
              SUM(CASE WHEN f_status = 'draft' THEN 1 ELSE 0 END) AS draft,
              SUM(CASE WHEN f_status = 'archived' THEN 1 ELSE 0 END) AS archived
            FROM " . self::TABLE_NAME . "
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'draft' => (int)($row['draft'] ?? 0),
            'archived' => (int)($row['archived'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    public function save(array $input, string $actor): string
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('AI Chatbot knowledge table is not available.');
        }

        $data = $this->validate($input);
        $publicId = trim((string)($input['public_id'] ?? $input['f_publicID'] ?? ''));
        $existing = $publicId !== '' ? $this->findByPublicId($publicId) : null;

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE_NAME . "
                SET f_title = :title,
                    f_question = :question,
                    f_answer = :answer,
                    f_language = :language,
                    f_visibility = :visibility,
                    f_allowedGroups = :allowed_groups,
                    f_tags = :tags,
                    f_sourceType = :source_type,
                    f_sourceTitle = :source_title,
                    f_version = :version,
                    f_reviewStatus = :review_status,
                    f_effectiveDate = :effective_date,
                    f_reviewDueDate = :review_due_date,
                    f_status = :status,
                    f_priority = :priority,
                    f_updatedBy = :updated_by,
                    f_updatedDt = NOW()
                WHERE f_publicID = :public_id
                LIMIT 1
            ");
            $stmt->execute($this->params($data, [
                ':public_id' => $publicId,
                ':updated_by' => $actor,
            ]));
            return $publicId;
        }

        $publicId = self::uuidV4();
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE_NAME . " (
              f_publicID,
              f_title,
              f_question,
              f_answer,
              f_language,
              f_visibility,
              f_allowedGroups,
              f_tags,
              f_sourceType,
              f_sourceTitle,
              f_version,
              f_reviewStatus,
              f_effectiveDate,
              f_reviewDueDate,
              f_status,
              f_priority,
              f_createdBy,
              f_createdDt,
              f_updatedBy,
              f_updatedDt
            ) VALUES (
              :public_id,
              :title,
              :question,
              :answer,
              :language,
              :visibility,
              :allowed_groups,
              :tags,
              :source_type,
              :source_title,
              :version,
              :review_status,
              :effective_date,
              :review_due_date,
              :status,
              :priority,
              :created_by,
              NOW(),
              :updated_by,
              NOW()
            )
        ");
        $stmt->execute($this->params($data, [
            ':public_id' => $publicId,
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]));

        return $publicId;
    }

    public function setStatus(string $publicId, string $status, string $actor): void
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('AI Chatbot knowledge table is not available.');
        }

        $status = $this->choice($status, ['draft', 'active', 'archived'], 'draft');
        if ($status === 'active') {
            $row = $this->findByPublicId($publicId);
            if ($row === null) {
                throw new RuntimeException('Knowledge item not found.');
            }
            $this->assertCanActivate($row);
        }

        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE_NAME . "
            SET f_status = :status,
                f_updatedBy = :updated_by,
                f_updatedDt = NOW()
            WHERE f_publicID = :public_id
            LIMIT 1
        ");
        $stmt->execute([
            ':status' => $status,
            ':updated_by' => $actor,
            ':public_id' => trim($publicId),
        ]);
    }

    public function delete(string $publicId): void
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('AI Chatbot knowledge table is not available.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE_NAME . ' WHERE f_publicID = :public_id LIMIT 1');
        $stmt->execute([':public_id' => trim($publicId)]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function assertCanActivate(array $row): void
    {
        $language = (string)($row['f_language'] ?? 'ms');
        if (!in_array($language, self::ALLOWED_LANGUAGES, true)) {
            throw new InvalidArgumentException('Knowledge item language is not supported for retrieval.');
        }

        $visibility = (string)($row['f_visibility'] ?? 'selected_groups');
        $allowedGroups = trim((string)($row['f_allowedGroups'] ?? ''));
        if ($visibility === 'selected_groups' && $allowedGroups === '') {
            throw new InvalidArgumentException('Allowed groups are required before activating selected group knowledge.');
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function validate(array $input): array
    {
        $title = $this->cleanText((string)($input['title'] ?? $input['f_title'] ?? ''), 255);
        $question = $this->nullableText($input['question'] ?? $input['f_question'] ?? null, 500);
        $answer = $this->cleanLongText((string)($input['answer'] ?? $input['f_answer'] ?? ''));
        $language = $this->choice((string)($input['language'] ?? $input['f_language'] ?? 'ms'), self::ALLOWED_LANGUAGES, 'ms');
        $visibility = $this->choice((string)($input['visibility'] ?? $input['f_visibility'] ?? 'selected_groups'), ['all_authenticated', 'selected_groups', 'super_admin_only'], 'selected_groups');
        $allowedGroups = $this->normalizeAllowedGroups($input['allowed_groups'] ?? $input['f_allowedGroups'] ?? '');
        $tags = $this->nullableText($input['tags'] ?? $input['f_tags'] ?? null, 500);
        $sourceType = $this->choice((string)($input['source_type'] ?? $input['f_sourceType'] ?? 'manual_text'), ['manual_text'], 'manual_text');
        $sourceTitle = $this->nullableText($input['source_title'] ?? $input['f_sourceTitle'] ?? null, 255);
        $version = $this->nullableText($input['version'] ?? $input['f_version'] ?? null, 50);
        $reviewStatus = $this->choice((string)($input['review_status'] ?? $input['f_reviewStatus'] ?? 'draft'), self::REVIEW_STATUSES, 'draft');
        $effectiveDate = $this->nullableDate($input['effective_date'] ?? $input['f_effectiveDate'] ?? null);
        $reviewDueDate = $this->nullableDate($input['review_due_date'] ?? $input['f_reviewDueDate'] ?? null);
        $status = $this->choice((string)($input['status'] ?? $input['f_status'] ?? 'draft'), ['draft', 'active', 'archived'], 'draft');
        $priority = (int)($input['priority'] ?? $input['f_priority'] ?? 100);

        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }
        if ($answer === '') {
            throw new InvalidArgumentException('Answer is required.');
        }
        if ($visibility === 'selected_groups' && $allowedGroups === null) {
            throw new InvalidArgumentException('Allowed groups are required when visibility is selected groups.');
        }

        if ($priority < 1) {
            $priority = 1;
        } elseif ($priority > 9999) {
            $priority = 9999;
        }

        return [
            'title' => $title,
            'question' => $question,
            'answer' => $answer,
            'language' => $language,
            'visibility' => $visibility,
            'allowed_groups' => $allowedGroups,
            'tags' => $tags,
            'source_type' => $sourceType,
            'source_title' => $sourceTitle,
            'version' => $version,
            'review_status' => $reviewStatus,
            'effective_date' => $effectiveDate,
            'review_due_date' => $reviewDueDate,
            'status' => $status,
            'priority' => $priority,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function params(array $data, array $extra = []): array
    {
        return array_merge([
            ':title' => $data['title'],
            ':question' => $data['question'],
            ':answer' => $data['answer'],
            ':language' => $data['language'],
            ':visibility' => $data['visibility'],
            ':allowed_groups' => $data['allowed_groups'],
            ':tags' => $data['tags'],
            ':source_type' => $data['source_type'],
            ':source_title' => $data['source_title'],
            ':version' => $data['version'],
            ':review_status' => $data['review_status'],
            ':effective_date' => $data['effective_date'],
            ':review_due_date' => $data['review_due_date'],
            ':status' => $data['status'],
            ':priority' => $data['priority'],
        ], $extra);
    }

    private function cleanText(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = trim((string)$value);
        return mb_substr($value, 0, max(1, $maxLength), 'UTF-8');
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $clean = $this->cleanText((string)$value, $maxLength);
        return $clean === '' ? null : $clean;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Date fields must use YYYY-MM-DD format.');
        }

        return $value;
    }

    private function cleanLongText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value);
        return trim((string)$value);
    }

    /**
     * @param mixed $value
     */
    private function normalizeAllowedGroups(mixed $value): ?string
    {
        $items = is_array($value) ? $value : preg_split('/[,;\s]+/', (string)$value);
        $normalized = [];
        foreach ($items ?: [] as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $item) !== 1) {
                continue;
            }
            $normalized[] = $item;
        }

        $normalized = array_values(array_unique($normalized));
        return $normalized === [] ? null : mb_substr(implode(',', $normalized), 0, 500, 'UTF-8');
    }

    /**
     * @param array<int,string> $choices
     */
    private function choice(string $value, array $choices, string $fallback): string
    {
        $value = trim($value);
        return in_array($value, $choices, true) ? $value : $fallback;
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
