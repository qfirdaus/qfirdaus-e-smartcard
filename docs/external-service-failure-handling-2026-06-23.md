# External Service Failure Handling Standard

Date: 2026-06-23

This standard defines how IQS Framework Core classifies failures from external providers so they are not incorrectly reported as internal application/server failures.

## Goal

External integrations must not be treated as HTTP 500 by default.

Examples of external integrations:

- AI providers: OpenAI, Gemini, OpenRouter, Anthropic, Grok, Groq, Ollama
- SMTP and mail relays
- Payment gateways
- SSO providers
- Third-party REST APIs
- Future HTTP/API integrations

Internal application failures still use the existing internal-error path.

Examples of internal failures:

- PHP fatal/coding error
- Missing framework dependency
- Database failure
- Invalid internal state
- Broken template/registry/config structure

## Exception Types

Framework-aware exceptions live under `public/classes/`.

Core classes:

- `FrameworkException`
- `ApplicationException`
- `ValidationException`
- `ExternalServiceException`
- `ExternalServiceAuthenticationException`
- `ExternalServiceRateLimitException`
- `ExternalServiceTimeoutException`
- `ExternalServiceUnavailableException`
- `ExternalServiceInvalidResponseException`

Use `ExternalServiceException` or one of its subclasses only for failures outside the application boundary.

Do not use it for local coding bugs, invalid local state, missing framework files, or database failures.

## HTTP Status Mapping

`FrameworkExceptionHandler::httpStatusFor()` owns the standard mapping.

Current mapping:

- Validation failure: `422`
- External authentication failure: `424`
- External rate limit: `429`
- External timeout: `504`
- External invalid response: `502`
- External provider `5xx` or unavailable: `503`
- Other application/internal failure: `500`

Endpoint handlers should use `jsonExceptionResponse()` from `public/ajax/_helpers.php` when returning AJAX JSON.

## Logging Standard

External incidents are logged with `[ExternalService]`.

Example:

```text
[ExternalService] Provider=OpenAI Category=rate_limit Retryable=true Endpoint=https://api.openai.com/v1/chat/completions HTTPStatus=429 Message=Rate limit exceeded
```

The logger redacts common secrets:

- `Authorization: Bearer ...`
- `key=...`
- `api_key=...`
- `token=...`
- `access_token=...`
- `password=...`

Do not add raw API keys, SMTP passwords, cookies, CSRF tokens, or full AI prompts into external-service log context.

## External HTTP Client

Use `ExternalHttpClient` for new outbound HTTP calls.

Example:

```php
$client = new ExternalHttpClient('PaymentGateway', 15);
$response = $client->postJson(
    'https://provider.example/api/charge',
    ['amount' => 1000],
    ['Authorization' => 'Bearer ' . $apiKey],
    15
);

$data = $response->json();
```

The client throws external-service exceptions for:

- HTTP `401` or `403`
- HTTP `429`
- HTTP `5xx`
- timeout
- DNS/connection/transport failure
- missing HTTP status

The client returns `ExternalHttpResponse` for successful responses.

If the provider returns HTTP `2xx` with invalid JSON, the caller should throw `ExternalServiceInvalidResponseException`.

## AJAX Endpoint Pattern

Use this catch order:

```php
try {
    // endpoint logic
} catch (InvalidArgumentException|ValidationException $e) {
    jsonErrorResponse($e->getMessage(), 422);
} catch (ExternalServiceException $e) {
    jsonExceptionResponse($e, 'Perkhidmatan luaran tidak tersedia buat masa ini.', [
        'endpoint' => 'example-endpoint',
    ]);
} catch (Throwable $e) {
    error_log('[example-endpoint] ' . $e->getMessage());
    jsonErrorResponse('Ralat sistem semasa memproses permintaan.', 500);
}
```

If the endpoint already logs usage/audit metadata before returning a response, use:

```php
jsonExceptionResponse($e, 'Perkhidmatan luaran tidak tersedia buat masa ini.', [
    '_skip_log' => true,
]);
```

Only use `_skip_log` when the same exception has already been logged through `FrameworkExceptionHandler::log()`.

## SMTP Pattern

`Mailer::send()` remains backward compatible and returns `bool`.

When it returns `false`, HTTP-facing callers should use:

```php
jsonExceptionResponse(
    $mailer->lastFailureAsExternalServiceException('Emel tidak berjaya dihantar.'),
    $mailer->getLastError() ?: 'Emel tidak berjaya dihantar.',
    ['endpoint' => 'email-example']
);
```

Service-layer code may throw:

```php
throw $mailer->lastFailureAsExternalServiceException('Email could not be sent.');
```

This lets caller endpoints decide the final public response.

## Migration Checklist

For existing integrations:

1. Identify outbound calls: `curl_*`, `file_get_contents($url)`, `stream_context_create`, SDK calls, SMTP, SSO, payment APIs.
2. Decide whether failure is external or internal.
3. Replace duplicated HTTP transport code with `ExternalHttpClient` where practical.
4. Convert provider/network failures to `ExternalServiceException` subclasses.
5. Keep local validation as `InvalidArgumentException` or `ValidationException`.
6. Keep coding bugs, missing dependencies, database failures, and corrupt local state as internal failures.
7. Use `jsonExceptionResponse()` in AJAX endpoints.
8. Log external incidents with safe context only.
9. Test invalid API key, timeout, provider `429`, provider `5xx`, invalid JSON, and success.

## Current Core Adoption

Implemented in core:

- AI provider chat calls under `public/classes/AiChatbotProviders/`
- AI model fetch endpoint `public/ajax/ai-chatbot-models.php`
- AI message endpoint `public/ajax/ai-chatbot-message.php`
- SMTP wrapper `public/classes/Mailer.php`
- Email template test send endpoint
- Email submit endpoint
- Email test endpoint external-service logging

## Backward Compatibility

Existing projects are not required to change immediately.

The framework keeps:

- `Mailer::send()` returning `bool`
- `jsonErrorResponse()` behavior unchanged
- native `Throwable`/`RuntimeException` behavior as HTTP 500 unless an endpoint opts into `jsonExceptionResponse()`

New integrations should use the new exception and HTTP client pattern from the start.

## Risk Notes

Do not over-classify failures as external.

These should remain internal/application failures:

- JSON encoding of a local request payload fails
- provider class has a coding bug
- required local class/file is missing
- database connection/query fails
- framework config structure is corrupt

These should be external-service failures:

- provider returns `401`, `403`, `429`, `5xx`
- timeout
- DNS failure
- connection refused
- SSL/TLS/certificate failure
- SMTP authentication failure
- provider returns malformed JSON for an otherwise successful HTTP response

