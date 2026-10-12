<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
require_once __DIR__ . '/../../classes/Database.php';

if (!function_exists('distance_site_registry')) {
    function distance_site_registry(): array
    {
        return [
            'upnm_kampus' => [
                'code' => 'upnm_kampus',
                'label' => 'Kampus UPNM',
                'address' => 'UNIVERSITI PERTAHANAN NASIONAL MALAYSIA, KEM SUNGAI BESI, 57000 KUALA LUMPUR, WILAYAH PERSEKUTUAN KUALA LUMPUR, MALAYSIA',
                'coords' => [
                    'lat' => 3.052805,
                    'lon' => 101.723300,
                ],
            ],
            'hat_mizan' => [
                'code' => 'hat_mizan',
                'label' => 'HAT Tuanku Mizan',
                'address' => 'Hospital Angkatan Tentera Tuanku Mizan',
                'coords' => [
                    'lat' => 3.209712,
                    'lon' => 101.737811,
                ],
            ],
        ];
    }
}

if (!function_exists('distance_default_site_code')) {
    function distance_default_site_code(): string
    {
        return 'upnm_kampus';
    }
}

if (!function_exists('distance_normalize_site_code')) {
    function distance_normalize_site_code(?string $siteCode): string
    {
        $siteCode = strtolower(trim((string)$siteCode));
        $registry = distance_site_registry();

        return isset($registry[$siteCode]) ? $siteCode : distance_default_site_code();
    }
}

if (!function_exists('distance_site_config')) {
    function distance_site_config(?string $siteCode = null): array
    {
        $registry = distance_site_registry();
        $resolved = distance_normalize_site_code($siteCode);

        return $registry[$resolved] ?? $registry[distance_default_site_code()];
    }
}

if (!function_exists('distance_site_code_from_office_address')) {
    function distance_site_code_from_office_address(?string $officeAddress): string
    {
        $officeAddress = trim((string)$officeAddress);
        if ($officeAddress === '') {
            return distance_default_site_code();
        }

        foreach (distance_site_registry() as $siteCode => $site) {
            if (strcasecmp($officeAddress, trim((string)($site['address'] ?? ''))) === 0) {
                return (string)$siteCode;
            }
        }

        return distance_default_site_code();
    }
}

if (!function_exists('distance_office_address')) {
    function distance_office_address(?string $siteCode = null): string
    {
        return (string)(distance_site_config($siteCode)['address'] ?? '');
    }
}

if (!function_exists('distance_office_coords')) {
    function distance_office_coords(?string $siteCode = null): array
    {
        $site = distance_site_config($siteCode);
        $coords = is_array($site['coords'] ?? null) ? $site['coords'] : [];

        return [
            'lat' => (float)($coords['lat'] ?? 0),
            'lon' => (float)($coords['lon'] ?? 0),
            'display_name' => distance_office_address($siteCode),
        ];
    }
}

if (!function_exists('distance_office_label')) {
    function distance_office_label(?string $siteCode = null): string
    {
        return (string)(distance_site_config($siteCode)['label'] ?? '');
    }
}

