<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// controllers/DashboardController.php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

/**
 * Cache ringan dalam $_SESSION (TTL saat)
 */
final class DashCache {
    public static function get(string $key, int $ttl): mixed {
        $now = time();
        $c = $_SESSION['dash_cache'][$key] ?? null;
        if (!$c) return null;
        if (($c['ts'] + $ttl) < $now) { unset($_SESSION['dash_cache'][$key]); return null; }
        return $c['val'];
    }
    public static function set(string $key, mixed $val): void {
        $_SESSION['dash_cache'][$key] = ['ts' => time(), 'val' => $val];
    }
    public static function clear(?string $prefix = null): void {
        if (!isset($_SESSION['dash_cache'])) return;
        if ($prefix === null) { unset($_SESSION['dash_cache']); return; }
        foreach (array_keys($_SESSION['dash_cache']) as $k) {
            if (str_starts_with($k, $prefix)) unset($_SESSION['dash_cache'][$k]);
        }
    }
}

class DashboardController
{
    public string $lang = 'ms';
    public string $role;
    public array  $profile = [];

    /** Sybase aktif (semua data prestasi & staf) — LAZY init */
    private ?PDO $db = null;

    public function __construct()
    {
        // ❗JANGAN buka Sybase di sini (lazy). Ini laju masa login/first paint.
        // ✅ Role & profile guna model User (MySQL)
        try {
            $userModel   = new User(Database::pdoMysql());  // BaseModel expect PDO
            $fLoginID    = trim((string)($_SESSION['f_loginID'] ?? ''));
            $fStafID     = trim((string)($_SESSION['f_stafID'] ?? ''));
            $prof        = $fLoginID !== ''
                ? ($userModel->getProfileByLoginID($fLoginID) ?: [])
                : ($userModel->getProfile($fStafID) ?: []);
            $roleLbl     = $userModel->getRoleLabel($prof);

            $this->profile = $prof ?? ($_SESSION['user'] ?? []);
            $this->role    = strtoupper(trim((string)($roleLbl ?: ($_SESSION['role'] ?? 'USER'))));
        } catch (Throwable $e) {
            // Fallback kalau MySQL tak available
            $this->profile = $_SESSION['user'] ?? [];
            $this->role    = strtoupper(trim((string)($_SESSION['role'] ?? 'USER')));
        }
    }

    // Strategic dashboard compatibility stubs (return empty data for new setup)
    public function getStats(?int $year = null, ?string $filterTeras = null): array
    {
        return [
            'total' => 0,
            'on_track' => 0,
            'delayed' => 0,
            'critical' => 0,
            'completed' => 0,
        ];
    }

    public function getAllProjectLists(int $limit = 50, ?string $filterTeras = null): array
    {
        return [
            'total' => [],
            'completed' => [],
            'on_track' => [],
            'delayed' => [],
            'critical' => [],
        ];
    }

    public function getAvailableTeras(): array
    {
        return [];
    }

    /** Lazy getter utk Sybase PDO */
    private function db(): PDO
    {
        if ($this->db === null) {
            // ✅ Domain staf (environment-aware)
            $this->db = Database::pdoSybaseStaff();
        }
        // ✅ FIX: Check if connection is still alive
        try {
            $this->db->query('SELECT 1');
        } catch (PDOException $e) {
            // ✅ FIX: If connection is dead, clear singleton and reconnect
            if (strpos($e->getMessage(), 'DBPROCESS') !== false || 
                strpos($e->getMessage(), '20047') !== false) {
                error_log('[DashboardController] Connection dead, reconnecting...');
                $staffBase = function_exists('get_sybase_staff_base') ? get_sybase_staff_base() : 'sybase_active';
                Database::clearInstance($staffBase);
                $this->db = null;
                $this->db = Database::pdoSybaseStaff();
            } else {
                throw $e; // Re-throw jika bukan DBPROCESS error
            }
        }
        return $this->db;
    }

    public function setLang(string $lang): void
    {
        $this->lang = in_array($lang, ['ms','en','ta','zh'], true) ? $lang : 'ms';
    }

