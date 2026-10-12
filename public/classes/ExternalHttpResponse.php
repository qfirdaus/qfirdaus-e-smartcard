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

final class ExternalHttpResponse
{
    private int $statusCode;
    private string $body;

    /** @var array<string,string> */
    private array $headers;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        int $statusCode,
        string $body,
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower(trim((string)$name))] = trim((string)$value);
        }
        $this->headers = $normalized;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        $key = strtolower(trim($name));
        return $key !== '' ? ($this->headers[$key] ?? null) : null;
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string,mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
