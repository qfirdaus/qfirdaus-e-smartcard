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

final class DatabasePreviewSanitizer
{
    public const MASKED_VALUE = '[MASKED]';
    public const MAX_STRING_LENGTH = 500;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, masked_columns: string[], truncated_values: int, binary_values: int}
     */
    public function sanitize(array $rows): array
    {
        $maskedColumns = [];
        $truncatedValues = 0;
        $binaryValues = 0;
        $safeRows = [];

        foreach ($rows as $row) {
            $safeRow = [];
            foreach ($row as $column => $value) {
                $columnName = (string)$column;
                if ($this->isSensitiveColumn($columnName)) {
                    $safeRow[$columnName] = $value === null ? null : self::MASKED_VALUE;
                    $maskedColumns[] = $columnName;
                    continue;
                }
                if (!is_string($value)) {
                    $safeRow[$columnName] = $value;
                    continue;
                }
                if ($this->isBinary($value)) {
                    $safeRow[$columnName] = sprintf('[BINARY %d bytes]', strlen($value));
                    $binaryValues++;
                    continue;
                }

                $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
                if ($length > self::MAX_STRING_LENGTH) {
                    $prefix = function_exists('mb_substr')
                        ? mb_substr($value, 0, self::MAX_STRING_LENGTH, 'UTF-8')
                        : substr($value, 0, self::MAX_STRING_LENGTH);
                    $safeRow[$columnName] = $prefix . sprintf('… [TRUNCATED %d chars]', $length);
                    $truncatedValues++;
                    continue;
                }
                $safeRow[$columnName] = $value;
            }
            $safeRows[] = $safeRow;
        }

        $maskedColumns = array_values(array_unique($maskedColumns));
        sort($maskedColumns);

        return [
            'rows' => $safeRows,
            'masked_columns' => $maskedColumns,
            'truncated_values' => $truncatedValues,
            'binary_values' => $binaryValues,
        ];
    }

    private function isSensitiveColumn(string $column): bool
    {
        $normalized = strtolower(trim($column));
        return preg_match(
            '/(^|_)(password|passwd|pass|pwd|secret|token|api_?key|private_?key|access_?key|auth(?:orization)?|credential|ciphertext|salt|otp|pin)($|_)/i',
            $normalized
        ) === 1;
    }

    private function isBinary(string $value): bool
    {
        return str_contains($value, "\0") || preg_match('//u', $value) !== 1;
    }
}
