<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/TemplateRegistryService.php';

final class TemplateResolverService
{
    private TemplateRegistryService $registry;
    private string $templateRoot;

    public function __construct(?TemplateRegistryService $registry = null, ?string $templateRoot = null)
    {
        $this->registry = $registry ?: new TemplateRegistryService();
        $this->templateRoot = rtrim($templateRoot ?: dirname(__DIR__, 1) . '/templates/generator/page-generator', '/\\');
    }

    public function getTemplateRoot(): string
    {
        return $this->templateRoot;
    }

    /**
     * @return array<string,mixed>
     */
    public function resolveTemplate(string $key): array
    {
        $template = $this->registry->getTemplate($key);

        $resolved = [
            'key' => (string)($template['key'] ?? ''),
            'label' => (string)($template['label'] ?? ''),
            'description' => (string)($template['description'] ?? ''),
            'version' => (int)($template['version'] ?? 1),
            'outputs' => is_array($template['outputs'] ?? null) ? array_values($template['outputs']) : [],
            'paths' => [
                'page_stub' => $this->resolveRequiredRelativePath((string)($template['page_stub'] ?? '')),
                'controller_stub' => $this->resolveRequiredRelativePath((string)($template['controller_stub'] ?? '')),
                'css_stub' => $this->resolveRequiredRelativePath((string)($template['css_stub'] ?? '')),
                'meta_stub' => $this->resolveRequiredRelativePath((string)($template['meta_stub'] ?? '')),
            ],
        ];

        $resolved['meta'] = $this->loadMeta($resolved['paths']['meta_stub']);

        return $resolved;
    }

    private function resolveRequiredRelativePath(string $relativePath): string
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            throw new RuntimeException('Template path is missing in registry.');
        }

        $fullPath = $this->templateRoot . '/' . str_replace('\\', '/', $relativePath);
        if (!is_file($fullPath)) {
            throw new RuntimeException("Template stub file not found: {$fullPath}");
        }

        return $fullPath;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadMeta(string $metaPath): array
    {
        $json = (string)file_get_contents($metaPath);
        if ($json === '') {
            return [];
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid template meta JSON: {$e->getMessage()}", 0, $e);
        }

        return is_array($data) ? $data : [];
    }
}
