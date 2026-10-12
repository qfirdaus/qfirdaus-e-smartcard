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

final class GroqProvider extends OpenAICompatibleProvider
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config)
    {
        parent::__construct(
            $config,
            'groq',
            'https://api.groq.com/openai/v1',
            'llama-3.1-8b-instant'
        );
    }
}
