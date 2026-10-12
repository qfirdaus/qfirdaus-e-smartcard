<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// setting/helper/number_helper.php
declare(strict_types=1);

/**
 * ✅ PHASE 3 FIX: Extract duplicate helper functions
 * 
 * Helper functions untuk number/zero checking
 * Reusable across all files
 */

/**
 * Check if value is zero or empty
 * 
 * @param mixed $v Value to check
 * @return bool True if value is null, empty string, or zero
 */
function isZeroOrEmpty(mixed $v): bool
{
    if ($v === null) return true;
    if (is_string($v)) {
        $v = trim($v);
    }
    if ($v === '') return true;
    if (!is_numeric($v)) return false;
    return ((float)$v) == 0.0;
}

/**
 * Format number to 2 decimal places
 * 
 * @param mixed $v Value to format
 * @return string Formatted number or empty string
 */
function fmt2(mixed $v): string
{
    if ($v === null) {
        return '';
    }
    if (is_string($v)) {
        $v = trim($v);
    }
    if ($v === '' || !is_numeric($v)) {
        return '';
    }
    return number_format((float)$v, 2, '.', '');
}






