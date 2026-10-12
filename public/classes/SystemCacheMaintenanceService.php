<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

final class SystemCacheMaintenanceService
{
    private string $projectRoot;

    /** @var string[] */
    private array $standardCacheRoots = [
        'app/cache',
        'public/cache',
        'storage/cache',
    ];

    /** @var array<string,bool> */
    private array $preservedFiles = [
        '.gitkeep' => true,
        '.htaccess' => true,
        '.system-cache-maintenance.lock' => true,
    ];

    /** @var resource|null */
    private $operationLock = null;

    public function __construct(?string $projectRoot = null)
    {
        $resolvedRoot = realpath($projectRoot ?? dirname(__DIR__, 2));
        if ($resolvedRoot === false) {
            throw new RuntimeException('Project root cannot be resolved.');
        }

        $this->projectRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/');
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function discover(): array
    {
        $locations = [];
        foreach ($this->standardCacheRoots as $relativePath) {
            $absolute = $this->resolveProjectPath($relativePath);
            if ($absolute === null || !is_dir($absolute) || !is_readable($absolute)) {
                continue;
            }

            $locations[] = $this->inspectLocation($absolute, $relativePath);
        }

        return $locations;
    }

    public function summary(array $locations): array
    {
        $files = 0;
        $bytes = 0;
        foreach ($locations as $location) {
            $files += (int)($location['files'] ?? 0);
            $bytes += (int)($location['bytes'] ?? 0);
        }

        return [
            'locations' => count($locations),
            'files' => $files,
            'bytes' => $bytes,
            'size' => self::formatBytes($bytes),
            'opcache' => $this->opcacheStatus(),
            'apcu' => $this->apcuStatus(),
        ];
    }

    public function clear(array $selectedIds = [], bool $clearAll = false): array
    {
        $this->acquireOperationLock();
        try {
            return $this->performClear($selectedIds, $clearAll);
        } finally {
            $this->releaseOperationLock();
        }
    }

    private function performClear(array $selectedIds, bool $clearAll): array
    {
        $locations = $this->discover();
        $selectedLookup = array_fill_keys(array_map('strval', $selectedIds), true);
        $targets = [];

        foreach ($locations as $location) {
            $id = (string)($location['id'] ?? '');
            if ($clearAll || isset($selectedLookup[$id])) {
                $targets[] = $location;
            }
        }

        if (!$clearAll && $targets === []) {
            throw new InvalidArgumentException('No valid cache locations were selected.');
        }

        $filesRemoved = 0;
        $freedBytes = 0;
        $clearedLocations = [];
        $errors = [];

        foreach ($targets as $location) {
            $result = $this->clearLocation((string)$location['absolute_path']);
            $filesRemoved += $result['files_removed'];
            $freedBytes += $result['freed_bytes'];
            $errors = array_merge($errors, $result['errors']);
            $clearedLocations[] = [
                'id' => (string)$location['id'],
                'location' => (string)$location['location'],
                'files_removed' => $result['files_removed'],
                'freed_bytes' => $result['freed_bytes'],
                'freed_size' => self::formatBytes($result['freed_bytes']),
            ];
        }

        return [
            'targets_matched' => count($targets),
            'locations_cleared' => $clearedLocations,
            'files_removed' => $filesRemoved,
            'freed_bytes' => $freedBytes,
            'freed_size' => self::formatBytes($freedBytes),
            'opcache' => $this->resetOpcache(),
            'apcu' => $this->clearApcu(),
            'errors' => $errors,
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return number_format($value, $value >= 10 ? 1 : 2) . ' ' . $unit;
            }
        }

        return number_format($value, 1) . ' TB';
    }

    private function inspectLocation(string $absolutePath, string $relativePath): array
    {
        $files = 0;
        $bytes = 0;
        $lastModified = null;

        foreach ($this->iterateFiles($absolutePath) as $file) {
            if ($this->shouldPreserveFile($file)) {
                continue;
            }

            $files++;
            $size = $file->getSize();
            $mtime = $file->getMTime();
            $bytes += $size;
            $lastModified = max($lastModified ?? 0, $mtime);
        }

        $normalized = $this->normalizePath($absolutePath);

        return [
            'id' => hash('sha256', $normalized),
            'location' => $relativePath,
            'absolute_path' => $normalized,
            'files' => $files,
            'bytes' => $bytes,
            'size' => self::formatBytes($bytes),
            'last_modified' => $lastModified ? date('Y-m-d H:i:s', $lastModified) : '-',
        ];
    }

