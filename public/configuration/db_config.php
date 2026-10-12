<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
if (!function_exists('db_config_load_env_once')) {
    /**
     * Load .env values into $_ENV/$_SERVER/getenv once for config usage.
     */
    function db_config_load_env_once(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $candidates = [
            dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
        ];

        $envPath = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $envPath = $candidate;
                break;
            }
        }

        if ($envPath === null) {
            return;
        }

        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            $value = trim($value);
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $value = str_replace(["\\n", "\\r", "\\t"], ["\n", "\r", "\t"], $value);

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('db_env')) {
    /**
     * Read environment value with default fallback.
     */
    function db_env(string $key, ?string $default = null): ?string
    {
        db_config_load_env_once();
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string)$value;
    }
}

if (!function_exists('db_env_required')) {
    /**
     * Read required environment value and fail fast when missing.
     */
    function db_env_required(string $key): string
    {
        $value = db_env($key, null);
        if ($value === null || $value === '') {
            throw new RuntimeException("Missing required environment variable: {$key}");
        }

        return $value;
    }
}

if (!function_exists('db_env_first')) {
    /**
     * Return the first non-empty environment value from a list of keys.
     */
    function db_env_first(array $keys, ?string $default = null): ?string
    {
        foreach ($keys as $key) {
            $value = db_env((string)$key, null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('db_env_required_first')) {
    /**
     * Return the first non-empty environment value from a list of keys or fail fast.
     */
    function db_env_required_first(array $keys): string
    {
        $value = db_env_first($keys, null);
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing required environment variable from candidates: ' . implode(', ', $keys));
        }

        return $value;
    }
}

return [

    // ===================================================
    // ✅ MySQL Utama (environment-aware)
    // ===================================================
    'mysql_prod' => [
        'driver' => 'mysql',
        'dsn'    => sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            db_env_required('DB_MYSQL_MAIN_PROD_HOST'),
            db_env('DB_MYSQL_MAIN_PROD_PORT', '3306'),
            db_env_required('DB_MYSQL_MAIN_PROD_NAME'),
            db_env('DB_MYSQL_MAIN_PROD_CHARSET', 'utf8mb4')
        ),
        'user'   => db_env_required('DB_MYSQL_MAIN_PROD_USER'),
        'pass'   => db_env_required('DB_MYSQL_MAIN_PROD_PASS'),
    ],

    'mysql_dev' => [
        'driver' => 'mysql',
        'dsn'    => sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            db_env_required('DB_MYSQL_MAIN_DEV_HOST'),
            db_env('DB_MYSQL_MAIN_DEV_PORT', '3306'),
            db_env_required('DB_MYSQL_MAIN_DEV_NAME'),
            db_env('DB_MYSQL_MAIN_DEV_CHARSET', 'utf8mb4')
        ),
        'user'   => db_env_required('DB_MYSQL_MAIN_DEV_USER'),
        'pass'   => db_env_required('DB_MYSQL_MAIN_DEV_PASS'),
    ],

    'mysql' => strtolower((string) db_env('MAIN_DB_ENVIRONMENT', 'production')) === 'development'
        ? [
            'driver' => 'mysql',
            'dsn'    => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                db_env_required('DB_MYSQL_MAIN_DEV_HOST'),
                db_env('DB_MYSQL_MAIN_DEV_PORT', '3306'),
                db_env_required('DB_MYSQL_MAIN_DEV_NAME'),
                db_env('DB_MYSQL_MAIN_DEV_CHARSET', 'utf8mb4')
            ),
            'user'   => db_env_required('DB_MYSQL_MAIN_DEV_USER'),
            'pass'   => db_env_required('DB_MYSQL_MAIN_DEV_PASS'),
        ]
        : [
            'driver' => 'mysql',
            'dsn'    => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                db_env_required('DB_MYSQL_MAIN_PROD_HOST'),
                db_env('DB_MYSQL_MAIN_PROD_PORT', '3306'),
                db_env_required('DB_MYSQL_MAIN_PROD_NAME'),
                db_env('DB_MYSQL_MAIN_PROD_CHARSET', 'utf8mb4')
            ),
            'user'   => db_env_required('DB_MYSQL_MAIN_PROD_USER'),
            'pass'   => db_env_required('DB_MYSQL_MAIN_PROD_PASS'),
        ],

    // ===================================================
    // ✅ Sybase Domain Registry
    // ===================================================

    'sybase_staff_prod_dblib' => [
        'driver' => 'dblib',
        'dsn'    => sprintf(
            'dblib:host=%s:%s;dbname=%s',
            db_env('SYBASE_STAFF_PROD_HOST', '172.16.2.14'),
            db_env('SYBASE_STAFF_PROD_PORT', '5004'),
            db_env('SYBASE_STAFF_PROD_DB', 'ehrmdb')
        ),
        'user'   => db_env_required('SYBASE_STAFF_PROD_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_PROD_PASS'),
    ],

    'sybase_staff_prod_dsn' => [
        'driver' => 'odbc',
        'dsn'    => 'odbc:' . db_env_required('SYBASE_STAFF_PROD_DSN'),
        'user'   => db_env_required('SYBASE_STAFF_PROD_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_PROD_PASS'),
    ],

    'sybase_staff_dev_dblib' => [
        'driver' => 'dblib',
        'dsn'    => sprintf(
            'dblib:host=%s:%s;dbname=%s',
            db_env('SYBASE_STAFF_DEV_HOST', '172.16.2.8'),
            db_env('SYBASE_STAFF_DEV_PORT', '7000'),
            db_env('SYBASE_STAFF_DEV_DB', 'ehrmdb')
        ),
        'user'   => db_env_required('SYBASE_STAFF_DEV_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_DEV_PASS'),
    ],

    'sybase_staff_dev_dsn' => [
        'driver' => 'odbc',
        'dsn'    => 'odbc:' . db_env_required('SYBASE_STAFF_DEV_DSN'),
        'user'   => db_env_required('SYBASE_STAFF_DEV_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_DEV_PASS'),
    ],

    // ===================================================
    // ✅ Legacy aliases mapped to staff runtime values
    // ===================================================

    'sybase_ehrmdb_dblib' => [
        'driver' => 'dblib',
        'dsn'    => sprintf(
            'dblib:host=%s:%s;dbname=%s',
            db_env('SYBASE_STAFF_PROD_HOST', '172.16.2.14'),
            db_env('SYBASE_STAFF_PROD_PORT', '5004'),
            db_env('SYBASE_STAFF_PROD_DB', 'ehrmdb')
        ),
        'user'   => db_env_required('SYBASE_STAFF_PROD_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_PROD_PASS'),
    ],

    'sybase_ehrmdb_dsn' => [
        'driver' => 'odbc',
        'dsn'    => 'odbc:' . db_env_required('SYBASE_STAFF_PROD_DSN'),
        'user'   => db_env_required('SYBASE_STAFF_PROD_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_PROD_PASS'),
    ],

    'sybase_ehrmdb_dev_dblib' => [
        'driver' => 'dblib',
        'dsn'    => sprintf(
            'dblib:host=%s:%s;dbname=%s',
            db_env('SYBASE_STAFF_DEV_HOST', '172.16.2.8'),
            db_env('SYBASE_STAFF_DEV_PORT', '7000'),
            db_env('SYBASE_STAFF_DEV_DB', 'ehrmdb')
        ),
        'user'   => db_env_required('SYBASE_STAFF_DEV_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_DEV_PASS'),
    ],

    'sybase_ehrmdb_dev_dsn' => [
        'driver' => 'odbc',
        'dsn'    => 'odbc:' . db_env_required('SYBASE_STAFF_DEV_DSN'),
        'user'   => db_env_required('SYBASE_STAFF_DEV_USER'),
        'pass'   => db_env_required('SYBASE_STAFF_DEV_PASS'),
    ],

    'sybase_student_prod_dblib' => [
        'driver' => 'dblib',
        'dsn'    => sprintf(
            'dblib:host=%s:%s;dbname=%s',
            db_env('SYBASE_STUDENT_PROD_HOST', '172.16.2.14'),
            db_env('SYBASE_STUDENT_PROD_PORT', '5004'),
            db_env('SYBASE_STUDENT_PROD_DB', 'asisdb')
        ),
        'user'   => db_env_required('SYBASE_STUDENT_PROD_USER'),
        'pass'   => db_env_required('SYBASE_STUDENT_PROD_PASS'),
    ],

    'sybase_student_prod_dsn' => [
        'driver' => 'odbc',
        'dsn'    => 'odbc:' . db_env_required('SYBASE_STUDENT_PROD_DSN'),
        'user'   => db_env_required('SYBASE_STUDENT_PROD_USER'),
        'pass'   => db_env_required('SYBASE_STUDENT_PROD_PASS'),
    ],

    'sybase_student_dev_dblib' => [
        'driver' => 'dblib',
        'dsn'    => sprintf(
            'dblib:host=%s:%s;dbname=%s',
            db_env('SYBASE_STUDENT_DEV_HOST', '172.16.2.8'),
            db_env('SYBASE_STUDENT_DEV_PORT', '7000'),
            db_env('SYBASE_STUDENT_DEV_DB', 'asisdb')
        ),
        'user'   => db_env_required('SYBASE_STUDENT_DEV_USER'),
        'pass'   => db_env_required('SYBASE_STUDENT_DEV_PASS'),
    ],

    'sybase_student_dev_dsn' => [
        'driver' => 'odbc',
        'dsn'    => 'odbc:' . db_env_required('SYBASE_STUDENT_DEV_DSN'),
        'user'   => db_env_required('SYBASE_STUDENT_DEV_USER'),
        'pass'   => db_env_required('SYBASE_STUDENT_DEV_PASS'),
    ],
];
