<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// setting/helper/audit_helper.php
declare(strict_types=1);

/**
 * Audit helper — path-safe dari dalam setting/helper/
 * Root projek diasumsikan di: /var/www/html
 */
$ROOT = dirname(__DIR__, 2); // -> /var/www/html

require_once $ROOT . '/classes/Database.php';
require_once $ROOT . '/classes/AuditLogger.php';

/* ======================================================
 *  CORE SINGLETON & SAFE WRAPPER
 *  GOVERNANCE CRITICAL – DO NOT MODIFY: audit pipeline & safety wrapper
 * ====================================================== */

/** Dapatkan AuditLogger (singleton) */
if (!function_exists('audit_logger')) {
    function audit_logger(): AuditLogger {
        static $inst = null;
        if ($inst === null) {
            $pdo = Database::getInstance('mysql')->getConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $inst = new AuditLogger($pdo);
        }
        return $inst;
    }
}

/** Jalan callable dengan try/catch senyap (tak ganggu flow utama) */
if (!function_exists('audit_safe')) {
    function audit_safe(callable $fn) {
        try { 
            return $fn(); 
        } catch (Throwable $e) { 
            // Log error untuk debugging (jangan block main flow)
            error_log('[audit_safe] Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
            if (defined('AUDIT_DEBUG') && AUDIT_DEBUG) {
                error_log('[audit_safe] Trace: ' . $e->getTraceAsString());
            }
        }
        return null;
    }
}

/* ======================================================
 *  HIGH-LEVEL API: EVENT & CHANGE DIFF
 * ====================================================== */

/** Log event am (VIEW/CREATE/UPDATE/DELETE/LOGIN/LOGOUT/...) */
if (!function_exists('audit_event')) {
    function audit_event(array $e): ?int {
        return audit_safe(function() use ($e) {
            // Enrich event payload with common contextual meta if not present
            $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
            $sessionId = session_id() ?: null;
            $ip = null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if (class_exists('AuditLogger') && method_exists('AuditLogger', 'clientIp')) {
                try { $ip = AuditLogger::clientIp(); } catch (Throwable $_) { $ip = null; }
            } else {
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            }

            // Ensure meta is an array
            if (!isset($e['meta']) || !is_array($e['meta'])) $e['meta'] = [];

            if (function_exists('impersonation_is_active') && impersonation_is_active()) {
                $actorContext = function_exists('impersonation_current_actor_context') ? impersonation_current_actor_context() : [];
                $effectiveContext = function_exists('impersonation_current_effective_user_context') ? impersonation_current_effective_user_context() : [];
                $state = function_exists('impersonation_state') ? impersonation_state() : [];
                $e['meta']['impersonation'] = array_merge([
                    'active' => true,
                    'mode' => function_exists('impersonation_mode') ? impersonation_mode() : ($state['mode'] ?? 'view_only'),
                    'reason' => (string)($state['reason'] ?? ''),
                    'actor_login_id' => $actorContext['login_id'] ?? null,
                    'actor_user_id' => $actorContext['user_id'] ?? null,
                    'actor_name' => $actorContext['name'] ?? null,
                    'effective_login_id' => $effectiveContext['login_id'] ?? null,
                    'effective_user_id' => $effectiveContext['user_id'] ?? null,
                    'effective_name' => $effectiveContext['name'] ?? null,
                ], is_array($e['meta']['impersonation'] ?? null) ? $e['meta']['impersonation'] : []);
            }

            // Merge non-duplicate contextual fields into meta
            if (!isset($e['meta']['request_id']) && $requestId) $e['meta']['request_id'] = $requestId;
            if (!isset($e['meta']['session_id']) && $sessionId) $e['meta']['session_id'] = $sessionId;
            if (!isset($e['meta']['ip'])) $e['meta']['ip'] = $ip;
            if (!isset($e['meta']['user_agent']) && $userAgent) $e['meta']['user_agent'] = $userAgent;
            if (!isset($e['meta']['module'])) {
                $path = strtok($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/'), '?') ?: '/';
                $e['meta']['module'] = $path;
            }
            if (!isset($e['meta']['action']) && !empty($e['event_type'])) {
                $e['meta']['action'] = $e['event_type'];
            }

            // Ensure login_id is present as the primary human-facing audit identifier.
            if (!isset($e['login_id']) || trim((string)$e['login_id']) === '') {
                $derivedLoginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));

                if ($derivedLoginId === '' && !empty($e['meta']) && is_array($e['meta'])) {
                    foreach (['login_id', 'attempted_login_id', 'resolved_login_id', 'f_loginID'] as $key) {
                        if (!empty($e['meta'][$key])) {
                            $derivedLoginId = trim((string)$e['meta'][$key]);
                            break;
                        }
                    }

                    if ($derivedLoginId === '' && !empty($e['meta']['user']) && is_array($e['meta']['user'])) {
                        $metaUser = $e['meta']['user'];
                        if (!empty($metaUser['f_loginID'])) {
                            $derivedLoginId = trim((string)$metaUser['f_loginID']);
                        }
                    }
                }

                if ($derivedLoginId !== '') {
                    $e['login_id'] = $derivedLoginId;
                }
            }

            // Ensure numeric user_id is present (prefer top-level passed value, otherwise derive from session)
            if (!isset($e['user_id']) || $e['user_id'] === null || $e['user_id'] === '') {
                // Try several session/common fields to derive integer staff number
                $derivedUid = null;
                // Prefer explicit numeric f_nopekerja
                if (!empty($_SESSION['f_nopekerja']) && is_numeric((string)$_SESSION['f_nopekerja'])) {
                    $derivedUid = (int)$_SESSION['f_nopekerja'];
                }
                // fallback to nested user array
                if ($derivedUid === null && !empty($_SESSION['user']['f_nopekerja']) && is_numeric((string)$_SESSION['user']['f_nopekerja'])) {
                    $derivedUid = (int)$_SESSION['user']['f_nopekerja'];
                }
                // fallback to f_stafID like "0530-09" -> take first 4 digits as integer 530
                if ($derivedUid === null && !empty($_SESSION['f_stafID']) && preg_match('/^(\d{4})-\d{2}$/', (string)$_SESSION['f_stafID'], $m)) {
                    $derivedUid = (int)$m[1];
                }
                if ($derivedUid === null && !empty($_SESSION['user']['f_stafID']) && preg_match('/^(\d{4})-\d{2}$/', (string)$_SESSION['user']['f_stafID'], $m2)) {
                    $derivedUid = (int)$m2[1];
                }

                // As last resort, if meta contains a numeric staff field, try that
                if ($derivedUid === null && !empty($e['meta']) && is_array($e['meta'])) {
                    foreach (['nopek','no_pekerja','employee_no','staff_no','user_id'] as $k) {
                        if (!empty($e['meta'][$k]) && is_numeric((string)$e['meta'][$k])) { $derivedUid = (int)$e['meta'][$k]; break; }
                    }
                    // also check nested 'user' in meta
                    if ($derivedUid === null && !empty($e['meta']['user']) && is_array($e['meta']['user'])) {
                        $u = $e['meta']['user'];
                        foreach (['f_nopekerja','nopek','no_pekerja','id'] as $k) {
                            if (!empty($u[$k]) && is_numeric((string)$u[$k])) { $derivedUid = (int)$u[$k]; break; }
                        }
                    }
                }

                    if ($derivedUid !== null) {
                        $e['user_id'] = $derivedUid;
                    }
            }

            // Ensure top-level request_id/session_id are present for DB columns
            if (!isset($e['request_id']) && $requestId) $e['request_id'] = $requestId;
            if (!isset($e['session_id']) && $sessionId) $e['session_id'] = $sessionId;

            // If still missing user_id, try to read it from audit_request (request binding done in init.php)
            if ((!isset($e['user_id']) || $e['user_id'] === null || $e['user_id'] === '')) {
                try {
                    $pdo = Database::getInstance('mysql')->getConnection();
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $rid = $e['request_id'] ?? ($GLOBALS['__AUDIT_REQUEST_ID'] ?? null);
                    if ($rid) {
                        $q = $pdo->prepare("SELECT user_id FROM audit_request WHERE request_id = :rid LIMIT 1");
                        $q->execute([':rid' => $rid]);
                        $row = $q->fetch(PDO::FETCH_ASSOC);
                        if (!empty($row['user_id'])) {
                            $e['user_id'] = is_numeric($row['user_id']) ? (int)$row['user_id'] : $row['user_id'];
                        }
                    }
                    // Fallback: try by session_id if still missing
                    if ((!isset($e['user_id']) || $e['user_id'] === null || $e['user_id'] === '') && !empty($e['session_id'])) {
                        $q2 = $pdo->prepare("SELECT user_id FROM audit_request WHERE session_id = :sid ORDER BY id DESC LIMIT 1");
                        $q2->execute([':sid' => $e['session_id']]);
                        $r2 = $q2->fetch(PDO::FETCH_ASSOC);
                        if (!empty($r2['user_id'])) {
                            $e['user_id'] = is_numeric($r2['user_id']) ? (int)$r2['user_id'] : $r2['user_id'];
                        }
                    }
                } catch (Throwable $_) {
                    // ignore — best effort only
                }
            }

            // Debug: if still missing user_id, log diagnostic info when AUDIT_DEBUG enabled
            if (( !isset($e['user_id']) || $e['user_id'] === null || $e['user_id'] === '') && defined('AUDIT_DEBUG') && AUDIT_DEBUG) {
                $dbg = [
                    'request_id' => $e['request_id'] ?? ($GLOBALS['__AUDIT_REQUEST_ID'] ?? null),
                    'session_id' => $e['session_id'] ?? session_id(),
                    'session_f_stafID' => $_SESSION['f_stafID'] ?? null,
                    'session_f_nopekerja' => $_SESSION['f_nopekerja'] ?? null,
                    'profile_f_userID' => $GLOBALS['profile']['f_userID'] ?? null,
                ];
                error_log('[audit_safe] DEBUG: user_id still missing after lookup: ' . json_encode($dbg));
            }

            // Ensure user_id numeric is set (audit_logger will normalize)
            if ((!isset($e['user_id']) || $e['user_id'] === null || $e['user_id'] === '') && isset($derivedUid) && $derivedUid !== null) {
                $e['user_id'] = $derivedUid;
            }

            return audit_logger()->logEvent($e);
        });
    }
}

