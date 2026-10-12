<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/SystemTemplate.php';
require_once __DIR__ . '/FileGenerationService.php';

final class SystemTemplateCreationService
{
    private PDO $pdoMysql;
    private SystemTemplate $systemTemplateModel;
    private FileGenerationService $fileGenerationService;

    public function __construct(
        PDO $pdoMysql,
        ?SystemTemplate $systemTemplateModel = null,
        ?FileGenerationService $fileGenerationService = null
    ) {
        $this->pdoMysql = $pdoMysql;
        $this->systemTemplateModel = $systemTemplateModel ?: new SystemTemplate($pdoMysql);
        $this->fileGenerationService = $fileGenerationService ?: new FileGenerationService();
    }

    /**
     * @param array<string,string> $input
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function create(array $input, array $context = []): array
    {
        $normalized = $this->validateInput($input);
        $generationInput = $this->buildGenerationInput($normalized);
        $preview = $this->fileGenerationService->preview($normalized['template_key'], $generationInput);

        $this->assertDatabaseDoesNotExist($preview);

        $filesResult = null;
        $templateId = null;
        $langResult = null;

        try {
            $filesResult = $this->fileGenerationService->generateFilesOnly($normalized['template_key'], $generationInput);
            $startedTransaction = !$this->pdoMysql->inTransaction();
            if ($startedTransaction) {
                $this->pdoMysql->beginTransaction();
            }
            $templateId = $this->insertRecord(
                $this->buildDbPayload($normalized, $preview, $filesResult, $context)
            );
            $langResult = $this->fileGenerationService->appendLanguageEntriesForTemplate($normalized['template_key'], $generationInput);
            if ($startedTransaction && $this->pdoMysql->inTransaction()) {
                $this->pdoMysql->commit();
            }

            return [
                'template_id' => $templateId,
                'template' => $filesResult['template'],
                'template_label' => $filesResult['template_label'],
                'template_version' => $filesResult['template_version'],
                'page_slug' => $filesResult['page_slug'],
                'page_key_prefix' => $filesResult['page_key_prefix'],
                'controller_class' => $filesResult['controller_class'],
                'page_icon' => $filesResult['page_icon'],
                'files_created' => [
                    ...$filesResult['files_created'],
                    ...($langResult['lang_files_updated'] ?? []),
                ],
            ];
        } catch (Throwable $e) {
            if (isset($startedTransaction) && $startedTransaction && $this->pdoMysql->inTransaction()) {
                $this->pdoMysql->rollBack();
            }

            if ($filesResult !== null) {
                $this->fileGenerationService->rollbackLanguageBlocks((string)($filesResult['page_key_prefix'] ?? ''));
            }

            if ($templateId !== null && (!isset($startedTransaction) || !$startedTransaction)) {
                $this->systemTemplateModel->deleteRecord((int)$templateId);
            }

            if ($filesResult !== null) {
                $this->fileGenerationService->rollbackGeneratedFiles((array)($filesResult['files_created'] ?? []));
            }

            throw $e;
        }
    }

    /**
     * @param array<string,string> $input
     * @return array<string,string>
     */
    private function validateInput(array $input): array
    {
        $normalized = [
            'template_name' => trim((string)($input['template_name'] ?? '')),
            'template_key' => trim((string)($input['template_key'] ?? '')),
            'page_name' => trim((string)($input['page_name'] ?? '')),
            'page_title_ms' => trim((string)($input['page_title_ms'] ?? '')),
            'page_title_en' => trim((string)($input['page_title_en'] ?? '')),
            'page_icon' => trim((string)($input['page_icon'] ?? '')),
        ];

        foreach (['template_name', 'template_key', 'page_name', 'page_title_ms', 'page_title_en', 'page_icon'] as $key) {
            if ($normalized[$key] === '') {
                throw new InvalidArgumentException('Missing required template creation fields.');
            }
        }

        $limits = ['template_name' => 120, 'page_name' => 80, 'page_title_ms' => 160, 'page_title_en' => 160, 'page_icon' => 80];
        foreach ($limits as $key => $limit) {
            if (mb_strlen($normalized[$key]) > $limit) {
                throw new InvalidArgumentException('Template field exceeds the permitted length.');
            }
        }

        if (!preg_match('/^ri-[a-z0-9-]+$/', $normalized['page_icon'])) {
            throw new InvalidArgumentException('Invalid page icon.');
        }

        $normalized['access_mode'] = trim((string)($input['access_mode'] ?? FileGenerationService::ACCESS_MODE_GROUP_MENU));
        if (!in_array($normalized['access_mode'], [FileGenerationService::ACCESS_MODE_GROUP_MENU, FileGenerationService::ACCESS_MODE_SUPER_ADMIN_ONLY], true)) {
            throw new InvalidArgumentException('Invalid template access mode.');
        }

        return $normalized;
    }

    /**
     * @param array<string,string> $normalized
     * @return array<string,string>
     */
    private function buildGenerationInput(array $normalized): array
    {
        return [
            'page_name' => $normalized['page_name'],
            'page_title_ms' => $normalized['page_title_ms'],
            'page_title_en' => $normalized['page_title_en'],
            'page_icon' => $normalized['page_icon'],
            'access_mode' => $normalized['access_mode'],
        ];
    }

    /**
     * @param array<string,mixed> $preview
     */
    private function assertDatabaseDoesNotExist(array $preview): void
    {
        $pageSlug = trim((string)($preview['page_slug'] ?? ''));
        $controllerClass = trim((string)($preview['controller_class'] ?? ''));

        if ($pageSlug !== '' && $this->systemTemplateModel->existsBySlug($pageSlug)) {
            throw new RuntimeException('Template record already exists for this page slug.');
        }

        if ($controllerClass !== '' && $this->systemTemplateModel->existsByControllerClass($controllerClass)) {
            throw new RuntimeException('Template record already exists for this controller class.');
        }
    }

    /**
     * @param array<string,string> $input
     * @param array<string,mixed> $preview
     * @param array<string,mixed> $filesResult
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function buildDbPayload(array $input, array $preview, array $filesResult, array $context): array
    {
        return [
            'template_name' => $input['template_name'],
            'template_type' => $input['template_key'],
            'page_name' => $input['page_name'],
            'page_title_ms' => $input['page_title_ms'],
            'page_title_en' => $input['page_title_en'],
            'page_slug' => (string)($preview['page_slug'] ?? ''),
            'page_icon' => $input['page_icon'],
            'controller_class' => (string)($preview['controller_class'] ?? ''),
            'lang_key_prefix' => (string)($preview['page_key_prefix'] ?? ''),
            'output_page_path' => (string)($filesResult['files']['page'] ?? ''),
            'output_controller_path' => (string)($filesResult['files']['controller'] ?? ''),
            'output_css_path' => (string)($filesResult['files']['css'] ?? ''),
            'generation_summary' => json_encode([
                'template_key' => $filesResult['template'] ?? $input['template_key'],
                'template_version' => $filesResult['template_version'] ?? 1,
                'page_icon' => $input['page_icon'],
                'access_mode' => $input['access_mode'],
                'files_created' => $filesResult['files_created'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'GENERATED',
            'update_by' => trim((string)($context['update_by'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function insertRecord(array $payload): int
    {
        return $this->systemTemplateModel->create($payload);
    }
}
