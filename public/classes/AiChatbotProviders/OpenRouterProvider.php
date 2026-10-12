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

require_once __DIR__ . '/OpenAICompatibleProvider.php';

final class OpenRouterProvider extends OpenAICompatibleProvider
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config)
    {
        parent::__construct(
            $config,
            'openrouter',
            'https://openrouter.ai/api/v1',
            'openrouter/free'
        );
    }

    protected function headers(string $apiKey): array
    {
        $headers = parent::headers($apiKey);
        $appUrl = trim((string)($this->config['app_url'] ?? 'https://iqs-framework.dev'));
        $appTitle = trim((string)($this->config['app_title'] ?? 'IQS-Framework AI Chatbot'));
        if ($appUrl !== '') {
            $headers[] = 'HTTP-Referer: ' . $appUrl;
        }
        if ($appTitle !== '') {
            $headers[] = 'X-OpenRouter-Title: ' . $appTitle;
        }
        return $headers;
    }
}