/** Mulakan bundle perubahan untuk satu target (kumpulan field) */
if (!function_exists('audit_begin_change')) {
    function audit_begin_change(int $eventId, string $tType, string $tId, ?string $reason = null, ?array $meta = null): ?int {
        return audit_safe(function() use ($eventId, $tType, $tId, $reason, $meta) {
            // Ensure meta is an array and enrich with common context
            if (!is_array($meta)) $meta = [];
            $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
            $sessionId = session_id() ?: null;
            $ip = null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if (class_exists('AuditLogger') && method_exists('AuditLogger', 'clientIp')) {
                try { $ip = AuditLogger::clientIp(); } catch (Throwable $_) { $ip = null; }
            } else {
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            }

            if (!isset($meta['request_id']) && $requestId) $meta['request_id'] = $requestId;
            if (!isset($meta['session_id']) && $sessionId) $meta['session_id'] = $sessionId;
            if (!isset($meta['ip'])) $meta['ip'] = $ip;
            if (!isset($meta['user_agent']) && $userAgent) $meta['user_agent'] = $userAgent;
            if (!isset($meta['source'])) {
                $meta['source'] = strtok($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/'), '?') ?: '/';
            }

            return audit_logger()->beginChange($eventId, $tType, $tId, $reason, $meta);
        });
    }
}

/** Tambah perubahan per field (old -> new) */
if (!function_exists('audit_change')) {
    function audit_change(int $changeSetId, string $field, $old, $new, string $type = 'string', bool $sensitive = false, ?string $hint = null): void {
        // Auto-compute diff hint if not provided
        if ($hint === null) {
            $oldEmpty = ($old === null || $old === '');
            $newEmpty = ($new === null || $new === '');
            if ($oldEmpty && !$newEmpty) {
                $hint = 'add';
            } elseif (!$oldEmpty && $newEmpty) {
                $hint = 'remove';
            } else {
                $hint = 'update';
            }
        }

        // Ensure sensitive flag is boolean to match AuditLogger signature
        audit_safe(fn() => audit_logger()->addFieldChange($changeSetId, $field, $old, $new, $type, (bool)$sensitive, $hint));
    }
}

/* ======================================================
 *  REQUEST LINKING: user_id & route
 *  - Guna multi-strategy finder supaya confirm lekat.
 *  - Helper ni overwrite terus (tanpa syarat).
 * ====================================================== */

/** Util: cari ID rekod audit_request semasa */
if (!function_exists('audit_request__find_current_id')) {
    function audit_request__find_current_id(?string $requestId): ?int
    {
        /** @var PDO $pdo */
        $pdo = Database::getInstance('mysql')->getConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1) Paling tepat: request_id (jika diberi)
        if ($requestId) {
            $q = $pdo->prepare("SELECT id FROM audit_request WHERE request_id = :rid LIMIT 1");
            $q->execute([':rid' => $requestId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['id'])) return (int)$row['id'];
        }

        // 2) Rekod "open" (belum ended_at) — biasanya rekod request semasa
        $q = $pdo->query("SELECT id FROM audit_request WHERE ended_at IS NULL ORDER BY id DESC LIMIT 1");
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['id'])) return (int)$row['id'];

        // 3) Ikut session_id TERKINI
        $sid = session_id() ?: null;
        if ($sid) {
            $q = $pdo->prepare("SELECT id FROM audit_request WHERE session_id = :sid ORDER BY id DESC LIMIT 1");
            $q->execute([':sid' => $sid]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['id'])) return (int)$row['id'];
        }

        // 4) Fallback: by IP + path (dalam 2 minit terakhir)
        $ipBin = null;
        if (class_exists('AuditLogger') && method_exists('AuditLogger', 'clientIp')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $ipBin = AuditLogger::ipToBinary(AuditLogger::clientIp());
        }
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        if ($ipBin !== null && $path) {
            $q = $pdo->prepare("
                SELECT id
                FROM audit_request
                WHERE ip_address = :ip
                  AND path = :p
                  AND started_at >= (NOW(6) - INTERVAL 2 MINUTE)
                ORDER BY id DESC
                LIMIT 1
            ");
            $q->execute([':ip' => $ipBin, ':p' => $path]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['id'])) return (int)$row['id'];
        }

        return null;
    }
}

