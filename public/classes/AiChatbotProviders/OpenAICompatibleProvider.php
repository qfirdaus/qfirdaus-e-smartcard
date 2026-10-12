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

require_once __DIR__ . '/../AiChatbotProviderInterface.php';
require_once __DIR__ . '/../ExternalHttpClient.php';

class OpenAICompatibleProvider implements AiChatbotProviderInterface
{
    /** @var array<string,mixed> */
    protected array $config;
    protected string $providerCode;
    protected string $defaultBaseUrl;
    protected string $defaultModel;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        array $config,
        string $providerCode,
        string $defaultBaseUrl,
        string $defaultModel
    ) {
        $this->config = $config;
        $this->providerCode = $providerCode;
        $this->defaultBaseUrl = $defaultBaseUrl;
        $this->defaultModel = $defaultModel;
    }

    public function send(array $messages, array $options = []): array
    {
        $started = microtime(true);
        $baseUrlValue = trim((string)($this->config['base_url'] ?? ''));
        $modelValue = trim((string)($this->config['model'] ?? ''));
        $baseUrl = rtrim($baseUrlValue !== '' ? $baseUrlValue : $this->defaultBaseUrl, '/');
        $model = $modelValue !== '' ? $modelValue : $this->defaultModel;
        $apiKey = trim((string)($this->config['api_key'] ?? ''));
        $timeout = max(1, (int)($this->config['timeout_seconds'] ?? 30));
        $maxTokens = max(64, (int)($this->config['max_output_tokens'] ?? 800));

        if ($model === '') {
            throw new InvalidArgumentException($this->providerCode . ' model is not configured.');
        }

        if ($apiKey === '') {
            throw new ExternalServiceAuthenticationException($this->providerCode . ' API key is not configured.', $this->providerCode);
        }

        $endpoint = $this->chatCompletionsEndpoint($baseUrl);
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => $maxTokens,
            'temperature' => (float)($options['temperature'] ?? 0.3),
        ];

        $response = $this->postJson($endpoint, $payload, $timeout, $apiKey);
        $content = trim((string)($response['choices'][0]['message']['content'] ?? ''));

        if ($content === '') {
            throw new ExternalServiceInvalidResponseException($this->providerCode . ' returned an empty response.', $this->providerCode, $endpoint);
        }

        return [
            'success' => true,
            'provider' => $this->providerCode,
            'model' => $model,
            'message' => $content,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            'usage' => is_array($response['usage'] ?? null) ? $response['usage'] : [],
            'error_code' => null,
            'error_message' => null,
        ];
    }

    protected function chatCompletionsEndpoint(string $baseUrl): string
    {
        if (str_ends_with($baseUrl, '/chat/completions')) {
            return $baseUrl;
        }

        return str_ends_with($baseUrl, '/v1')
            ? $baseUrl . '/chat/completions'
            : $baseUrl . '/v1/chat/completions';
    }

    /**
     * @return array<int,string>
     */
    protected function headers(string $apiKey): array
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    protected function postJson(string $url, array $payload, int $timeout, string $apiKey): array
    {
        $response = (new ExternalHttpClient($this->providerCode, $timeout))->postJson($url, $payload, $this->headers($apiKey), $timeout);
        return $this->decodeResponse($response->body(), $response->statusCode(), $url);
    }

    /**
     * @return array<string,mixed>
     */
    protected function decodeResponse(string $raw, int $status, ?string $endpoint = null): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ExternalServiceInvalidResponseException($this->providerCode . ' returned invalid JSON.', $this->providerCode, $endpoint, $status);
        }

        if ($status >= 400) {
            $message = (string)($decoded['error']['message'] ?? $decoded['error'] ?? $this->providerCode . ' provider error.');
            throw new ExternalServiceException($message, $this->providerCode, $endpoint, $status, 'provider_error', $status >= 500);
        }

        return $decoded;
    }
}
