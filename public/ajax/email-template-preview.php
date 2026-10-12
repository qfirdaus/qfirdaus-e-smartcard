<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/EmailPlaceholder.php';
require_once __DIR__ . '/../classes/EmailTemplateRenderService.php';

header('Content-Type: application/json; charset=utf-8');

const EMAIL_TEMPLATE_PREVIEW_MAX_SUBJECT_LENGTH = 255;
const EMAIL_TEMPLATE_PREVIEW_MAX_HTML_LENGTH = 50000;
const EMAIL_TEMPLATE_PREVIEW_MAX_TEXT_LENGTH = 20000;
const EMAIL_TEMPLATE_PREVIEW_MAX_JSON_BYTES = 50000;

function normalizeEmailTemplateJsonInput(string $value): string
{
    $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $normalized = str_replace("\xC2\xA0", ' ', $normalized);
    $normalized = str_replace(["\u{2018}", "\u{2019}"], "'", $normalized);
    $normalized = str_replace(["\u{201C}", "\u{201D}"], '"', $normalized);
    $normalized = preg_replace('/,\s*([}\]])/', '$1', $normalized) ?? $normalized;

    return trim($normalized);
}

/**
 * @return array<string,mixed>
 */
function emailTemplatePreviewPayload(): array
{
    $sampleVariables = [];
    $sampleInput = normalizeEmailTemplateJsonInput((string)($_POST['sample_variables'] ?? '{}'));
    if (strlen($sampleInput) > EMAIL_TEMPLATE_PREVIEW_MAX_JSON_BYTES) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_sample_json_too_large') ?: 'Sample variables JSON terlalu besar.'));
    }
    if ($sampleInput !== '') {
        $decoded = json_decode($sampleInput, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new InvalidArgumentException((string)(__('emailTemplate_error_sample_json_invalid') ?: 'Sample variables mesti dalam format JSON yang sah.'));
        }
        $sampleVariables = $decoded;
    }

    $subjectTemplate = trim((string)($_POST['subject_template'] ?? ''));
    $bodyHtml = trim((string)($_POST['body_html'] ?? ''));
    $bodyText = trim((string)($_POST['body_text'] ?? ''));

    if (mb_strlen($subjectTemplate) > EMAIL_TEMPLATE_PREVIEW_MAX_SUBJECT_LENGTH) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_subject_too_long') ?: 'Subjek template terlalu panjang.'));
    }
    if (strlen($bodyHtml) > EMAIL_TEMPLATE_PREVIEW_MAX_HTML_LENGTH) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_body_html_too_long') ?: 'Kandungan HTML template terlalu panjang.'));
    }
    if (strlen($bodyText) > EMAIL_TEMPLATE_PREVIEW_MAX_TEXT_LENGTH) {
        throw new InvalidArgumentException((string)(__('emailTemplate_error_body_text_too_long') ?: 'Kandungan text template terlalu panjang.'));
    }

    return [
        'subject_template' => $subjectTemplate,
        'body_html' => $bodyHtml,
        'body_text' => $bodyText,
        'sample_variables' => $sampleVariables,
    ];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonErrorResponse('Method not allowed', 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }

    if (!checkRateLimit('email_template_preview', 30, 60)) {
        jsonErrorResponse((string)(__('emailTemplate_error_preview_rate_limited') ?: 'Terlalu banyak permintaan preview. Sila tunggu sebentar dan cuba lagi.'), 429);
    }

    $pdo = Database::pdoMysql();
    ensureAjaxGroupManagePermission($pdo);

    $payload = emailTemplatePreviewPayload();
    if ($payload['subject_template'] === '' || $payload['body_html'] === '') {
        jsonErrorResponse((string)(__('emailTemplate_error_preview_required') ?: 'Subjek dan kandungan HTML diperlukan untuk preview.'), 422);
    }

    $placeholderModel = new EmailPlaceholder($pdo);
    $renderService = new EmailTemplateRenderService($placeholderModel);
    $rendered = $renderService->renderTemplate([
        'subject_template' => $payload['subject_template'],
        'body_html' => $payload['body_html'],
        'body_text' => $payload['body_text'],
    ], (array)$payload['sample_variables']);

    jsonSuccessResponse([
        'message' => (string)(__('emailTemplate_preview_success') ?: 'Preview template berjaya dijana.'),
        'preview' => [
            'subject' => (string)($rendered['subject'] ?? ''),
            'html' => (string)($rendered['html'] ?? ''),
            'text' => (string)($rendered['text'] ?? ''),
            'used_placeholders' => array_values((array)($rendered['used_placeholders'] ?? [])),
            'invalid_placeholders' => array_values((array)($rendered['invalid_placeholders'] ?? [])),
            'missing_placeholders' => array_values((array)($rendered['missing_placeholders'] ?? [])),
        ],
    ]);
} catch (InvalidArgumentException $e) {
    jsonErrorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('[email-template-preview] ' . $e->getMessage());
    jsonErrorResponse((string)(__('emailTemplate_error_preview_failed') ?: 'Ralat sistem semasa menjana preview template emel.'), 500);
}