/** Overwrite user_id pada rekod request semasa */
if (!function_exists('audit_request_bind_identity')) {
    function audit_request_bind_identity(?int $userId, ?string $loginId = null, ?string $requestId = null): void
    {
        audit_safe(function() use ($userId, $loginId, $requestId) {
            /** @var PDO $pdo */
            $pdo = Database::getInstance('mysql')->getConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $rid = $requestId ?: ($GLOBALS['__AUDIT_REQUEST_ID'] ?? null);
            $id  = audit_request__find_current_id($rid);
            if ($id === null) return;

            $assignments = [];
            $params = [':id' => $id];

            if ($userId !== null) {
                $assignments[] = 'user_id = :uid';
                $params[':uid'] = $userId;
            }

            $loginIdValue = trim((string)$loginId);
            if ($loginIdValue !== '') {
                try {
                    $check = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'audit_request'
                          AND COLUMN_NAME = 'login_id'
                    ");
                    $check->execute();
                    if ((int)$check->fetchColumn() > 0) {
                        $assignments[] = 'login_id = :login_id';
                        $params[':login_id'] = $loginIdValue;
                    }
                } catch (Throwable $_) {
                    // best effort only
                }
            }

            if ($assignments === []) {
                return;
            }

            $upd = $pdo->prepare("UPDATE audit_request SET " . implode(', ', $assignments) . " WHERE id = :id");
            $upd->execute($params);

            if (defined('AUDIT_DEBUG') && AUDIT_DEBUG) {
                error_log("[AUDIT] bind_identity ok id={$id} uid=" . ($userId ?? 'null') . " login_id=" . ($loginIdValue !== '' ? $loginIdValue : 'null') . " rows=" . $upd->rowCount());
            }
        });
    }
}

