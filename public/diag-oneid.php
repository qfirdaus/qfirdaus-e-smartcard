<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/includes/sso-config.php';

$ssoConfig = function_exists('sso_shared_config') ? sso_shared_config() : [];
$idpDomain = (string)($ssoConfig['idp_domain'] ?? 'https://oneid.upnm.edu.my/');
$host = (string)(parse_url($idpDomain, PHP_URL_HOST) ?: 'oneid.upnm.edu.my');
$url = rtrim($idpDomain, '/') . '/api.php';

function out(string $label, $value): void
{
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } elseif ($value === false) {
        $value = 'false';
    } elseif ($value === null) {
        $value = 'null';
    }

    echo $label . ': ' . $value . PHP_EOL;
}

echo "=== OneID Runtime Diagnostic ===" . PHP_EOL;
out('timestamp', date('c'));
out('php_version', PHP_VERSION);
out('server_software', $_SERVER['SERVER_SOFTWARE'] ?? null);
out('server_name', $_SERVER['SERVER_NAME'] ?? null);
out('http_host', $_SERVER['HTTP_HOST'] ?? null);
out('remote_addr', $_SERVER['REMOTE_ADDR'] ?? null);
out('host', $host);
out('url', $url);
echo PHP_EOL;

echo "=== DNS ===" . PHP_EOL;
$resolved = gethostbyname($host);
out('gethostbyname', $resolved);
out('dns_resolved', $resolved !== $host ? 'yes' : 'no');

if (function_exists('dns_get_record')) {
    $dnsRecords = @dns_get_record($host, DNS_A);
    out('dns_get_record_count', is_array($dnsRecords) ? count($dnsRecords) : 0);
    out('dns_get_record', $dnsRecords);
} else {
    out('dns_get_record', 'function_unavailable');
}
echo PHP_EOL;

echo "=== OpenSSL / cURL ===" . PHP_EOL;
out('openssl_loaded', extension_loaded('openssl') ? 'yes' : 'no');
out('curl_loaded', extension_loaded('curl') ? 'yes' : 'no');

if (!extension_loaded('curl')) {
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/plain',
]);

$body = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
$namelookupTime = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
$connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
$totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
curl_close($ch);

out('curl_errno', $errno);
out('curl_error', $error !== '' ? $error : null);
out('http_code', $httpCode);
out('primary_ip', $primaryIp ?: null);
out('namelookup_time', $namelookupTime);
out('connect_time', $connectTime);
out('total_time', $totalTime);
out('body_preview', is_string($body) ? mb_substr($body, 0, 500) : $body);