    /**
     * ✅ Kod & nama jabatan user (staf aktif)
     * - Cuba: idpekerja → nopekerja → fallback dari smp_m_penilai (kod → nama)
     * - Cache 10 min
     */
    public function getUserDefaultJabatanInfo(): ?array
    {
        // Cache key ikut user (nopekerja / stafID / session id)
        $userKey = (string)($this->profile['nopekerja'] ?? $this->profile['f_nopekerja'] ?? '');
        $ck = 'user_jab:' . ($userKey ?: session_id());
        if ($val = DashCache::get($ck, 600)) return $val;

        // =========================
        // 1) FAST-PATH: MySQL (tbl_m_user)
        // =========================
        try {
            $pdoMy = Database::pdoMysql();

            // Cuba ambil terus dari profile kalau model User dah populate
            $kodMy  = trim((string)($this->profile['f_jabatanKod'] ?? $this->profile['jabatanKod'] ?? ''));
            $namaMy = trim((string)($this->profile['f_namajabatan'] ?? $this->profile['namajabatan'] ?? ''));

            // Kalau profile tak ada, cuba query ikut stafID / nopekerja
            if ($kodMy === '' || $namaMy === '') {
                $sid = null;
                foreach (['f_stafID','stafid','idpekerja','staff_id'] as $k) {
                    if (!empty($this->profile[$k])) { $sid = trim((string)$this->profile[$k]); break; }
                }
                $no  = null;
                foreach (['f_nopekerja','nopekerja','no_pekerja'] as $k) {
                    if (!empty($this->profile[$k])) { $no = trim((string)$this->profile[$k]); break; }
                }

                if ($sid || $no) {
                    $sql = "SELECT f_jabatanKod, f_namajabatan
                            FROM tbl_m_user
                            WHERE " . ($sid ? "LTRIM(RTRIM(f_stafID)) = LTRIM(RTRIM(:sid))" : "1=0") .
                            ($sid && $no ? " OR " : "") .
                        ($no ? "LTRIM(RTRIM(f_nopekerja)) = LTRIM(RTRIM(:no))" : "") . "
                            ORDER BY f_userID DESC LIMIT 1";
                    $st = $pdoMy->prepare($sql);
                    if ($sid) $st->bindValue(':sid', $sid);
                    if ($no)  $st->bindValue(':no',  $no);
                    $st->execute();
                    $rowMy = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($rowMy) {
                        $kodMy  = trim((string)($rowMy['f_jabatanKod'] ?? ''));
                        $namaMy = trim((string)($rowMy['f_namajabatan'] ?? ''));
                    }
                }
            }

            if ($kodMy !== '') {
                $out = ['kod' => $kodMy, 'nama' => $namaMy];
                DashCache::set($ck, $out);
                return $out;
            }
        } catch (Throwable $e) {
            // Senyap: kalau MySQL down, teruskan fallback Sybase
        }

        // =========================
        // 2) FALLBACK (kod asal kau) – Sybase
        // =========================

        // Calon nilai dari profile/session
        $sidCandidates = [];   // staf id / idpekerja
        $noCandidates  = [];   // no pekerja

        foreach (['f_stafID','stafid','idpekerja','staff_id'] as $k) {
            if (!empty($this->profile[$k])) $sidCandidates[] = trim((string)$this->profile[$k]);
        }
        if (!empty($_SESSION['f_stafID'])) $sidCandidates[] = trim((string)$_SESSION['f_stafID']);

        foreach (['f_nopekerja','nopekerja','no_pekerja'] as $k) {
            if (!empty($this->profile[$k])) $noCandidates[] = trim((string)$this->profile[$k]);
        }

        // 2.1 Cuba dengan idpekerja
        foreach ($sidCandidates as $sid) {
            if ($sid === '') continue;
            $sql = "
                SELECT TOP 1 
                    LTRIM(RTRIM(kdjbtnsemasa)) AS kod,
                    LTRIM(RTRIM(jabatansemasa)) AS nama
                FROM v630staf_prestasi
                WHERE LTRIM(RTRIM(idpekerja)) = LTRIM(RTRIM(:id))
                AND (kodstatus IS NULL OR CONVERT(INT, kodstatus) <> 9)
                ORDER BY jabatansemasa
            ";
            $st = $this->db()->prepare($sql);
            $st->bindValue(':id', $sid);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && ($row['kod'] ?? '') !== '') {
                $out = ['kod'=>$row['kod'], 'nama'=>$row['nama']];
                DashCache::set($ck, $out);
                return $out;
            }
        }

