<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class DatabaseConnectionDefinition
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $family,
        public readonly string $category,
        public readonly string $purpose,
        public readonly bool $required,
        public readonly bool $enabled,
        public readonly string $driverMode,
        public readonly array $environments = [],
    ) {
    }

    public function isMain(): bool
    {
        return $this->category === 'main';
    }

    public function isAdditional(): bool
    {
        return $this->category === 'additional';
    }
}
