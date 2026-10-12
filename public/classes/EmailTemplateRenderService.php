<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/EmailPlaceholder.php';

final class EmailTemplateRenderService
{
    private EmailPlaceholder $emailPlaceholderModel;

    public function __construct(EmailPlaceholder $emailPlaceholderModel)
    {
        $this->emailPlaceholderModel = $emailPlaceholderModel;
    }

    /**
     * @return array<int,string>
     */
    public function extractPlaceholders(string $content): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $content, $matches);
        $keys = array_map(
            static fn($value): string => trim((string)$value),
            $matches[1] ?? []
        );

        $keys = array_values(array_unique(array_filter($keys, static fn($value): bool => $value !== '')));
        sort($keys);

        return $keys;
    }

    /**
     * @param array<int,string> $placeholders
     * @param array<int,string> $allowedKeys
     * @return array{valid:array<int,string>,invalid:array<int,string>}
     */
    public function validatePlaceholders(array $placeholders, array $allowedKeys): array
    {
        $allowedMap = array_fill_keys($allowedKeys, true);
        $valid = [];
        $invalid = [];

        foreach ($placeholders as $placeholder) {
            if (isset($allowedMap[$placeholder])) {
                $valid[] = $placeholder;
                continue;
            }
            $invalid[] = $placeholder;
        }

        return [
            'valid' => array_values(array_unique($valid)),
            'invalid' => array_values(array_unique($invalid)),
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @param array<string,mixed> $variables
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function renderTemplate(array $template, array $variables = [], array $context = []): array
    {
        $subjectTemplate = (string)($template['f_subjectTemplate'] ?? $template['subject_template'] ?? '');
        $bodyHtmlTemplate = (string)($template['f_bodyHtml'] ?? $template['body_html'] ?? '');
        $bodyTextTemplate = (string)($template['f_bodyText'] ?? $template['body_text'] ?? '');

        $defaultVariables = $this->getDefaultVariables($context);
        $mergedVariables = array_merge($defaultVariables, $variables);

        $subjectPlaceholders = $this->extractPlaceholders($subjectTemplate);
        $htmlPlaceholders = $this->extractPlaceholders($bodyHtmlTemplate);
        $textPlaceholders = $this->extractPlaceholders($bodyTextTemplate);
        $usedPlaceholders = array_values(array_unique(array_merge(
            $subjectPlaceholders,
            $htmlPlaceholders,
            $textPlaceholders
        )));
        sort($usedPlaceholders);

        $allowedPlaceholders = $this->getAllowedPlaceholderKeys($mergedVariables);
        $validation = $this->validatePlaceholders($usedPlaceholders, $allowedPlaceholders);

        $subject = $this->replacePlaceholders($subjectTemplate, $mergedVariables);
        $bodyHtml = $this->replacePlaceholders($bodyHtmlTemplate, $mergedVariables);
        $bodyText = $bodyTextTemplate !== ''
            ? $this->replacePlaceholders($bodyTextTemplate, $mergedVariables)
            : $this->buildPlainTextFromHtml($bodyHtml);

        $wrappedHtml = $this->wrapHtml($bodyHtml, [
            'title' => $subject,
            'preheader' => $context['preheader'] ?? $subject,
            'system_name' => $mergedVariables['system_name'] ?? '',
            'organization_name' => $mergedVariables['organization_name'] ?? '',
            'support_email' => $mergedVariables['support_email'] ?? '',
            'footer_note' => $context['footer_note'] ?? '',
        ]);

        $wrappedText = $this->wrapText($bodyText, [
            'title' => $subject,
            'system_name' => $mergedVariables['system_name'] ?? '',
            'support_email' => $mergedVariables['support_email'] ?? '',
            'footer_note' => $context['footer_note'] ?? '',
        ]);

        $missingPlaceholders = [];
        foreach ($usedPlaceholders as $placeholderKey) {
            if (!array_key_exists($placeholderKey, $mergedVariables)) {
                $missingPlaceholders[] = $placeholderKey;
            }
        }

        return [
            'subject' => $subject,
            'html' => $wrappedHtml,
            'text' => $wrappedText,
            'raw_html' => $bodyHtml,
            'raw_text' => $bodyText,
            'used_placeholders' => $usedPlaceholders,
            'valid_placeholders' => $validation['valid'],
            'invalid_placeholders' => $validation['invalid'],
            'missing_placeholders' => array_values(array_unique($missingPlaceholders)),
            'variables' => $mergedVariables,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function getDefaultVariables(array $context = []): array
    {
        $timezone = (string)($context['timezone'] ?? date_default_timezone_get() ?: 'Asia/Kuala_Lumpur');
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone));

        $organizationName = $this->configValue('organization.name', 'Base Organization');
        $organizationShort = $this->configValue('organization.short', '');
        $systemName = $this->configValue('system.name', 'Base System');
        $supportEmail = $this->configValue('system.support', 'support@example.com');
        $senderName = $this->configValue('mail.from_name', 'System Administrator');

        return [
            'recipient_name' => (string)($context['recipient_name'] ?? ''),
            'recipient_email' => (string)($context['recipient_email'] ?? ''),
            'recipient_role' => (string)($context['recipient_role'] ?? ''),
            'recipient_position' => (string)($context['recipient_position'] ?? ''),
            'recipient_department' => (string)($context['recipient_department'] ?? ''),
            'organization_name' => $organizationName,
            'organization_short' => $organizationShort,
            'system_name' => $systemName,
            'support_email' => $supportEmail,
            'sender_name' => (string)($context['sender_name'] ?? $senderName),
            'current_date' => $now->format('d F Y'),
            'current_datetime' => $now->format('d F Y h:i A'),
            'current_year' => $now->format('Y'),
        ];
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function wrapHtml(string $bodyContent, array $meta = []): string
    {
        $templatePath = __DIR__ . '/../templates/mail/layout-default.html.php';
        if (!is_file($templatePath)) {
            return $bodyContent;
        }

        $contentHtml = $bodyContent;
        $title = (string)($meta['title'] ?? '');
        $preheader = (string)($meta['preheader'] ?? '');
        $systemName = (string)($meta['system_name'] ?? '');
        $organizationName = (string)($meta['organization_name'] ?? '');
        $supportEmail = (string)($meta['support_email'] ?? '');
        $footerNote = (string)($meta['footer_note'] ?? '');

        ob_start();
        require $templatePath;
        return (string)ob_get_clean();
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function wrapText(string $bodyText, array $meta = []): string
    {
        $templatePath = __DIR__ . '/../templates/mail/layout-default.txt.php';
        if (!is_file($templatePath)) {
            return $bodyText;
        }

        $contentText = $bodyText;
        $title = (string)($meta['title'] ?? '');
        $systemName = (string)($meta['system_name'] ?? '');
        $supportEmail = (string)($meta['support_email'] ?? '');
        $footerNote = (string)($meta['footer_note'] ?? '');

        ob_start();
        require $templatePath;
        return (string)ob_get_clean();
    }

    /**
     * @param array<string,mixed> $variables
     */
    private function replacePlaceholders(string $content, array $variables): string
    {
        if ($content === '') {
            return '';
        }

        return (string)preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $matches) use ($variables): string {
                $key = trim((string)($matches[1] ?? ''));
                if ($key === '') {
                    return '';
                }

                if (!array_key_exists($key, $variables)) {
                    return '';
                }

                $value = $variables[$key];
                if (is_scalar($value) || $value === null) {
                    return trim((string)$value);
                }

                return '';
            },
            $content
        );
    }

    /**
     * @param array<string,mixed> $variables
     * @return array<int,string>
     */
    private function getAllowedPlaceholderKeys(array $variables): array
    {
        $keys = array_keys($variables);

        try {
            $placeholderRows = $this->emailPlaceholderModel->getAllActive();
            foreach ($placeholderRows as $row) {
                $placeholderKey = trim((string)($row['f_placeholderKey'] ?? ''));
                if ($placeholderKey !== '') {
                    $keys[] = $placeholderKey;
                }
            }
        } catch (Throwable $e) {
            // Fall back to variable keys only when DB access fails.
        }

        $keys = array_values(array_unique(array_filter($keys, static fn($value): bool => $value !== '')));
        sort($keys);

        return $keys;
    }

    private function buildPlainTextFromHtml(string $html): string
    {
        $normalized = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);
        $stripped = strip_tags($normalized);
        $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = preg_replace("/\n{3,}/", "\n\n", $stripped);

        return trim((string)$stripped);
    }

    private function configValue(string $key, string $default = ''): string
    {
        if (function_exists('app_config')) {
            try {
                return (string)app_config($key, $default);
            } catch (Throwable $e) {
                return $default;
            }
        }

        return $default;
    }
}
