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

require_once __DIR__ . '/AiChatbotProviderInterface.php';
require_once __DIR__ . '/AiChatbotProviders/OllamaProvider.php';
require_once __DIR__ . '/AiChatbotProviders/OpenAIProvider.php';
require_once __DIR__ . '/AiChatbotProviders/GeminiProvider.php';
require_once __DIR__ . '/AiChatbotProviders/GrokProvider.php';
require_once __DIR__ . '/AiChatbotProviders/GroqProvider.php';
require_once __DIR__ . '/AiChatbotProviders/AnthropicProvider.php';
require_once __DIR__ . '/AiChatbotProviders/OpenRouterProvider.php';
require_once __DIR__ . '/AiChatbotProviders/OpenAICompatibleProvider.php';

final class AiChatbotProviderRegistry
{
    /**
     * @param array<string,mixed> $config
     */
    public static function resolve(array $config): AiChatbotProviderInterface
    {
        $provider = strtolower(trim((string)($config['provider'] ?? 'ollama')));

        return match ($provider) {
            'ollama' => new OllamaProvider($config),
            'openai' => new OpenAIProvider($config),
            'gemini' => new GeminiProvider($config),
            'grok' => new GrokProvider($config),
            'groq' => new GroqProvider($config),
            'anthropic' => new AnthropicProvider($config),
            'openrouter' => new OpenRouterProvider($config),
            'openai_compatible' => new OpenAICompatibleProvider(
                $config,
                'openai_compatible',
                'https://api.openai.com',
                'gpt-4o-mini'
            ),
            default => throw new InvalidArgumentException('Unsupported AI chatbot provider.'),
        };
    }
}
