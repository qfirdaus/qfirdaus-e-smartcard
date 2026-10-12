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

final class AiChatbotKnowledgeSourceService
{
    private const TABLE_NAME = 'tbl_ai_chat_knowledge_source';
    private const ALLOWED_LANGUAGES = ['ms', 'en'];
    private const UPLOAD_RELATIVE_DIR = 'uploads/ai-chatbot-knowledge/';

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
            WHERE f_status <> 'deleted'
            ORDER BY f_createdDt DESC, f_aiChatKnowledgeSourceID DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    public function uploadPdf(array $input, array $file, string $actor): array
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('AI Chatbot knowledge source table is not available.');
        }

        $data = $this->validateInput($input);
        $this->validatePdfFile($file);

        $publicId = self::uuidV4();
        $originalName = $this->cleanFilename((string)$file['name']);
        $storedFilename = 'knowledge_pdf_' . str_replace('-', '', $publicId) . '_' . date('YmdHis') . '.pdf';
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::UPLOAD_RELATIVE_DIR);
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Unable to prepare PDF upload directory.');
        }

        $tmpPath = (string)$file['tmp_name'];
        $hash = hash_file('sha256', $tmpPath);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Unable to hash uploaded PDF.');
        }

        $destPath = $uploadDir . $storedFilename;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Unable to store uploaded PDF.');
        }

        $relativePath = self::UPLOAD_RELATIVE_DIR . $storedFilename;
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE_NAME . " (
              f_publicID,
              f_title,
              f_description,
              f_sourceType,
              f_originalFilename,
              f_storedFilename,
              f_storedPath,
              f_mimeType,
              f_fileSizeBytes,
              f_fileHashSha256,
              f_language,
              f_visibility,
              f_allowedGroups,
              f_tags,
              f_version,
              f_effectiveDate,
              f_reviewDueDate,
              f_reviewStatus,
              f_extractionStatus,
              f_status,
              f_priority,
              f_createdBy,
              f_createdDt,
              f_updatedBy,
              f_updatedDt
            ) VALUES (
              :public_id,
              :title,
              :description,
              'pdf',
              :original_filename,
              :stored_filename,
              :stored_path,
              :mime_type,
              :file_size,
              :file_hash,
              :language,
              :visibility,
              :allowed_groups,
              :tags,
              :version,
              :effective_date,
              :review_due_date,
              :review_status,
              'pending',
              'draft',
              :priority,
              :created_by,
              NOW(),
              :updated_by,
              NOW()
            )
        ");
        $stmt->execute([
            ':public_id' => $publicId,
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':original_filename' => $originalName,
            ':stored_filename' => $storedFilename,
            ':stored_path' => $relativePath,
            ':mime_type' => 'application/pdf',
            ':file_size' => (int)$file['size'],
            ':file_hash' => $hash,
            ':language' => $data['language'],
            ':visibility' => $data['visibility'],
            ':allowed_groups' => $data['allowed_groups'],
            ':tags' => $data['tags'],
            ':version' => $data['version'],
            ':effective_date' => $data['effective_date'],
            ':review_due_date' => $data['review_due_date'],
            ':review_status' => $data['review_status'],
            ':priority' => $data['priority'],
            ':created_by' => $actor,
            ':updated_by' => $actor,
        ]);

        $row = $this->findByPublicId($publicId);
        if ($row === null) {
            throw new RuntimeException('Uploaded PDF metadata could not be loaded.');
        }

        return $row;
    }

    public function findByPublicId(string $publicId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM " . self::TABLE_NAME . "
            WHERE f_publicID = :public_id
            LIMIT 1
        ");
        $stmt->execute([':public_id' => trim($publicId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    public function getStoredPdfPath(array $row): string
    {
        $storedPath = trim((string)($row['f_storedPath'] ?? ''));
        if ($storedPath === '') {
            throw new RuntimeException('Stored PDF path is missing.');
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath);
        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
        $baseDir = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ai-chatbot-knowledge');
        $resolved = realpath($fullPath);
        if ($baseDir === false || $resolved === false || !str_starts_with($resolved, $baseDir)) {
            throw new RuntimeException('Stored PDF path is outside the allowed upload directory.');
        }

        return $resolved;
    }

    public function markExtractionProcessed(string $publicId, int $charCount, string $actor): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE_NAME . "
            SET f_extractionStatus = 'processed',
                f_extractionError = NULL,
                f_extractedCharCount = :char_count,
                f_processedBy = :processed_by,
                f_processedDt = NOW(),
                f_updatedBy = :updated_by,
                f_updatedDt = NOW()
            WHERE f_publicID = :public_id
            LIMIT 1
        ");
        $stmt->execute([
            ':char_count' => max(0, $charCount),
            ':processed_by' => $actor,
            ':updated_by' => $actor,
            ':public_id' => trim($publicId),
        ]);
    }

    public function markExtractionFailed(string $publicId, string $error, string $actor): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE_NAME . "
            SET f_extractionStatus = 'failed',
                f_extractionError = :error,
                f_processedBy = :processed_by,
                f_processedDt = NOW(),
                f_updatedBy = :updated_by,
                f_updatedDt = NOW()
            WHERE f_publicID = :public_id
            LIMIT 1
        ");
        $stmt->execute([
            ':error' => mb_substr($error, 0, 1000, 'UTF-8'),
            ':processed_by' => $actor,
            ':updated_by' => $actor,
            ':public_id' => trim($publicId),
        ]);
    }

    public function updateChunkCount(string $publicId, int $chunkCount, string $actor): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE_NAME . "
            SET f_chunkCount = :chunk_count,
                f_updatedBy = :updated_by,
                f_updatedDt = NOW()
            WHERE f_publicID = :public_id
            LIMIT 1
        ");
        $stmt->execute([
            ':chunk_count' => max(0, $chunkCount),
            ':updated_by' => $actor,
            ':public_id' => trim($publicId),
        ]);
    }

    public function setStatus(string $publicId, string $status, string $actor): void
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('AI Chatbot knowledge source table is not available.');
        }

        $status = $this->choice($status, ['draft', 'active', 'archived'], 'draft');
        $row = $this->findByPublicId($publicId);
        if ($row === null) {
            throw new RuntimeException('PDF source not found.');
        }

        if ($status === 'active') {
            if ((string)($row['f_extractionStatus'] ?? '') !== 'processed') {
                throw new InvalidArgumentException('PDF source must be processed before activation.');
            }
            if ((int)($row['f_chunkCount'] ?? 0) < 1) {
                throw new InvalidArgumentException('PDF source must have at least one generated chunk before activation.');
            }
            $language = (string)($row['f_language'] ?? 'ms');
            if (!in_array($language, self::ALLOWED_LANGUAGES, true)) {
                throw new InvalidArgumentException('PDF source language is not supported for retrieval.');
            }
            $visibility = (string)($row['f_visibility'] ?? 'selected_groups');
            $allowedGroups = trim((string)($row['f_allowedGroups'] ?? ''));
            if ($visibility === 'selected_groups' && $allowedGroups === '') {
                throw new InvalidArgumentException('Allowed groups are required before activating selected group PDF source.');
            }
        }

        $reviewStatusSql = $status === 'active'
            ? "f_reviewStatus = 'approved',"
            : ($status === 'archived' ? "f_reviewStatus = 'needs_update'," : "f_reviewStatus = 'draft',");

        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE_NAME . "
            SET f_status = :status,
                {$reviewStatusSql}
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

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function validateInput(array $input): array
    {
        $title = $this->cleanText((string)($input['source_title'] ?? ''), 255);
        $description = $this->nullableLongText($input['description'] ?? null);
        $language = $this->choice((string)($input['language'] ?? 'ms'), self::ALLOWED_LANGUAGES, 'ms');
        $visibility = $this->choice((string)($input['visibility'] ?? 'selected_groups'), ['all_authenticated', 'selected_groups', 'super_admin_only'], 'selected_groups');
        $allowedGroups = $this->normalizeAllowedGroups($input['allowed_groups'] ?? '');
        $tags = $this->nullableText($input['tags'] ?? null, 500);
        $version = $this->nullableText($input['version'] ?? null, 50);
        $effectiveDate = $this->nullableDate($input['effective_date'] ?? null);
        $reviewDueDate = $this->nullableDate($input['review_due_date'] ?? null);
        $reviewStatus = $this->choice((string)($input['review_status'] ?? 'draft'), ['draft', 'reviewed', 'approved', 'needs_update'], 'draft');
        $priority = (int)($input['priority'] ?? 100);

        if ($title === '') {
            throw new InvalidArgumentException('Source title is required.');
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
            'description' => $description,
            'language' => $language,
            'visibility' => $visibility,
            'allowed_groups' => $allowedGroups,
            'tags' => $tags,
            'version' => $version,
            'effective_date' => $effectiveDate,
            'review_due_date' => $reviewDueDate,
            'review_status' => $reviewStatus,
            'priority' => $priority,
        ];
    }

    /**
     * @param array<string,mixed> $file
     */
    private function validatePdfFile(array $file): void
    {
        if (!isset($file['error'], $file['name'], $file['size'], $file['tmp_name'])) {
            throw new InvalidArgumentException('PDF upload is incomplete.');
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('PDF upload failed.');
        }
        if (!is_uploaded_file((string)$file['tmp_name'])) {
            throw new InvalidArgumentException('Invalid PDF upload.');
        }
        if (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            throw new InvalidArgumentException('Only PDF files are allowed.');
        }

        $maxMb = function_exists('app_config') ? (int)app_config('upload.manual_max_mb', 10) : 10;
        if ($maxMb < 1) {
            $maxMb = 1;
        } elseif ($maxMb > 100) {
            $maxMb = 100;
        }
        if ((int)$file['size'] > ($maxMb * 1024 * 1024)) {
            throw new InvalidArgumentException('PDF file exceeds the maximum upload size of ' . $maxMb . ' MB.');
        }

        $mime = null;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string)$file['tmp_name']) ?: null;
        }
        if ($mime !== null && !in_array(strtolower((string)$mime), ['application/pdf', 'application/x-pdf'], true)) {
            throw new InvalidArgumentException('Invalid PDF file type.');
        }

        $fh = @fopen((string)$file['tmp_name'], 'rb');
        $signature = $fh ? (string)fread($fh, 4) : '';
        if (is_resource($fh)) {
            fclose($fh);
        }
        if ($signature !== '%PDF') {
            throw new InvalidArgumentException('Invalid PDF file signature.');
        }
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

    private function nullableLongText(mixed $value): ?string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', (string)$value);
        $value = trim((string)$value);
        return $value === '' ? null : $value;
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

    private function cleanFilename(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename($value));
        $value = trim((string)$value);
        return mb_substr($value !== '' ? $value : 'knowledge.pdf', 0, 255, 'UTF-8');
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
