<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *//**
 * Constants for Prestasi (Performance Appraisal) Module
 * 
 * This file contains all constants used across the prestasi module to avoid
 * magic numbers and hardcoded strings throughout the codebase.
 * 
 * @package Prestasi
 * @version 1.0
 */

if (defined('PRESTASI_CONSTANTS_INCLUDED')) return;
define('PRESTASI_CONSTANTS_INCLUDED', true);

// ============================================================================
// CACHE CONFIGURATION
// ============================================================================

/** Cache TTL in seconds (10 minutes) */
define('PRESTASI_CACHE_TTL', 600);

/** Cache key prefix for prestasi list */
define('PRESTASI_CACHE_KEY_PREFIX', 'senarai-prestasi:v2');

/** Cache file prefix for fallback file-based caching */
define('PRESTASI_CACHE_FILE_PREFIX', 'senarai-prestasi-cache-');

/** Compression level for gzip cache files (1-9, 5 is balanced) */
define('PRESTASI_CACHE_GZIP_LEVEL', 5);

// ============================================================================
// SECURITY & CSRF
// ============================================================================

/** CSRF token length in bytes (16 bytes = 32 hex characters) */
define('PRESTASI_CSRF_TOKEN_BYTES', 16);

/** Session key for CSRF token */
define('PRESTASI_SESSION_CSRF_TOKEN', 'csrf_token');

/** Session key for updated row ID */
define('PRESTASI_SESSION_UPDATED_ROW_ID', 'updated_row_id');

// ============================================================================
// AJAX & API ENDPOINTS
// ============================================================================

/** AJAX action: Save marks */
define('PRESTASI_AJAX_ACTION_SAVE', 'simpan_markah');

/** AJAX endpoint: Get table data */
define('PRESTASI_AJAX_ENDPOINT_TABLE', 'table');

/** AJAX endpoint: Get jabatan list */
define('PRESTASI_AJAX_ENDPOINT_JABATAN', 'jabatan');

/** AJAX endpoint: Get user jabatan */
define('PRESTASI_AJAX_ENDPOINT_MY_JABATAN', 'myJabatan');

/** AJAX endpoint: Get latest year */
define('PRESTASI_AJAX_ENDPOINT_LATEST_YEAR', 'latestYear');

/** HTTP header for AJAX requests */
define('PRESTASI_HTTP_X_REQUESTED_WITH', 'X-Requested-With');

/** HTTP header for CSRF token */
define('PRESTASI_HTTP_X_CSRF_TOKEN', 'X-CSRF-Token');

/** HTTP header to skip loader */
define('PRESTASI_HTTP_X_NO_LOADER', 'X-No-Loader');

// ============================================================================
// HTTP STATUS CODES
// ============================================================================

/** HTTP 400 Bad Request */
define('PRESTASI_HTTP_BAD_REQUEST', 400);

/** HTTP 500 Internal Server Error */
define('PRESTASI_HTTP_INTERNAL_ERROR', 500);

// ============================================================================
// CONTENT TYPES
// ============================================================================

/** JSON content type header */
define('PRESTASI_CONTENT_TYPE_JSON', 'application/json; charset=utf-8');

/** JSON content type check string */
define('PRESTASI_CONTENT_TYPE_JSON_CHECK', 'application/json');

// ============================================================================
// JSON ENCODING FLAGS
// ============================================================================

