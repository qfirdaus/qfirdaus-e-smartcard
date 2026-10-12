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

final class GeminiProvider implements AiChatbotProviderInterface
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
        $baseUrl = rtrim($baseUrlValue !== '' ? $baseUrlValue : 'https://generativelanguage.googleapis.com', '/');
        $model = $modelValue !== '' ? $modelValue : 'gemini-1.5-flash';
        $apiKey = trim((string)($this->config['api_key'] ?? ''));
        $timeout = max(1, (int)($this->config['timeout_seconds'] ?? 30));
        $maxTokens = max(64, (int)($this->config['max_output_tokens'] ?? 800));

        if ($model === '') {
            throw new InvalidArgumentException('Gemini model is not configured.');
        }

        if ($apiKey === '') {
            throw new ExternalServiceAuthenticationException('Gemini API key is not configured.', 'gemini');
        }

        $systemText = '';
        $contents = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? '');
            $content = (string)($message['content'] ?? '');
            if ($content === '') {
                continue;
            }
            if ($role === 'system') {
                $systemText = trim($systemText . "\n" . $content);
                continue;
            }
            $contents[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [
                    ['text' => $content],
                ],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature' => (float)($options['temperature'] ?? 0.3),
            ],
        ];
        if ($systemText !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemText],
                ],
            ];
        }

        $endpoint = $this->generateContentEndpoint($baseUrl, $model, $apiKey);
        $response = $this->postJson($endpoint, $payload, $timeout);
        $content = $this->extractText($response);
        if ($content === '') {
            throw new ExternalServiceInvalidResponseException('Gemini returned an empty response.', 'gemini', $endpoint);
        }

        return [
            'success' => true,
            'provider' => 'gemini',
            'model' => $model,
            'message' => $content,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            'usage' => is_array($response['usageMetadata'] ?? null) ? $response['usageMetadata'] : [],
            'error_code' => null,
            'error_message' => null,
        ];
    }

    private function generateContentEndpoint(string $baseUrl, string $model, string $apiKey): string
    {
        $encodedModel = rawurlencode($model);
        if (str_contains($baseUrl, ':generateContent')) {
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            return $baseUrl . $separator . 'key=' . rawurlencode($apiKey);
        }

        return $baseUrl . '/v1beta/models/' . $encodedModel . ':generateContent?key=' . rawurlencode($apiKey);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function postJson(string $url, array $payload, int $timeout): array
    {
        $response = (new ExternalHttpClient('gemini', $timeout))->postJson($url, $payload, [], $timeout);
        return $this->decodeResponse($response->body(), $response->statusCode(), $url);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeResponse(string $raw, int $status, ?string $endpoint = null): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ExternalServiceInvalidResponseException('Gemini returned invalid JSON.', 'gemini', $endpoint, $status);
        }

        if ($status >= 400) {
            $message = (string)($decoded['error']['message'] ?? $decoded['error'] ?? 'Gemini provider error.');
            throw new ExternalServiceException($message, 'gemini', $endpoint, $status, 'provider_error', $status >= 500);
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractText(array $response): string
    {
        $parts = [];
        foreach ((array)($response['candidates'] ?? []) as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            foreach ((array)($candidate['content']['parts'] ?? []) as $part) {
                if (is_array($part)) {
                    $text = trim((string)($part['text'] ?? ''));
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
