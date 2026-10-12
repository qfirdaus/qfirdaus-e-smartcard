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

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/Config.php';
    require_once __DIR__ . '/../classes/SystemConfigConstants.php';
    require_once __DIR__ . '/../setting/constants/prestasi_constants.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse('Kaedah permintaan tidak sah.', 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse('CSRF token tidak sah. Sila muat semula halaman dan cuba lagi.', 403);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    $profile = $GLOBALS['profile'] ?? [];
    $profile = is_array($profile) ? $profile : [];
    $groupId = (int)($profile['f_groupID'] ?? $_SESSION['group_active_id'] ?? 0);
    if ($groupId !== (int)PRESTASI_ROLE_ID_ADM_SA) {
        jsonErrorResponse('Akses ditolak. Hanya Super Admin dibenarkan.', 403);
    }

    if (!checkRateLimit('ai_chatbot_models', 20, 60)) {
        jsonErrorResponse('Terlalu banyak permintaan model. Sila cuba semula sebentar lagi.', 429);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $provider = strtolower(trim((string)($data['provider'] ?? '')));
    $baseUrl = trim((string)($data['base_url'] ?? ''));
    $apiKey = trim((string)($data['api_key'] ?? ''));
    $allowedProviders = ['ollama', 'openai', 'gemini', 'grok', 'anthropic', 'openrouter', 'openai_compatible'];

    if (!in_array($provider, $allowedProviders, true)) {
        jsonErrorResponse('Provider tidak sah.', 422);
    }

    if ($apiKey === '') {
        $configModel = new Config($pdo);
        $settings = $configModel->getGroup(SystemConfigConstants::CONFIG_GROUP_AI_CHATBOT);
        $apiKey = trim((string)($settings['api_key'] ?? ''));
    }

    $models = ai_chatbot_fetch_provider_models($provider, $baseUrl, $apiKey);
    jsonSuccessResponse([
        'provider' => $provider,
        'models' => $models,
    ]);
} catch (ExternalServiceException $e) {
    jsonExceptionResponse($e, 'Tidak dapat mengambil senarai model provider buat masa ini.', [
        'endpoint' => 'ai-chatbot-models',
    ]);
} catch (Throwable $e) {
    error_log('[ai-chatbot-models] ' . $e->getMessage());
    jsonErrorResponse($e->getMessage(), 500);
}

/**
 * @return array<int,string>
 */
function ai_chatbot_fetch_provider_models(string $provider, string $baseUrl, string $apiKey): array
{
    return match ($provider) {
        'ollama' => ai_chatbot_fetch_ollama_models($baseUrl),
        'gemini' => ai_chatbot_fetch_gemini_models($baseUrl, $apiKey),
        'anthropic' => ai_chatbot_fetch_anthropic_models($baseUrl, $apiKey),
        'openrouter' => ai_chatbot_fetch_openai_compatible_models('openrouter', $baseUrl !== '' ? $baseUrl : 'https://openrouter.ai/api/v1', $apiKey, false),
        'openai' => ai_chatbot_fetch_openai_compatible_models('openai', $baseUrl !== '' ? $baseUrl : 'https://api.openai.com/v1', $apiKey, true),
        'grok' => ai_chatbot_fetch_openai_compatible_models('grok', $baseUrl !== '' ? $baseUrl : 'https://api.x.ai/v1', $apiKey, true),
        'openai_compatible' => ai_chatbot_fetch_openai_compatible_models('openai_compatible', $baseUrl, $apiKey, false),
        default => [],
    };
}

/**
 * @return array<int,string>
 */
function ai_chatbot_fetch_ollama_models(string $baseUrl): array
{
    $baseUrl = rtrim($baseUrl !== '' ? $baseUrl : 'http://127.0.0.1:11434', '/');
    if (str_ends_with($baseUrl, '/v1')) {
        $baseUrl = substr($baseUrl, 0, -3);
    }

    $decoded = ai_chatbot_http_json('ollama', 'GET', $baseUrl . '/api/tags', [], null, 10);
    $models = [];
    foreach ((array)($decoded['models'] ?? []) as $model) {
        if (is_array($model)) {
            $name = trim((string)($model['name'] ?? $model['model'] ?? ''));
            if ($name !== '') {
                $models[] = $name;
            }
        }
    }

    return ai_chatbot_unique_sorted_models($models);
}

/**
 * @return array<int,string>
 */
