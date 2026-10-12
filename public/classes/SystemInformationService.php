<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * Read-only, redacted system diagnostics for the Super Admin configuration page.
 */

declare(strict_types=1);

final class SystemInformationService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $projectRoot
    ) {
    }

    public function build(): array
    {
        return [
            'summary' => $this->buildSummary(),
            'phpSettings' => $this->buildPhpSettings(),
            'phpSettingsProfile' => ucfirst($this->phpSettingsProfile()),
            'configuration' => $this->buildConfiguration(),
            'folders' => $this->buildFolderPermissions(),
            'phpInformation' => $this->buildPhpInformation(),
            'generatedAt' => date(DATE_ATOM),
        ];
    }

    private function buildSummary(): array
    {
        $database = $this->databaseInformation();

        return [
            $this->row('IQS Framework Version', $this->frameworkVersion()),
            $this->row('Application Environment', $this->environmentLabel()),
            $this->row('PHP Version', PHP_VERSION),
            $this->row('PHP Built On', php_uname()),
            $this->row('Database Type', $database['driver']),
            $this->row('Database Version', $database['version']),
            $this->row('Database Collation', $database['database_collation']),
            $this->row('Database Connection Collation', $database['connection_collation']),
            $this->row('Database Character Set', $database['connection_charset']),
            $this->row('Database Connection Encryption', $database['encryption']),
            $this->row('Database Server Supports Encryption', $database['encryption_supported']),
            $this->row('Web Server', $this->serverValue('SERVER_SOFTWARE')),
            $this->row('WebServer to PHP Interface', PHP_SAPI),
            $this->row('Server Timezone', date_default_timezone_get()),
            $this->row('User Agent', $this->serverValue('HTTP_USER_AGENT')),
        ];
    }

    private function databaseInformation(): array
    {
        $result = [
            'driver' => $this->safePdoAttribute(PDO::ATTR_DRIVER_NAME),
            'version' => 'Unavailable',
            'database_collation' => 'Unavailable',
            'connection_collation' => 'Unavailable',
            'connection_charset' => 'Unavailable',
            'encryption' => 'Unavailable',
            'encryption_supported' => 'Unavailable',
        ];

        try {
            $version = $this->pdo->query('SELECT VERSION()');
            $result['version'] = (string)($version?->fetchColumn() ?: 'Unavailable');
        } catch (Throwable) {
        }

        try {
            $statement = $this->pdo->query(
                'SELECT @@collation_database AS database_collation, '
                . '@@collation_connection AS connection_collation, '
                . '@@character_set_connection AS connection_charset'
            );
            $row = $statement?->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach (['database_collation', 'connection_collation', 'connection_charset'] as $key) {
                if (isset($row[$key]) && is_scalar($row[$key])) {
                    $result[$key] = (string)$row[$key];
                }
            }
        } catch (Throwable) {
        }

        try {
            $statement = $this->pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
            $row = $statement?->fetch(PDO::FETCH_NUM) ?: [];
            $cipher = trim((string)($row[1] ?? ''));
            $result['encryption'] = $cipher !== '' ? $cipher : 'None';
        } catch (Throwable) {
        }

        try {
            $statement = $this->pdo->query("SHOW VARIABLES LIKE 'have_ssl'");
            $row = $statement?->fetch(PDO::FETCH_NUM) ?: [];
            $supported = strtoupper(trim((string)($row[1] ?? '')));
            $result['encryption_supported'] = match ($supported) {
                'YES' => 'Yes',
                'NO', 'DISABLED' => 'No',
                default => 'Unavailable',
            };
        } catch (Throwable) {
        }

        return $result;
    }

    private function buildPhpSettings(): array
    {
        $environment = $this->phpSettingsProfile();
        $definitions = [
            ['label' => 'Memory Limit', 'key' => 'memory_limit', 'recommended' => '256M-512M'],
            ['label' => 'Maximum Execution Time', 'key' => 'max_execution_time', 'recommended' => '60-120 seconds'],
            ['label' => 'Maximum Input Time', 'key' => 'max_input_time', 'recommended' => '60-120 seconds'],
            ['label' => 'POST Maximum Size', 'key' => 'post_max_size', 'recommended' => '100M, not below upload limit'],
            ['label' => 'Upload Maximum Filesize', 'key' => 'upload_max_filesize', 'recommended' => 'Up to 100M for IQS uploads'],
            ['label' => 'Maximum File Uploads', 'key' => 'max_file_uploads', 'recommended' => '20-50'],
            ['label' => 'Display Errors', 'key' => 'display_errors', 'recommended' => 'Off'],
            ['label' => 'Log Errors', 'key' => 'log_errors', 'recommended' => 'On'],
            ['label' => 'Error Reporting', 'key' => 'error_reporting', 'recommended' => 'E_ALL & ~E_DEPRECATED (22527)'],
            ['label' => 'Session Save Handler', 'key' => 'session.save_handler', 'recommended' => 'files; Redis for multi-node/high load'],
            ['label' => 'Session GC Max Lifetime', 'key' => 'session.gc_maxlifetime', 'recommended' => '28800 seconds (8 hours)'],
            ['label' => 'PHP Timezone', 'key' => 'date.timezone', 'recommended' => 'Asia/Kuala_Lumpur'],
            ['label' => 'Realpath Cache Size', 'key' => 'realpath_cache_size', 'recommended' => '16M'],
            ['label' => 'Realpath Cache TTL', 'key' => 'realpath_cache_ttl', 'recommended' => '600 seconds'],
            ['label' => 'OPcache Enabled', 'key' => 'opcache.enable', 'recommended' => 'On'],
            ['label' => 'OPcache Memory', 'key' => 'opcache.memory_consumption', 'recommended' => '256 MB'],
            ['label' => 'OPcache Interned Strings', 'key' => 'opcache.interned_strings_buffer', 'recommended' => '16 MB'],
            ['label' => 'OPcache Maximum Files', 'key' => 'opcache.max_accelerated_files', 'recommended' => '20000'],
            ['label' => 'OPcache Revalidate Frequency', 'key' => 'opcache.revalidate_freq', 'recommended' => '30 seconds'],
            ['label' => 'JIT Buffer Size', 'key' => 'opcache.jit_buffer_size', 'recommended' => '0 for web workloads'],
        ];

        $rows = [];
        foreach ($definitions as $definition) {
            $value = ini_get($definition['key']);
            $display = $value === false || $value === '' ? 'Not set' : (string)$value;
            if (in_array($definition['key'], ['display_errors', 'log_errors', 'opcache.enable'], true)) {
                $display = $this->iniBoolean($value) ? 'On' : 'Off';
            }
            $assessment = $this->assessPhpSetting($definition['key'], $display, $environment);
            $rows[] = [
                'setting' => $definition['label'],
                'value' => $display,
                'recommended' => $definition['recommended'],
                'status' => $assessment['status'],
                'note' => $assessment['note'],
            ];
        }

        return $rows;
    }

    private function assessPhpSetting(string $key, string $value, string $environment): array
    {
        $number = (int)$value;
        $bytes = $this->iniSizeToBytes($value);
        $mb = $bytes > 0 ? $bytes / 1048576 : 0;

        return match ($key) {
            'memory_limit' => $mb >= 256 && $mb <= 512
                ? $this->assessment('optimized', 'Balanced headroom for IQS reports and uploads.')
                : ($mb >= 128 ? $this->assessment('acceptable', 'Usable, but verify aggregate FPM worker memory.') : $this->assessment('action', 'Too low for heavier IQS workflows.')),
            'max_execution_time' => $number >= 60 && $number <= 120
                ? $this->assessment('optimized', 'Suitable for reports, exports and external integrations.')
                : ($number >= 30 ? $this->assessment('acceptable', 'Adequate for normal requests.') : $this->assessment('action', 'Long-running IQS requests may be terminated.')),
            'max_input_time' => $number >= 60 && $number <= 120
                ? $this->assessment('optimized', 'Suitable for normal form and upload processing.')
                : $this->assessment('review', 'Review against upload and import workloads.'),
            'post_max_size' => $mb >= 100
                ? $this->assessment('optimized', 'Matches the IQS 100M upload profile.')
                : $this->assessment('review', 'Must not be lower than upload_max_filesize.'),
            'upload_max_filesize' => $mb >= 100 && $mb <= 128
                ? $this->assessment('optimized', 'Supports the approved IQS upload ceiling.')
                : ($mb > 128 ? $this->assessment('review', 'A larger limit increases upload and storage risk.') : $this->assessment('acceptable', 'Suitable only if modules do not require 100M uploads.')),
            'max_file_uploads' => $number >= 20 && $number <= 50
                ? $this->assessment('optimized', 'Balanced batch-upload capacity.')
                : $this->assessment('review', 'Very low or very high batch limits should be justified.'),
            'display_errors' => $value === 'Off'
                ? $this->assessment('optimized', 'Prevents runtime details from leaking to users.')
                : $this->assessment('action', 'Must be Off outside local development.'),
            'log_errors' => $value === 'On'
                ? $this->assessment('optimized', 'Runtime failures remain observable in logs.')
                : $this->assessment('action', 'Enable error logging for operational visibility.'),
            'error_reporting' => $number === (E_ALL & ~E_DEPRECATED)
                ? $this->assessment('optimized', 'Captures actionable errors without PHP 8.4 deprecation noise.')
                : ($number === E_ALL ? $this->assessment('acceptable', 'Useful during compatibility testing but may generate noisy logs.') : $this->assessment('review', 'Confirm that important error classes are not suppressed.')),
            'session.save_handler' => strtolower($value) === 'files'
                ? $this->assessment('acceptable', 'Safe for this single-server deployment; Redis is required before using a managed session handler.')
                : $this->assessment('optimized', 'Managed session storage is suitable for distributed deployments.'),
            'session.gc_maxlifetime' => $number === 28800
                ? $this->assessment('optimized', 'Matches the current eight-hour IQS session policy.')
                : $this->assessment('review', 'Align this value with the configured application session policy.'),
            'date.timezone' => $value === 'Asia/Kuala_Lumpur'
                ? $this->assessment('optimized', 'Matches the server and application operating timezone.')
                : $this->assessment('review', 'Use Asia/Kuala_Lumpur for consistent timestamps.'),
            'realpath_cache_size' => $mb >= 16
                ? $this->assessment('optimized', 'Enough path cache for the framework and modules.')
                : $this->assessment('review', 'Increase to reduce repeated filesystem path resolution.'),
            'realpath_cache_ttl' => $number >= 600
                ? $this->assessment('optimized', 'Uses the approved performance-oriented filesystem cache lifetime.')
                : $this->assessment('review', 'Increase to 600 seconds for the approved performance profile.'),
            'opcache.enable' => $value === 'On'
                ? $this->assessment('optimized', 'Compiled PHP code caching is active.')
                : $this->assessment('action', 'OPcache should be enabled for web performance.'),
            'opcache.memory_consumption' => $number >= 256
                ? $this->assessment('optimized', 'Provides sufficient shared cache headroom for IQS applications.')
                : $this->assessment('review', 'Increase shared OPcache memory to 256 MB.'),
            'opcache.interned_strings_buffer' => $number >= 16
                ? $this->assessment('optimized', 'Provides sufficient interned-string capacity.')
                : $this->assessment('review', 'Increase to 16 MB for the framework footprint.'),
            'opcache.max_accelerated_files' => $number >= 20000
                ? $this->assessment('optimized', 'Enough hash capacity for framework and downstream files.')
                : $this->assessment('review', 'Increase to 20000 to avoid hash pressure.'),
            'opcache.revalidate_freq' => $number >= 15 && $number <= 30
                ? $this->assessment('optimized', 'Reduces filesystem checks while retaining timestamp validation.')
                : $this->assessment('review', 'Set to 30 seconds for the approved performance profile.'),
            'opcache.jit_buffer_size' => in_array(strtolower($value), ['0', '0m'], true)
                ? $this->assessment('optimized', 'Avoids reserving unused JIT memory for web/database workloads.')
                : $this->assessment('review', 'JIT normally provides little benefit for this workload.'),
            default => $this->assessment('acceptable', 'No critical issue detected.'),
        };
    }

    private function assessment(string $status, string $note): array
    {
        return ['status' => $status, 'note' => $note];
    }

    private function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return $value === '-1' ? PHP_INT_MAX : 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float)$value;
        return (int)match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function buildConfiguration(): array
    {
        $definitions = [
            ['System Name', 'system.name', 'IQS Framework'],
            ['System Version', 'system.version', $this->frameworkVersion()],
            ['Application Environment', null, $this->environmentLabel()],
            ['Default Language', null, (string)($_SESSION['lang'] ?? 'ms')],
            ['Site Title', 'site.title', 'IQS Framework'],
            ['Default Home Route', 'site.default_home', 'pages/dashboard.php'],
            ['Support Email', 'system.support', 'Not configured'],
            ['Session Idle Timeout', 'session.idle_timeout_minutes', '30'],
            ['Manual Upload Limit', 'upload.manual_max_mb', '10'],
            ['Timezone', null, date_default_timezone_get()],
            ['Database Credentials', null, 'Configured (hidden)'],
            ['Environment Secrets', null, 'Protected (not displayed)'],
        ];

        $rows = [];
        foreach ($definitions as [$label, $key, $fallback]) {
            $value = $key !== null && function_exists('app_config')
                ? app_config($key, $fallback)
                : $fallback;
            $rows[] = $this->row((string)$label, $this->scalarText($value, (string)$fallback));
        }
        return $rows;
    }

    private function buildFolderPermissions(): array
    {
        $publicRoot = $this->projectRoot . '/public';
        $sessionPath = $this->normaliseSessionPath((string)ini_get('session.save_path'));
        $definitions = [
            ['Project Root', $this->projectRoot, false, '0755', 'Application code should be read-only to PHP-FPM.'],
            ['Public Root', $publicRoot, false, '0755', 'Public application code should be read-only to PHP-FPM.'],
            ['Assets', $publicRoot . '/assets', false, '0755', 'Static application assets should be read-only to PHP-FPM.'],
            ['Generated Pages', $publicRoot . '/pages', true, '2775', 'Required by the Super Admin Template Generator.'],
            ['Generated Controllers', $publicRoot . '/controllers', true, '2775', 'Required by the Super Admin Template Generator.'],
            ['Generated CSS', $publicRoot . '/assets/css/pages', true, '2775', 'Required by the Super Admin Template Generator.'],
            ['Custom Languages', $publicRoot . '/lang/custom', true, '2775', 'Required when generated language entries are updated.'],
            ['Application Logs', $publicRoot . '/log', true, '2770', 'Must be writable and blocked from HTTP access.'],
            ['Application Cache', $publicRoot . '/cache', true, '2770', 'Must be writable and blocked from HTTP access.'],
            ['Uploads', $publicRoot . '/uploads', true, '2770', 'Must be writable; PHP execution must be blocked.'],
            ['Manual Storage', $this->projectRoot . '/storage/manuals', true, '2770', 'Private manual files stored outside the public web root.'],
            ['PHP Session Path', $sessionPath, true, '1733', 'Managed by the PHP package with restricted sticky permissions.'],
            ['PHP Upload Temp', (string)(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()), true, '1777', 'Shared temporary directory with sticky-bit protection.'],
        ];

        $rows = [];
        foreach ($definitions as [$label, $path, $expectsWritable, $expectedPermission, $note]) {
            $exists = $path !== '' && file_exists($path);
            $writable = $exists && is_writable($path);
            $status = 'missing';
            if ($exists) {
                $status = $expectsWritable
                    ? ($writable ? 'writable' : 'blocked')
                    : ($writable ? 'review' : 'protected');
            }
            $rows[] = [
                'label' => $label,
                'path' => $this->displayPath($path),
                'exists' => $exists,
                'readable' => $exists && is_readable($path),
                'writable' => $writable,
                'expects_writable' => $expectsWritable,
                'expected_permissions' => $expectedPermission,
                'note' => $note,
                'permissions' => $exists ? $this->permissionString($path) : '----',
                'owner' => $exists ? $this->ownerLabel($path) : '-',
                'status' => $status,
            ];
        }
        return $rows;
    }

    private function buildPhpInformation(): array
    {
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $memory = is_array($opcache) ? ($opcache['memory_usage'] ?? []) : [];
        $statistics = is_array($opcache) ? ($opcache['opcache_statistics'] ?? []) : [];

        return [
            'general' => [
                $this->row('PHP Version', PHP_VERSION),
                $this->row('Server API', PHP_SAPI),
                $this->row('Architecture', PHP_INT_SIZE === 8 ? '64-bit' : '32-bit'),
                $this->row('Loaded Configuration File', php_ini_loaded_file() ?: 'None'),
                $this->row('Additional INI Files', php_ini_scanned_files() ?: 'None'),
                $this->row('Zend Engine', zend_version()),
                $this->row('Debug Build', PHP_DEBUG ? 'Yes' : 'No'),
                $this->row('Thread Safety', PHP_ZTS ? 'Enabled' : 'Disabled'),
            ],
            'opcache' => [
                $this->row('Enabled', !empty($opcache['opcache_enabled']) ? 'Yes' : 'No'),
                $this->row('Cache Full', !empty($opcache['cache_full']) ? 'Yes' : 'No'),
                $this->row('Used Memory', $this->formatBytes((int)($memory['used_memory'] ?? 0))),
                $this->row('Free Memory', $this->formatBytes((int)($memory['free_memory'] ?? 0))),
                $this->row('Wasted Memory', $this->formatBytes((int)($memory['wasted_memory'] ?? 0))),
                $this->row('Cached Scripts', (string)($statistics['num_cached_scripts'] ?? 0)),
                $this->row('Hit Rate', isset($statistics['opcache_hit_rate']) ? number_format((float)$statistics['opcache_hit_rate'], 2) . '%' : 'Unavailable'),
            ],
            'extensions' => $this->extensionInformation(),
        ];
    }

    private function extensionInformation(): array
    {
        $important = ['PDO', 'pdo_mysql', 'pdo_dblib', 'mysqli', 'curl', 'gd', 'mbstring', 'openssl', 'xml', 'zip', 'Zend OPcache'];
        $rows = [];
        foreach ($important as $extension) {
            $loaded = extension_loaded($extension);
            $version = $loaded ? phpversion($extension) : false;
            $rows[] = [
                'extension' => $extension,
                'loaded' => $loaded,
                'version' => $version === false ? ($loaded ? 'Bundled' : '-') : (string)$version,
            ];
        }
        return $rows;
    }

    private function frameworkVersion(): string
    {
        if (function_exists('app_current_version')) {
            return (string)app_current_version();
        }
        $versionFile = $this->projectRoot . '/VERSION';
        return is_readable($versionFile) ? trim((string)file_get_contents($versionFile)) : 'Unknown';
    }

    private function environmentLabel(): string
    {
        if (function_exists('app_env')) {
            return ucfirst(strtolower(trim((string)app_env())));
        }
        $value = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
        return ucfirst(strtolower(trim((string)$value)));
    }

    private function phpSettingsProfile(): string
    {
        $environment = strtolower($this->environmentLabel());
        if (in_array($environment, ['development', 'staging'], true)) {
            return 'staging';
        }

        $hostname = strtolower((string)gethostname());
        if (str_contains($hostname, 'staging') || str_contains($hostname, 'stage')) {
            return 'staging';
        }

        return 'production';
    }

    private function serverValue(string $key): string
    {
        $value = trim((string)($_SERVER[$key] ?? ''));
        return $value !== '' ? $value : 'Unavailable';
    }

    private function safePdoAttribute(int $attribute): string
    {
        try {
            return (string)$this->pdo->getAttribute($attribute);
        } catch (Throwable) {
            return 'Unavailable';
        }
    }

    private function iniBoolean(string|false $value): bool
    {
        return in_array(strtolower((string)$value), ['1', 'on', 'yes', 'true'], true);
    }

    private function normaliseSessionPath(string $value): string
    {
        if (str_contains($value, ';')) {
            $parts = explode(';', $value);
            $value = (string)end($parts);
        }
        return trim($value);
    }

    private function displayPath(string $path): string
    {
        if ($path === '') {
            return 'Not configured';
        }
        if (str_starts_with($path, $this->projectRoot)) {
            $relative = ltrim(substr($path, strlen($this->projectRoot)), '/');
            return $relative === '' ? '[project root]' : '[project root]/' . $relative;
        }
        return $path;
    }

    private function permissionString(string $path): string
    {
        $permissions = @fileperms($path);
        return $permissions === false ? '----' : substr(sprintf('%o', $permissions), -4);
    }

    private function ownerLabel(string $path): string
    {
        $owner = @fileowner($path);
        $group = @filegroup($path);
        $ownerName = $owner === false ? '-' : (string)$owner;
        $groupName = $group === false ? '-' : (string)$group;
        if ($owner !== false && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid($owner);
            $ownerName = is_array($info) ? (string)($info['name'] ?? $ownerName) : $ownerName;
        }
        if ($group !== false && function_exists('posix_getgrgid')) {
            $info = @posix_getgrgid($group);
            $groupName = is_array($info) ? (string)($info['name'] ?? $groupName) : $groupName;
        }
        return $ownerName . ':' . $groupName;
    }

    private function scalarText(mixed $value, string $fallback): string
    {
        return is_scalar($value) || $value === null ? (string)($value ?? $fallback) : $fallback;
    }

    private function row(string $setting, string $value): array
    {
        return ['setting' => $setting, 'value' => $value !== '' ? $value : 'Unavailable'];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KiB', 'MiB', 'GiB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
