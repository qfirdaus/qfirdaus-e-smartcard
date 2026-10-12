<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// setting/helper/rate_limit_helper.php
declare(strict_types=1);

/**
 * ✅ PHASE 2: Rate Limiting Helper
 * 
 * File-based rate limiting untuk AJAX endpoints
 * Prevents abuse/DoS attacks by limiting requests per user per time window
 * 
 * @param string $key Unique identifier untuk rate limit (usually user ID or session ID)
 * @param int $maxRequests Maximum requests allowed dalam time window
 * @param int $windowSeconds Time window dalam seconds (default: 60)
 * @return bool True jika request allowed, false jika rate limit exceeded
 */
function check_rate_limit(string $key, int $maxRequests = 60, int $windowSeconds = 60): bool
{
    // ✅ Configurable via environment variables
    $maxRequests = (int)($_ENV['APC_RATE_LIMIT_MAX'] ?? $maxRequests);
    $windowSeconds = (int)($_ENV['APC_RATE_LIMIT_WINDOW'] ?? $windowSeconds);
    $cleanupAge = 3600; // Cleanup files older than 1 hour
    
    // ✅ Create rate limit directory inside project (avoid open_basedir + temp)
    // Support deployments with or without /app folder:
    // - /var/www/app\e-prestasi\app\cache\apc_rate
    // - /var/www/app\e-prestasi\cache\apc_rate
    $candidates = [
        realpath(__DIR__ . '/../../cache'),
        realpath(__DIR__ . '/../../../cache'),
        __DIR__ . '/../../cache',
        __DIR__ . '/../../../cache',
    ];
    $rateBase = null;
    foreach ($candidates as $cand) {
        if ($cand && is_dir($cand)) {
            $rateBase = $cand;
            break;
        }
    }
    if ($rateBase === null) {
        // Fallback: use project root relative path
        $rateBase = __DIR__ . '/../../../cache';
    }
    $rateLimitDir = rtrim($rateBase, '//') . '/apc_rate';
    if (!is_dir($rateLimitDir)) {
        @mkdir($rateLimitDir, 0700, true);
    }
    
    // ✅ Rate limit file based on key
    $rateLimitFile = $rateLimitDir . '/' . md5($key) . '.tmp';
    
    // ✅ Cleanup old rate limit files (run occasionally, not every request)
    if (mt_rand(1, 100) === 1) { // 1% chance per request
        $files = glob($rateLimitDir . '/*.tmp');
        $now = time();
        foreach ($files as $file) {
            if (filemtime($file) < ($now - $cleanupAge)) {
                @unlink($file);
            }
        }
    }
    
    // ✅ Improved rate limiting dengan better concurrency handling
    $rateData = ['count' => 0, 'reset' => time() + $windowSeconds];
    if (file_exists($rateLimitFile)) {
        $fp = @fopen($rateLimitFile, 'r+');
        if ($fp && flock($fp, LOCK_EX | LOCK_NB)) { // Non-blocking lock
            $content = stream_get_contents($fp);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $rateData = $decoded;
                }
            }
            
            // Check if window expired
            if (time() > $rateData['reset']) {
                $rateData = ['count' => 0, 'reset' => time() + $windowSeconds];
            }
            
            $rateData['count']++;
            if ($rateData['count'] > $maxRequests) {
                flock($fp, LOCK_UN);
                fclose($fp);
                return false; // Rate limit exceeded
            }
            
            // Update file
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($rateData));
            flock($fp, LOCK_UN);
            fclose($fp);
        } else {
            // If lock failed, assume rate limit not exceeded (fail open)
            // This prevents blocking if file system is slow
            return true;
        }
    } else {
        // First request - create file
        $rateData = ['count' => 1, 'reset' => time() + $windowSeconds];
        @file_put_contents($rateLimitFile, json_encode($rateData), LOCK_EX);
    }
    
    return true; // Request allowed
}

/**
 * ✅ Apply rate limiting untuk AJAX endpoint
 * 
 * @param string $endpointName Name of endpoint untuk logging
 * @param int $maxRequests Maximum requests (default: 60)
 * @param int $windowSeconds Time window (default: 60)
 * @return void Exits with 429 if rate limit exceeded
 */
function apply_rate_limit(string $endpointName = 'apc_ajax', int $maxRequests = 60, int $windowSeconds = 60): void
{
    // ✅ Generate rate limit key based on user ID or session ID
    $userId = $_SESSION['auth']['f_userID'] ?? null;
    $rateLimitKey = $endpointName . '_' . ($userId ?? session_id());
    
    if (!check_rate_limit($rateLimitKey, $maxRequests, $windowSeconds)) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => true,
            'message' => 'Terlalu banyak permintaan. Sila cuba lagi selepas beberapa saat.',
            'retry_after' => $windowSeconds
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}




