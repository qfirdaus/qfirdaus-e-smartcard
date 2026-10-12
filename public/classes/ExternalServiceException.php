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

require_once __DIR__ . '/FrameworkException.php';

/**
 * Standard exception for failures outside the application boundary.
 *
 * Examples: AI providers, SMTP, payment gateway, SSO, REST API, DNS, timeout,
 * SSL, connection refused, provider quota, provider invalid response.
 */
class ExternalServiceException extends FrameworkException
{
    /** @var array<string,mixed> */
    private array $context;
    private string $provider;
    private ?string $endpoint;
    private ?int $httpStatus;
    private string $category;
    private bool $retryable;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(
        string $message,
        string $provider = 'external',
        ?string $endpoint = null,
        ?int $httpStatus = null,
        string $category = 'external_service',
        bool $retryable = false,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->provider = $provider !== '' ? $provider : 'external';
        $this->endpoint = $endpoint;
        $this->httpStatus = $httpStatus;
        $this->category = $category !== '' ? $category : 'external_service';
        $this->retryable = $retryable;
        $this->context = $context;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function endpoint(): ?string
    {
        return $this->endpoint;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }

    /**
     * @return array<string,mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}

class ExternalServiceAuthenticationException extends ExternalServiceException
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $message, string $provider = 'external', ?string $endpoint = null, ?int $httpStatus = null, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $provider, $endpoint, $httpStatus, 'authentication', false, $context, 0, $previous);
    }
}

class ExternalServiceRateLimitException extends ExternalServiceException
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $message, string $provider = 'external', ?string $endpoint = null, ?int $httpStatus = 429, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $provider, $endpoint, $httpStatus, 'rate_limit', true, $context, 0, $previous);
    }
}

class ExternalServiceTimeoutException extends ExternalServiceException
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $message, string $provider = 'external', ?string $endpoint = null, ?int $httpStatus = null, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $provider, $endpoint, $httpStatus, 'timeout', true, $context, 0, $previous);
    }
}

class ExternalServiceUnavailableException extends ExternalServiceException
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $message, string $provider = 'external', ?string $endpoint = null, ?int $httpStatus = null, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $provider, $endpoint, $httpStatus, 'unavailable', true, $context, 0, $previous);
    }
}

class ExternalServiceInvalidResponseException extends ExternalServiceException
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $message, string $provider = 'external', ?string $endpoint = null, ?int $httpStatus = null, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $provider, $endpoint, $httpStatus, 'invalid_response', false, $context, 0, $previous);
    }
}

