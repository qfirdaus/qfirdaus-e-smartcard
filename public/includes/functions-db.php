<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// includes/functions-db.php
// ======================================
// ✅ Funksi Database Tambahan - e-Prestasi
// - Support Sybase: ehrmdb, ehrmdb_dev
// - Auto pilih DSN vs DBLIB ikut driver tersedia
// - Fallback + ping connection
// - Compat untuk PHP lama (tanpa str_ends_with)
// ======================================

declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Config.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
require_once __DIR__ . '/../setting/helper/config_helper.php';

if (!function_exists('sybase_normalize_allowed_value')) {
    function sybase_normalize_allowed_value($value, array $allowed, string $default): string {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, $allowed, true) ? $normalized : $default;
    }
}

/* ----------------------------------------------------
 * 🔧 Polyfill: str_ends_with (PHP < 8)
 * ---------------------------------------------------- */
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

/* ----------------------------------------------------
 * 🔐 HELPER TRANSAKSI (ODBC-safe untuk SAP ASE)
 * - ODBC (Windows/ASE): guna T-SQL BEGIN/COMMIT/ROLLBACK
 * - Selain ODBC (cth dblib): guna transaksi PDO biasa
 * ---------------------------------------------------- */
if (!function_exists('txBegin')) {
    function txBegin(\PDO $pdo) /*: void*/ { // (avoid 'void' for PHP<7.1)
        $drv = strtolower((string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        if ($drv === 'odbc') {
            $pdo->exec('BEGIN TRAN');
        } else {
            if (!$pdo->inTransaction()) { $pdo->beginTransaction(); }
        }
    }
}
if (!function_exists('txCommit')) {
    function txCommit(\PDO $pdo) /*: void*/ {
        $drv = strtolower((string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        if ($drv === 'odbc') {
            $pdo->exec('COMMIT TRAN');
        } else {
            if ($pdo->inTransaction()) { $pdo->commit(); }
        }
    }
}
if (!function_exists('txRollback')) {
    function txRollback(\PDO $pdo) /*: void*/ {
        $drv = strtolower((string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        if ($drv === 'odbc') {
            $pdo->exec('ROLLBACK TRAN');
        } else {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
        }
    }
}

if (!function_exists('sybase_resolve_driver_key')) {
    function sybase_resolve_driver_key(string $baseName): string {
        $drivers  = \PDO::getAvailableDrivers();
        $hasOdbc  = in_array('odbc',  $drivers, true);
        $hasDblib = in_array('dblib', $drivers, true);
        $configs = require __DIR__ . '/../configuration/db_config.php';

        if (str_ends_with($baseName, '_dsn') || str_ends_with($baseName, '_dblib')) {
            return $baseName;
        }

        $prefer = (PHP_OS_FAMILY === 'Windows') ? 'dsn' : 'dblib';
        $primary = $baseName . ($prefer === 'dsn' ? '_dsn' : '_dblib');
        $alternate = $baseName . ($prefer === 'dsn' ? '_dblib' : '_dsn');

        if ($prefer === 'dsn' && !$hasOdbc) {
            $primary = $alternate;
        }
        if ($prefer === 'dblib' && !$hasDblib) {
            $primary = $alternate;
        }

        if (isset($configs[$primary])) {
            return $primary;
        }
        if (isset($configs[$alternate])) {
            return $alternate;
        }

        return $primary;
    }
}

if (!function_exists('get_sybase_environment')) {
    function get_sybase_environment(): string {
        if (defined('SYBASE_ENVIRONMENT')) {
            return sybase_normalize_allowed_value(
                (string)SYBASE_ENVIRONMENT,
                SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS,
                SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT
            );
        }

        try {
            $pdo = Database::getInstance('mysql')->getConnection();
            $config = new Config($pdo);
            $value = $config->getSybaseEnvironment(SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT);
            return sybase_normalize_allowed_value(
                $value,
                SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS,
                SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT
            );
        } catch (\Throwable $e) {
            // ignore and fallback
        }

        return SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT;
    }
}

if (!function_exists('get_main_mysql_environment')) {
    function get_main_mysql_environment(): string {
        if (defined('MAIN_DB_ENVIRONMENT')) {
            return sybase_normalize_allowed_value(
                (string)MAIN_DB_ENVIRONMENT,
                SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS,
                SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT
            );
        }

        try {
            $pdo = Database::getInstance('mysql')->getConnection();
            $config = new Config($pdo);
            $value = $config->getMainDbEnvironment(SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT);
            return sybase_normalize_allowed_value(
                $value,
                SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS,
                SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT
            );
        } catch (\Throwable $e) {
            // ignore and fallback
        }

        return SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT;
    }
}

if (!function_exists('get_sybase_operational_mode')) {
    function get_sybase_operational_mode(): string {
        if (defined('SYBASE_OPERATIONAL_MODE')) {
            return sybase_normalize_allowed_value(
                (string)SYBASE_OPERATIONAL_MODE,
                SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES,
                SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE
            );
        }

        try {
            $pdo = Database::getInstance('mysql')->getConnection();
            $config = new Config($pdo);
            $value = $config->getSybaseOperationalMode(SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE);
            return sybase_normalize_allowed_value(
                $value,
                SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES,
                SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE
            );
        } catch (\Throwable $e) {
            // ignore and fallback
        }

        return SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE;
    }
}

if (!function_exists('is_student_mode_enabled')) {
    function is_student_mode_enabled(): bool {
        return get_sybase_operational_mode() === 'staff_student';
    }
}

if (!function_exists('get_auth_policy_defaults')) {
    function get_auth_policy_defaults(): array {
        return [
            'maintenance_mode' => false,
            'login_enable_staf' => true,
            'login_enable_pelajar' => true,
            'login_enable_umum' => true,
            'sso_enabled' => false,
            'sso_mode' => 'MANUAL',
            'sso_hybrid_staf' => 'SSO',
            'sso_hybrid_pelajar' => 'SSO',
            'sso_hybrid_umum' => 'MANUAL',
            'auto_provision_staf_sso' => false,
            'auto_provision_pelajar_sso' => false,
            'default_group_staff_code' => 'ADM-STAF',
            'default_group_student_code' => 'ADM-STUDENT',
            'password_min_length' => 8,
            'password_expiry_days' => 90,
            'password_history_count' => 5,
            'password_reset_token_minutes' => 30,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_number' => true,
            'password_require_symbol' => false,
            'password_block_loginid_variants' => true,
            'login_max_attempts' => 3,
            'login_lock_seconds' => 60,
            'login_identifier_ip_max_attempts' => 5,
            'login_identifier_ip_lock_seconds' => 300,
            'login_ip_max_attempts' => 10,
            'login_ip_lock_seconds' => 300,
        ];
    }
  }

if (!function_exists('get_auth_policy_raw_config')) {
    function get_auth_policy_raw_config(): array {
        $defaults = get_auth_policy_defaults();

        return [
            'maintenance_mode' => app_config('auth.maintenance_mode', $defaults['maintenance_mode']),
            'login_enable_staf' => app_config('auth.login_enable_staf', $defaults['login_enable_staf']),
            'login_enable_pelajar' => app_config('auth.login_enable_pelajar', $defaults['login_enable_pelajar']),
            'login_enable_umum' => app_config('auth.login_enable_umum', $defaults['login_enable_umum']),
            'sso_enabled' => app_config('auth.sso_enabled', $defaults['sso_enabled']),
            'sso_mode' => app_config('auth.sso_mode', $defaults['sso_mode']),
            'sso_hybrid_staf' => app_config('auth.sso_hybrid_staf', $defaults['sso_hybrid_staf']),
            'sso_hybrid_pelajar' => app_config('auth.sso_hybrid_pelajar', $defaults['sso_hybrid_pelajar']),
            'sso_hybrid_umum' => app_config('auth.sso_hybrid_umum', $defaults['sso_hybrid_umum']),
            'auto_provision_staf_sso' => app_config('auth.auto_provision_staf_sso', $defaults['auto_provision_staf_sso']),
            'auto_provision_pelajar_sso' => app_config('auth.auto_provision_pelajar_sso', $defaults['auto_provision_pelajar_sso']),
            'default_group_staff_code' => app_config('auth.default_group_staff_code', $defaults['default_group_staff_code']),
            'default_group_student_code' => app_config('auth.default_group_student_code', $defaults['default_group_student_code']),
            'password_min_length' => app_config('auth.password_min_length', $defaults['password_min_length']),
            'password_expiry_days' => app_config('auth.password_expiry_days', $defaults['password_expiry_days']),
            'password_history_count' => app_config('auth.password_history_count', $defaults['password_history_count']),
            'password_reset_token_minutes' => app_config('auth.password_reset_token_minutes', $defaults['password_reset_token_minutes']),
            'password_require_uppercase' => app_config('auth.password_require_uppercase', $defaults['password_require_uppercase']),
            'password_require_lowercase' => app_config('auth.password_require_lowercase', $defaults['password_require_lowercase']),
            'password_require_number' => app_config('auth.password_require_number', $defaults['password_require_number']),
            'password_require_symbol' => app_config('auth.password_require_symbol', $defaults['password_require_symbol']),
            'password_block_loginid_variants' => app_config('auth.password_block_loginid_variants', $defaults['password_block_loginid_variants']),
            'login_max_attempts' => app_config('auth.login_max_attempts', $defaults['login_max_attempts']),
            'login_lock_seconds' => app_config('auth.login_lock_seconds', $defaults['login_lock_seconds']),
            'login_identifier_ip_max_attempts' => app_config('auth.login_identifier_ip_max_attempts', $defaults['login_identifier_ip_max_attempts']),
            'login_identifier_ip_lock_seconds' => app_config('auth.login_identifier_ip_lock_seconds', $defaults['login_identifier_ip_lock_seconds']),
            'login_ip_max_attempts' => app_config('auth.login_ip_max_attempts', $defaults['login_ip_max_attempts']),
            'login_ip_lock_seconds' => app_config('auth.login_ip_lock_seconds', $defaults['login_ip_lock_seconds']),
        ];
    }
  }

if (!function_exists('auth_normalize_bool')) {
    function auth_normalize_bool($value, bool $default = false): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }

        $truthy = ['1', 'true', 'on', 'yes'];
        $falsy = ['0', 'false', 'off', 'no'];

        if (in_array($normalized, $truthy, true)) {
            return true;
        }
        if (in_array($normalized, $falsy, true)) {
            return false;
        }

        return $default;
    }
}

  if (!function_exists('auth_raw_bool_is_valid')) {
      function auth_raw_bool_is_valid($value): bool {
        if (is_bool($value)) {
            return true;
        }
        if (is_int($value) || is_float($value)) {
            return in_array((int)$value, [0, 1], true);
        }
        if ($value === null) {
            return true;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, ['1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'], true);
      }
  }

  if (!function_exists('auth_normalize_int')) {
      function auth_normalize_int($value, int $default, int $min, int $max): int {
          if (is_int($value) || is_float($value) || (is_string($value) && trim($value) !== '' && is_numeric($value))) {
              $normalized = (int)$value;
              if ($normalized < $min) {
                  return $min;
              }
              if ($normalized > $max) {
                  return $max;
              }
              return $normalized;
          }

          return $default;
      }
  }

  if (!function_exists('auth_raw_int_is_valid')) {
      function auth_raw_int_is_valid($value, int $min, int $max): bool {
          if ($value === null || (is_string($value) && trim($value) === '')) {
              return true;
          }
          if (!(is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value))))) {
              return false;
          }

          $normalized = (int)$value;
          return $normalized >= $min && $normalized <= $max;
      }
  }

if (!function_exists('auth_normalize_sso_mode')) {
    function auth_normalize_sso_mode($value, string $default = 'MANUAL'): string {
        $normalized = strtoupper(trim((string)$value));
        return in_array($normalized, ['ALL', 'MANUAL', 'HYBRID'], true) ? $normalized : $default;
    }
}

if (!function_exists('auth_normalize_hybrid_mode')) {
    function auth_normalize_hybrid_mode($value, string $default = 'MANUAL'): string {
        $normalized = strtoupper(trim((string)$value));
        return in_array($normalized, ['SSO', 'MANUAL'], true) ? $normalized : $default;
    }
}

if (!function_exists('auth_normalize_group_code')) {
    function auth_normalize_group_code($value, string $default = ''): string {
        $normalized = strtoupper(trim((string)$value));
        return $normalized !== '' ? $normalized : strtoupper(trim($default));
    }
}

if (!function_exists('validate_auth_policy_raw_config')) {
    function validate_auth_policy_raw_config(array $raw): array {
        $warnings = [];
        $errors = [];

        foreach (['maintenance_mode', 'login_enable_staf', 'login_enable_pelajar', 'login_enable_umum', 'sso_enabled', 'auto_provision_staf_sso', 'auto_provision_pelajar_sso', 'password_require_uppercase', 'password_require_lowercase', 'password_require_number', 'password_require_symbol', 'password_block_loginid_variants'] as $boolKey) {
            if (!auth_raw_bool_is_valid($raw[$boolKey] ?? null)) {
                $errors[] = sprintf('Invalid boolean value for %s.', $boolKey);
            }
        }

        $rawSsoMode = strtoupper(trim((string)($raw['sso_mode'] ?? '')));
        if ($rawSsoMode !== '' && !in_array($rawSsoMode, ['ALL', 'MANUAL', 'HYBRID'], true)) {
            $errors[] = 'Invalid value for sso_mode.';
        }

          foreach (['sso_hybrid_staf', 'sso_hybrid_pelajar', 'sso_hybrid_umum'] as $hybridKey) {
              $rawHybrid = strtoupper(trim((string)($raw[$hybridKey] ?? '')));
              if ($rawHybrid !== '' && !in_array($rawHybrid, ['SSO', 'MANUAL'], true)) {
                  $errors[] = sprintf('Invalid value for %s.', $hybridKey);
              }
          }

          $integerRanges = [
              'password_min_length' => [8, 128],
              'password_expiry_days' => [1, 365],
              'password_history_count' => [0, 24],
              'password_reset_token_minutes' => [5, 180],
              'login_max_attempts' => [1, 10],
              'login_lock_seconds' => [30, 3600],
              'login_identifier_ip_max_attempts' => [1, 20],
              'login_identifier_ip_lock_seconds' => [30, 3600],
              'login_ip_max_attempts' => [1, 50],
              'login_ip_lock_seconds' => [30, 3600],
          ];
          foreach ($integerRanges as $intKey => [$min, $max]) {
              if (!auth_raw_int_is_valid($raw[$intKey] ?? null, $min, $max)) {
                  $errors[] = sprintf('Invalid integer value for %s.', $intKey);
              }
          }

        $normalizedSsoMode = auth_normalize_sso_mode($raw['sso_mode'] ?? null, get_auth_policy_defaults()['sso_mode']);
        $ssoEnabled = auth_normalize_bool($raw['sso_enabled'] ?? null, get_auth_policy_defaults()['sso_enabled']);
        $staffEnabled = auth_normalize_bool($raw['login_enable_staf'] ?? null, get_auth_policy_defaults()['login_enable_staf']);
        $studentEnabled = auth_normalize_bool($raw['login_enable_pelajar'] ?? null, get_auth_policy_defaults()['login_enable_pelajar']);
        $staffAutoProvision = auth_normalize_bool($raw['auto_provision_staf_sso'] ?? null, get_auth_policy_defaults()['auto_provision_staf_sso']);
        $studentAutoProvision = auth_normalize_bool($raw['auto_provision_pelajar_sso'] ?? null, get_auth_policy_defaults()['auto_provision_pelajar_sso']);
        $staffGroupCode = auth_normalize_group_code($raw['default_group_staff_code'] ?? null, get_auth_policy_defaults()['default_group_staff_code']);
        $studentGroupCode = auth_normalize_group_code($raw['default_group_student_code'] ?? null, get_auth_policy_defaults()['default_group_student_code']);

        $staffLoginMethod = 'MANUAL';
        $studentLoginMethod = 'MANUAL';
        if ($ssoEnabled) {
            if ($normalizedSsoMode === 'ALL') {
                $staffLoginMethod = 'SSO';
                $studentLoginMethod = 'SSO';
            } elseif ($normalizedSsoMode === 'HYBRID') {
                $staffLoginMethod = auth_normalize_hybrid_mode($raw['sso_hybrid_staf'] ?? null, get_auth_policy_defaults()['sso_hybrid_staf']);
                $studentLoginMethod = auth_normalize_hybrid_mode($raw['sso_hybrid_pelajar'] ?? null, get_auth_policy_defaults()['sso_hybrid_pelajar']);
            }
        }

        if ($normalizedSsoMode === 'HYBRID') {
            foreach (['sso_hybrid_staf', 'sso_hybrid_pelajar', 'sso_hybrid_umum'] as $hybridKey) {
                $rawHybrid = strtoupper(trim((string)($raw[$hybridKey] ?? '')));
                if ($rawHybrid === '' || !in_array($rawHybrid, ['SSO', 'MANUAL'], true)) {
                    $errors[] = sprintf('Hybrid mapping is incomplete for %s.', $hybridKey);
                }
            }
        }

        if (!$ssoEnabled && $normalizedSsoMode !== 'MANUAL') {
            $warnings[] = 'SSO mode is configured but SSO is disabled.';
        }

        if (!$ssoEnabled) {
            $hybridValues = [
                strtoupper(trim((string)($raw['sso_hybrid_staf'] ?? ''))),
                strtoupper(trim((string)($raw['sso_hybrid_pelajar'] ?? ''))),
                strtoupper(trim((string)($raw['sso_hybrid_umum'] ?? ''))),
            ];
            if (array_filter($hybridValues, static fn($value) => $value !== '')) {
                $warnings[] = 'Hybrid mapping is defined but SSO is disabled.';
            }
        }

        if ($normalizedSsoMode === 'ALL') {
            $hybridValues = [
                strtoupper(trim((string)($raw['sso_hybrid_staf'] ?? ''))),
                strtoupper(trim((string)($raw['sso_hybrid_pelajar'] ?? ''))),
                strtoupper(trim((string)($raw['sso_hybrid_umum'] ?? ''))),
            ];
            if (array_filter($hybridValues, static fn($value) => $value !== '')) {
                $warnings[] = 'Hybrid mapping is defined but not used in ALL mode.';
            }
        }

        $categoriesEnabled = [
            auth_normalize_bool($raw['login_enable_staf'] ?? null, get_auth_policy_defaults()['login_enable_staf']),
            auth_normalize_bool($raw['login_enable_pelajar'] ?? null, get_auth_policy_defaults()['login_enable_pelajar']),
            auth_normalize_bool($raw['login_enable_umum'] ?? null, get_auth_policy_defaults()['login_enable_umum']),
        ];
        if (!in_array(true, $categoriesEnabled, true)) {
            $warnings[] = 'All login categories are disabled. Only Super Admin access will remain.';
        }

        if ($staffAutoProvision && $staffGroupCode === '') {
            $errors[] = 'Default group code for staff SSO auto provision is required.';
        }

        if ($studentAutoProvision && $studentGroupCode === '') {
            $errors[] = 'Default group code for student SSO auto provision is required.';
        }

        if ($staffAutoProvision && !$staffEnabled) {
            $warnings[] = 'Staff SSO auto provision is enabled while staff login is disabled.';
        }

        if ($studentAutoProvision && !$studentEnabled) {
            $warnings[] = 'Student SSO auto provision is enabled while student login is disabled.';
        }

        if ($staffAutoProvision && $staffLoginMethod !== 'SSO') {
            $warnings[] = 'Staff SSO auto provision is enabled but the current staff login route is not SSO.';
        }

        if ($studentAutoProvision && $studentLoginMethod !== 'SSO') {
            $warnings[] = 'Student SSO auto provision is enabled but the current student login route is not SSO.';
        }

        return [
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
        ];
    }
}

if (!function_exists('get_auth_policy_config')) {
    function get_auth_policy_config(): array {
        $defaults = get_auth_policy_defaults();
        $raw = get_auth_policy_raw_config();
        $validation = validate_auth_policy_raw_config($raw);

        $config = [
            'maintenance_mode' => auth_normalize_bool($raw['maintenance_mode'] ?? null, $defaults['maintenance_mode']),
            'categories' => [
                'staf' => auth_normalize_bool($raw['login_enable_staf'] ?? null, $defaults['login_enable_staf']),
                'pelajar' => auth_normalize_bool($raw['login_enable_pelajar'] ?? null, $defaults['login_enable_pelajar']),
                'umum' => auth_normalize_bool($raw['login_enable_umum'] ?? null, $defaults['login_enable_umum']),
            ],
              'sso' => [
                  'enabled' => auth_normalize_bool($raw['sso_enabled'] ?? null, $defaults['sso_enabled']),
                  'mode' => auth_normalize_sso_mode($raw['sso_mode'] ?? null, $defaults['sso_mode']),
                  'hybrid' => [
                      'staf' => auth_normalize_hybrid_mode($raw['sso_hybrid_staf'] ?? null, $defaults['sso_hybrid_staf']),
                      'pelajar' => auth_normalize_hybrid_mode($raw['sso_hybrid_pelajar'] ?? null, $defaults['sso_hybrid_pelajar']),
                      'umum' => auth_normalize_hybrid_mode($raw['sso_hybrid_umum'] ?? null, $defaults['sso_hybrid_umum']),
                  ],
              ],
              'provisioning' => [
                  'staf_sso_enabled' => auth_normalize_bool($raw['auto_provision_staf_sso'] ?? null, $defaults['auto_provision_staf_sso']),
                  'pelajar_sso_enabled' => auth_normalize_bool($raw['auto_provision_pelajar_sso'] ?? null, $defaults['auto_provision_pelajar_sso']),
                  'default_group_staff_code' => auth_normalize_group_code($raw['default_group_staff_code'] ?? null, $defaults['default_group_staff_code']),
                  'default_group_student_code' => auth_normalize_group_code($raw['default_group_student_code'] ?? null, $defaults['default_group_student_code']),
              ],
              'password' => [
                  'min_length' => auth_normalize_int($raw['password_min_length'] ?? null, $defaults['password_min_length'], 8, 128),
                  'expiry_days' => auth_normalize_int($raw['password_expiry_days'] ?? null, $defaults['password_expiry_days'], 1, 365),
                  'history_count' => auth_normalize_int($raw['password_history_count'] ?? null, $defaults['password_history_count'], 0, 24),
                  'reset_token_minutes' => auth_normalize_int($raw['password_reset_token_minutes'] ?? null, $defaults['password_reset_token_minutes'], 5, 180),
                  'require_uppercase' => auth_normalize_bool($raw['password_require_uppercase'] ?? null, $defaults['password_require_uppercase']),
                  'require_lowercase' => auth_normalize_bool($raw['password_require_lowercase'] ?? null, $defaults['password_require_lowercase']),
                  'require_number' => auth_normalize_bool($raw['password_require_number'] ?? null, $defaults['password_require_number']),
                  'require_symbol' => auth_normalize_bool($raw['password_require_symbol'] ?? null, $defaults['password_require_symbol']),
                  'block_loginid_variants' => auth_normalize_bool($raw['password_block_loginid_variants'] ?? null, $defaults['password_block_loginid_variants']),
              ],
              'login_security' => [
                  'max_attempts' => auth_normalize_int($raw['login_max_attempts'] ?? null, $defaults['login_max_attempts'], 1, 10),
                  'lock_seconds' => auth_normalize_int($raw['login_lock_seconds'] ?? null, $defaults['login_lock_seconds'], 30, 3600),
                  'identifier_ip_max_attempts' => auth_normalize_int($raw['login_identifier_ip_max_attempts'] ?? null, $defaults['login_identifier_ip_max_attempts'], 1, 20),
                  'identifier_ip_lock_seconds' => auth_normalize_int($raw['login_identifier_ip_lock_seconds'] ?? null, $defaults['login_identifier_ip_lock_seconds'], 30, 3600),
                  'ip_max_attempts' => auth_normalize_int($raw['login_ip_max_attempts'] ?? null, $defaults['login_ip_max_attempts'], 1, 50),
                  'ip_lock_seconds' => auth_normalize_int($raw['login_ip_lock_seconds'] ?? null, $defaults['login_ip_lock_seconds'], 30, 3600),
              ],
              'valid' => empty($validation['errors']),
              'warnings' => $validation['warnings'],
              'errors' => $validation['errors'],
              'raw' => $raw,
          ];

        return $config;
      }
  }

  if (!function_exists('get_auth_password_policy_config')) {
      function get_auth_password_policy_config(): array {
          $policy = get_auth_policy_config();
          return is_array($policy['password'] ?? null) ? $policy['password'] : [];
      }
  }

  if (!function_exists('get_auth_login_security_config')) {
      function get_auth_login_security_config(): array {
          $policy = get_auth_policy_config();
          return is_array($policy['login_security'] ?? null) ? $policy['login_security'] : [];
      }
  }

if (!function_exists('get_sybase_staff_base')) {
    function get_sybase_staff_base(?string $environment = null): string {
        $environment = trim((string)($environment ?? get_sybase_environment()));
        return $environment === 'development' ? 'sybase_staff_dev' : 'sybase_staff_prod';
    }
}

if (!function_exists('get_sybase_student_base')) {
    function get_sybase_student_base(?string $environment = null): string {
        $environment = trim((string)($environment ?? get_sybase_environment()));
        return $environment === 'development' ? 'sybase_student_dev' : 'sybase_student_prod';
    }
}

if (!function_exists('get_sybase_staff_key')) {
    function get_sybase_staff_key(?string $environment = null): string {
        return sybase_resolve_driver_key(get_sybase_staff_base($environment));
    }
}

if (!function_exists('get_sybase_student_key')) {
    function get_sybase_student_key(?string $environment = null): string {
        return sybase_resolve_driver_key(get_sybase_student_base($environment));
    }
}

if (!function_exists('getSybaseStaffPDO')) {
    function getSybaseStaffPDO(?string $environment = null): \PDO {
        $key = get_sybase_staff_key($environment);
        $pdo = Database::getInstance($key)->getConnection();
        $pdo->query('select 1');
        return $pdo;
    }
}

if (!function_exists('getSybaseStudentPDO')) {
    function getSybaseStudentPDO(?string $environment = null): ?\PDO {
        if (!is_student_mode_enabled()) {
            return null;
        }
        $key = get_sybase_student_key($environment);
        $pdo = Database::getInstance($key)->getConnection();
        $pdo->query('select 1');
        return $pdo;
    }
}

/**
 * Uji sambungan semua DB dalam configuration/db_config.php
 * Pulangkan array ringkas status.
 */
function testAllDatabaseConnections(): array
{
    $results = [];
    $configsFile = __DIR__ . '/../configuration/db_config.php';
    if (!is_file($configsFile)) return ['error' => 'db_config.php not found'];

    $configs = require $configsFile;

    foreach ($configs as $key => $cfg) {
        try {
            $pdo = Database::getInstance($key)->getConnection();
            if ($pdo) { $pdo->query('select 1'); }
            $status = '✅ Berjaya';
        } catch (\Throwable $e) {
            $status = '❌ ' . $e->getMessage();
        }
        $results[$key] = $status;
    }
    return $results;
}

/**
 * Info diagnostik pantas — boleh dipanggil bila perlu.
 */
function sybase_diag_info(): array
{
    return [
        'os'       => PHP_OS_FAMILY,
        'php'      => PHP_VERSION,
        'ini'      => php_ini_loaded_file(),
        'drivers'  => \PDO::getAvailableDrivers(),
        'environment' => get_sybase_environment(),
        'operational_mode' => get_sybase_operational_mode(),
        'staff_key' => get_sybase_staff_key(),
        'student_key' => get_sybase_student_key(),
        'student_enabled' => is_student_mode_enabled(),
        'testAll'  => testAllDatabaseConnections(),
    ];
}

if (!function_exists('password_reset_normalize_category')) {
    function password_reset_normalize_category(?string $category): string
    {
        $normalized = strtoupper(trim((string)$category));
        return in_array($normalized, ['STAF', 'PELAJAR', 'UMUM'], true) ? $normalized : 'UNKNOWN';
    }
}

if (!function_exists('password_reset_evaluate_eligibility')) {
    function password_reset_is_super_admin(array $user, ?PDO $pdo = null): bool
    {
        $groupCode = trim((string)($user['f_groupKod'] ?? ''));
        $superAdminCode = function_exists('prestasi_super_admin_code')
            ? (string)prestasi_super_admin_code()
            : 'ADM-SA';
        if ($groupCode !== '' && function_exists('prestasi_group_code_equals')) {
            return prestasi_group_code_equals($groupCode, $superAdminCode);
        }
        if ($groupCode !== '') {
            return strtoupper($groupCode) === strtoupper($superAdminCode);
        }

        $groupId = (int)($user['f_groupID'] ?? 0);
        $legacyRoleId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
        if ($groupId > 0 && $legacyRoleId > 0 && $groupId === $legacyRoleId) {
            return true;
        }
        if ($groupId <= 0 || !$pdo instanceof PDO) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('SELECT f_groupKod FROM tbl_m_group WHERE f_groupID = :groupId LIMIT 1');
            $stmt->execute([':groupId' => $groupId]);
            $resolvedCode = trim((string)($stmt->fetchColumn() ?: ''));
            return function_exists('prestasi_group_code_equals')
                ? prestasi_group_code_equals($resolvedCode, $superAdminCode)
                : strtoupper($resolvedCode) === strtoupper($superAdminCode);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Nilai kelayakan reset kata laluan tempatan menggunakan polisi auth semasa.
     * Super Admin hanya memintas maintenance dan category switch; akaun SSO
     * kekal perlu menggunakan saluran pemulihan Identity Provider.
     */
    function password_reset_evaluate_eligibility(array $user, ?PDO $pdo = null): array
    {
        $category = password_reset_normalize_category((string)($user['f_categoryUser'] ?? ''));
        $isSuperAdmin = password_reset_is_super_admin($user, $pdo);
        $result = [
            'eligible' => false,
            'reason' => 'password_reset_ineligible',
            'category' => $category,
            'is_super_admin' => $isSuperAdmin,
        ];

        if ((int)($user['f_flag'] ?? 0) !== 1) {
            $result['reason'] = 'password_reset_account_disabled';
            return $result;
        }
        if ((int)($user['f_statusID'] ?? 0) === 9) {
            $result['reason'] = 'password_reset_account_deleted';
            return $result;
        }
        if ($category === 'UNKNOWN') {
            $result['reason'] = 'password_reset_unknown_category';
            return $result;
        }

        $policy = function_exists('get_auth_policy_config') ? get_auth_policy_config() : [];
        if (!is_array($policy) || $policy === []) {
            $result['reason'] = 'password_reset_policy_unavailable';
            return $result;
        }

        if (!empty($policy['maintenance_mode']) && !$isSuperAdmin) {
            $result['reason'] = 'password_reset_maintenance_blocked';
            return $result;
        }

        $categoryKey = strtolower($category);
        if (empty($policy['categories'][$categoryKey]) && !$isSuperAdmin) {
            $result['reason'] = 'password_reset_category_disabled';
            return $result;
        }

        $sso = is_array($policy['sso'] ?? null) ? $policy['sso'] : [];
        if (!empty($sso['enabled'])) {
            $mode = strtoupper(trim((string)($sso['mode'] ?? 'MANUAL')));
            if ($mode === 'ALL') {
                $result['reason'] = 'password_reset_sso_managed';
                return $result;
            }
            if ($mode === 'HYBRID') {
                $hybridMode = strtoupper(trim((string)($sso['hybrid'][$categoryKey] ?? 'MANUAL')));
                if ($hybridMode !== 'MANUAL') {
                    $result['reason'] = 'password_reset_sso_managed';
                    return $result;
                }
            }
        }

        $result['eligible'] = true;
        $result['reason'] = 'password_reset_eligible';
        return $result;
    }
}
