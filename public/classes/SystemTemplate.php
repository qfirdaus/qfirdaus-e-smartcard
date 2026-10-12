<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class SystemTemplate
{
    protected PDO $pdo;
    protected string $table = 'tbl_m_system_template';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT
                f_templateID,
                f_templateName,
                f_templateType,
                f_pageName,
                f_pageTitleMs,
                f_pageTitleEn,
                f_pageSlug,
                f_pageIcon,
                f_controllerClass,
                f_langKeyPrefix,
                f_outputPagePath,
                f_outputControllerPath,
                f_outputCssPath,
                f_generationSummary,
                f_status,
                f_insertdt,
                f_updatedt,
                f_updateby
            FROM {$this->table}
            ORDER BY COALESCE(f_updatedt, f_insertdt) DESC, f_templateID DESC
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE f_templateID = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByPageSlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE f_pageSlug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByControllerClass(string $controllerClass): ?array
    {
        $controllerClass = trim($controllerClass);
        if ($controllerClass === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE f_controllerClass = :controller LIMIT 1");
        $stmt->execute([':controller' => $controllerClass]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function existsBySlug(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->table} WHERE f_pageSlug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);

        return (bool)$stmt->fetchColumn();
    }

    public function existsByControllerClass(string $controllerClass): bool
    {
        $controllerClass = trim($controllerClass);
        if ($controllerClass === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->table} WHERE f_controllerClass = :controller LIMIT 1");
        $stmt->execute([':controller' => $controllerClass]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $pageSlug = trim((string)($data['page_slug'] ?? ''));
        $controllerClass = trim((string)($data['controller_class'] ?? ''));
        $templateType = trim((string)($data['template_type'] ?? ''));

        if ($pageSlug === '' || $controllerClass === '' || $templateType === '') {
            throw new InvalidArgumentException('Invalid system template payload.');
        }

        $sql = "
            INSERT INTO {$this->table} (
                f_templateName,
                f_templateType,
                f_pageName,
                f_pageTitleMs,
                f_pageTitleEn,
                f_pageSlug,
                f_pageIcon,
                f_controllerClass,
                f_langKeyPrefix,
                f_outputPagePath,
                f_outputControllerPath,
                f_outputCssPath,
                f_generationSummary,
                f_status,
                f_insertdt,
                f_updatedt,
                f_updateby
            ) VALUES (
                :template_name,
                :template_type,
                :page_name,
                :page_title_ms,
                :page_title_en,
                :page_slug,
                :page_icon,
                :controller_class,
                :lang_key_prefix,
                :output_page_path,
                :output_controller_path,
                :output_css_path,
                :generation_summary,
                :status,
                NOW(),
                NOW(),
                :update_by
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':template_name' => trim((string)($data['template_name'] ?? '')),
            ':template_type' => $templateType,
            ':page_name' => trim((string)($data['page_name'] ?? '')),
            ':page_title_ms' => trim((string)($data['page_title_ms'] ?? '')),
            ':page_title_en' => trim((string)($data['page_title_en'] ?? '')),
            ':page_slug' => $pageSlug,
            ':page_icon' => trim((string)($data['page_icon'] ?? '')),
            ':controller_class' => $controllerClass,
            ':lang_key_prefix' => trim((string)($data['lang_key_prefix'] ?? '')),
            ':output_page_path' => trim((string)($data['output_page_path'] ?? '')),
            ':output_controller_path' => trim((string)($data['output_controller_path'] ?? '')),
            ':output_css_path' => trim((string)($data['output_css_path'] ?? '')),
            ':generation_summary' => (string)($data['generation_summary'] ?? ''),
            ':status' => trim((string)($data['status'] ?? 'GENERATED')),
            ':update_by' => trim((string)($data['update_by'] ?? '')),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $updateBy = null): bool
    {
        if ($id <= 0 || trim($status) === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET f_status = :status,
                f_updatedt = NOW(),
                f_updateby = :update_by
            WHERE f_templateID = :id
        ");

        return $stmt->execute([
            ':status' => trim($status),
            ':update_by' => trim((string)$updateBy),
            ':id' => $id,
        ]);
    }

    public function deleteRecord(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE f_templateID = :id");
        return $stmt->execute([':id' => $id]);
    }
}
