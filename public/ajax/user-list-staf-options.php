<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-list-staf-options.php
// Return HTML options for staf dropdown (for refresh after delete/add)
// With caching (5 min TTL) and rate limiting
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Clean output buffers
while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

function is_staff_option_record(array $row): bool {
    $nopekerja = trim((string)($row['nopekerja'] ?? ''));
    $idpekerja = trim((string)($row['idpekerja'] ?? ''));
    $jawatan   = trim((string)($row['jawatan'] ?? ''));
    $jabatan   = trim((string)($row['jabatan'] ?? ''));

    if ($nopekerja === '') {
        return false;
    }

    return $idpekerja !== '' || $jawatan !== '' || $jabatan !== '';
}

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    // Debug source for troubleshooting: 'db', 'session-cache', 'cache-files', 'cache-fallback'
    $responseSource = 'unknown';
    $dbMySQL = Database::getInstance('mysql')->getConnection();
    ensureAjaxGroupManagePermission($dbMySQL);

    // Rate limiting: max 30 requests per 60 seconds
    if (!checkRateLimit('staf_options', 30, 60)) {
        http_response_code(429);
        echo json_encode([
            'error' => true,
            'message' => (string)__('userList_ajax_rate_limited'),
            'html' => ''
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Cache helper untuk staf options (session-based, 5 min TTL)
     */
    $stafCacheKey = 'staf_options_list';
    $stafCacheTTL = 300; // 5 minit
    
    // Check cache
    $cachedStaf = null;
    if (isset($_SESSION['userlist_cache'][$stafCacheKey])) {
        $cache = $_SESSION['userlist_cache'][$stafCacheKey];
        if (is_array($cache) && isset($cache['ts'], $cache['val'])) {
            if ((time() - $cache['ts']) < $stafCacheTTL) {
                $cachedStaf = $cache['val'];
            } else {
                unset($_SESSION['userlist_cache'][$stafCacheKey]);
            }
        }
    }
    
    // Get list of existing staff IDs from tbl_m_user (always fresh - no cache)
    $existingStafIDs = [];
    try {
        $existingSql = "SELECT DISTINCT f_stafID FROM tbl_m_user WHERE TRIM(COALESCE(f_categoryUser, '')) = 'STAF' AND f_stafID IS NOT NULL AND f_stafID <> ''";
        $existingStmt = $dbMySQL->query($existingSql);
        $existingRows = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
        $existingRaw = array_map('trim', array_filter($existingRows));
        // Normalize: remove dashes for matching
        $existingStafIDs = array_map(function($id) {
            return str_replace('-', '', trim((string)$id));
        }, $existingRaw);
    } catch (Throwable $e) {
        error_log('[user-list-staf-options] Error loading existing staf IDs: ' . $e->getMessage());
        $existingStafIDs = [];
    }

    // Load staf data from Sybase (with cache)
    $senaraiStaf = [];
    if (is_array($cachedStaf)) {
        // Use cached data
        $senaraiStaf = array_values(array_filter(
            $cachedStaf,
            static fn($row) => is_array($row) && is_staff_option_record($row)
        ));
        $responseSource = 'session-cache';
    } else {
        // Cache miss - load from database
        try {
            $dbSybase = Database::pdoSybaseStaff();
            
            $sql = "
                SELECT DISTINCT
                    LTRIM(RTRIM(s.nopekerja))     AS nopekerja,
                    LTRIM(RTRIM(s.idpekerja))     AS idpekerja,
                    LTRIM(RTRIM(s.gelar_nama))    AS nama,
                    LTRIM(RTRIM(s.jawatansemasa)) AS jawatan,
                    LTRIM(RTRIM(s.jabatansemasa)) AS jabatan
                FROM v630staf_service_skim_all s
                WHERE CONVERT(INT, s.kodstatus) = 1
                    AND s.nopekerja IS NOT NULL
                    AND LTRIM(RTRIM(s.nopekerja)) <> ''
                ORDER BY s.gelar_nama ASC
            ";
            
            $stmt = $dbSybase->query($sql);
            $senaraiStaf = array_values(array_filter(
                $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                static fn($row) => is_array($row) && is_staff_option_record($row)
            ));
            $responseSource = 'db';
            
            // Store in cache
            if (!isset($_SESSION['userlist_cache'])) {
                $_SESSION['userlist_cache'] = [];
            }
            $_SESSION['userlist_cache'][$stafCacheKey] = [
                'ts' => time(),
                'val' => $senaraiStaf
            ];
        } catch (Throwable $e) {
            error_log('[user-list-staf-options] DB error loading staf: ' . $e->getMessage());
            $senaraiStaf = [];
            // Fallback: jika sambungan ke Sybase gagal, cuba baca cache JSON di folder app/cache
            try {
                $cacheDir = __DIR__ . '/../cache';
                if (is_dir($cacheDir)) {
                    $files = glob($cacheDir . '/*.json');
                    if (!empty($files)) {
                        $merged = [];
                        foreach ($files as $f) {
                            if (!is_readable($f)) continue;
                            $txt = @file_get_contents($f);
                            if ($txt === false) continue;
                            $data = json_decode($txt, true);
                            if (!is_array($data)) continue;
                            // Each cache file may contain an array of staf objects
                            foreach ($data as $entry) {
                                if (is_array($entry)) $merged[] = $entry;
                            }
                        }
                        if (!empty($merged)) {
                            // Normalize merged entries to expected keys (nopekerja, idpekerja, nama, jawatan, jabatan)
                            $normalized = [];
                            foreach ($merged as $s) {
                                $nopekerja = trim((string)($s['nopekerja'] ?? $s['no_staf'] ?? $s['nopekerja_n'] ?? $s['nopek'] ?? $s['no_pekerja'] ?? ''));
                                if ($nopekerja === '') continue;
                                $idpekerja = trim((string)($s['idpekerja'] ?? $s['id_pekerja'] ?? $s['idpegawai'] ?? ''));
                                $nama = trim((string)($s['nama'] ?? $s['gelar_nama'] ?? $s['name'] ?? ''));
                                $jawatan = trim((string)($s['jawatan'] ?? $s['jawatansemasa'] ?? $s['position'] ?? ''));
                                $jabatan = trim((string)($s['jabatan'] ?? $s['jabatan_nama'] ?? $s['jabatan_kod'] ?? $s['department'] ?? ''));
                                $normalized[] = [
                                    'nopekerja' => $nopekerja,
                                    'idpekerja' => $idpekerja,
                                    'nama' => $nama,
                                    'jawatan' => $jawatan,
                                    'jabatan' => $jabatan
                                ];
                            }

                            // honour order by name if possible
                            usort($normalized, function($a, $b){
                                $na = trim((string)($a['nama'] ?? ''));
                                $nb = trim((string)($b['nama'] ?? ''));
                                return strcasecmp($na, $nb);
                            });
                            $senaraiStaf = array_values(array_filter(
                                $normalized,
                                static fn($row) => is_array($row) && is_staff_option_record($row)
                            ));
                            $responseSource = 'cache-files';
                            // also store into session cache so subsequent calls are faster
                            $_SESSION['userlist_cache'][$stafCacheKey] = ['ts' => time(), 'val' => $senaraiStaf];
                        }
                    }
                }
            } catch (Throwable $_e) {
                // ignore fallback failures
            }
        }
    }

    // Generate HTML options
    $html = '<option value=""></option>';
    $options = [];
    
    if (!empty($senaraiStaf)) {
        foreach ($senaraiStaf as $s) {
            $nopekerja = trim((string)($s['nopekerja'] ?? ''));
            $idpekerja = trim((string)($s['idpekerja'] ?? ''));
            $nama = trim((string)($s['nama'] ?? ''));
            $jawatan = trim((string)($s['jawatan'] ?? ''));
            $jabatan = trim((string)($s['jabatan'] ?? ''));
            
            if ($nopekerja === '') continue;
            
            $nopekerjaNormalized = str_replace('-', '', $nopekerja);
            $isDisabled = in_array($nopekerjaNormalized, $existingStafIDs, true);
            
            $displayText = $nama;
            if ($nopekerja) {
                $displayText .= ' (' . $nopekerja . ')';
            }
                      if ($isDisabled) {
                        $displayText .= ' [' . __('userList_staff_already_exists') . ']';
                      }
            
            $html .= '<option'
                . ' value="' . htmlspecialchars($nopekerja, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-idpekerja="' . htmlspecialchars($idpekerja, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-nama="' . htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-jawatan="' . htmlspecialchars($jawatan, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-jabatan="' . htmlspecialchars($jabatan, ENT_QUOTES, 'UTF-8') . '"'
                . ($isDisabled ? ' disabled' : '')
                . '>'
                . htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8')
                . '</option>';
            // Add structured option for JSON consumers
            $options[] = [
                'value' => $nopekerja,
                'idpekerja' => $idpekerja,
                'nama' => $nama,
                'jawatan' => $jawatan,
                'jabatan' => $jabatan,
                'disabled' => $isDisabled,
                'display' => $displayText
            ];
        }
    } else {
        $html .= '<option value="" disabled>-- ' . __('userList_no_staff_data') . ' --</option>';
    }

    // Ensure no accidental output (BOM / whitespace) was emitted by included files
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $payload = json_encode([
        'error' => false,
        'html' => $html,
        'options' => $options,
        'source' => $responseSource
    ], JSON_UNESCAPED_UNICODE);

    // Strip UTF-8 BOM if present at start
    $payload = preg_replace('/^\x{FEFF}/u', '', $payload);
    echo $payload;

} catch (Throwable $e) {
    error_log('[user-list-staf-options] Error: ' . $e->getMessage());
    http_response_code(500);
    // Ensure no accidental output (BOM / whitespace) was emitted by included files
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $payload = json_encode([
        'error' => true,
        'message' => (string)__('userList_ajax_load_staff_error'),
        'html' => ''
    ], JSON_UNESCAPED_UNICODE);
    $payload = preg_replace('/^\x{FEFF}/u', '', $payload);
    echo $payload;
}