/** JSON encoding flags (unicode + slashes preserved) */
define('PRESTASI_JSON_FLAGS', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ============================================================================
// USER ROLES (ID-BASED ONLY)
// ============================================================================
// SECURITY CRITICAL – DO NOT MODIFY: role IDs are used by permission gates

/** Role ID: System Administrator (set via env if available) */
$__prestasi_role_id_adm_sa = $_ENV['PRESTASI_ROLE_ID_ADM_SA'] ?? getenv('PRESTASI_ROLE_ID_ADM_SA');
define('PRESTASI_ROLE_ID_ADM_SA', (int)(($__prestasi_role_id_adm_sa !== null && $__prestasi_role_id_adm_sa !== false && $__prestasi_role_id_adm_sa !== '') ? $__prestasi_role_id_adm_sa : 1));

// GOVERNANCE CRITICAL – DO NOT MODIFY: feature flag controls admin-only exposure
// OPTIONAL: System Resources panel (feature flag)
$__enable_system_resources = $_ENV['ENABLE_SYSTEM_RESOURCES'] ?? getenv('ENABLE_SYSTEM_RESOURCES');
define('ENABLE_SYSTEM_RESOURCES', (bool)(($__enable_system_resources !== null && $__enable_system_resources !== false && $__enable_system_resources !== '') ? $__enable_system_resources : false)); // OPTIONAL (default OFF)

/** Role ID: Human Resources (set via env if available) */
define('PRESTASI_ROLE_ID_ADM_HR', (int)($_ENV['PRESTASI_ROLE_ID_ADM_HR'] ?? getenv('PRESTASI_ROLE_ID_ADM_HR') ?? 0));

/** Role ID: Finance/Accounts (set via env if available) */
define('PRESTASI_ROLE_ID_ADM_KE', (int)($_ENV['PRESTASI_ROLE_ID_ADM_KE'] ?? getenv('PRESTASI_ROLE_ID_ADM_KE') ?? 0));

/**
 * Super Admin role code used for access checks.
 * Can be overridden with environment variable PRESTASI_ROLE_KOD_ADM_SA.
 */
if (!defined('PRESTASI_ROLE_ADM_SA')) {
    define('PRESTASI_ROLE_ADM_SA', 'ADM-SA');
}
if (!defined('PRESTASI_ROLE_ADM_HR')) {
    define('PRESTASI_ROLE_ADM_HR', 'ADM-HR');
}
if (!defined('PRESTASI_ROLE_ADM_KE')) {
    define('PRESTASI_ROLE_ADM_KE', 'ADM-KE');
}
if (!defined('PRESTASI_ROLE_KOD_ADM_SA')) {
    $roleKodSa = $_ENV['PRESTASI_ROLE_KOD_ADM_SA'] ?? getenv('PRESTASI_ROLE_KOD_ADM_SA');
    define('PRESTASI_ROLE_KOD_ADM_SA', (string)(is_string($roleKodSa) && trim($roleKodSa) !== '' ? trim($roleKodSa) : PRESTASI_ROLE_ADM_SA));
}

/**
 * Protected staff IDs that cannot be deleted from user management.
 * Keep values in display format for readability; helper logic normalizes them.
 */
if (!defined('PRESTASI_PROTECTED_STAFF_IDS')) {
    define('PRESTASI_PROTECTED_STAFF_IDS', ['0530-09']);
}



// ============================================================================
// DATA FORMATTING
// ============================================================================

/** Decimal places for marks (scores) */
define('PRESTASI_DECIMAL_PLACES', 2);

/** Month padding length */
define('PRESTASI_MONTH_PAD_LENGTH', 2);

/** Staff ID format: left part length */
define('PRESTASI_STAFF_ID_LEFT_LENGTH', 4);

/** Staff ID format: right part length */
define('PRESTASI_STAFF_ID_RIGHT_LENGTH', 2);

/** Default language code */
define('PRESTASI_DEFAULT_LANG', 'ms');

/** Default year format (if not provided, use current year) */
define('PRESTASI_DEFAULT_YEAR_FORMAT', 'Y');

// ============================================================================
// VALIDATION CONSTRAINTS
// ============================================================================

/** Minimum markah (score) value */
define('PRESTASI_MARKAH_MIN', 0);

/** Maximum markah (score) value */
define('PRESTASI_MARKAH_MAX', 100);

// ============================================================================
// ERROR LOG PREFIXES
// ============================================================================

/** Error log prefix for AJAX save errors */
define('PRESTASI_LOG_PREFIX_AJAX_SAVE', '[senarai-prestasi] AJAX save error: ');

/** Error log prefix for legacy POST errors */
define('PRESTASI_LOG_PREFIX_LEGACY_POST', '[senarai-prestasi] Legacy POST error: ');

/** Error log prefix for AJAX table errors */
define('PRESTASI_LOG_PREFIX_AJAX_TABLE', '[senarai-prestasi] AJAX table error: ');

// ============================================================================
// TABLE/DISPLAY CONSTANTS
// ============================================================================

/** Table column index for status (0-based: 6 = 7th column) */
define('PRESTASI_TABLE_COL_STATUS', 6);

/** Number of table columns (for colspan in empty state) */
define('PRESTASI_TABLE_COL_COUNT', 8);

// ============================================================================
// CACHE KEY COMPONENTS
// ============================================================================

/** Cache key component: tahun */
define('PRESTASI_CACHE_KEY_TAHUN', 'tahun');

/** Cache key component: dept */
define('PRESTASI_CACHE_KEY_DEPT', 'dept');

/** Cache key component: lang */
define('PRESTASI_CACHE_KEY_LANG', 'lang');



