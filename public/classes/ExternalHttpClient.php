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

require_once __DIR__ . '/ExternalHttpResponse.php';
require_once __DIR__ . '/ExternalServiceException.php';
require_once __DIR__ . '/ApplicationException.php';

final class ExternalHttpClient
{
    private string $provider;
    private int $defaultTimeoutSeconds;
    private int $defaultConnectTimeoutSeconds;

    public function __construct(string $provider, int $defaultTimeoutSeconds = 15, int $defaultConnectTimeoutSeconds = 5)
    {
        $this->provider = $provider !== '' ? $provider : 'external';
        $this->defaultTimeoutSeconds = $defaultTimeoutSeconds;
        $this->defaultConnectTimeoutSeconds = $defaultConnectTimeoutSeconds;
    }

    /**
     * @param array<int,string>|array<string,string> $headers
     */
    public function get(string $url, array $headers = [], ?int $timeoutSeconds = null): ExternalHttpResponse
    {
        return $this->request('GET', $url, $headers, null, $timeoutSeconds);
    }

    /**
     * @param array<int,string>|array<string,string> $headers
     * @param array<string,mixed> $payload
     */
    public function postJson(string $url, array $payload, array $headers = [], ?int $timeoutSeconds = null): ExternalHttpResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            throw new ApplicationException('Unable to encode external HTTP JSON payload.');
        }

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        return $this->request('POST', $url, $headers, $body, $timeoutSeconds);
    }

    /**
     * @param array<int,string>|array<string,string> $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $timeoutSeconds = null
    ): ExternalHttpResponse {
        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new InvalidArgumentException('External HTTP method is required.');
        }

        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('External HTTP URL is invalid.');
        }

        $timeout = max(1, $timeoutSeconds ?? $this->defaultTimeoutSeconds);
        $connectTimeout = max(1, min($timeout, $this->defaultConnectTimeoutSeconds));

        if (function_exists('curl_init')) {
            return $this->requestWithCurl($method, $url, $headers, $body, $timeout, $connectTimeout);
        }

        return $this->requestWithStreams($method, $url, $headers, $body, $timeout);
    }

    /**
     * @param array<int,string>|array<string,string> $headers
     */
    private function requestWithCurl(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        int $connectTimeout
    ): ExternalHttpResponse {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ApplicationException('Unable to initialize external HTTP client.');
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->normalizeHeaderLines($headers),
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $line = trim($headerLine);
                if ($line !== '' && str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = (int)curl_errno($ch);
        $error = (string)curl_error($ch);
        curl_close($ch);

        if (!is_string($raw)) {
            throw $this->transportException($url, $error !== '' ? $error : 'External HTTP request failed.', $errno, null);
        }

        return $this->buildResponseOrThrow($url, $status, $raw, $responseHeaders);
    }

    /**
     * @param array<int,string>|array<string,string> $headers
     */
    private function requestWithStreams(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout
    ): ExternalHttpResponse {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL) && ini_get('allow_url_fopen') !== '1') {
            throw new ApplicationException('External HTTP stream transport is disabled.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $this->normalizeHeaderLines($headers)) . "\r\n",
                'content' => $body ?? '',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        $responseHeaders = [];
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                $line = trim((string)$header);
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $matches)) {
                    $status = (int)$matches[1];
                    continue;
                }
                if ($line !== '' && str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
            }
        }

        if (!is_string($raw)) {
            throw $this->transportException($url, 'External HTTP request failed.', 0, null);
        }

        return $this->buildResponseOrThrow($url, $status, $raw, $responseHeaders);
    }

    /**
     * @param array<string,string> $headers
     */
    private function buildResponseOrThrow(string $url, int $status, string $body, array $headers): ExternalHttpResponse
    {
        $response = new ExternalHttpResponse($status, $body, $headers);
        if ($status <= 0) {
            throw new ExternalServiceUnavailableException('External provider did not return an HTTP status.', $this->provider, $url, null, ['provider_http_status' => $status]);
        }
        if ($status >= 400) {
            throw $this->providerStatusException($url, $status, $body);
        }

        return $response;
    }

    private function providerStatusException(string $url, int $status, string $body): ExternalServiceException
    {
        $message = $this->extractProviderMessage($body) ?: 'External provider returned HTTP ' . $status . '.';
        $context = ['provider_http_status' => $status];

        if (in_array($status, [401, 403], true)) {
            return new ExternalServiceAuthenticationException($message, $this->provider, $url, $status, $context);
        }
        if ($status === 429) {
            return new ExternalServiceRateLimitException($message, $this->provider, $url, $status, $context);
        }
        if ($status >= 500) {
            return new ExternalServiceUnavailableException($message, $this->provider, $url, $status, $context);
        }

        return new ExternalServiceException($message, $this->provider, $url, $status, 'provider_error', false, $context);
    }

    private function transportException(string $url, string $message, int $curlErrno, ?Throwable $previous): ExternalServiceException
    {
        $lower = strtolower($message);
        $context = $curlErrno > 0 ? ['curl_errno' => $curlErrno] : [];

        if (
            $curlErrno === 28
            || str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
        ) {
            return new ExternalServiceTimeoutException($message, $this->provider, $url, null, $context, $previous);
        }

        return new ExternalServiceUnavailableException($message, $this->provider, $url, null, $context, $previous);
    }

    /**
     * @param array<int,string>|array<string,string> $headers
     * @return array<int,string>
     */
    private function normalizeHeaderLines(array $headers): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $line = trim((string)$value);
            } else {
                $line = trim((string)$name) . ': ' . trim((string)$value);
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function extractProviderMessage(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $candidates = [
                $decoded['error']['message'] ?? null,
                $decoded['error_description'] ?? null,
                $decoded['message'] ?? null,
                is_string($decoded['error'] ?? null) ? $decoded['error'] : null,
            ];
            foreach ($candidates as $candidate) {
                $message = trim((string)$candidate);
                if ($message !== '') {
                    return mb_substr($message, 0, 500, 'UTF-8');
                }
            }
        }

        return mb_substr(trim(strip_tags($body)), 0, 500, 'UTF-8');
    }
}
