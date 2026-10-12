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

interface AiChatbotProviderInterface
{
    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function send(array $messages, array $options = []): array;
}
