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

final class OllamaProvider implements AiChatbotProviderInterface
{
    /** @var array<string,mixed> */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $messages, array $options = []): array
    {
        $started = microtime(true);
        $baseUrlValue = trim((string)($this->config['base_url'] ?? ''));
        $modelValue = trim((string)($this->config['model'] ?? ''));
        $baseUrl = rtrim($baseUrlValue !== '' ? $baseUrlValue : 'http://127.0.0.1:11434', '/');
        $model = $modelValue !== '' ? $modelValue : 'llama3.2:3b';
        $timeout = max(1, (int)($this->config['timeout_seconds'] ?? 30));
        $maxTokens = max(64, (int)($this->config['max_output_tokens'] ?? 800));

        if ($model === '') {
            throw new InvalidArgumentException('Ollama model is not configured.');
        }

        $endpoint = str_ends_with($baseUrl, '/v1')
            ? $baseUrl . '/chat/completions'
            : $baseUrl . '/v1/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => $maxTokens,
            'temperature' => (float)($options['temperature'] ?? 0.3),
        ];

        $response = $this->postJson($endpoint, $payload, $timeout);
        $content = trim((string)($response['choices'][0]['message']['content'] ?? ''));

        if ($content === '') {
            throw new ExternalServiceInvalidResponseException('Ollama returned an empty response.', 'ollama', $endpoint);
        }

        return [
            'success' => true,
            'provider' => 'ollama',
            'model' => $model,
            'message' => $content,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            'usage' => is_array($response['usage'] ?? null) ? $response['usage'] : [],
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function postJson(string $url, array $payload, int $timeout): array
    {
        $response = (new ExternalHttpClient('ollama', $timeout))->postJson($url, $payload, [], $timeout);
        return $this->decodeResponse($response->body(), $response->statusCode(), $url);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeResponse(string $raw, int $status, ?string $endpoint = null): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ExternalServiceInvalidResponseException('Ollama returned invalid JSON.', 'ollama', $endpoint, $status);
        }

        if ($status >= 400) {
            $message = (string)($decoded['error']['message'] ?? $decoded['error'] ?? 'Ollama provider error.');
            throw new ExternalServiceException($message, 'ollama', $endpoint, $status, 'provider_error', $status >= 500);
        }

        return $decoded;
    }
}