function ai_chatbot_fetch_openai_compatible_models(string $provider, string $baseUrl, string $apiKey, bool $requireKey): array
{
    if ($requireKey && $apiKey === '') {
        throw new ExternalServiceAuthenticationException('API key diperlukan untuk fetch senarai model provider ini.', $provider);
    }

    $baseUrl = rtrim($baseUrl !== '' ? $baseUrl : 'https://api.openai.com/v1', '/');
    if (!str_contains($baseUrl, '/v1') && !str_ends_with($baseUrl, '/models')) {
        $baseUrl .= '/v1';
    }

    $endpoint = str_ends_with($baseUrl, '/models') ? $baseUrl : $baseUrl . '/models';

    $headers = [];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

    $decoded = ai_chatbot_http_json($provider, 'GET', $endpoint, $headers, null, 15);
    $models = [];
    foreach ((array)($decoded['data'] ?? []) as $model) {
        if (is_array($model)) {
            $id = trim((string)($model['id'] ?? ''));
            if ($id !== '') {
                $models[] = $id;
            }
        }
    }

    return ai_chatbot_unique_sorted_models($models);
}

/**
 * @return array<int,string>
 */
function ai_chatbot_fetch_gemini_models(string $baseUrl, string $apiKey): array
{
    if ($apiKey === '') {
        throw new ExternalServiceAuthenticationException('API key diperlukan untuk fetch senarai model Gemini.', 'gemini');
    }

    $baseUrl = rtrim($baseUrl !== '' ? $baseUrl : 'https://generativelanguage.googleapis.com/v1beta', '/');
    if (!str_contains($baseUrl, '/v1')) {
        $baseUrl .= '/v1beta';
    }

    $endpoint = str_ends_with($baseUrl, '/models')
        ? $baseUrl
        : $baseUrl . '/models';
    $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') . 'key=' . rawurlencode($apiKey);

    $decoded = ai_chatbot_http_json('gemini', 'GET', $endpoint, [], null, 15);
    $models = [];
    foreach ((array)($decoded['models'] ?? []) as $model) {
        if (!is_array($model)) {
            continue;
        }
        $methods = (array)($model['supportedGenerationMethods'] ?? []);
        if ($methods !== [] && !in_array('generateContent', $methods, true)) {
            continue;
        }
        $name = trim((string)($model['name'] ?? ''));
        $name = preg_replace('#^models/#', '', $name) ?? $name;
        if ($name !== '') {
            $models[] = $name;
        }
    }

    return ai_chatbot_unique_sorted_models($models);
}

/**
 * @return array<int,string>
 */
function ai_chatbot_fetch_anthropic_models(string $baseUrl, string $apiKey): array
{
    if ($apiKey === '') {
        throw new ExternalServiceAuthenticationException('API key diperlukan untuk fetch senarai model Anthropic.', 'anthropic');
    }

    $baseUrl = rtrim($baseUrl !== '' ? $baseUrl : 'https://api.anthropic.com/v1', '/');
    if (!str_contains($baseUrl, '/v1')) {
        $baseUrl .= '/v1';
    }

    $endpoint = str_ends_with($baseUrl, '/models') ? $baseUrl : $baseUrl . '/models';
    $decoded = ai_chatbot_http_json('anthropic', 'GET', $endpoint, [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ], null, 15);

    $models = [];
    foreach ((array)($decoded['data'] ?? []) as $model) {
        if (is_array($model)) {
            $id = trim((string)($model['id'] ?? ''));
            if ($id !== '') {
                $models[] = $id;
            }
        }
    }

    return ai_chatbot_unique_sorted_models($models);
}

/**
 * @param array<int,string> $headers
 * @param array<string,mixed>|null $payload
 * @return array<string,mixed>
 */
function ai_chatbot_http_json(string $provider, string $method, string $url, array $headers = [], ?array $payload = null, int $timeout = 15): array
{
    $headers[] = 'Accept: application/json';
    $client = new ExternalHttpClient($provider, $timeout);
    $response = $payload === null
        ? $client->request($method, $url, $headers, null, $timeout)
        : $client->postJson($url, $payload, $headers, $timeout);

    return ai_chatbot_decode_http_json($provider, $url, $response->body(), $response->statusCode());
}

/**
 * @return array<string,mixed>
 */
function ai_chatbot_decode_http_json(string $provider, string $endpoint, string $raw, int $status): array
{
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new ExternalServiceInvalidResponseException('Provider returned invalid JSON while fetching models.', $provider, $endpoint, $status);
    }

    if ($status >= 400) {
        $message = (string)($decoded['error']['message'] ?? $decoded['error'] ?? 'Provider returned an error while fetching models.');
        throw new ExternalServiceException($message, $provider, $endpoint, $status, 'provider_error', $status >= 500);
    }

    return $decoded;
}

/**
 * @param array<int,string> $models
 * @return array<int,string>
 */
function ai_chatbot_unique_sorted_models(array $models): array
{
    $models = array_values(array_unique(array_filter(array_map(
        static fn($model): string => trim((string)$model),
        $models
    ))));
    natcasesort($models);

    return array_values($models);
}
