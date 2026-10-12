<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DatabaseRuntimeConfig.php';
require_once __DIR__ . '/DatabaseConnectionRegistry.php';
require_once __DIR__ . '/DatabaseConnectionResolver.php';
require_once __DIR__ . '/DatabaseConnectionFactory.php';
require_once __DIR__ . '/DatabaseConnectionRepository.php';

final class DatabaseManager
{
    private static ?DatabaseConnectionResolver $resolver = null;
    private static ?DatabaseConnectionFactory $factory = null;
    /** @var array<string, PDO> */
    private static array $pdoCache = [];

    public function __construct(
        private readonly ?DatabaseConnectionResolver $customResolver = null,
        private readonly ?DatabaseConnectionFactory $customFactory = null,
    ) {
    }

    public function mainMysql(): PDO
    {
        return $this->connection('mysql_main');
    }

    public function mainSybaseStaff(): PDO
    {
        return $this->connection('sybase_staff');
    }

    public function mainSybaseStudent(): ?PDO
    {
        try {
            return $this->connection('sybase_student');
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'disabled')) {
                return null;
            }
            throw $e;
        }
    }

    public function additional(string $code, ?string $environment = null): PDO
    {
        $resolved = $this->resolver()->resolveAdditional($code, $environment);
        return $this->buildConnection($resolved);
    }

    public function testResolved(string $code, ?string $environment = null): array
    {
        $resolved = $environment === null
            ? $this->resolver()->resolveByCode($code)
            : $this->resolver()->resolveAdditional($code, $environment);

        $status = 'success';
        $message = 'Connection test passed.';

        try {
            $pdo = $this->buildConnection($resolved);
            $pdo->query('select 1');
        } catch (Throwable $e) {
            $status = 'error';
            $message = $e->getMessage();
        }

        return [
            'code' => $code,
            'environment' => $environment,
            'resolved_key' => $resolved['resolved_key'] ?? $code,
            'status' => $status,
            'message' => $message,
        ];
    }

    public function connection(string $code): PDO
    {
        $resolved = $this->resolver()->resolveByCode($code);
        return $this->buildConnection($resolved);
    }

    public function clear(string $code): void
    {
        $candidates = [$code, strtolower(trim($code))];

        try {
            $resolved = $this->resolver()->resolveByCode($code);
            $candidates[] = (string)($resolved['resolved_key'] ?? '');
            $candidates[] = (string)($resolved['fallback_key'] ?? '');
        } catch (Throwable $e) {
            // ignore resolution errors during cache clear
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && isset(self::$pdoCache[$candidate])) {
                unset(self::$pdoCache[$candidate]);
            }
        }
    }

    public static function clearAll(): void
    {
        self::$pdoCache = [];
        self::$resolver = null;
        self::$factory = null;
    }

    private function resolver(): DatabaseConnectionResolver
    {
        if ($this->customResolver instanceof DatabaseConnectionResolver) {
            return $this->customResolver;
        }

        if (self::$resolver instanceof DatabaseConnectionResolver) {
            return self::$resolver;
        }

        $configs = require __DIR__ . '/../configuration/db_config.php';
        $runtimeConfig = new DatabaseRuntimeConfig();
        $registry = DatabaseConnectionRegistry::fromConfigArray($configs);
        $factory = $this->factory();

        try {
            $bootstrapResolver = new DatabaseConnectionResolver($runtimeConfig, $registry);
            $mysqlResolved = $bootstrapResolver->resolveMainMysql();
            $mysqlPdo = $factory->make((array)$mysqlResolved['config']);
            $repository = new DatabaseConnectionRepository($mysqlPdo);
            $additionalRows = $repository->findAllAdditional(true);

            if ($additionalRows !== []) {
                $registry = $registry->withAdditionalRows($additionalRows);
            }
        } catch (Throwable $e) {
            // Keep runtime backward compatible even when additional registry is unavailable.
        }

        self::$resolver = new DatabaseConnectionResolver($runtimeConfig, $registry);

        return self::$resolver;
    }

    private function factory(): DatabaseConnectionFactory
    {
        if ($this->customFactory instanceof DatabaseConnectionFactory) {
            return $this->customFactory;
        }

        if (self::$factory instanceof DatabaseConnectionFactory) {
            return self::$factory;
        }

        self::$factory = new DatabaseConnectionFactory();
        return self::$factory;
    }

    private function buildConnection(array $resolved): PDO
    {
        $resolvedKey = strtolower(trim((string)($resolved['resolved_key'] ?? '')));
        if ($resolvedKey !== '' && isset(self::$pdoCache[$resolvedKey])) {
            return self::$pdoCache[$resolvedKey];
        }

        try {
            $pdo = $this->factory()->make((array)$resolved['config']);
        } catch (Throwable $e) {
            $fallbackConfig = $resolved['fallback_config'] ?? null;
            $fallbackKey = strtolower(trim((string)($resolved['fallback_key'] ?? '')));
            if (!is_array($fallbackConfig) || $fallbackConfig === []) {
                throw $e;
            }

            $pdo = $this->factory()->make($fallbackConfig);
            if ($fallbackKey !== '') {
                self::$pdoCache[$fallbackKey] = $pdo;
            }
            return $pdo;
        }

        if ($resolvedKey !== '') {
            self::$pdoCache[$resolvedKey] = $pdo;
        }

        return $pdo;
    }
}