        // 2.2 Cuba dengan nopekerja
        foreach ($noCandidates as $no) {
            if ($no === '') continue;
            $sql = "
                SELECT TOP 1 
                    LTRIM(RTRIM(kdjbtnsemasa)) AS kod,
                    LTRIM(RTRIM(jabatansemasa)) AS nama
                FROM v630staf_prestasi
                WHERE LTRIM(RTRIM(nopekerja)) = LTRIM(RTRIM(:no))
                AND (kodstatus IS NULL OR CONVERT(INT, kodstatus) <> 9)
                ORDER BY jabatansemasa
            ";
            $st = $this->db()->prepare($sql);
            $st->bindValue(':no', $no);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && ($row['kod'] ?? '') !== '') {
                $out = ['kod'=>$row['kod'], 'nama'=>$row['nama']];
                DashCache::set($ck, $out);
                return $out;
            }
        }

        // 2.3 Fallback terakhir: smp_m_penilai → map ke nama
        if ($sidCandidates) {
            $sid = $sidCandidates[0];
            $sqlKod = "
                SELECT TOP 1 LTRIM(RTRIM(f_kodjbt)) AS kod
                FROM smp_m_penilai
                WHERE LTRIM(RTRIM(f_stafID)) = LTRIM(RTRIM(:sid))
                AND f_kodjbt IS NOT NULL AND LTRIM(RTRIM(f_kodjbt)) <> ''
                ORDER BY CONVERT(INT, f_tahun) DESC
            ";
            $st = $this->db()->prepare($sqlKod);
            $st->bindValue(':sid', $sid);
            $st->execute();
            $kod = (string)($st->fetchColumn() ?: '');
            if ($kod !== '') {
                $sqlNama = "
                    SELECT TOP 1 LTRIM(RTRIM(jabatansemasa)) AS nama
                    FROM v630staf_prestasi
                    WHERE LTRIM(RTRIM(kdjbtnsemasa)) = LTRIM(RTRIM(:kod))
                    ORDER BY jabatansemasa
                ";
                $st = $this->db()->prepare($sqlNama);
                $st->bindValue(':kod', $kod);
                $st->execute();
                $nama = (string)($st->fetchColumn() ?: '');
                $out = ['kod'=>$kod, 'nama'=>$nama];
                DashCache::set($ck, $out);
                return $out;
            }
        }

        DashCache::set($ck, null);
        return null;
    }


    /** 
     * Senarai tahun (dropdown) — dynamic berdasarkan jabatan yang dipilih
     * - Jika jabatan dipilih: papar hanya tahun yang ada data untuk jabatan tersebut
     * - Jika "Semua": papar semua tahun yang ada (>= 2007)
     * - Sort DESC, cache 10 min
     */
    public function getYearOptions(?string $kodJab = null, int $max = 25): array
    {
        $kodJab = ($kodJab !== null && trim($kodJab) !== '') ? trim($kodJab) : null;
        $ck = "year_opts:" . ($kodJab ?: 'ALL') . ":$max";
        if ($val = DashCache::get($ck, 600)) {
            return $val;
        }

        $max = max(1, (int)$max);
        $minYear = 2007; // ✅ Minimum year bermula dari 2007

        $sql = "
            SELECT DISTINCT CONVERT(INT, p.f_tahun) AS tahun
            FROM smp_m_penilai p
            WHERE p.f_tahun IS NOT NULL
            AND LTRIM(RTRIM(p.f_tahun)) <> ''
            AND ISNUMERIC(p.f_tahun) = 1         -- 💡 tapis hanya yang numeric
            AND CONVERT(INT, p.f_tahun) >= :minYear  -- ✅ Filter minimum tahun 2007
            " . ($kodJab ? " AND LTRIM(RTRIM(p.f_kodjbt)) = LTRIM(RTRIM(:kod)) " : "") . "
            ORDER BY tahun DESC
        ";

        $st = $this->db()->prepare($sql);
        $st->bindValue(':minYear', $minYear, PDO::PARAM_INT);
        if ($kodJab) $st->bindValue(':kod', $kodJab);
        $st->execute();
        $years = [];

        foreach ($st->fetchAll(PDO::FETCH_COLUMN, 0) ?: [] as $y) {
            $yy = (int)$y;
            if ($yy >= $minYear) {  // ✅ Pastikan tahun >= 2007
                $years[] = $yy;
            }
        }

        // ✅ Limit to max years (ambil yang terbaru) - hanya untuk "Semua" jika terlalu banyak
        if (!$kodJab && count($years) > $max) {
            $years = array_slice($years, 0, $max);
        }

        if (!$years) {
            // ✅ Fallback: generate dari 2007 hingga tahun semasa
            $currentYear = (int)date('Y');
            $years = [];
            for ($y = $currentYear; $y >= $minYear && count($years) < $max; $y--) {
                $years[] = $y;
            }
        }

        DashCache::set($ck, $years);
        return $years;
    }


    /**
     * ⭐ Tahun TERKINI yang ada data; optional ikut KOD jabatan (p.f_kodjbt)
     * - Kekalkan LEFT JOIN untuk kes padanan longgar
     * - Cache 10 min
     * - ✅ FIX: Add error handling untuk DBPROCESS dead error
     */
    public function getLatestYearForJabatan(?string $kodJab = null): int
    {
        $kodJab = ($kodJab !== null && trim($kodJab) !== '') ? trim($kodJab) : null;
        $ck = "latest_year:" . ($kodJab ?? 'ALL');
        if ($val = DashCache::get($ck, 600)) return (int)$val;

        try {
            // ✅ FIX: Try query dengan LEFT JOIN (preferred method)
            $sql = "
                SELECT MAX(CONVERT(INT, p.f_tahun)) AS y
                FROM smp_m_penilai p
                LEFT JOIN v630staf_prestasi v
                  ON LTRIM(RTRIM(v.idpekerja)) = LTRIM(RTRIM(p.f_stafID))
                WHERE p.f_tahun IS NOT NULL AND LTRIM(RTRIM(p.f_tahun)) <> ''
                  " . ($kodJab ? " AND LTRIM(RTRIM(p.f_kodjbt)) = LTRIM(RTRIM(:kod)) " : "") . "
                  AND (v.kodstatus IS NULL OR CONVERT(INT, v.kodstatus) <> 9)
            ";
            
            // ✅ FIX: Force reconnect jika connection mati
            try {
                $db = $this->db();
                $st = $db->prepare($sql);
                if ($kodJab) $st->bindValue(':kod', $kodJab);
                $st->execute();
                $y = (int)($st->fetchColumn() ?: 0);
            } catch (PDOException $e) {
                // ✅ FIX: Jika DBPROCESS dead, reconnect dan retry
                if (strpos($e->getMessage(), 'DBPROCESS') !== false || 
                    strpos($e->getMessage(), '20047') !== false) {
                    error_log('[DashboardController] DBPROCESS dead, reconnecting...');
                    $this->db = null; // Reset connection
                    $db = $this->db(); // Reconnect
                    $st = $db->prepare($sql);
                    if ($kodJab) $st->bindValue(':kod', $kodJab);
                    $st->execute();
                    $y = (int)($st->fetchColumn() ?: 0);
                } else {
                    throw $e; // Re-throw jika bukan DBPROCESS error
                }
            }
            
            // ✅ FIX: Fallback jika query dengan JOIN gagal atau return 0
            if ($y <= 0) {
                try {
                    $sql2 = "SELECT MAX(CONVERT(INT, f_tahun)) FROM smp_m_penilai WHERE LTRIM(RTRIM(f_tahun)) <> ''";
                    if ($kodJab) {
                        $sql2 .= " AND LTRIM(RTRIM(f_kodjbt)) = LTRIM(RTRIM(:kod))";
                    }
                    $st2 = $this->db()->prepare($sql2);
                    if ($kodJab) $st2->bindValue(':kod', $kodJab);
                    $st2->execute();
                    $y = (int)($st2->fetchColumn() ?: 0);
                } catch (PDOException $e2) {
                    // ✅ FIX: Jika fallback juga gagal, reconnect dan retry
                    if (strpos($e2->getMessage(), 'DBPROCESS') !== false || 
                        strpos($e2->getMessage(), '20047') !== false) {
                        error_log('[DashboardController] DBPROCESS dead in fallback, reconnecting...');
                        $this->db = null; // Reset connection
                        $db = $this->db(); // Reconnect
                        $st2 = $db->prepare($sql2);
                        if ($kodJab) $st2->bindValue(':kod', $kodJab);
                        $st2->execute();
                        $y = (int)($st2->fetchColumn() ?: 0);
                    } else {
                        error_log('[DashboardController] getLatestYearForJabatan error: ' . $e2->getMessage());
                        $y = (int)date('Y'); // Final fallback to current year
                    }
                }
            }
            
            // ✅ FIX: Final fallback jika masih 0
            if ($y <= 0) {
                $y = (int)date('Y');
            }
            
            DashCache::set($ck, $y);
            return $y;
            
        } catch (Throwable $e) {
            error_log('[DashboardController] getLatestYearForJabatan fatal error: ' . $e->getMessage());
            // ✅ FIX: Return current year sebagai fallback
            $y = (int)date('Y');
            // Don't cache error result - allow retry on next request
            return $y;
        }
    }

    /**
     * ⭐ Tahun TERKINI dari smp_m_kuotaprestasi mengikut jabatan
     * - Query dari table smp_m_kuotaprestasi (bukan smp_m_penilai)
     * - Cache 10 min
     */
    public function getLatestYearFromKuotaPrestasi(?string $kodJab = null, bool $forceFresh = false): int
    {
        $kodJab = ($kodJab !== null && trim($kodJab) !== '') ? trim($kodJab) : null;
        $ck = "latest_year_kuota:" . ($kodJab ?? 'ALL');
        
        // ✅ FIX: Skip cache if forceFresh is true
        if (!$forceFresh && ($val = DashCache::get($ck, 600))) {
            return (int)$val;
        }

        try {
            $sql = "
                SELECT MAX(CONVERT(INT, f_tahun)) AS y
                FROM smp_m_kuotaprestasi
                WHERE f_tahun IS NOT NULL 
                  AND LTRIM(RTRIM(f_tahun)) <> ''
                  AND ISNUMERIC(f_tahun) = 1
            ";
            
            $params = [];
            if ($kodJab !== null) {
                $sql .= " AND LTRIM(RTRIM(f_kdjbt)) = LTRIM(RTRIM(:kod))";
                $params[':kod'] = $kodJab;
            }
            
            // ✅ FIX: Test connection before query
            $db = $this->db();
            
            // ✅ FIX: Retry logic if connection is dead
            $maxRetries = 2;
            $y = 0;
            for ($retry = 0; $retry < $maxRetries; $retry++) {
                try {
                    $st = $db->prepare($sql);
                    foreach ($params as $k => $v) {
                        $st->bindValue($k, $v);
                    }
                    $st->execute();
                    $result = $st->fetchColumn();
                    $y = (int)($result ?: 0);
                    break; // Success, exit retry loop
                } catch (\PDOException $e) {
                    if (strpos($e->getMessage(), 'DBPROCESS') !== false || 
                        strpos($e->getMessage(), '20047') !== false) {
                        error_log('[DashboardController] Connection dead during query, retry ' . ($retry + 1) . '/' . $maxRetries);
                        // ✅ FIX: Clear Database singleton instance to force reconnect
                        $staffBase = function_exists('get_sybase_staff_base') ? get_sybase_staff_base() : 'sybase_staff_prod';
                        Database::clearInstance($staffBase);
                        $this->db = null;
                        $db = $this->db();
                        if ($retry === $maxRetries - 1) {
                            // Last retry failed, throw exception
                            throw $e;
                        }
                    } else {
                        // Not a connection error, throw immediately
                        throw $e;
                    }
                }
            }
            
            // ✅ FIX: Return actual latest year from database, don't fallback to current year
            // Only fallback if query fails completely (will be handled in catch block)
            if ($y <= 0) {
                // No data found in database - return 0 to indicate no data
                // Don't cache 0 result
                return 0;
            }
            
            DashCache::set($ck, $y);
            return $y;
            
        } catch (\Throwable $e) {
            error_log('[DashboardController] getLatestYearFromKuotaPrestasi error: ' . $e->getMessage());
            // ✅ FIX: Return 0 instead of current year - let caller handle the fallback
            // Don't cache error result
            return 0;
        }
    }

    /**
     * Return the latest kuota record for a jabatan (tahun + kuota + kod_jabatan) or null if none.
     * Cached for 10 minutes.
     */
    public function getLatestKuotaRecordForJabatan(?string $kodJab = null, bool $forceFresh = false): ?array
    {
        $kodJab = ($kodJab !== null && trim($kodJab) !== '') ? trim($kodJab) : null;
        $ck = 'latest_kuota_record:' . ($kodJab ?? 'ALL');
        if (!$forceFresh && ($val = DashCache::get($ck, 600))) return is_array($val) ? $val : null;

        try {
            $sql = "SELECT TOP 1 f_kuota, f_tahun, f_kdjbt FROM smp_m_kuotaprestasi WHERE f_tahun IS NOT NULL AND LTRIM(RTRIM(f_tahun)) <> '' ";
            $params = [];
            if ($kodJab !== null) {
                $sql .= " AND LTRIM(RTRIM(f_kdjbt)) = LTRIM(RTRIM(:kod))";
                $params[':kod'] = $kodJab;
            }
            $sql .= " ORDER BY CONVERT(INT, f_tahun) DESC, f_kuotaid DESC";

            $st = $this->db()->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            $out = [
                'tahun' => (int)($row['f_tahun'] ?? 0),
                'kuota' => trim((string)($row['f_kuota'] ?? '')),
                'kod_jabatan' => trim((string)($row['f_kdjbt'] ?? '')),
            ];

            DashCache::set($ck, $out);
            return $out;
        } catch (\Throwable $e) {
            error_log('[DashboardController] getLatestKuotaRecordForJabatan error: ' . $e->getMessage());
            return null;
        }
    }

    /** Senarai jabatan aktif (kod + nama), kodstatus != 9 — cache 10 min */
    public function listJabatan(): array
    {
        $ck = "jabatan_list:v1";
        if ($val = DashCache::get($ck, 600)) return $val;

        $sql = "SELECT DISTINCT 
                    LTRIM(RTRIM(kdjbtnsemasa))  AS kod,
                    LTRIM(RTRIM(jabatansemasa)) AS nama
                FROM v630staf_prestasi
                WHERE kdjbtnsemasa IS NOT NULL AND LTRIM(RTRIM(kdjbtnsemasa)) <> ''
                  AND (kodstatus IS NULL OR CONVERT(INT, kodstatus) <> 9)";
        $st = $this->db()->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // susun nama ASC (konsisten)
        usort($rows, fn($a,$b)=> strcmp((string)($a['nama'] ?? ''), (string)($b['nama'] ?? '')));

        DashCache::set($ck, $rows);
        return $rows;
    }

    /** KPI ringkas (ikut tahun + opsyen KOD jabatan) — cache 2 min */
    public function getKpi(int $tahun, ?string $kodJab = null): array
    {
        $ck = "kpi:$tahun:" . ($kodJab ?: 'ALL');
        if ($val = DashCache::get($ck, 120)) return $val;

        $rows = $this->fetchJoinedByYear($tahun, $kodJab);
        if (!$rows) {
            $out = [
                'staf_aktif'=>0,'purata_lppt'=>0,'median_lppt'=>0,
                'avg_beza_ppp_ppk'=>0,'belum_lengkap'=>0,'kadar_selesai_pct'=>0
            ];
            DashCache::set($ck, $out);
            return $out;
        }

        $total= count($rows); $belum=0; $puratas=[]; $bezas=[];
        foreach ($rows as $r) {
            $ppp = self::toFloatOrNull($r['ppp']);
            $ppk = self::toFloatOrNull($r['ppk']);
            $pur = self::toFloatOrZero($r['purata']);
            if ($ppp === null || $ppk === null || $pur === 0.0) $belum++;
            if ($pur > 0) $puratas[] = $pur;
            if ($ppp !== null && $ppk !== null) $bezas[] = ($ppk - $ppp);
        }

        $purataAvg = $puratas ? array_sum($puratas)/count($puratas) : 0.0;
        $median    = $this->median($puratas);
        $avgBeza   = $bezas ? array_sum($bezas)/count($bezas) : 0.0;
        $siapPct   = $total ? (100.0 * ($total - $belum) / $total) : 0.0;

        $out = [
            'staf_aktif'        => $total,
            'purata_lppt'       => round($purataAvg, 2),
            'median_lppt'       => round($median, 2),
            'avg_beza_ppp_ppk'  => round($avgBeza, 2),
            'belum_lengkap'     => $belum,
            'kadar_selesai_pct' => round($siapPct, 2),
        ];
        DashCache::set($ck, $out);
        return $out;
    }

    /**
     * Trend SEMUA TAHUN (optional KOD jabatan) — cache 5 min
     * - JOIN ke subquery aktif (DISTINCT idpekerja) untuk elak view berat penuh
     * - Papar SEMUA tahun yang ada data untuk jabatan yang dipilih (tiada limit)
     * - Return both AVG dan MEDIAN untuk comparison
     * - Round 2 tempat perpuluhan untuk match neo card (consistency)
     * - Filter minimum tahun 2007
     * - ✅ Use same calculation method as getKpi() untuk consistency
     */
    public function getTrendAll(?string $kodJab = null, ?int $limitYears = null): array
    {
        $kodJab = ($kodJab !== null && trim($kodJab) !== '') ? trim($kodJab) : null;
        // ✅ Include median in cache key untuk ensure fresh data
        $ck = "trend:ALL:MED:" . ($kodJab ?: 'ALL');
        if ($val = DashCache::get($ck, 300)) return $val;

        $minYear = 2007; // ✅ Minimum year bermula dari 2007

        // ✅ Fetch all purata values per tahun untuk calculate median
        // ✅ Use same JOIN logic as fetchJoinedByYear() untuk consistency dengan getKpi()
        $sql = "
            SELECT CONVERT(INT, p.f_tahun) AS tahun,
                   p.f_purata AS purata
            FROM smp_m_penilai p
            LEFT JOIN (
                SELECT DISTINCT LTRIM(RTRIM(idpekerja)) AS idpekerja
                FROM v630staf_prestasi
                WHERE 
                    -- kodstatus NULL = masih dikira (same as fetchJoinedByYear)
                    kodstatus IS NULL
                    OR (
                        -- hanya convert kalau memang numeric (same as fetchJoinedByYear)
                        ISNUMERIC(kodstatus) = 1
                        AND CONVERT(INT, kodstatus) <> 9
                    )
            ) av ON av.idpekerja = LTRIM(RTRIM(p.f_stafID))
            WHERE 
                -- Elak error 'f_kodjwt' pada f_tahun (same as fetchJoinedByYear)
                ISNUMERIC(p.f_tahun) = 1
                AND CONVERT(INT, p.f_tahun) >= :minYear  -- ✅ Filter minimum tahun 2007
              " . ($kodJab ? " AND LTRIM(RTRIM(p.f_kodjbt)) = LTRIM(RTRIM(:kod)) " : "") . "
            ORDER BY tahun DESC
        ";
        $st = $this->db()->prepare($sql);
        $st->bindValue(':minYear', $minYear, PDO::PARAM_INT);
        if ($kodJab) $st->bindValue(':kod', $kodJab);
        $st->execute();
        $allRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ✅ Group by tahun dan calculate avg + median
        // ✅ Use same filter logic as getKpi() - only include purata > 0
        $groupedByYear = [];
        foreach ($allRows as $r) {
            $tahun = (int)($r['tahun'] ?? 0);
            $purata = self::toFloatOrZero($r['purata']); // ✅ Use same conversion method as getKpi()
            if ($tahun >= $minYear && $purata > 0) { // ✅ Same condition: only purata > 0
                if (!isset($groupedByYear[$tahun])) {
                    $groupedByYear[$tahun] = [];
                }
                $groupedByYear[$tahun][] = $purata;
            }
        }

        // ✅ Calculate avg and median per tahun
        $out = [];
        foreach ($groupedByYear as $tahun => $values) {
            if (empty($values)) continue;
            
            // Calculate average
            $avg = array_sum($values) / count($values);
            
            // Calculate median
            sort($values);
            $count = count($values);
            $median = 0.0;
            if ($count % 2 === 0) {
                // Even number of values - average of middle two
                $median = ($values[($count / 2) - 1] + $values[$count / 2]) / 2;
            } else {
                // Odd number of values - middle value
                $median = $values[($count - 1) / 2];
            }
            
            $out[] = [
                'tahun'      => $tahun,
                'avg_lppt'   => round($avg, 2), // ✅ Change to 2 decimal places untuk match neo card
                'median_lppt' => round($median, 2), // ✅ Change to 2 decimal places untuk consistency
            ];
        }

        // ✅ Sort by tahun ascending untuk chart (tahun lama → baru)
        usort($out, function($a, $b) {
            return $a['tahun'] <=> $b['tahun'];
        });

        DashCache::set($ck, $out);
        return $out;
    }

    /**
     * Taburan band (tahun + optional KOD jabatan) — cache 5 min
     * - JOIN ke subquery aktif (DISTINCT idpekerja)
     */
    public function getBands(int $tahun, ?string $kodJab = null): array
    {
        $kodJab = ($kodJab !== null && trim($kodJab) !== '') ? trim($kodJab) : null;
        $ck = "bands:$tahun:" . ($kodJab ?: 'ALL');
        if ($val = DashCache::get($ck, 300)) return $val;

        $sql = "
            SELECT
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 0  AND 50  THEN 1 ELSE 0 END) AS b_0_50,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 51 AND 54  THEN 1 ELSE 0 END) AS b_51_54,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 55 AND 60  THEN 1 ELSE 0 END) AS b_55_60,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 61 AND 64  THEN 1 ELSE 0 END) AS b_61_64,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 65 AND 70  THEN 1 ELSE 0 END) AS b_65_70,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 71 AND 75  THEN 1 ELSE 0 END) AS b_71_75,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 76 AND 80  THEN 1 ELSE 0 END) AS b_76_80,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 81 AND 85  THEN 1 ELSE 0 END) AS b_81_85,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 86 AND 90  THEN 1 ELSE 0 END) AS b_86_90,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 91 AND 95  THEN 1 ELSE 0 END) AS b_91_95,
              SUM(CASE WHEN CONVERT(NUMERIC(10,2), p.f_purata) BETWEEN 96 AND 100 THEN 1 ELSE 0 END) AS b_96_100
            FROM smp_m_penilai p
            LEFT JOIN (
                SELECT DISTINCT LTRIM(RTRIM(idpekerja)) AS idpekerja
                FROM v630staf_prestasi
                WHERE (kodstatus IS NULL OR CONVERT(INT, kodstatus) <> 9)
            ) av ON av.idpekerja = LTRIM(RTRIM(p.f_stafID))
            WHERE CONVERT(INT, p.f_tahun) = :tahun
              " . ($kodJab ? " AND LTRIM(RTRIM(p.f_kodjbt)) = LTRIM(RTRIM(:kod)) " : "") . "
        ";
        $st = $this->db()->prepare($sql);
        $st->bindValue(':tahun', (int)$tahun, PDO::PARAM_INT);
        if ($kodJab) $st->bindValue(':kod', $kodJab);
        $st->execute();
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $out = [
            ['bucket'=>'0–50',   'bil'=>(int)($r['b_0_50']   ?? 0)],
            ['bucket'=>'51–54',  'bil'=>(int)($r['b_51_54']  ?? 0)],
            ['bucket'=>'55–60',  'bil'=>(int)($r['b_55_60']  ?? 0)],
            ['bucket'=>'61–64',  'bil'=>(int)($r['b_61_64']  ?? 0)],
            ['bucket'=>'65–70',  'bil'=>(int)($r['b_65_70']  ?? 0)],
            ['bucket'=>'71–75',  'bil'=>(int)($r['b_71_75']  ?? 0)],
            ['bucket'=>'76–80',  'bil'=>(int)($r['b_76_80']  ?? 0)],
            ['bucket'=>'81–85',  'bil'=>(int)($r['b_81_85']  ?? 0)],
            ['bucket'=>'86–90',  'bil'=>(int)($r['b_86_90']  ?? 0)],
            ['bucket'=>'91–95',  'bil'=>(int)($r['b_91_95']  ?? 0)],
            ['bucket'=>'96–100', 'bil'=>(int)($r['b_96_100'] ?? 0)],
        ];
        DashCache::set($ck, $out);
        return $out;
    }

    /** Rekod asas untuk KPI (tahun + opsyen KOD jabatan) — JOIN ke subquery aktif */
    private function fetchJoinedByYear(int $year, ?string $kodJbt): array
    {
        $sql = "
            SELECT 
                p.f_mark_ppp AS ppp,
                p.f_mark_ppk AS ppk,
                p.f_purata   AS purata
            FROM smp_m_penilai p
            LEFT JOIN (
                SELECT DISTINCT 
                    LTRIM(RTRIM(idpekerja)) AS idpekerja
                FROM v630staf_prestasi
                WHERE 
                    -- kodstatus NULL = masih dikira
                    kodstatus IS NULL
                    OR (
                        -- hanya convert kalau memang numeric
                        ISNUMERIC(kodstatus) = 1
                        AND CONVERT(INT, kodstatus) <> 9
                    )
            ) av 
                ON av.idpekerja = LTRIM(RTRIM(p.f_stafID))
            WHERE 
                -- Elak error 'f_kodjwt' pada f_tahun
                ISNUMERIC(p.f_tahun) = 1
                AND CONVERT(INT, p.f_tahun) = :tahun
                " . ($kodJbt !== null ? " AND LTRIM(RTRIM(p.f_kodjbt)) = LTRIM(RTRIM(:kodjbt))" : "") . "
        ";

        $st = $this->db()->prepare($sql);
        $st->bindValue(':tahun', $year, PDO::PARAM_INT);
        if ($kodJbt !== null) {
            $st->bindValue(':kodjbt', $kodJbt, PDO::PARAM_STR);
        }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    // ========================= Helper =========================

    private static function toFloatOrZero($v): float
    {
        if ($v === null) return 0.0;
        if (is_numeric($v)) return (float)$v;
        $vv = trim((string)$v);
        return is_numeric($vv) ? (float)$vv : 0.0;
    }

    private static function toFloatOrNull($v): ?float
    {
        if ($v === null) return null;
        if (is_numeric($v)) return (float)$v;
        $vv = trim((string)$v);
        return is_numeric($vv) ? (float)$vv : null;
    }

    private function median(array $vals): float
    {
        $n = count($vals);
        if ($n === 0) return 0.0;
        sort($vals, SORT_NUMERIC);
        $m = intdiv($n, 2);
        return ($n % 2) ? (float)$vals[$m] : (float)(($vals[$m-1]+$vals[$m])/2);
    }

    public function warmUp(): void
    {
        // Paksa lazy-conn berlaku awal + query paling ringan
        $pdo = $this->db();
        try {
            // Sybase/SQL Server friendly no-op
            $pdo->query("SELECT 1");
        } catch (Throwable $e) {
            // Jangan rosakkan UX kalau gagal — biar fetch lain cuba macam biasa
        }
    }

}
