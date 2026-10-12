<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/system-resources.php
// Read-only system resources snapshot (admin-only)
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    // SECURITY CRITICAL – DO NOT MODIFY: feature flag must gate exposure
    if (!defined('ENABLE_SYSTEM_RESOURCES') || !ENABLE_SYSTEM_RESOURCES) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => 'Disabled'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // SECURITY CRITICAL – DO NOT MODIFY: admin-only access enforcement
    $activeRoleId = (int)($_SESSION['group_active_id'] ?? 0);
    $roleAdminSaId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
    if ($activeRoleId <= 0 || $roleAdminSaId <= 0 || $activeRoleId !== $roleAdminSaId) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resources = [];
    $cpuUsagePct = null;
    $memUsagePct = null;

    $resourceStatus = function(float $pct, float $okMax, float $warnMax): string {
        if ($pct < $okMax) return 'OK';
        if ($pct < $warnMax) return 'Warning';
        return 'Critical';
    };

    // CPU Usage (best-effort)
    $cpuStatus = 'Unknown';
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $load1 = is_array($load) ? (float)$load[0] : null;
        $cpuCores = null;
        $cpuInfoFile = '/proc/cpuinfo';
        if (is_file($cpuInfoFile) && is_readable($cpuInfoFile)) {
            $cpuCores = preg_match_all('/^processor\\s*:/m', (string)@file_get_contents($cpuInfoFile));
        }
        if ($load1 !== null && $cpuCores && $cpuCores > 0) {
            $cpuUsagePct = min(100, max(0, ($load1 / $cpuCores) * 100));
            $cpuStatus = $resourceStatus($cpuUsagePct, 70, 85);
        }
    }

    // Memory Usage (best-effort)
    $memStatus = 'Unknown';
    $memInfoFile = '/proc/meminfo';
    if (is_file($memInfoFile) && is_readable($memInfoFile)) {
        $data = (string)@file_get_contents($memInfoFile);
        if (preg_match('/MemTotal:\\s+(\\d+)/', $data, $m1) && preg_match('/MemAvailable:\\s+(\\d+)/', $data, $m2)) {
            $total = (float)$m1[1];
            $avail = (float)$m2[1];
            if ($total > 0) {
                $memUsagePct = min(100, max(0, (1 - ($avail / $total)) * 100));
                $memStatus = $resourceStatus($memUsagePct, 75, 90);
            }
        }
    }

    // Disk Usage
    $diskStatus = 'Unknown';
    $basePath = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $free = @disk_free_space($basePath);
    $total = @disk_total_space($basePath);
    $diskUsagePct = null;
    if ($free !== false && $total !== false && $total > 0) {
        $diskUsagePct = min(100, max(0, (1 - ($free / $total)) * 100));
        $diskStatus = $resourceStatus($diskUsagePct, 80, 90);
    }

    $resources[] = ['name' => 'CPU', 'usage' => $cpuUsagePct, 'status' => $cpuStatus];
    $resources[] = ['name' => 'Memory', 'usage' => $memUsagePct, 'status' => $memStatus];
    $resources[] = ['name' => 'Disk', 'usage' => $diskUsagePct, 'status' => $diskStatus];

    // CPU history (session, 5 minutes, max 10 points)
    $now = time();
    $hist = $_SESSION['sysres_cpu_history'] ?? [];
    if (is_array($hist)) {
        $hist = array_filter($hist, function($p) use ($now) {
            return is_array($p) && isset($p['t'], $p['v']) && ($now - (int)$p['t'] <= 300);
        });
    } else {
        $hist = [];
    }
    if ($cpuUsagePct !== null) {
        $hist[] = ['t' => $now, 'v' => (float)$cpuUsagePct];
    }
    $hist = array_slice($hist, -10);
    $_SESSION['sysres_cpu_history'] = $hist;
    $cpuHistory = array_map(fn($p) => (float)$p['v'], $hist);

    echo json_encode([
        'error' => false,
        'resources' => $resources,
        'cpu_history' => $cpuHistory,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Server error'], JSON_UNESCAPED_UNICODE);
}
