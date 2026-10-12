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

interface AiChatbotProjectContextProviderInterface
{
    public function code(): string;

    public function label(): string;

    /**
     * @return array<int,string>
     */
    public function aliases(): array;

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function build(string $message, array $profile, array $actor): array;
}