if (!function_exists('audit_request_bind_user')) {
    function audit_request_bind_user(int $userId, ?string $requestId = null): void
    {
        $sessionLoginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
        audit_request_bind_identity($userId, $sessionLoginId !== '' ? $sessionLoginId : null, $requestId);
    }
}

/** Overwrite route pada rekod request semasa */
if (!function_exists('audit_request_set_route')) {
    function audit_request_set_route(string $route, ?string $requestId = null): void
    {
        audit_safe(function() use ($route, $requestId) {
            /** @var PDO $pdo */
            $pdo = Database::getInstance('mysql')->getConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $rid = $requestId ?: ($GLOBALS['__AUDIT_REQUEST_ID'] ?? null);
            $id  = audit_request__find_current_id($rid);
            if ($id === null) return;

            $upd = $pdo->prepare("UPDATE audit_request SET route = :r WHERE id = :id");
            $upd->execute([':r' => $route, ':id' => $id]);

            if (defined('AUDIT_DEBUG') && AUDIT_DEBUG) {
                error_log("[AUDIT] set_route ok id={$id} route={$route} rows=" . $upd->rowCount());
            }
        });
    }
}

/* ======================================================
 *  ACTOR LABEL FORMATTING: [nama] (login/legacy identifier)
 * ====================================================== */

