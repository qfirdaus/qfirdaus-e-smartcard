<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/AuditLogger.php
declare(strict_types=1);

// GOVERNANCE CRITICAL – DO NOT MODIFY: central audit writer
final class AuditLogger
{
    public function __construct(private \PDO $pdo) {}
    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    /** Get maximum character length for a varchar/text column from information_schema (fallback to default) */
    private function getColumnMaxLength(string $table, string $column, int $fallback = 32): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
            $stmt->execute([':t' => $table, ':c' => $column]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                $int = (int)$val;
                return $int > 0 ? $int : $fallback;
            }
        } catch (Throwable $e) {
            error_log('[AuditLogger] Failed to get column max length for ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
        return $fallback;
    }

    /** Get column type and enum values (if any) */
    private function getColumnInfo(string $table, string $column): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
            $stmt->execute([':t' => $table, ':c' => $column]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $colType = $row['COLUMN_TYPE'] ?? '';
                $max = isset($row['CHARACTER_MAXIMUM_LENGTH']) ? (int)$row['CHARACTER_MAXIMUM_LENGTH'] : null;
                $enum = null;
                if (str_starts_with($colType, 'enum(')) {
                    // parse enum values like: enum('A','B','C')
                    $inside = substr($colType, 5, -1);
                    $parts = str_getcsv($inside, ',', "'", '');
                    $enum = array_map(fn($v) => trim($v, " '\""), $parts);
                }
                return ['column_type' => $colType, 'max_length' => $max, 'enum_values' => $enum];
            }
        } catch (Throwable $e) {
            error_log('[AuditLogger] getColumnInfo failed: ' . $e->getMessage());
        }
        return ['column_type'=>'', 'max_length'=>null, 'enum_values'=>null];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                   AND COLUMN_NAME = :c
                 LIMIT 1"
            );
            $stmt->execute([':t' => $table, ':c' => $column]);
            $this->columnExistsCache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $this->columnExistsCache[$cacheKey] = false;
        }

        return $this->columnExistsCache[$cacheKey];
    }

    private static function normalizeUserId($uid): ?int
    {
        if ($uid === null) return null;
        if (is_int($uid)) return $uid;
        if (is_string($uid) && preg_match('/^\d+$/', $uid)) return (int)$uid;
        return null; // Unknown format, avoid inserting non-numeric into numeric column
    }

    private static function normalizeLoginId($loginId): ?string
    {
        $value = trim((string)$loginId);
        return $value === '' ? null : $value;
    }

    private function normalizeAuditUserId($uid, ?string $loginId = null): ?int
    {
        $normalized = self::normalizeUserId($uid);
        if ($normalized === null) {
            return null;
        }

        $sessionNoPekerja = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
        $sessionUserId = $_SESSION['f_userID'] ?? $_SESSION['user']['f_userID'] ?? null;
        if ($sessionNoPekerja !== null && is_numeric((string)$sessionNoPekerja)) {
            if ($sessionUserId !== null && is_numeric((string)$sessionUserId) && (int)$sessionUserId === $normalized) {
                return (int)$sessionNoPekerja;
            }
            if ((int)$sessionNoPekerja === $normalized) {
                return $normalized;
            }
        }

        $effectiveLoginId = self::normalizeLoginId($loginId)
            ?? self::normalizeLoginId($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? null);

        if ($effectiveLoginId !== null) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT f_userID, f_nopekerja
                    FROM tbl_m_user
                    WHERE TRIM(f_loginID) = :login_id
                    LIMIT 1
                ");
                $stmt->execute([':login_id' => $effectiveLoginId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $rowUserId = isset($row['f_userID']) && is_numeric((string)$row['f_userID']) ? (int)$row['f_userID'] : null;
                $rowNoPekerja = isset($row['f_nopekerja']) && is_numeric((string)$row['f_nopekerja']) ? (int)$row['f_nopekerja'] : null;
                if ($rowNoPekerja !== null && ($rowUserId === $normalized || $rowNoPekerja === $normalized)) {
                    return $rowNoPekerja;
                }
            } catch (Throwable $e) {
                error_log('[AuditLogger] normalizeAuditUserId lookup failed: ' . $e->getMessage());
            }
        }

        return $normalized;
    }

    private static function normalizeAuditLabel($value): ?string
    {
        $value = strtoupper(trim((string)$value));
        return $value === '' ? null : $value;
    }

    /* ===================== REQUEST ===================== */

    /** Log mula request, pulangkan request_id (26 char) */
    public function logRequestStart(array $ctx): string
    {
        $rid = self::ulid26();
        $columns = [
            'request_id', 'session_id', 'user_id', 'method', 'path', 'route', 'query_string',
            'ip_address', 'referrer', 'started_at', 'extra',
        ];
        $placeholders = [
            ':rid', ':sid', ':uid', ':method', ':path', ':route', ':qs',
            ':ip', ':ref', 'NOW(6)', ':extra',
        ];
        $params = [
            ':rid'   => $rid,
            ':sid'   => $ctx['session_id'] ?? null,
            ':uid'   => $this->normalizeAuditUserId($ctx['user_id'] ?? null, $ctx['login_id'] ?? null),
            ':method'=> $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ':path'  => strtok($_SERVER['REQUEST_URI'] ?? '/', '?'),
            ':route' => $ctx['route'] ?? null,
            ':qs'    => $_SERVER['QUERY_STRING'] ?? null,
            ':ip'    => self::ipToBinary(self::clientIp()),
            ':ref'   => $_SERVER['HTTP_REFERER'] ?? null,
            ':extra' => json_encode([
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ], JSON_UNESCAPED_UNICODE),
        ];
        if ($this->tableHasColumn('audit_request', 'login_id')) {
            array_splice($columns, 3, 0, ['login_id']);
            array_splice($placeholders, 3, 0, [':login_id']);
            $params[':login_id'] = self::normalizeLoginId($ctx['login_id'] ?? null);
        }

        $sql = "INSERT INTO audit_request
                (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $rid;
    }

    /** Tamat request: simpan status & latency (ms) */
    public function logRequestEnd(string $rid, int $statusCode, int $latencyMs): void
    {
        $sql = "UPDATE audit_request
                SET status_code=:sc, latency_ms=:lat, ended_at=NOW(6)
                WHERE request_id=:rid";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':rid'=>$rid, ':sc'=>$statusCode, ':lat'=>$latencyMs]);
    }

    /* ====================== EVENT ====================== */

    /** Log event am (VIEW/CREATE/UPDATE/DELETE/LOGIN/...) — pulang event_id */
    public function logEvent(array $e): int
    {
        // Benarkan override IP:
        // - $e['ip']    : string (contoh "203.0.113.7")
        // - $e['ip_bin']: binari (terus simpan)
        $ipBin = $e['ip_bin'] ?? self::ipToBinary($e['ip'] ?? self::clientIp());

        // Determine event_type safely without causing DB truncation warnings.
        $originalEtype = isset($e['event_type']) ? (string)$e['event_type'] : null;
        $etype = null;
        $colInfo = $this->getColumnInfo('audit_event', 'event_type');
        $enumVals = $colInfo['enum_values'] ?? null;
        $maxLen = $colInfo['max_length'] ?? null;

        // Ensure meta is array so we can store original value if needed
        if (!isset($e['meta']) || !is_array($e['meta'])) $e['meta'] = [];

        if (!empty($enumVals)) {
            // If original value is one of allowed enums, use it; otherwise map to OTHER (if available)
            if ($originalEtype !== null && in_array($originalEtype, $enumVals, true)) {
                $etype = $originalEtype;
            } else {
                $fallback = in_array('OTHER', $enumVals, true) ? 'OTHER' : ($enumVals[0] ?? null);
                error_log('[audit_safe] event_type "' . ($originalEtype ?? '') . '" not in enum(' . implode(',', $enumVals) . ') — falling back to "' . ($fallback ?? '') . '"');
                // Preserve original full value in meta for forensic purposes
                if ($originalEtype !== null) $e['meta']['orig_event_type'] = $originalEtype;
                $etype = $fallback;
            }
        } else {
            // No enum restriction: store full original, but truncate if exceeds max length
            if ($originalEtype === null) {
                $etype = null;
            } else {
                $etype = $originalEtype;
                if ($maxLen !== null) {
                    $len = function_exists('mb_strlen') ? mb_strlen($etype) : strlen($etype);
                    if ($len > $maxLen) {
                        error_log("[audit_safe] Truncating event_type from {$len} to {$maxLen} chars");
                        if (function_exists('mb_substr')) $etype = mb_substr($etype, 0, $maxLen);
                        else $etype = substr($etype, 0, $maxLen);
                        if (!isset($e['meta']['orig_event_type'])) $e['meta']['orig_event_type'] = $originalEtype;
                    }
                }
            }
        }

        $outcomeOriginal = self::normalizeAuditLabel($e['outcome'] ?? null);
        $outcome = $outcomeOriginal;
        $outcomeInfo = $this->getColumnInfo('audit_event', 'outcome');
        $outcomeEnum = $outcomeInfo['enum_values'] ?? null;

        if (!isset($e['meta']) || !is_array($e['meta'])) $e['meta'] = [];

        if (!empty($outcomeEnum)) {
            if ($outcome !== null && in_array($outcome, $outcomeEnum, true)) {
                // use as-is
            } else {
                $fallbackMap = [
                    'ATTEMPT' => ['INFO', 'UNKNOWN', 'PENDING'],
                    'PENDING' => ['INFO', 'UNKNOWN', 'FAIL'],
                    'IGNORED' => ['INFO', 'UNKNOWN', 'FAIL'],
                    'SKIPPED' => ['INFO', 'UNKNOWN', 'FAIL'],
                    'DENIED'  => ['FAIL', 'ERROR', 'REJECTED', 'BLOCKED'],
                    'BLOCKED' => ['FAIL', 'ERROR', 'REJECTED', 'DENIED'],
                ];

                $candidates = $fallbackMap[$outcomeOriginal ?? ''] ?? ['INFO', 'UNKNOWN', 'FAIL', 'ERROR'];
                $fallback = null;
                foreach ($candidates as $candidate) {
                    if (in_array($candidate, $outcomeEnum, true)) {
                        $fallback = $candidate;
                        break;
                    }
                }
                if ($fallback === null) {
                    $fallback = $outcomeEnum[0] ?? null;
                }

                if ($outcomeOriginal !== null) {
                    $e['meta']['orig_outcome'] = $outcomeOriginal;
                }
                error_log('[audit_safe] outcome "' . ($outcomeOriginal ?? '') . '" not in enum(' . implode(',', $outcomeEnum) . ') — falling back to "' . ($fallback ?? '') . '"');
                $outcome = $fallback;
            }
        }

        $columns = [
            'occurred_at', 'request_id', 'session_id', 'user_id', 'actor_label', 'ip_address',
            'event_type', 'severity', 'outcome', 'target_type', 'target_id', 'target_label',
            'message', 'meta',
        ];
        $placeholders = [
            'NOW(6)', ':rid', ':sid', ':uid', ':actor', ':ip',
            ':etype', ':sev', ':outc', ':tt', ':tid', ':tlabel',
            ':msg', ':meta',
        ];

        // Additional safety: if column is ENUM, ensure value is valid
        $colInfo = $this->getColumnInfo('audit_event', 'event_type');
        if (!empty($colInfo['enum_values']) && $etype !== null) {
            $allowed = $colInfo['enum_values'];
            if (!in_array($etype, $allowed, true)) {
                // choose a safe fallback (first enum) to avoid warnings
                error_log('[audit_safe] event_type "' . $etype . '" not in enum(' . implode(',', $allowed) . ') — falling back to "' . ($allowed[0] ?? '') . '"');
                $etype = $allowed[0] ?? '';
            }
        }

        $params = [
            ':rid'   => $e['request_id'] ?? null,
            ':sid'   => $e['session_id'] ?? null,
            ':uid'   => $this->normalizeAuditUserId($e['user_id'] ?? null, $e['login_id'] ?? null),
            ':actor' => $e['actor_label'] ?? null,
            ':ip'    => $ipBin,
            ':etype' => $etype,
            ':sev'   => $e['severity'] ?? 'INFO',
            ':outc'  => $outcome,
            ':tt'    => $e['target_type'] ?? null,
            ':tid'   => $e['target_id'] ?? null,
            ':tlabel'=> $e['target_label'] ?? null,
            ':msg'   => $e['message'] ?? null,
            ':meta'  => isset($e['meta']) ? json_encode($e['meta'], JSON_UNESCAPED_UNICODE) : null,
        ];
        if ($this->tableHasColumn('audit_event', 'login_id')) {
            array_splice($columns, 4, 0, ['login_id']);
            array_splice($placeholders, 4, 0, [':login_id']);
            $params[':login_id'] = self::normalizeLoginId($e['login_id'] ?? null);
        }

        $sql = "INSERT INTO audit_event
        (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute($params);
        } catch (Throwable $ex) {
            // Log statement + params for debugging, but don't throw
            error_log('[audit_safe] logEvent execute failed: ' . $ex->getMessage());
            error_log('[audit_safe] SQL: ' . $sql);
            error_log('[audit_safe] Params: ' . json_encode(array_map(function($v){ return is_string($v) ? $v : (is_null($v) ? null : json_encode($v)); }, $params)));
        }
        return (int)$this->pdo->lastInsertId();
    }


    /* =================== CHANGE (DIFF) ================= */

    public function beginChange(int $eventId, string $targetType, string $targetId, ?string $reason=null, ?array $meta=null): int
    {
        $sql = "INSERT INTO audit_change_set (event_id, target_type, target_id, change_reason, meta)
                VALUES (:eid, :tt, :tid, :reason, :meta)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':eid'=>$eventId, ':tt'=>$targetType, ':tid'=>$targetId,
            ':reason'=>$reason,
            ':meta'=> $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function addFieldChange(
        int $changeSetId,
        string $field,
        $old,
        $new,
        string $type='string',
        bool $sensitive=false,
        ?string $hint=null
    ): void {
        if ($sensitive) {
            $old = $old !== null ? self::mask((string)$old) : null;
            $new = $new !== null ? self::mask((string)$new) : null;
        }
        // ✅ Buang noise kalau sama (handle NULL properly)
        // Compare dengan betul: NULL !== empty string, dan NULL !== NULL (same) should skip
        $oldStr = ($old === null) ? null : (string)$old;
        $newStr = ($new === null) ? null : (string)$new;
        if ($oldStr === $newStr) return; // Skip jika sama (termasuk kedua-dua NULL)

        $sql = "INSERT INTO audit_change_field
                (change_set_id, field, old_value, new_value, data_type, is_sensitive, diff_hint)
                VALUES (:cid, :f, :o, :n, :t, :s, :h)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cid'=>$changeSetId, ':f'=>$field,
            ':o'=> self::stringify($old), ':n'=> self::stringify($new),
            ':t'=>$type, ':s'=>$sensitive?1:0, ':h'=>$hint
        ]);
    }

    /* ====================== UTIL ======================= */

    public static function clientIp(): string
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'
        ];
        foreach ($candidates as $h) {
            if (!empty($_SERVER[$h])) {
                $v = $_SERVER[$h];
                if ($h === 'HTTP_X_FORWARDED_FOR') $v = explode(',', $v)[0];
                return trim($v);
            }
        }
        return '0.0.0.0';
    }

    public static function ipToBinary(?string $ip): ?string
    {
        if (!$ip) return null;
        $bin = @inet_pton($ip);
        return $bin === false ? null : $bin;
    }

    /** 26-char random hex (stable length), cukup untuk CHAR(26) */
    public static function ulid26(): string
    {
        return bin2hex(random_bytes(13));
    }

    private static function mask(string $val): string
    {
        $len = function_exists('mb_strlen') ? mb_strlen($val) : strlen($val);
        if ($len <= 4) return str_repeat('*', $len);
        $prefix = function_exists('mb_substr') ? mb_substr($val, 0, 2) : substr($val, 0, 2);
        $suffix = function_exists('mb_substr') ? mb_substr($val, -2) : substr($val, -2);
        return $prefix . str_repeat('*', $len-4) . $suffix;
    }

    private static function stringify($v): ?string
    {
        if ($v === null) return null;
        if (is_scalar($v)) return (string)$v;
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }
}
