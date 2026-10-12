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

final class AnthropicProvider implements AiChatbotProviderInterface
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
        $baseUrl = rtrim($baseUrlValue !== '' ? $baseUrlValue : 'https://api.anthropic.com', '/');
        $model = $modelValue !== '' ? $modelValue : 'claude-3-5-haiku-latest';
        $apiKey = trim((string)($this->config['api_key'] ?? ''));
        $timeout = max(1, (int)($this->config['timeout_seconds'] ?? 30));
        $maxTokens = max(64, (int)($this->config['max_output_tokens'] ?? 800));

        if ($model === '') {
            throw new InvalidArgumentException('Anthropic model is not configured.');
        }

        if ($apiKey === '') {
            throw new ExternalServiceAuthenticationException('Anthropic API key is not configured.', 'anthropic');
        }

        $system = '';
        $anthropicMessages = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? '');
            $content = (string)($message['content'] ?? '');
            if ($content === '') {
                continue;
            }
            if ($role === 'system') {
                $system = trim($system . "\n" . $content);
                continue;
            }
            $anthropicMessages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $anthropicMessages,
            'max_tokens' => $maxTokens,
            'temperature' => (float)($options['temperature'] ?? 0.3),
        ];
        if ($system !== '') {
            $payload['system'] = $system;
        }

        $response = $this->postJson($this->messagesEndpoint($baseUrl), $payload, $timeout, $apiKey);
        $content = $this->extractText($response);
        if ($content === '') {
            throw new ExternalServiceInvalidResponseException('Anthropic returned an empty response.', 'anthropic', $this->messagesEndpoint($baseUrl));
        }

        return [
            'success' => true,
            'provider' => 'anthropic',
            'model' => $model,
            'message' => $content,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            'usage' => is_array($response['usage'] ?? null) ? $response['usage'] : [],
            'error_code' => null,
            'error_message' => null,
        ];
    }

    private function messagesEndpoint(string $baseUrl): string
    {
        return str_ends_with($baseUrl, '/v1/messages')
            ? $baseUrl
            : rtrim($baseUrl, '/') . '/v1/messages';
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function postJson(string $url, array $payload, int $timeout, string $apiKey): array
    {
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];

        $response = (new ExternalHttpClient('anthropic', $timeout))->postJson($url, $payload, $headers, $timeout);
        return $this->decodeResponse($response->body(), $response->statusCode(), $url);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeResponse(string $raw, int $status, ?string $endpoint = null): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ExternalServiceInvalidResponseException('Anthropic returned invalid JSON.', 'anthropic', $endpoint, $status);
        }

        if ($status >= 400) {
            $message = (string)($decoded['error']['message'] ?? $decoded['error'] ?? 'Anthropic provider error.');
            throw new ExternalServiceException($message, 'anthropic', $endpoint, $status, 'provider_error', $status >= 500);
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractText(array $response): string
    {
        $parts = [];
        foreach ((array)($response['content'] ?? []) as $part) {
            if (is_array($part) && (string)($part['type'] ?? '') === 'text') {
                $text = trim((string)($part['text'] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
