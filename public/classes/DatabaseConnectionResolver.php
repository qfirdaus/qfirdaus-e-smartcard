<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/DatabaseRuntimeConfig.php';
require_once __DIR__ . '/DatabaseConnectionRegistry.php';
require_once __DIR__ . '/AdditionalDatabaseException.php';

final class DatabaseConnectionResolver
{
    public function __construct(
        private readonly DatabaseRuntimeConfig $runtimeConfig,
        private readonly DatabaseConnectionRegistry $registry,
        private readonly ?array $availableDrivers = null,
    ) {
    }

    public function resolveMainMysql(): array
    {
        $definition = $this->registry->getMain('mysql_main');
        $environment = $this->runtimeConfig->getMainMysqlEnvironment();

        return $this->resolveMysqlEnvironment($definition, $environment);
    }

    public function resolveMainSybaseStaff(): array
    {
        $definition = $this->registry->getMain('sybase_staff');
        $environment = $this->runtimeConfig->getSybaseEnvironment();

        return $this->resolveSybaseEnvironment($definition, $environment);
    }

    public function resolveMainSybaseStudent(): ?array
    {
        if (!$this->runtimeConfig->isStudentModeEnabled()) {
            return null;
        }

        $definition = $this->registry->getMain('sybase_student');
        $environment = $this->runtimeConfig->getSybaseEnvironment();

        return $this->resolveSybaseEnvironment($definition, $environment);
    }

    public function resolveAdditional(string $code, ?string $environment = null): array
    {
        $definition = $this->registry->getAdditional($code);
        if ($definition instanceof DatabaseConnectionDefinition) {
            if (!$definition->enabled) {
                throw AdditionalDatabaseException::disabled($code);
            }

            $targetEnvironment = $environment ?: $this->runtimeConfig->getMainMysqlEnvironment();

            if ($definition->family === 'sybase') {
                return $this->resolveSybaseEnvironment($definition, $targetEnvironment);
            }

            if ($definition->family === 'mssql') {
                return $this->resolveMssqlEnvironment($definition, $targetEnvironment);
            }

            return $this->resolveMysqlEnvironment($definition, $targetEnvironment);
        }

        $flatConfig = $this->registry->getFlatConfig($code);
        if (is_array($flatConfig)) {
            return [
                'requested_code' => $code,
                'resolved_key' => $code,
                'config' => $flatConfig,
                'fallback_key' => null,
                'fallback_config' => null,
            ];
        }

        throw AdditionalDatabaseException::notFound($code);
    }

    public function resolveByCode(string $code): array
    {
        $code = strtolower(trim($code));
        if ($code === '' || $code === 'mysql' || $code === 'mysql_main') {
            return $this->resolveMainMysql();
        }

        if ($code === 'sybase_staff') {
            return $this->resolveMainSybaseStaff();
        }

        if ($code === 'sybase_student') {
            $resolved = $this->resolveMainSybaseStudent();
            if ($resolved === null) {
                throw new RuntimeException('Sybase student runtime is disabled by operational mode.');
            }
            return $resolved;
        }

        if ($this->registry->getAdditional($code) instanceof DatabaseConnectionDefinition) {
            return $this->resolveAdditional($code);
        }

        if (str_starts_with($code, 'dbx_')) {
            return $this->resolveAdditional($code);
        }

        if ($this->registry->getFlatConfig($code) !== null) {
            if (preg_match('/^(sybase_[a-z0-9_]+)$/', $code) === 1 && !preg_match('/_(dsn|dblib|odbc|sqlsrv)$/', $code)) {
                return $this->resolveSybaseBaseKey($code);
            }

            return [
                'requested_code' => $code,
                'resolved_key' => $code,
                'config' => $this->registry->getFlatConfig($code),
                'fallback_key' => null,
                'fallback_config' => null,
            ];
        }

        if (preg_match('/^(sybase_[a-z0-9_]+)$/', $code) === 1) {
            return $this->resolveSybaseBaseKey($code);
        }

        throw new RuntimeException("Database connection not found: {$code}");
    }

    private function resolveMysqlEnvironment(DatabaseConnectionDefinition $definition, string $environment): array
    {
        $entry = $definition->environments[$environment] ?? null;
        if ($definition->isAdditional() && is_array($entry) && !isset($entry['config'])) {
            return $this->resolveVariantMap($definition->code, 'MySQL', $entry, ['mysql'], $definition->driverMode);
        }
        if (!is_array($entry) || empty($entry['config'])) {
            throw $definition->isAdditional()
                ? AdditionalDatabaseException::environmentNotConfigured('MySQL', $definition->code, $environment)
                : new RuntimeException("MySQL environment not configured for {$definition->code}: {$environment}");
        }

        return [
            'requested_code' => $definition->code,
            'resolved_key' => (string)($entry['resolved_key'] ?? $definition->code),
            'config' => $entry['config'],
            'fallback_key' => null,
            'fallback_config' => null,
        ];
    }

