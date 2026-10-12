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

require_once __DIR__ . '/../classes/SystemCacheMaintenanceService.php';

final class SystemCacheMaintenanceController
{
    private SystemCacheMaintenanceService $service;
    private array $locations;

    public function __construct(?SystemCacheMaintenanceService $service = null)
    {
        $this->service = $service ?? new SystemCacheMaintenanceService();
        $this->locations = $this->service->discover();
    }

    public function getLocations(): array
    {
        return $this->locations;
    }

    public function getSummary(): array
    {
        return $this->service->summary($this->locations);
    }
}
