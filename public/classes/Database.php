<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ======================================
// ✅ Kelas Database Tunggal (Singleton)
// - Support config MySQL + Sybase domain-based keys
// - Auto suffix _dsn/_dblib ikut OS
// ======================================
// Guarded include for SSO client:
// - Keep SSO client available for normal web requests (needed for login)
// - Avoid executing the client during CLI/test contexts or when explicitly disabled
// if (PHP_SAPI !== 'cli' && !defined('DISABLE_SSO_SP_CLIENT')) {
//     if (!defined('SSO_SP_CLIENT_INCLUDED')) {
//         define('SSO_SP_CLIENT_INCLUDED', true);
//         include_once __DIR__ . '/../sso_sp_client.php';
//     }
// }
require_once __DIR__ . '/DatabaseManager.php';
class Database
{
    private static array $instances = [];
    private ?PDO $connection = null;

    /**
     * 🚪 Private constructor
     */
    private function __construct(array $config)
    {
        try {
            $options = $config['options'] ?? [];
            $driver = $config['driver'] ?? '';
            $defaults = [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            if (strtolower((string)$driver) === 'mysql' && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $defaults[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
            }
            // Untuk Sybase via ODBC, elakkan server-side prepares (boleh cetus HY010)
            if (strtolower($driver) === 'odbc' && str_contains(strtolower($config['dsn'] ?? ''), 'sybase')) {
                $defaults[PDO::ATTR_EMULATE_PREPARES] = true;
                $defaults[PDO::ATTR_CURSOR] = PDO::CURSOR_FWDONLY;
            }
            $options = $options + $defaults;

            $this->connection = new PDO(
                $config['dsn'],
                $config['user'] ?? null,
                $config['pass'] ?? null,
                $options
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("❌ Gagal sambungan ke DB: " . $e->getMessage());
        }
    }

    /**
     * 🧠 Dapatkan instance tunggal berdasarkan nama konfigurasi
     *
     * @param string $baseName Contoh:
     *   - 'mysql'
      *   - 'sybase_ehrmdb'  (base; suffix auto ditambah)
      *   - 'sybase_ehrmdb_dsn' / 'sybase_ehrmdb_dblib' (explicit)
     */
    public static function getInstance(string $baseName = 'mysql'): self
    {
        $requestedBaseName = strtolower(trim($baseName));
        if ($requestedBaseName === '') {
            $requestedBaseName = 'mysql';
        }

        if (!isset(self::$instances[$requestedBaseName])) {
            $manager = new DatabaseManager();

            try {
                $pdo = $manager->connection($requestedBaseName);
                self::$instances[$requestedBaseName] = self::fromPdo($pdo);
            } catch (Throwable $e) {
                throw new Exception("⚠️ Gagal resolve sambungan '{$requestedBaseName}': " . $e->getMessage());
            }
        }

        return self::$instances[$requestedBaseName];
    }

    /**
     * 🔌 Dapatkan sambungan PDO
     */
    public function getConnection(): PDO
    {
        if (!$this->connection instanceof PDO) {
            throw new Exception('⚠️ Sambungan PDO tidak tersedia.');
        }
        return $this->connection;
    }

    // ——————————————————————————————
    // 🔧 Convenience helpers (optional)
    // ——————————————————————————————

    /**
     * 🎯 Terus dapatkan PDO MySQL
     */
    public static function pdoMysql(): PDO
    {
        return self::getInstance('mysql')->getConnection();
    }

    /**
     * 🎯 PDO Sybase domain staff (environment-aware helper)
     */
    public static function pdoSybaseStaff(?string $environment = null): PDO
    {
        if (!function_exists('getSybaseStaffPDO')) {
            $helperPath = __DIR__ . '/../includes/functions-db.php';
            if (is_file($helperPath)) {
                require_once $helperPath;
            }
        }
        if (!function_exists('getSybaseStaffPDO')) {
            throw new Exception("⚠️ Helper getSybaseStaffPDO() belum tersedia.");
        }
        return getSybaseStaffPDO($environment);
    }

    /**
     * 🎯 PDO Sybase domain student (environment-aware helper)
     */
    public static function pdoSybaseStudent(?string $environment = null): ?PDO
    {
        if (!function_exists('getSybaseStudentPDO')) {
            $helperPath = __DIR__ . '/../includes/functions-db.php';
            if (is_file($helperPath)) {
                require_once $helperPath;
            }
        }
        if (!function_exists('getSybaseStudentPDO')) {
            throw new Exception("⚠️ Helper getSybaseStudentPDO() belum tersedia.");
        }
        return getSybaseStudentPDO($environment);
    }

    /**
     * 🎯 PDO Additional connection from registry/runtime manager.
     */
    public static function pdoAdditional(string $code, ?string $environment = null): PDO
    {
        return (new DatabaseManager())->additional($code, $environment);
    }

    /**
     * 🔁 Clear a cached instance so callers can force a reconnect.
     *
     * @param string $baseName The base config name (same rules as getInstance)
     */
    public static function clearInstance(string $baseName = 'mysql'): void
    {
        $baseName = strtolower(trim($baseName));
        if ($baseName === '') {
            $baseName = 'mysql';
        }

        if (isset(self::$instances[$baseName])) {
            // close PDO connection reference to allow GC
            try {
                self::$instances[$baseName]->connection = null; // release PDO
            } catch (Throwable $e) {
                // ignore — defensive
            }
            unset(self::$instances[$baseName]);
        }

        (new DatabaseManager())->clear($baseName);
    }

    /**
     * 🔁 Clear all cached instances (useful for full reconnect)
     */
    public static function clearAllInstances(): void
    {
        foreach (array_keys(self::$instances) as $k) {
            try {
                self::$instances[$k]->connection = null;
            } catch (Throwable $e) {
                // ignore
            }
            unset(self::$instances[$k]);
        }

        DatabaseManager::clearAll();
    }

    private static function fromPdo(PDO $pdo): self
    {
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->connection = $pdo;
        return $instance;
    }
}
