<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class TemplateRegistryService
{
    private string $registryPath;
    private ?array $templates = null;

    public function __construct(?string $registryPath = null)
    {
        $this->registryPath = $registryPath ?: dirname(__DIR__, 1) . '/templates/generator/page-generator/registry/templates.json';
    }

    public function getRegistryPath(): string
    {
        return $this->registryPath;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAllTemplates(): array
    {
        $data = $this->loadRegistry();
        $templates = $data['templates'] ?? [];
        return is_array($templates) ? array_values(array_filter($templates, 'is_array')) : [];
    }

    /**
     * @return array<string,mixed>
     */
    public function getTemplate(string $key): array
    {
        $normalizedKey = trim($key);
        if ($normalizedKey === '') {
            throw new InvalidArgumentException('Template key is required.');
        }

        foreach ($this->getAllTemplates() as $template) {
            if (strcasecmp((string)($template['key'] ?? ''), $normalizedKey) === 0) {
                return $template;
            }
        }

        throw new RuntimeException("Template '{$normalizedKey}' was not found.");
    }

    /**
     * @return array<string,mixed>
     */
    private function loadRegistry(): array
    {
        if ($this->templates !== null) {
            return $this->templates;
        }

        if (!is_file($this->registryPath)) {
            throw new RuntimeException("Template registry file not found: {$this->registryPath}");
        }

        $json = (string)file_get_contents($this->registryPath);
        if ($json === '') {
            throw new RuntimeException("Template registry file is empty: {$this->registryPath}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid template registry JSON: {$e->getMessage()}", 0, $e);
        }

        if (!is_array($data) || !isset($data['templates']) || !is_array($data['templates'])) {
            throw new RuntimeException('Template registry must contain a templates array.');
        }

        $this->templates = $data;
        return $this->templates;
    }
}