    private function resolveSybaseEnvironment(DatabaseConnectionDefinition $definition, string $environment): array
    {
        $map = $definition->environments[$environment] ?? null;
        if (!is_array($map) || $map === []) {
            throw $definition->isAdditional()
                ? AdditionalDatabaseException::environmentNotConfigured('Sybase', $definition->code, $environment)
                : new RuntimeException("Sybase environment not configured for {$definition->code}: {$environment}");
        }

        return $this->resolveSybaseVariantMap($definition->code, $map, $definition->driverMode);
    }

    private function resolveMssqlEnvironment(DatabaseConnectionDefinition $definition, string $environment): array
    {
        $map = $definition->environments[$environment] ?? null;
        if (!is_array($map) || $map === []) {
            throw $definition->isAdditional()
                ? AdditionalDatabaseException::environmentNotConfigured('MSSQL', $definition->code, $environment)
                : new RuntimeException("MSSQL environment not configured for {$definition->code}: {$environment}");
        }

        return $this->resolveMssqlVariantMap($definition->code, $map, $definition->driverMode);
    }

    private function resolveSybaseBaseKey(string $baseKey): array
    {
        $map = [];
        $dsnKey = $baseKey . '_dsn';
        $dblibKey = $baseKey . '_dblib';

        if ($this->registry->getFlatConfig($dsnKey) !== null) {
            $map['windows']['dsn'] = [
                'resolved_key' => $dsnKey,
                'config' => $this->registry->getFlatConfig($dsnKey),
            ];
            $map['linux']['dsn'] = [
                'resolved_key' => $dsnKey,
                'config' => $this->registry->getFlatConfig($dsnKey),
            ];
        }

        if ($this->registry->getFlatConfig($dblibKey) !== null) {
            $map['windows']['dblib'] = [
                'resolved_key' => $dblibKey,
                'config' => $this->registry->getFlatConfig($dblibKey),
            ];
            $map['linux']['dblib'] = [
                'resolved_key' => $dblibKey,
                'config' => $this->registry->getFlatConfig($dblibKey),
            ];
        }

        if ($map === []) {
            throw new RuntimeException("Sybase base connection not configured: {$baseKey}");
        }

        return $this->resolveSybaseVariantMap($baseKey, $map, 'auto');
    }

    private function resolveSybaseVariantMap(string $requestedCode, array $variantMap, string $driverMode = 'auto'): array
    {
        $preferences = $this->runtimeConfig->getOsFamily() === 'windows'
            ? ['odbc', 'dsn', 'dblib']
            : ['dblib', 'odbc', 'dsn'];
        return $this->resolveVariantMap($requestedCode, 'Sybase', $variantMap, $preferences, $driverMode);
    }

    private function resolveMssqlVariantMap(string $requestedCode, array $variantMap, string $driverMode = 'auto'): array
    {
        $preferences = $this->runtimeConfig->getOsFamily() === 'windows'
            ? ['sqlsrv', 'odbc', 'dblib']
            : ['odbc', 'dblib', 'sqlsrv'];
        return $this->resolveVariantMap($requestedCode, 'MSSQL', $variantMap, $preferences, $driverMode);
    }

    /**
     * Select only from the exact runtime OS and then the explicit `any` scope.
     * A variant belonging to the other OS is never considered.
     */
    private function resolveVariantMap(
        string $requestedCode,
        string $family,
        array $variantMap,
        array $preferences,
        string $driverMode = 'auto',
    ): array {
        $os = $this->runtimeConfig->getOsFamily();
        $drivers = array_map('strtolower', $this->availableDrivers ?? PDO::getAvailableDrivers());
        $driverMode = strtolower(trim($driverMode));
        $orderedDrivers = $driverMode !== '' && $driverMode !== 'auto'
            ? array_values(array_unique(array_merge([$driverMode], $preferences)))
            : $preferences;
        $candidates = [];

        foreach ([$os, 'any'] as $scope) {
            $scopeVariants = is_array($variantMap[$scope] ?? null) ? $variantMap[$scope] : [];
            foreach ($orderedDrivers as $variantKey) {
                $entry = $scopeVariants[$variantKey] ?? null;
                if (!is_array($entry) || empty($entry['config'])) {
                    continue;
                }
                $pdoDriver = strtolower((string)($entry['config']['driver'] ?? ($variantKey === 'dsn' ? 'odbc' : $variantKey)));
                if (!in_array($pdoDriver, $drivers, true)) {
                    continue;
                }
                $candidates[] = $entry;
            }
        }

        $primary = $candidates[0] ?? null;
        $fallback = $candidates[1] ?? null;
        if (!is_array($primary)) {
            throw str_starts_with($requestedCode, 'dbx_')
                ? AdditionalDatabaseException::driverUnavailable($family, $requestedCode)
                : new RuntimeException("No suitable {$family} driver variant found for {$requestedCode}");
        }

        return [
            'requested_code' => $requestedCode,
            'resolved_key' => (string)($primary['resolved_key'] ?? $requestedCode),
            'config' => $primary['config'],
            'fallback_key' => is_array($fallback) ? (string)($fallback['resolved_key'] ?? '') : null,
            'fallback_config' => is_array($fallback) ? ($fallback['config'] ?? null) : null,
        ];
    }
}