    private function clearLocation(string $absolutePath): array
    {
        $absolutePath = $this->normalizePath($absolutePath);
        if (!$this->isInsideProject($absolutePath) || !is_dir($absolutePath)) {
            error_log('[SystemCacheMaintenanceService] Invalid cache location: ' . $absolutePath);
            return [
                'files_removed' => 0,
                'freed_bytes' => 0,
                'errors' => [$this->safeRelativePath($absolutePath)],
            ];
        }

        $filesRemoved = 0;
        $freedBytes = 0;
        $errors = [];

        foreach ($this->iterateFiles($absolutePath) as $file) {
            if ($this->shouldPreserveFile($file)) {
                continue;
            }

            $path = $this->normalizePath($file->getPathname());
            if (!$this->isInsideProject($path)) {
                error_log('[SystemCacheMaintenanceService] Rejected cache path outside project: ' . $path);
                $errors[] = $this->safeRelativePath($path);
                continue;
            }

            $size = $file->getSize();
            if (@unlink($path)) {
                $filesRemoved++;
                $freedBytes += $size;
            } else {
                error_log('[SystemCacheMaintenanceService] Unable to remove cache file: ' . $path);
                $errors[] = $this->safeRelativePath($path);
            }
        }

        return [
            'files_removed' => $filesRemoved,
            'freed_bytes' => $freedBytes,
            'errors' => $errors,
        ];
    }

    private function resolveProjectPath(string $relativePath): ?string
    {
        $candidate = $this->projectRoot . '/' . trim(str_replace('\\', '/', $relativePath), '/');
        $resolved = realpath($candidate);
        if ($resolved === false) {
            return null;
        }

        $normalized = $this->normalizePath($resolved);
        return $this->isInsideProject($normalized) ? $normalized : null;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function isInsideProject(string $path): bool
    {
        $path = $this->normalizePath($path);
        return $path === $this->projectRoot || str_starts_with($path, $this->projectRoot . '/');
    }

    private function safeRelativePath(string $path): string
    {
        $normalized = $this->normalizePath($path);
        if ($this->isInsideProject($normalized)) {
            return ltrim(substr($normalized, strlen($this->projectRoot)), '/');
        }
        return basename($normalized);
    }

    private function acquireOperationLock(): void
    {
        $lockPath = $this->projectRoot . '/public/cache/.system-cache-maintenance.lock';
        $lockDirectory = dirname($lockPath);
        if (!is_dir($lockDirectory) || !$this->isInsideProject($lockDirectory)) {
            throw new RuntimeException('Cache maintenance lock directory is unavailable.');
        }

        $handle = fopen($lockPath, 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Cache maintenance is already running.');
        }
        $this->operationLock = $handle;
    }

    private function releaseOperationLock(): void
    {
        if (!is_resource($this->operationLock)) {
            $this->operationLock = null;
            return;
        }
        flock($this->operationLock, LOCK_UN);
        fclose($this->operationLock);
        $this->operationLock = null;
    }

    private function shouldPreserveFile(SplFileInfo $file): bool
    {
        return isset($this->preservedFiles[$file->getBasename()]);
    }

    private function iterateFiles(string $absolutePath): Generator
    {
        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, $flags),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                yield $file;
            }
        }
    }

    private function opcacheStatus(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['available' => false, 'enabled' => false, 'label' => 'Unavailable'];
        }

        $status = @opcache_get_status(false);
        $enabled = is_array($status) && !empty($status['opcache_enabled']);

        return [
            'available' => true,
            'enabled' => $enabled,
            'label' => $enabled ? 'Enabled' : 'Disabled',
        ];
    }

    private function apcuStatus(): array
    {
        if (!function_exists('apcu_enabled')) {
            return ['available' => false, 'enabled' => false, 'label' => 'Unavailable'];
        }

        $enabled = (bool)@apcu_enabled();

        return [
            'available' => true,
            'enabled' => $enabled,
            'label' => $enabled ? 'Enabled' : 'Disabled',
        ];
    }

    private function resetOpcache(): array
    {
        if (!function_exists('opcache_reset')) {
            return ['available' => false, 'success' => false, 'message' => 'Unavailable'];
        }

        $success = (bool)@opcache_reset();
        return [
            'available' => true,
            'success' => $success,
            'message' => $success ? 'Reset' : 'Unable to reset',
        ];
    }

    private function clearApcu(): array
    {
        if (!function_exists('apcu_clear_cache')) {
            return ['available' => false, 'success' => false, 'message' => 'Unavailable'];
        }

        $success = (bool)@apcu_clear_cache();
        return [
            'available' => true,
            'success' => $success,
            'message' => $success ? 'Cleared' : 'Unable to clear',
        ];
    }
}
