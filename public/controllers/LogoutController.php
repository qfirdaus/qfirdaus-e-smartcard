<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// controllers/LogoutController.php
declare(strict_types=1);

// Nota: audit_event(), AuditLogger, Database dsb. sepatutnya sudah tersedia
// melalui includes/init.php (HelperLoader autoload). Kita tetap guard supaya
// proses logout tak crash jika audit belum tersedia.

class LogoutController
{
    private static function normalizeAuthLoginMethod(?string $method): string
    {
        return strtoupper(trim((string)$method)) === 'SSO' ? 'SSO' : 'MANUAL';
    }

    public static function handle(): void
    {
        // Pastikan session aktif
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (function_exists('impersonation_is_active') && impersonation_is_active() && function_exists('impersonation_stop')) {
            try {
                $pdo = Database::getInstance('mysql')->getConnection();
                impersonation_stop($pdo, 'logout');
            } catch (Throwable $e) {
                error_log('[LogoutController] impersonation_stop error: ' . $e->getMessage());
            }
        }

        // ========== 0) Dapatkan info penting SEBELUM kosongkan session ==========
        $currSessionId = session_id();
        $loginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
        // Get user_id from f_nopekerja (no staf) for audit
        $nopekerja = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
        $userId = null;
        if ($nopekerja && preg_match('/^(\\d+)/', $nopekerja, $m)) {
            $userId = (int)$m[1];
        }
        $actorLabel = $_SESSION['user']['f_nama']
                         ?? ($_SESSION['f_nama'] ?? ($_SESSION['f_nickname'] ?? null));
        $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
        $authMethod = self::normalizeAuthLoginMethod($_SESSION['auth_login_method'] ?? ($_SESSION['user']['auth_login_method'] ?? null));

        // ========== 1) AUDIT: LOGOUT + tamatkan audit_session ==========
        // 1a) Catat event LOGOUT (selamat: jika helper tak ada, diam)
        try {
            if (function_exists('audit_event')) {
                // ✅ FIX: Format actor_label dengan nostaf full: "[nama] (nostaf)"
                $nama = $actorLabel;
                $nostaf = $nopekerja;
                $formattedActorLabel = null;
                if (function_exists('audit_format_actor_label')) {
                    $formattedActorLabel = audit_format_actor_label($nama, $nostaf);
                } else {
                    // Fallback: guna nama sahaja jika helper tidak available
                    $formattedActorLabel = $nama;
                }
                
                // ✅ FIX: Message dalam bahasa Inggeris dengan format: "[action] by [actor_label]"
                $message = audit_format_message('User logout', $formattedActorLabel);
                
                // Include useful meta (ip, user agent, session) so audit.meta column is populated
                $meta = [
                    'login_id'   => $loginId !== '' ? $loginId : null,
                    'session_id' => $currSessionId,
                    'nopekerja'  => $nopekerja,
                    'auth_method'=> $authMethod,
                    'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'request_id' => $requestId ?? null,
                ];

                audit_event([
                    'event_type'  => 'LOGOUT',
                    'severity'    => 'INFO',
                    'outcome'     => 'SUCCESS',
                    'target_type' => 'auth',
                    'target_id'   => 'logout',
                    'message'     => $message,
                    'meta'        => $meta,
                    'request_id'  => $requestId,
                    'session_id'  => $currSessionId,
                    'user_id'     => $userId,
                    'login_id'    => $loginId !== '' ? $loginId : null,
                    'actor_label' => $formattedActorLabel,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[LogoutController] audit_event error: ' . $e->getMessage());
        }

        // 1b) Tutup rekod audit_session (isi ended_at)
        try {
            if ($currSessionId) {
                // Database::getInstance('mysql')->getConnection() dijangka tersedia
                if (class_exists('Database')) {
                    /** @var \PDO $pdo */
                    $pdo = Database::getInstance('mysql')->getConnection();
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $stmt = $pdo->prepare("UPDATE audit_session SET ended_at = NOW(6) WHERE session_id = :sid");
                    $stmt->execute([':sid' => $currSessionId]);
                }
            }
        } catch (\Throwable $e) {
            // Jangan ganggu logout
        }

        // ========== 2) Clear semua data session ==========
        $_SESSION = [];

        // ========== 3) Padam session cookie ==========
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            // Guna options array (support SameSite) jika PHP >= 7.3
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path']     ?? '/',
                'domain'   => $params['domain']   ?? '',
                'secure'   => $params['secure']   ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => 'Lax',
            ]);
        }

        // ========== 4) Hapus session di server ==========
        session_destroy();

        // ========== 5) (Opsyen) buang apa-apa cookie lain (contoh "remember_me") ==========
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // ========== 6) Mula sesi BARU kosong supaya redirect login tidak bawa flash logout ==========
        session_start();
        session_write_close();

        // ========== 7) Anti-cache (elak tekan Back nampak page lama) ==========
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // ========== 8) Redirect ==========
        if (function_exists('redirect')) {
            redirect('index.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }

    /**
     * Perform logout actions (audit, session clear, cookies) but do NOT send
     * redirect or exit — caller can control client-side behaviour (close tab,
     * redirect to IdP, etc.). This is useful when logout page needs to close
     * window via JS after audit is recorded.
     */
    public static function performLogoutNoRedirect(): void
    {
        // Mirror steps 0..6 from handle() but stop before sending headers/redirect
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (function_exists('impersonation_is_active') && impersonation_is_active() && function_exists('impersonation_stop')) {
            try {
                $pdo = Database::getInstance('mysql')->getConnection();
                impersonation_stop($pdo, 'logout_without_redirect');
            } catch (Throwable $e) {
                error_log('[LogoutController] impersonation_stop error: ' . $e->getMessage());
            }
        }

        $currSessionId = session_id();
        $loginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
        $nopekerja = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
        $userId = null;
        if ($nopekerja && preg_match('/^(\\d+)/', $nopekerja, $m)) {
            $userId = (int)$m[1];
        }
        $actorLabel = $_SESSION['user']['f_nama'] ?? ($_SESSION['f_nama'] ?? ($_SESSION['f_nickname'] ?? null));
        $requestId = $GLOBALS['__AUDIT_REQUEST_ID'] ?? null;
        $authMethod = self::normalizeAuthLoginMethod($_SESSION['auth_login_method'] ?? ($_SESSION['user']['auth_login_method'] ?? null));

        try {
            if (function_exists('audit_event')) {
                $nama = $actorLabel;
                $nostaf = $nopekerja;
                $formattedActorLabel = null;
                if (function_exists('audit_format_actor_label')) {
                    $formattedActorLabel = audit_format_actor_label($nama, $nostaf);
                } else {
                    $formattedActorLabel = $nama;
                }
                $message = function_exists('audit_format_message') ? audit_format_message('User logout', $formattedActorLabel) : 'User logout';
                $meta = [
                    'login_id'   => $loginId !== '' ? $loginId : null,
                    'session_id' => $currSessionId,
                    'nopekerja'  => $nopekerja,
                    'auth_method'=> $authMethod,
                    'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'request_id' => $requestId ?? null,
                ];

                audit_event([
                    'event_type'  => 'LOGOUT',
                    'severity'    => 'INFO',
                    'outcome'     => 'SUCCESS',
                    'target_type' => 'auth',
                    'target_id'   => 'logout',
                    'message'     => $message,
                    'meta'        => $meta,
                    'request_id'  => $requestId,
                    'session_id'  => $currSessionId,
                    'user_id'     => $userId,
                    'login_id'    => $loginId !== '' ? $loginId : null,
                    'actor_label' => $formattedActorLabel,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[LogoutController] audit_event error: ' . $e->getMessage());
        }

        try {
            if ($currSessionId) {
                if (class_exists('Database')) {
                    $pdo = Database::getInstance('mysql')->getConnection();
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $stmt = $pdo->prepare("UPDATE audit_session SET ended_at = NOW(6) WHERE session_id = :sid");
                    $stmt->execute([':sid' => $currSessionId]);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Clear session & cookies
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path']     ?? '/',
                'domain'   => $params['domain']   ?? '',
                'secure'   => $params['secure']   ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();

        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // Start a new empty session without a logout flash alert.
        session_start();
        session_write_close();
    }
}
