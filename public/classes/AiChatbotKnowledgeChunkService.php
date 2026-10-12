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

final class AiChatbotKnowledgeChunkService
{
    private const TABLE_NAME = 'tbl_ai_chat_knowledge_chunk';
    private const TARGET_CHUNK_CHARS = 1800;
    private const MIN_CHUNK_CHARS = 350;
    private const OVERLAP_CHARS = 180;

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
     * @param array<string,mixed> $source
     */
    public function replaceChunksForSource(array $source, string $text, string $actor): int
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('AI Chatbot knowledge chunk table is not available.');
        }

        $sourcePublicId = trim((string)($source['f_publicID'] ?? ''));
        if ($sourcePublicId === '') {
            throw new InvalidArgumentException('Source public ID is required for chunking.');
        }

        $chunks = $this->chunkText($text);
        if ($chunks === []) {
            throw new RuntimeException('Extracted PDF text is not large enough to chunk.');
        }

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM ' . self::TABLE_NAME . ' WHERE f_sourcePublicID = :source_public_id');
            $delete->execute([':source_public_id' => $sourcePublicId]);

            $insert = $this->pdo->prepare("
                INSERT INTO " . self::TABLE_NAME . " (
                  f_publicID,
                  f_sourcePublicID,
                  f_chunkIndex,
                  f_chunkTitle,
                  f_chunkText,
                  f_chunkHashSha256,
                  f_pageStart,
                  f_pageEnd,
                  f_language,
                  f_visibility,
                  f_allowedGroups,
                  f_tags,
                  f_status,
                  f_priority,
                  f_embeddingStatus,
                  f_createdBy,
                  f_createdDt,
                  f_updatedBy,
                  f_updatedDt
                ) VALUES (
                  :public_id,
                  :source_public_id,
                  :chunk_index,
                  :chunk_title,
                  :chunk_text,
                  :chunk_hash,
                  NULL,
                  NULL,
                  :language,
                  :visibility,
                  :allowed_groups,
                  :tags,
                  'draft',
                  :priority,
                  'not_required',
                  :created_by,
                  NOW(),
                  :updated_by,
                  NOW()
                )
            ");

            foreach ($chunks as $index => $chunkText) {
                $insert->execute([
                    ':public_id' => self::uuidV4(),
                    ':source_public_id' => $sourcePublicId,
                    ':chunk_index' => $index + 1,
                    ':chunk_title' => $this->buildChunkTitle((string)($source['f_title'] ?? 'PDF Knowledge'), $index + 1),
                    ':chunk_text' => $chunkText,
                    ':chunk_hash' => hash('sha256', $sourcePublicId . ':' . ($index + 1) . ':' . $chunkText),
                    ':language' => (string)($source['f_language'] ?? 'ms'),
                    ':visibility' => (string)($source['f_visibility'] ?? 'selected_groups'),
                    ':allowed_groups' => $source['f_allowedGroups'] ?? null,
                    ':tags' => $source['f_tags'] ?? null,
                    ':priority' => (int)($source['f_priority'] ?? 100),
                    ':created_by' => $actor,
                    ':updated_by' => $actor,
                ]);
            }

            $this->pdo->commit();
            return count($chunks);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<string,int>
     */
    public function countBySource(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $stmt = $this->pdo->query("
            SELECT f_sourcePublicID, COUNT(*) AS total
            FROM " . self::TABLE_NAME . "
            WHERE f_status <> 'deleted'
            GROUP BY f_sourcePublicID
        ");

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $counts[(string)$row['f_sourcePublicID']] = (int)$row['total'];
        }

        return $counts;
    }

    public function setStatusForSource(string $sourcePublicId, string $status, string $actor): int
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('AI Chatbot knowledge chunk table is not available.');
        }

        $status = in_array($status, ['draft', 'active', 'archived'], true) ? $status : 'draft';
        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE_NAME . "
            SET f_status = :status,
                f_updatedBy = :updated_by,
                f_updatedDt = NOW()
            WHERE f_sourcePublicID = :source_public_id
              AND f_status <> 'deleted'
        ");
        $stmt->execute([
            ':status' => $status,
            ':updated_by' => $actor,
            ':source_public_id' => trim($sourcePublicId),
        ]);

        return $stmt->rowCount();
    }

    /**
     * @return array<int,string>
     */
    private function chunkText(string $text): array
    {
        $text = $this->normalizeText($text);
        if (mb_strlen($text, 'UTF-8') < self::MIN_CHUNK_CHARS) {
            return [];
        }

        $paragraphs = preg_split("/\n{2,}/u", $text) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string)$paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph, 'UTF-8') > self::TARGET_CHUNK_CHARS) {
                $this->flushChunk($chunks, $current);
                $current = '';
                foreach ($this->splitLongParagraph($paragraph) as $part) {
                    $this->flushChunk($chunks, $part);
                }
                continue;
            }

            $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;
            if (mb_strlen($candidate, 'UTF-8') > self::TARGET_CHUNK_CHARS) {
                $this->flushChunk($chunks, $current);
                $current = $this->overlapTail($current) . $paragraph;
            } else {
                $current = $candidate;
            }
        }

        $this->flushChunk($chunks, $current);

        return $chunks;
    }

    /**
     * @param array<int,string> $chunks
     */
    private function flushChunk(array &$chunks, string $chunk): void
    {
        $chunk = trim($chunk);
        if ($chunk === '') {
            return;
        }

        if ($chunks !== [] && mb_strlen($chunk, 'UTF-8') < self::MIN_CHUNK_CHARS) {
            $lastIndex = array_key_last($chunks);
            if ($lastIndex !== null && mb_strlen($chunks[$lastIndex] . "\n\n" . $chunk, 'UTF-8') <= self::TARGET_CHUNK_CHARS + self::OVERLAP_CHARS) {
                $chunks[$lastIndex] .= "\n\n" . $chunk;
                return;
            }
        }

        $chunks[] = $chunk;
    }

    /**
     * @return array<int,string>
     */
    private function splitLongParagraph(string $paragraph): array
    {
        $sentences = preg_split('/(?<=[.!?。])\s+/u', $paragraph) ?: [$paragraph];
        $parts = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim((string)$sentence);
            if ($sentence === '') {
                continue;
            }
            $candidate = $current === '' ? $sentence : $current . ' ' . $sentence;
            if (mb_strlen($candidate, 'UTF-8') > self::TARGET_CHUNK_CHARS) {
                $this->flushChunk($parts, $current);
                $current = $this->overlapTail($current) . $sentence;
            } else {
                $current = $candidate;
            }
        }

        $this->flushChunk($parts, $current);
        return $parts;
    }

    private function overlapTail(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $tail = mb_substr($text, max(0, mb_strlen($text, 'UTF-8') - self::OVERLAP_CHARS), null, 'UTF-8');
        return trim($tail) . "\n\n";
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', (string)$text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string)$text);
        return trim((string)$text);
    }

    private function buildChunkTitle(string $sourceTitle, int $chunkIndex): string
    {
        $sourceTitle = trim($sourceTitle) !== '' ? trim($sourceTitle) : 'PDF Knowledge';
        return mb_substr($sourceTitle . ' - Chunk ' . $chunkIndex, 0, 255, 'UTF-8');
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