/** Format actor label dengan nama + identifier, prefer f_loginID then legacy */
if (!function_exists('audit_format_actor_label')) {
    function audit_format_actor_label(?string $nama = null, ?string $identifier = null): ?string
    {
        if ($nama === null) {
            $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? $_SESSION['user']['f_nickname'] ?? $_SESSION['f_nickname'] ?? null;
        }

        $sessionLoginID = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
        $sessionStafID = trim((string)($_SESSION['f_stafID'] ?? $_SESSION['user']['f_stafID'] ?? ''));
        $suppliedIdentifier = trim((string)($identifier ?? ''));

        $resolvedIdentifier = null;
        if ($sessionLoginID !== '') {
            $resolvedIdentifier = $sessionLoginID;
        } elseif ($suppliedIdentifier !== '') {
            $resolvedIdentifier = $suppliedIdentifier;
        } elseif ($sessionStafID !== '') {
            $resolvedIdentifier = $sessionStafID;
        }

        if ($nama && $resolvedIdentifier) {
            return trim($nama) . ' (' . trim($resolvedIdentifier) . ')';
        } elseif ($nama) {
            return trim($nama);
        } elseif ($resolvedIdentifier) {
            return '(' . trim($resolvedIdentifier) . ')';
        }

        return null;
    }
}

/* ======================================================
 *  MESSAGE FORMATTING: "[action] by [actor_label]"
 * ====================================================== */

/** Format audit message dengan format konsisten: "[action] by [actor_label]" (English) */
if (!function_exists('audit_format_message')) {
    function audit_format_message(string $action, ?string $actorLabel = null): string
    {
        // Get actor_label jika tidak provided
        if ($actorLabel === null) {
            $actorLabel = audit_format_actor_label();
        }
        
        // Format message: "[action] by [actor_label]"
        if ($actorLabel) {
            return $action . ' by ' . $actorLabel;
        }
        
        return $action;
    }
}