if (!function_exists('distance_ors_api_key')) {
    function distance_ors_api_key(): string
    {
        $candidates = [
            $_ENV['ORS_API_KEY'] ?? null,
            $_SERVER['ORS_API_KEY'] ?? null,
            getenv('ORS_API_KEY') ?: null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('distance_tomtom_api_key')) {
    function distance_tomtom_api_key(): string
    {
        $candidates = [
            $_ENV['TOMTOM_API_KEY'] ?? null,
            $_SERVER['TOMTOM_API_KEY'] ?? null,
            getenv('TOMTOM_API_KEY') ?: null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('distance_google_api_key')) {
    function distance_google_api_key(): string
    {
        $candidates = [
            $_ENV['GOOGLE_MAPS_API_KEY'] ?? null,
            $_SERVER['GOOGLE_MAPS_API_KEY'] ?? null,
            getenv('GOOGLE_MAPS_API_KEY') ?: null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('distance_google_strict_mode')) {
    function distance_google_strict_mode(): bool
    {
        return distance_google_api_key() !== '';
    }
}

if (!function_exists('distance_geoapify_api_key')) {
    function distance_geoapify_api_key(): string
    {
        $candidates = [
            $_ENV['GEOAPIFY_API_KEY'] ?? null,
            $_SERVER['GEOAPIFY_API_KEY'] ?? null,
            getenv('GEOAPIFY_API_KEY') ?: null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('distance_cache_dir')) {
    function distance_cache_dir(): string
    {
        $dir = dirname(__DIR__, 2) . '/cache/tmp/bdr-distance';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }
}

if (!function_exists('distance_cache_file')) {
    function distance_cache_file(string $namespace, string $key): string
    {
        return rtrim(distance_cache_dir(), '/\\') . '/' . $namespace . '-' . md5($key) . '.json';
    }
}

if (!function_exists('distance_geo_cache_namespace')) {
    function distance_geo_cache_namespace(): string
    {
        return distance_google_strict_mode() ? 'geo-google-v1' : 'geo-v10';
    }
}

if (!function_exists('distance_route_cache_namespace')) {
    function distance_route_cache_namespace(): string
    {
        return distance_google_strict_mode() ? 'route-google-v1' : 'route-v7';
    }
}

if (!function_exists('distance_result_cache_namespace')) {
    function distance_result_cache_namespace(): string
    {
        return 'result-v2';
    }
}

if (!function_exists('distance_db')) {
    function distance_db(): ?PDO
    {
        static $pdo = false;

        if ($pdo === false) {
            try {
                $pdo = class_exists('Database')
                    ? Database::getInstance('mysql')->getConnection()
                    : null;
            } catch (Throwable $e) {
                error_log('[distance_helper] distance_db failed: ' . $e->getMessage());
                $pdo = null;
            }
        }

        return $pdo instanceof PDO ? $pdo : null;
    }
}

if (!function_exists('distance_result_table_name')) {
    function distance_result_table_name(): string
    {
        return 'tbl_m_staff_distance_cache';
    }
}

if (!function_exists('distance_result_table_ready')) {
    function distance_result_table_ready(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $pdo = distance_db();
        if (!$pdo instanceof PDO) {
            $ready = false;
            return false;
        }

        try {
            $table = distance_result_table_name();
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            if (!$stmt || !$stmt->fetchColumn()) {
                $pdo->exec(
                    "CREATE TABLE {$table} (
                        f_cacheID INT AUTO_INCREMENT PRIMARY KEY,
                        f_cacheKey CHAR(32) NOT NULL,
                        f_stafID VARCHAR(30) NULL,
                        f_siteCode VARCHAR(50) NOT NULL DEFAULT 'upnm_kampus',
                        f_homeAddressHash CHAR(64) NOT NULL,
                        f_homeAddress TEXT NOT NULL,
                        f_homeAddressJson LONGTEXT NULL,
                        f_officeAddress VARCHAR(255) NOT NULL,
                        f_distanceKm DECIMAL(10,2) NULL,
                        f_source VARCHAR(50) NULL,
                        f_routeProvider VARCHAR(50) NULL,
                        f_matchQuality VARCHAR(30) NULL,
                        f_resultJson LONGTEXT NOT NULL,
                        f_status VARCHAR(20) NOT NULL DEFAULT 'SUCCESS',
                        f_lastCalculatedAt DATETIME NOT NULL,
                        f_createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        f_updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_distance_cache_key (f_cacheKey),
                        KEY idx_distance_hash (f_homeAddressHash),
                        KEY idx_distance_staf (f_stafID),
                        KEY idx_distance_site (f_siteCode),
                        KEY idx_distance_site_staf (f_siteCode, f_stafID),
                        KEY idx_distance_site_hash (f_siteCode, f_homeAddressHash),
                        KEY idx_distance_status (f_status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            } else {
                $requiredColumns = [
                    'f_stafID' => "ALTER TABLE {$table} ADD COLUMN f_stafID VARCHAR(30) NULL AFTER f_cacheKey",
                    'f_siteCode' => "ALTER TABLE {$table} ADD COLUMN f_siteCode VARCHAR(50) NOT NULL DEFAULT 'upnm_kampus' AFTER f_stafID",
                ];

                $columnStmt = $pdo->prepare(
                    'SELECT COLUMN_NAME
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = :tableName'
                );
                $columnStmt->execute([':tableName' => $table]);
                $existingColumns = array_map('strtolower', $columnStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $existingColumnMap = array_fill_keys($existingColumns, true);

                foreach ($requiredColumns as $columnName => $ddl) {
                    if (!isset($existingColumnMap[strtolower($columnName)])) {
                        $pdo->exec($ddl);
                    }
                }

                $requiredIndexes = [
                    'idx_distance_staf' => "ALTER TABLE {$table} ADD KEY idx_distance_staf (f_stafID)",
                    'idx_distance_site' => "ALTER TABLE {$table} ADD KEY idx_distance_site (f_siteCode)",
                    'idx_distance_site_staf' => "ALTER TABLE {$table} ADD KEY idx_distance_site_staf (f_siteCode, f_stafID)",
                    'idx_distance_site_hash' => "ALTER TABLE {$table} ADD KEY idx_distance_site_hash (f_siteCode, f_homeAddressHash)",
                ];
                $indexStmt = $pdo->prepare(
                    'SELECT INDEX_NAME
                     FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = :tableName'
                );
                $indexStmt->execute([':tableName' => $table]);
                $existingIndexes = array_map('strtolower', $indexStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $existingIndexMap = array_fill_keys($existingIndexes, true);

                foreach ($requiredIndexes as $indexName => $ddl) {
                    if (!isset($existingIndexMap[strtolower($indexName)])) {
                        $pdo->exec($ddl);
                    }
                }
            }

            $ready = true;
        } catch (Throwable $e) {
            error_log('[distance_helper] distance_result_table_ready failed: ' . $e->getMessage());
            $ready = false;
        }

        return $ready;
    }
}

if (!function_exists('distance_cache_get')) {
    function distance_cache_get(string $namespace, string $key, int $ttlSeconds = 2592000): ?array
    {
        $file = distance_cache_file($namespace, $key);
        if (!is_file($file)) {
            return null;
        }

        $age = time() - (int)@filemtime($file);
        if ($ttlSeconds > 0 && $age > $ttlSeconds) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('distance_cache_put')) {
    function distance_cache_put(string $namespace, string $key, array $data): void
    {
        $file = distance_cache_file($namespace, $key);
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}

if (!function_exists('distance_http_get')) {
    function distance_http_get(string $url, array $headers = [], int $timeout = 12): ?string
    {
        $headers = array_values(array_filter($headers, static fn($value) => trim((string)$value) !== ''));

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 8));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno === 0 && $code >= 200 && $code < 300 && is_string($body)) {
                return $body;
            }
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL) && ini_get('allow_url_fopen') !== '1') {
            return null;
        }

        $headerText = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headerText,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if (!is_string($body) || $body === '') {
            return null;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', (string)$statusLine, $matches) === 1) {
            $code = (int)$matches[1];
            if ($code >= 200 && $code < 300) {
                return $body;
            }
        }

        return null;
    }
}

if (!function_exists('distance_http_get_json')) {
    function distance_http_get_json(string $url, array $headers = [], int $timeout = 12): ?array
    {
        $body = distance_http_get($url, $headers, $timeout);
        if (!is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('distance_http_post_json')) {
    function distance_http_post_json(string $url, array $payload, array $headers = [], int $timeout = 20): ?array
    {
        $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($bodyJson)) {
            return null;
        }

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 8));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno === 0 && $code >= 200 && $code < 300 && is_string($body)) {
                $decoded = json_decode($body, true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL) && ini_get('allow_url_fopen') !== '1') {
            return null;
        }

        $headerText = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerText,
                'timeout' => $timeout,
                'ignore_errors' => true,
                'content' => $bodyJson,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if (!is_string($body) || $body === '') {
            return null;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', (string)$statusLine, $matches) === 1) {
            $code = (int)$matches[1];
            if ($code >= 200 && $code < 300) {
                $decoded = json_decode($body, true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }
}

if (!function_exists('distance_ors_geocode_address')) {
    function distance_ors_geocode_address(string $address): ?array
    {
        $apiKey = distance_ors_api_key();
        if ($apiKey === '') {
            return null;
        }

        $url = 'https://api.openrouteservice.org/geocode/search?api_key='
            . urlencode($apiKey)
            . '&text=' . urlencode($address)
            . '&boundary.country=MY'
            . '&size=1';

        $response = distance_http_get_json($url, [
            'User-Agent: e-base-bdr-distance/1.0',
            'Accept: application/json',
        ], 15);

        if (!is_array($response) || empty($response['features'][0]['geometry']['coordinates'][0]) || empty($response['features'][0]['geometry']['coordinates'][1])) {
            return null;
        }

        return [
            'lat' => (float)$response['features'][0]['geometry']['coordinates'][1],
            'lon' => (float)$response['features'][0]['geometry']['coordinates'][0],
            'display_name' => (string)($response['features'][0]['properties']['label'] ?? $address),
        ];
    }
}

if (!function_exists('distance_tomtom_geocode_address')) {
    function distance_tomtom_geocode_address(string $address): ?array
    {
        $apiKey = distance_tomtom_api_key();
        if ($apiKey === '') {
            return null;
        }

        $url = 'https://api.tomtom.com/search/2/geocode/'
            . rawurlencode($address)
            . '.json?key=' . urlencode($apiKey)
            . '&countrySet=MYS'
            . '&limit=1';

        $response = distance_http_get_json($url, [
            'User-Agent: e-base-bdr-distance/1.0',
            'Accept: application/json',
        ], 15);

        if (!is_array($response) || empty($response['results'][0]['position']['lat']) || empty($response['results'][0]['position']['lon'])) {
            return null;
        }

        $result = $response['results'][0];

        return [
            'lat' => (float)$result['position']['lat'],
            'lon' => (float)$result['position']['lon'],
            'display_name' => (string)($result['address']['freeformAddress'] ?? $address),
            'provider' => 'tomtom',
        ];
    }
}

if (!function_exists('distance_geoapify_geocode_address')) {
    function distance_geoapify_geocode_address(string $address): ?array
    {
        $apiKey = distance_geoapify_api_key();
        if ($apiKey === '') {
            return null;
        }

        $url = 'https://api.geoapify.com/v1/geocode/search?text='
            . urlencode($address)
            . '&filter=' . urlencode('countrycode:my')
            . '&format=json'
            . '&limit=1'
            . '&apiKey=' . urlencode($apiKey);

        $response = distance_http_get_json($url, [
            'User-Agent: e-base-bdr-distance/1.0',
            'Accept: application/json',
        ], 15);

        if (!is_array($response) || empty($response['results'][0]['lat']) || empty($response['results'][0]['lon'])) {
            return null;
        }

        $result = $response['results'][0];

        return [
            'lat' => (float)$result['lat'],
            'lon' => (float)$result['lon'],
            'display_name' => (string)($result['formatted'] ?? $address),
            'provider' => 'geoapify',
        ];
    }
}

if (!function_exists('distance_google_geocode_address')) {
    function distance_google_geocode_address(string $address): ?array
    {
        $apiKey = distance_google_api_key();
        if ($apiKey === '') {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='
            . urlencode($address)
            . '&region=my'
            . '&components=' . urlencode('country:MY')
            . '&key=' . urlencode($apiKey);

        $response = distance_http_get_json($url, [
            'User-Agent: e-base-bdr-distance/1.0',
            'Accept: application/json',
        ], 15);

        if (!is_array($response) || ($response['status'] ?? '') !== 'OK' || empty($response['results'][0]['geometry']['location']['lat']) || empty($response['results'][0]['geometry']['location']['lng'])) {
            return null;
        }

        $result = $response['results'][0];

        return [
            'lat' => (float)$result['geometry']['location']['lat'],
            'lon' => (float)$result['geometry']['location']['lng'],
            'display_name' => (string)($result['formatted_address'] ?? $address),
            'provider' => 'google',
        ];
    }
}

if (!function_exists('distance_ors_route_km')) {
    function distance_ors_route_km(array $fromCoords, array $toCoords): ?float
    {
        $apiKey = distance_ors_api_key();
        if ($apiKey === '') {
            return null;
        }

        $response = distance_http_post_json(
            'https://api.openrouteservice.org/v2/directions/driving-car/json?api_key=' . urlencode($apiKey),
            [
                'preference' => 'shortest',
                'coordinates' => [
                    [(float)$fromCoords['lon'], (float)$fromCoords['lat']],
                    [(float)$toCoords['lon'], (float)$toCoords['lat']],
                ],
            ],
            [
                'User-Agent: e-base-bdr-distance/1.0',
            ],
            20
        );

        if (!is_array($response) || empty($response['routes'][0]['summary']['distance'])) {
            return null;
        }

        return ((float)$response['routes'][0]['summary']['distance']) / 1000;
    }
}

if (!function_exists('distance_tomtom_route_km')) {
    function distance_tomtom_route_km(array $fromCoords, array $toCoords): ?float
    {
        $details = distance_tomtom_route_details($fromCoords, $toCoords);
        return is_array($details) && isset($details['km']) ? (float)$details['km'] : null;
    }
}

if (!function_exists('distance_tomtom_route_details')) {
    function distance_tomtom_route_details(array $fromCoords, array $toCoords): ?array
    {
        $apiKey = distance_tomtom_api_key();
        if ($apiKey === '') {
            return null;
        }

        $url = sprintf(
            'https://api.tomtom.com/routing/1/calculateRoute/%s,%s:%s,%s/json?key=%s&travelMode=car&routeType=shortest&traffic=false',
            rawurlencode((string)$fromCoords['lat']),
            rawurlencode((string)$fromCoords['lon']),
            rawurlencode((string)$toCoords['lat']),
            rawurlencode((string)$toCoords['lon']),
            urlencode($apiKey)
        );

        $response = distance_http_get_json($url, [
            'User-Agent: e-base-bdr-distance/1.0',
            'Accept: application/json',
        ], 20);

        if (!is_array($response) || empty($response['routes'][0]['summary']['lengthInMeters'])) {
            return null;
        }

        $points = [];
        foreach (($response['routes'][0]['legs'][0]['points'] ?? []) as $point) {
            if (!isset($point['latitude'], $point['longitude'])) {
                continue;
            }

            $points[] = [
                'lat' => (float)$point['latitude'],
                'lon' => (float)$point['longitude'],
            ];
        }

        return [
            'km' => ((float)$response['routes'][0]['summary']['lengthInMeters']) / 1000,
            'provider' => 'tomtom',
            'points' => $points,
        ];
    }
}

if (!function_exists('distance_google_decode_polyline')) {
    function distance_google_decode_polyline(string $encoded): array
    {
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            $shift = 0;
            $result = 0;
            do {
                if ($index >= $length) {
                    return $points;
                }
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1f) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            $deltaLat = (($result & 1) !== 0) ? ~($result >> 1) : ($result >> 1);
            $lat += $deltaLat;

            $shift = 0;
            $result = 0;
            do {
                if ($index >= $length) {
                    return $points;
                }
                $byte = ord($encoded[$index++]) - 63;
                $result |= ($byte & 0x1f) << $shift;
                $shift += 5;
            } while ($byte >= 0x20);
            $deltaLng = (($result & 1) !== 0) ? ~($result >> 1) : ($result >> 1);
            $lng += $deltaLng;

            $points[] = [
                'lat' => $lat / 1e5,
                'lon' => $lng / 1e5,
            ];
        }

        return $points;
    }
}

if (!function_exists('distance_google_route_details')) {
    function distance_google_route_details(array $fromCoords, array $toCoords): ?array
    {
        $apiKey = distance_google_api_key();
        if ($apiKey === '') {
            return null;
        }

        $response = distance_http_post_json(
            'https://routes.googleapis.com/directions/v2:computeRoutes',
            [
                'origin' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => (float)$fromCoords['lat'],
                            'longitude' => (float)$fromCoords['lon'],
                        ],
                    ],
                ],
                'destination' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => (float)$toCoords['lat'],
                            'longitude' => (float)$toCoords['lon'],
                        ],
                    ],
                ],
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_UNAWARE',
                'computeAlternativeRoutes' => false,
                'languageCode' => 'en-MY',
                'units' => 'METRIC',
            ],
            [
                'User-Agent: e-base-bdr-distance/1.0',
                'X-Goog-Api-Key: ' . $apiKey,
                'X-Goog-FieldMask: routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline',
            ],
            20
        );

        if (!is_array($response) || empty($response['routes'][0]['distanceMeters'])) {
            return null;
        }

        $encodedPolyline = (string)($response['routes'][0]['polyline']['encodedPolyline'] ?? '');
        $points = $encodedPolyline !== '' ? distance_google_decode_polyline($encodedPolyline) : [];

        return [
            'km' => ((float)$response['routes'][0]['distanceMeters']) / 1000,
            'provider' => 'google',
            'points' => $points,
        ];
    }
}

if (!function_exists('distance_geoapify_route_details')) {
    function distance_geoapify_route_details(array $fromCoords, array $toCoords): ?array
    {
        $apiKey = distance_geoapify_api_key();
        if ($apiKey === '') {
            return null;
        }

        $url = 'https://api.geoapify.com/v1/routing?mode=drive&waypoints='
            . rawurlencode((string)$fromCoords['lat'] . ',' . (string)$fromCoords['lon'] . '|' . (string)$toCoords['lat'] . ',' . (string)$toCoords['lon'])
            . '&details=route_details'
            . '&apiKey=' . urlencode($apiKey);

        $response = distance_http_get_json($url, [
            'User-Agent: e-base-bdr-distance/1.0',
            'Accept: application/json',
        ], 20);

        if (!is_array($response) || empty($response['features'][0]['properties']['distance'])) {
            return null;
        }

        $points = [];
        foreach (($response['features'][0]['geometry']['coordinates'] ?? []) as $coordinate) {
            if (!is_array($coordinate) || count($coordinate) < 2) {
                continue;
            }

            $points[] = [
                'lat' => (float)$coordinate[1],
                'lon' => (float)$coordinate[0],
            ];
        }

        return [
            'km' => ((float)$response['features'][0]['properties']['distance']) / 1000,
            'provider' => 'geoapify',
            'points' => $points,
        ];
    }
}

if (!function_exists('distance_geocode_address')) {
    function distance_geocode_match_score(array $inputAddressData, array $geocodeResult, string $candidateAddress = ''): int
    {
        $inputNormalized = function_exists('alamat_normalize_output')
            ? alamat_normalize_output($inputAddressData)
            : [
                'alamat1' => trim((string)($inputAddressData['alamat1'] ?? '')),
                'alamat2' => trim((string)($inputAddressData['alamat2'] ?? '')),
                'alamat3' => trim((string)($inputAddressData['alamat3'] ?? '')),
                'poskod' => trim((string)($inputAddressData['poskod'] ?? '')),
                'bandar' => '',
                'negeri' => trim((string)($inputAddressData['negeri'] ?? '')),
            ];
        $resultNormalized = function_exists('alamat_normalize_output')
            ? alamat_normalize_output((string)($geocodeResult['display_name'] ?? ''))
            : [
                'alamat1' => trim((string)($geocodeResult['display_name'] ?? '')),
                'alamat2' => '',
                'alamat3' => '',
                'poskod' => '',
                'bandar' => '',
                'negeri' => '',
            ];

        $score = 0;
        $inputPostcode = trim((string)($inputNormalized['poskod'] ?? ''));
        $resultPostcode = trim((string)($resultNormalized['poskod'] ?? ''));
        if ($inputPostcode !== '') {
            if ($resultPostcode === $inputPostcode) {
                $score += 80;
            } elseif ($resultPostcode !== '') {
                $score -= 140;
            }
        }

        $inputState = trim((string)($inputNormalized['negeri'] ?? ''));
        $resultState = trim((string)($resultNormalized['negeri'] ?? ''));
        if ($inputState !== '') {
            if ($resultState === $inputState) {
                $score += 45;
            } elseif ($resultState !== '') {
                $score -= 120;
            }
        }

        $inputCity = trim((string)($inputNormalized['bandar'] ?? ''));
        $resultCity = trim((string)($resultNormalized['bandar'] ?? ''));
        if ($inputCity !== '') {
            if ($resultCity === $inputCity) {
                $score += 30;
            } elseif ($resultCity !== '') {
                $score -= 40;
            }
        }

        $inputLocality = trim((string)($inputNormalized['alamat3'] ?? ''));
        $resultLocality = trim((string)($resultNormalized['alamat3'] ?? ''));
        if ($inputLocality !== '' && $resultLocality !== '') {
            if (function_exists('alamat_lower') && alamat_lower($inputLocality) === alamat_lower($resultLocality)) {
                $score += 28;
            } else {
                $score -= 25;
            }
        }

        $tokenize = static function (array $parts): array {
            $text = implode(' ', array_values(array_filter([
                (string)($parts['alamat1'] ?? ''),
                (string)($parts['alamat2'] ?? ''),
                (string)($parts['alamat3'] ?? ''),
                (string)($parts['bandar'] ?? ''),
            ], static fn($value) => trim((string)$value) !== '')));
            preg_match_all('/[A-Z0-9\/-]{3,}/', strtoupper($text), $matches);
            $tokens = array_values(array_unique($matches[0] ?? []));
            $stop = ['MALAYSIA', 'WILAYAH', 'PERSEKUTUAN', 'KUALA', 'LUMPUR', 'JALAN', 'LORONG', 'TAMAN', 'KAMPUNG', 'SUNGAI', 'NO'];
            return array_values(array_filter($tokens, static fn($token) => !in_array($token, $stop, true)));
        };

        $inputTokens = $tokenize($inputNormalized);
        $resultTokens = $tokenize($resultNormalized);
        $overlap = array_values(array_intersect($inputTokens, $resultTokens));
        $score += count($overlap) * 10;

        $inputAddressText = strtoupper(trim(implode(' ', array_values(array_filter([
            (string)($inputNormalized['alamat1'] ?? ''),
            (string)($inputNormalized['alamat2'] ?? ''),
            (string)($inputNormalized['alamat3'] ?? ''),
        ], static fn($value) => trim((string)$value) !== '')))));
        $resultAddressText = strtoupper(trim((string)($geocodeResult['display_name'] ?? '')));
        $specificAreaTokens = [];
        if (preg_match_all('/\b(?:KAMPUNG|KG|TAMAN|TMN|PEKAN)\s+([A-Z0-9\/-]{3,})\b/u', $inputAddressText, $areaMatches) >= 1) {
            $specificAreaTokens = array_values(array_unique($areaMatches[1] ?? []));
        }
        foreach ($specificAreaTokens as $specificAreaToken) {
            if (!in_array($specificAreaToken, $resultTokens, true) && !str_contains($resultAddressText, $specificAreaToken)) {
                $score -= 45;
            }
        }

        $hasPremiseToken = preg_match('/\b(?:LOT|NO|PT|HS|H\s*S|GERAN|GM|PM|PN)\s+\d{2,}\b/u', $inputAddressText) === 1;
        if ($hasPremiseToken && count(array_intersect($inputTokens, $resultTokens)) < 2) {
            $score -= 30;
        }

        $inputPremise = strtoupper(trim((string)($inputNormalized['alamat1'] ?? '')));
        $resultPremise = strtoupper(trim((string)($resultNormalized['alamat1'] ?? '')));
        if ($inputPremise !== '' && $resultPremise !== '') {
            if ($inputPremise === $resultPremise) {
                $score += 20;
            } elseif (preg_match('/\b\d+[A-Z]?\b/', $inputPremise, $mInput) === 1 && preg_match('/\b\d+[A-Z]?\b/', $resultPremise, $mResult) === 1 && $mInput[0] !== $mResult[0]) {
                $score -= 25;
            }
        }

        $candidateAddress = trim($candidateAddress);
        if ($candidateAddress !== '' && isset($geocodeResult['matched_query']) && trim((string)$geocodeResult['matched_query']) === $candidateAddress) {
            $score += 5;
        }

        return $score;
    }

    function distance_geocode_address($address): ?array
    {
        static $memoryCache = [];

        $addressData = function_exists('normalize_distance_address_data')
            ? normalize_distance_address_data($address)
            : [
                'alamat1' => trim((string)$address),
                'alamat2' => '',
                'alamat3' => '',
                'poskod' => '',
                'negeri' => '',
                'negara' => 'MALAYSIA',
            ];
        $primaryAddress = function_exists('format_alamat_standard')
            ? format_alamat_standard($addressData)
            : trim((string)($addressData['alamat1'] ?? ''));

        if ($primaryAddress === '') {
            return null;
        }

        if (array_key_exists($primaryAddress, $memoryCache)) {
            return $memoryCache[$primaryAddress];
        }

        $cacheKey = 'geocode:' . $primaryAddress;
        $cached = distance_cache_get(distance_geo_cache_namespace(), $cacheKey, 15552000);
        if (
            is_array($cached)
            && isset($cached['lat'], $cached['lon'], $cached['matched_query'])
            && (
                !distance_google_strict_mode()
                || strtolower(trim((string)($cached['provider'] ?? ''))) === 'google'
            )
        ) {
            $memoryCache[$primaryAddress] = $cached;
            return $cached;
        }

        $bestResult = null;
        $bestScore = PHP_INT_MIN;
        $googleCandidates = function_exists('build_google_distance_search_candidates')
            ? build_google_distance_search_candidates($addressData)
            : [];
        array_unshift($googleCandidates, $primaryAddress);
        $googleCandidates = array_values(array_unique(array_filter(array_map('trim', $googleCandidates), static fn($value) => $value !== '')));

        foreach ($googleCandidates as $candidateAddress) {
            $result = distance_google_geocode_address($candidateAddress);
            if (!is_array($result) || !isset($result['lat'], $result['lon'])) {
                continue;
            }

            $result['matched_query'] = $candidateAddress;
            $score = distance_geocode_match_score($addressData, $result, $candidateAddress);
            $result['_match_score'] = $score;

            if ($bestResult === null || $score > $bestScore) {
                $bestResult = $result;
                $bestScore = $score;
            }
        }

        if (!is_array($bestResult) || !isset($bestResult['lat'], $bestResult['lon'])) {
            $memoryCache[$primaryAddress] = null;
            return null;
        }

        distance_cache_put(distance_geo_cache_namespace(), $cacheKey, $bestResult);
        $memoryCache[$primaryAddress] = $bestResult;

        return $bestResult;
    }
}

if (!function_exists('distance_haversine_km')) {
    function distance_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}

if (!function_exists('distance_route_km')) {
    function distance_route_km(array $fromCoords, array $toCoords): ?float
    {
        $details = distance_route_details($fromCoords, $toCoords);
        return is_array($details) && isset($details['km']) ? (float)$details['km'] : null;
    }
}

if (!function_exists('distance_route_details')) {
    function distance_route_details(array $fromCoords, array $toCoords): ?array
    {
        static $memoryCache = [];

        if (!isset($fromCoords['lon'], $fromCoords['lat'], $toCoords['lon'], $toCoords['lat'])) {
            return null;
        }

        $cacheKey = implode(':', [
            'route',
            (string)$fromCoords['lat'],
            (string)$fromCoords['lon'],
            (string)$toCoords['lat'],
            (string)$toCoords['lon'],
        ]);
        if (array_key_exists($cacheKey, $memoryCache)) {
            return $memoryCache[$cacheKey];
        }

        $cached = distance_cache_get(distance_route_cache_namespace(), $cacheKey, 15552000);
        if (
            is_array($cached)
            && isset($cached['km'])
            && (
                !distance_google_strict_mode()
                || strtolower(trim((string)($cached['provider'] ?? ''))) === 'google'
            )
        ) {
            $memoryCache[$cacheKey] = $cached;
            return $memoryCache[$cacheKey];
        }

        $details = distance_google_route_details($fromCoords, $toCoords);

        if (!is_array($details) || !isset($details['km']) || !is_numeric($details['km'])) {
            $memoryCache[$cacheKey] = null;
            return null;
        }

        distance_cache_put(distance_route_cache_namespace(), $cacheKey, $details);
        $memoryCache[$cacheKey] = $details;

        return $details;
    }
}

if (!function_exists('calculate_home_to_office_distance')) {
    function calculate_home_to_office_distance($homeAddress, ?string $officeAddress = null): array
    {
        $homeAddressData = function_exists('normalize_distance_address_data')
            ? normalize_distance_address_data($homeAddress)
            : [
                'alamat1' => trim((string)$homeAddress),
                'alamat2' => '',
                'alamat3' => '',
                'poskod' => '',
                'negeri' => '',
                'negara' => 'MALAYSIA',
            ];
        $homeAddressText = function_exists('format_alamat_standard')
            ? format_alamat_standard($homeAddressData)
            : trim((string)($homeAddressData['alamat1'] ?? ''));
        $officeAddress = trim((string)($officeAddress ?: distance_office_address()));

        if ($homeAddressText === '' || $officeAddress === '') {
            return [
                'km' => null,
                'source' => '',
                'home_coords' => null,
                'office_coords' => null,
            ];
        }

        $homeCoords = distance_geocode_address($homeAddressData);
        $officeCoords = distance_office_coords(distance_site_code_from_office_address($officeAddress));

        if (!is_array($homeCoords) || !is_array($officeCoords)) {
            return [
                'km' => null,
                'source' => '',
                'home_coords' => $homeCoords,
                'office_coords' => $officeCoords,
            ];
        }

        $routeDetails = distance_route_details($homeCoords, $officeCoords);
        if (is_array($routeDetails) && is_numeric($routeDetails['km'] ?? null)) {
            return [
                'km' => round((float)$routeDetails['km'], 2),
                'source' => 'route',
                'route_provider' => (string)($routeDetails['provider'] ?? ''),
                'route_points' => is_array($routeDetails['points'] ?? null) ? $routeDetails['points'] : [],
                'home_coords' => $homeCoords,
                'office_coords' => $officeCoords,
            ];
        }

        return [
            'km' => null,
            'source' => '',
            'route_provider' => '',
            'route_points' => [],
            'home_coords' => $homeCoords,
            'office_coords' => $officeCoords,
        ];
    }
}

if (!function_exists('distance_result_cache_key')) {
    function distance_result_cache_key($homeAddress, ?string $officeAddress = null): string
    {
        $officeAddress = trim((string)($officeAddress ?: distance_office_address()));
        $normalized = function_exists('format_alamat_standard')
            ? format_alamat_standard($homeAddress)
            : trim((string)$homeAddress);

        return 'result:' . $normalized . '|' . $officeAddress;
    }
}

if (!function_exists('distance_result_site_code')) {
    function distance_result_site_code(?string $officeAddress = null, ?string $siteCode = null): string
    {
        $siteCode = trim((string)$siteCode);
        if ($siteCode !== '') {
            return distance_normalize_site_code($siteCode);
        }

        return distance_site_code_from_office_address($officeAddress);
    }
}

if (!function_exists('distance_result_db_record')) {
    function distance_result_db_record($homeAddress, ?string $officeAddress = null, ?string $siteCode = null): ?array
    {
        if (!distance_result_table_ready()) {
            return null;
        }

        $pdo = distance_db();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $cacheKey = md5(distance_result_cache_key($homeAddress, $officeAddress));
        $siteCode = distance_result_site_code($officeAddress, $siteCode);

        try {
            $stmt = $pdo->prepare(
                'SELECT f_resultJson
                 FROM ' . distance_result_table_name() . '
                 WHERE f_cacheKey = :cacheKey
                   AND f_siteCode = :siteCode
                 LIMIT 1'
            );
            $stmt->execute([
                ':cacheKey' => $cacheKey,
                ':siteCode' => $siteCode,
            ]);
            $raw = $stmt->fetchColumn();
            if (!is_string($raw) || trim($raw) === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            error_log('[distance_helper] distance_result_db_record failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('distance_result_db_save')) {
    function distance_result_db_save($homeAddress, ?string $officeAddress, array $result, array $staffMeta = [], ?string $siteCode = null): void
    {
        if (!distance_result_table_ready()) {
            return;
        }

        $pdo = distance_db();
        if (!$pdo instanceof PDO) {
            return;
        }

        $officeAddress = trim((string)($officeAddress ?: distance_office_address()));
        $siteCode = distance_result_site_code($officeAddress, $siteCode ?? (string)($staffMeta['site_code'] ?? $staffMeta['f_siteCode'] ?? ''));
        $normalized = function_exists('format_alamat_standard')
            ? format_alamat_standard($homeAddress)
            : trim((string)$homeAddress);
        $normalized = trim((string)$normalized);
        $homeAddressJson = json_encode($homeAddress, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($resultJson)) {
            return;
        }

        $status = is_numeric($result['km'] ?? null) ? 'SUCCESS' : 'FAILED';
        $matchQuality = trim((string)($result['match_quality'] ?? ''));
        $stafId = trim((string)($staffMeta['f_stafID'] ?? $staffMeta['staff_no'] ?? ''));

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . distance_result_table_name() . ' (
                    f_cacheKey,
                    f_stafID,
                    f_siteCode,
                    f_homeAddressHash,
                    f_homeAddress,
                    f_homeAddressJson,
                    f_officeAddress,
                    f_distanceKm,
                    f_source,
                    f_routeProvider,
                    f_matchQuality,
                    f_resultJson,
                    f_status,
                    f_lastCalculatedAt
                ) VALUES (
                    :cacheKey,
                    :stafID,
                    :siteCode,
                    :homeAddressHash,
                    :homeAddress,
                    :homeAddressJson,
                    :officeAddress,
                    :distanceKm,
                    :source,
                    :routeProvider,
                    :matchQuality,
                    :resultJson,
                    :status,
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    f_stafID = VALUES(f_stafID),
                    f_siteCode = VALUES(f_siteCode),
                    f_homeAddressHash = VALUES(f_homeAddressHash),
                    f_homeAddress = VALUES(f_homeAddress),
                    f_homeAddressJson = VALUES(f_homeAddressJson),
                    f_officeAddress = VALUES(f_officeAddress),
                    f_distanceKm = VALUES(f_distanceKm),
                    f_source = VALUES(f_source),
                    f_routeProvider = VALUES(f_routeProvider),
                    f_matchQuality = VALUES(f_matchQuality),
                    f_resultJson = VALUES(f_resultJson),
                    f_status = VALUES(f_status),
                    f_lastCalculatedAt = VALUES(f_lastCalculatedAt)'
            );
            $stmt->bindValue(':cacheKey', md5(distance_result_cache_key($homeAddress, $officeAddress)));
            $stmt->bindValue(':stafID', $stafId !== '' ? $stafId : null);
            $stmt->bindValue(':siteCode', $siteCode);
            $stmt->bindValue(':homeAddressHash', hash('sha256', $normalized));
            $stmt->bindValue(':homeAddress', $normalized);
            $stmt->bindValue(':homeAddressJson', is_string($homeAddressJson) ? $homeAddressJson : null);
            $stmt->bindValue(':officeAddress', $officeAddress);
            if (is_numeric($result['km'] ?? null)) {
                $stmt->bindValue(':distanceKm', round((float)$result['km'], 2));
            } else {
                $stmt->bindValue(':distanceKm', null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(':source', (string)($result['source'] ?? ''));
            $stmt->bindValue(':routeProvider', (string)($result['route_provider'] ?? ''));
            $stmt->bindValue(':matchQuality', $matchQuality);
            $stmt->bindValue(':resultJson', $resultJson);
            $stmt->bindValue(':status', $status);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('[distance_helper] distance_result_db_save failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('distance_result_cache_get')) {
    function distance_result_cache_get($homeAddress, ?string $officeAddress = null, ?string $siteCode = null): ?array
    {
        return distance_result_db_record($homeAddress, $officeAddress, $siteCode);
    }
}

if (!function_exists('distance_result_cache_put')) {
    function distance_result_cache_put($homeAddress, ?string $officeAddress, array $result, array $staffMeta = [], ?string $siteCode = null): void
    {
        distance_result_db_save($homeAddress, $officeAddress, $result, $staffMeta, $siteCode);
    }
}

if (!function_exists('distance_lookup_cached_result')) {
    function distance_lookup_cached_result($homeAddress, ?string $officeAddress = null, bool $force = false, array $staffMeta = []): array
    {
        $siteCode = distance_result_site_code($officeAddress, (string)($staffMeta['site_code'] ?? $staffMeta['f_siteCode'] ?? ''));
        if (!$force) {
            $cached = distance_result_cache_get($homeAddress, $officeAddress, $siteCode);
            if (is_array($cached)) {
                $cached['cache_origin'] = 'db';
                if ($staffMeta !== []) {
                    distance_result_db_save($homeAddress, $officeAddress, $cached, $staffMeta, $siteCode);
                }
                return $cached;
            }
        }

        $distanceData = calculate_home_to_office_distance($homeAddress, $officeAddress);
        $payload = [
            'km' => is_numeric($distanceData['km'] ?? null) ? round((float)$distanceData['km'], 2) : null,
            'source' => (string)($distanceData['source'] ?? ''),
            'route_provider' => (string)($distanceData['route_provider'] ?? ''),
            'route_points' => is_array($distanceData['route_points'] ?? null) ? $distanceData['route_points'] : [],
            'home_coords' => is_array($distanceData['home_coords'] ?? null) ? $distanceData['home_coords'] : null,
            'office_coords' => is_array($distanceData['office_coords'] ?? null) ? $distanceData['office_coords'] : distance_office_coords($siteCode),
            'match_quality' => is_array($distanceData['home_coords'] ?? null) ? 'OK' : 'NONE',
            'cache_origin' => $force ? 'recalculated' : 'google_live',
            'cached_at' => date('c'),
        ];

        distance_result_cache_put($homeAddress, $officeAddress, $payload, $staffMeta, $siteCode);

        return $payload;
    }
}

if (!function_exists('format_distance_km')) {
    function format_distance_km($km): string
    {
        if (!is_numeric($km)) {
            return '';
        }

        return number_format((float)$km, 2) . ' KM';
    }
}

if (!function_exists('generate_direction_url')) {
    function generate_direction_url(string $origin, ?string $destination = null): string
    {
        $origin = trim($origin);
        $destination = trim((string)($destination ?: distance_office_address()));

        if ($origin === '' || $destination === '') {
            return '';
        }

        return 'https://www.google.com/maps/dir/?api=1&origin='
            . urlencode($origin)
            . '&destination='
            . urlencode($destination)
            . '&travelmode=driving';
    }
}
