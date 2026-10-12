<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// classes/Mailer.php
declare(strict_types=1);

require_once __DIR__ . '/ExternalServiceException.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /** @var PHPMailer */
    private PHPMailer $m;

    /** @var array<string,mixed>|null */
    private static ?array $smtpCache = null;   // cache setting dari DB

    /** @var self|null */
    private static ?self $singleton  = null;   // singleton

    /** @var string */
    private string $lastError = '';

    private ?ExternalServiceException $lastExternalServiceException = null;

    private static function currentAppEnv(): string
    {
        if (function_exists('app_env')) {
            return (string)app_env();
        }

        $raw = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ($_ENV['ENVIRONMENT'] ?? getenv('ENVIRONMENT') ?? '');
        $raw = strtolower(trim((string)$raw));
        if ($raw === 'dev') {
            return 'development';
        }
        if (in_array($raw, ['development', 'staging', 'production'], true)) {
            return $raw;
        }

        return 'production';
    }

    private static function appendLog(string $fileName, string $message): void
    {
        $logFile = __DIR__ . '/../log/' . $fileName;
        @file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND);
        error_log($message);
    }

    /**
     * @param array<string,mixed> $smtp
     * @throws \RuntimeException
     */
    private function __construct(array $smtp)
    {
        // === Pastikan PHPMailer tersedia ===
        if (!class_exists(PHPMailer::class)) {
            // composer autoload (dua kemungkinan laluan)
            $a1 = __DIR__ . '/../vendor/autoload.php';
            $a2 = __DIR__ . '/../../vendor/autoload.php';
            if (is_file($a1)) {
                require_once $a1;
            } elseif (is_file($a2)) {
                require_once $a2;
            }

            // fallback terus ke src (struktur seperti halaman uji-emel)
            if (!class_exists(PHPMailer::class)) {
                $b = __DIR__ . '/../assets/vendor/PHPMailer/src';
                if (is_dir($b)) {
                    require_once $b . '/PHPMailer.php';
                    require_once $b . '/SMTP.php';
                    require_once $b . '/Exception.php';
                }
            }
        }
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer not installed or autoload not found.');
        }

        // === Setup PHPMailer ===
        $this->m = new PHPMailer(true);
        $this->m->CharSet  = 'UTF-8';
        $this->m->isSMTP();

        // Host & Port awal dari config
        $this->m->Host = (string)($smtp['host'] ?? 'localhost');
        $this->m->Port = (int)($smtp['port'] ?? 25);

        // Encryption (ikut config)
        $secure = strtolower(trim((string)($smtp['secure'] ?? '')));
        if ($secure === 'ssl') {
            $this->m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls' || $secure === 'starttls') {
            $this->m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        // Auto TLS on (PHPMailer default true, kita pastikan)
        $this->m->SMTPAutoTLS = true;

        // Heuristik Microsoft 365
        $hostLower  = strtolower((string)($smtp['host'] ?? ''));
        $looksM365  = (str_contains($hostLower, 'office365.com')
                    || str_contains($hostLower, 'outlook.office365.com')
                    || str_contains($hostLower, 'smtp.office365.com')
                    || str_contains($hostLower, '.prod.outlook.com'));

        // AUTH:
        // - Jika 'auth' diberi dalam array, hormat nilainya (parse robust).
        // - Jika 'auth' tiada, auto true jika ada 'user'.
        // - Jika detect M365, paksa AUTH = true.
        if (array_key_exists('auth', $smtp)) {
            $smtpAuth = is_bool($smtp['auth']) ? $smtp['auth'] : self::toBool($smtp['auth']);
        } else {
            $smtpAuth = (($smtp['user'] ?? '') !== '');
        }
        if ($looksM365) {
            $smtpAuth = true;
        }
        $this->m->SMTPAuth = $smtpAuth;

        if ($smtpAuth) {
            $this->m->Username = (string)($smtp['user'] ?? '');
            $this->m->Password = (string)($smtp['pass'] ?? '');
        }

        // Jika M365: paksa host standard & STARTTLS:587 + AuthType LOGIN
        if ($looksM365) {
            $this->m->Host       = 'smtp.office365.com';
            $this->m->Port       = 587;
            $this->m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->m->SMTPAuth   = true;
            $this->m->AuthType   = 'LOGIN';
        }

        // Self-signed (dev)
        if (self::toBool($smtp['allow_self_signed'] ?? '0')) {
            $this->m->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        // From / Reply-To (dengan fallback pintar + force username untuk M365)
        $fromEmail = (string)($smtp['from_email'] ?? '');
        $fromName  = (string)($smtp['from_name']  ?? 'e-PrestaSO');

        // Default: untuk M365, force From = Username (boleh override melalui config)
        $forceFromUser = array_key_exists('force_from_username', $smtp)
            ? self::toBool($smtp['force_from_username'])
            : $looksM365;

        if ($forceFromUser && self::isValidEmail($smtp['user'] ?? '')) {
            $fromEmail = (string)$smtp['user'];
        }

        // fallback 1: kalau From masih tak valid & Username valid
        if (!self::isValidEmail($fromEmail) && self::isValidEmail($smtp['user'] ?? '')) {
            $fromEmail = (string)$smtp['user'];
        }
        // fallback 2: derive no-reply@<domain host>
        if (!self::isValidEmail($fromEmail)) {
            $domain    = self::guessDomain((string)($smtp['host'] ?? ''));
            $fromEmail = $domain ? ('no-reply@' . $domain) : 'no-reply@example.com';
        }

        // Set HELO/EHLO hostname & envelope sender
        $this->m->Hostname = self::guessDomain((string)($smtp['host'] ?? '')) ?: 'localhost.localdomain';
        $this->m->Sender   = $fromEmail;

        $this->m->setFrom($fromEmail, $fromName);

        if (!empty($smtp['reply_to'])) {
            $this->m->addReplyTo((string)$smtp['reply_to'], $fromName);
        }

        // SMTP Debug → log file bila APP_ENV local/dev/development
        if (in_array(self::currentAppEnv(), ['local', 'development'], true)) {
            $this->m->SMTPDebug = 2;
            $dbgFile = __DIR__ . '/../log/mail_smtp_debug.log';
            $this->m->Debugoutput = function ($str) use ($dbgFile) {
                @file_put_contents($dbgFile, '[' . date('Y-m-d H:i:s') . "] $str\n", FILE_APPEND);
            };
        }
    }

    /** Buat instance berdasarkan config DB (cached) */
    public static function fromConfig(PDO $pdoMysql): self
    {
        if (self::$singleton) return self::$singleton;
        if (!self::$smtpCache) self::$smtpCache = self::loadSmtpFromDb($pdoMysql);
        self::$singleton = new self(self::$smtpCache);
        return self::$singleton;
    }

    /** Refresh config selepas admin ubah tetapan */
    public static function refreshConfig(PDO $pdoMysql): void
    {
        self::$smtpCache = self::loadSmtpFromDb($pdoMysql);
        self::$singleton = new self(self::$smtpCache);
    }

    /**
     * Hantar emel
     * @param array<int,string>|string $to
     * @param array<string,mixed> $opts
     */
    public function send(
        array|string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $opts = [] // ['cc'=>[], 'bcc'=>[], 'attachments'=>['/path/a.pdf'=> 'a.pdf']]
    ): bool {
        $this->lastError = '';
        $this->lastExternalServiceException = null;
        try {
            $m = $this->m;
            $m->clearAllRecipients();
            $m->clearAttachments();

            foreach ((array)$to as $addr) {
                if (!$addr) continue;
                $m->addAddress((string)$addr);
            }
            if (!empty($opts['cc'])) {
                foreach ((array)$opts['cc'] as $cc) $m->addCC((string)$cc);
            }
            if (!empty($opts['bcc'])) {
                foreach ((array)$opts['bcc'] as $bcc) $m->addBCC((string)$bcc);
            }
            if (!empty($opts['attachments'])) {
                foreach ($opts['attachments'] as $path => $name) {
                    if (is_int($path)) $m->addAttachment((string)$name);
                    else $m->addAttachment((string)$path, (string)$name);
                }
            }

            $m->Subject = $subject;
            $m->isHTML(true);
            $m->Body    = $htmlBody;
            $m->AltBody = $textBody ?? strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $htmlBody));

            $ok = $m->send();
            if (!$ok) {
                $this->lastError = trim($m->ErrorInfo ?: 'Unknown mailer error');
                $this->lastExternalServiceException = $this->buildExternalServiceException($this->lastError);
                self::appendLog('mail_error.log', sprintf('[%s] send() failed: %s', date('Y-m-d H:i:s'), $this->lastError));
            }
            return $ok;

        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->lastExternalServiceException = $this->buildExternalServiceException($this->lastError, $e);
            self::appendLog('mail_error.log', sprintf('[%s] EXC: %s', date('Y-m-d H:i:s'), $this->lastError));
            return false;
        }
    }

    /** One-liner */
    public static function quickSend(
        PDO $pdoMysql, array|string $to, string $subject, string $htmlBody, ?string $textBody = null, array $opts = []
    ): bool {
        return self::fromConfig($pdoMysql)->send($to, $subject, $htmlBody, $textBody, $opts);
    }

    /**
     * Render template ringkas (templates/mail/{name}.html.php / .txt.php)
     * @return array{0:string,1:string}
     */
    public static function render(string $template, array $vars = []): array
    {
        $tplDir   = __DIR__ . '/../templates/mail/';
        $htmlFile = $tplDir . $template . '.html.php';
        $textFile = $tplDir . $template . '.txt.php';

        $html = '';
        $text = '';

        if (is_file($htmlFile)) {
            extract($vars, EXTR_SKIP);
            ob_start(); include $htmlFile; $html = (string)ob_get_clean();
        }
        if (is_file($textFile)) {
            extract($vars, EXTR_SKIP);
            ob_start(); include $textFile; $text = (string)ob_get_clean();
        } else {
            $text = strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $html));
        }
        return [$html, $text];
    }

    /**
     * Baca SMTP dari tbl_m_config (f_group, f_key, f_value)
     * - Sokong group 'smtp' (prefiks smtp_) dan 'email' (prefiks mail_)
     * - Helper $get: cari direct -> smtp_* -> mail_*
     * @return array<string,mixed>
     */
    private static function loadSmtpFromDb(PDO $pdo): array
    {
        $rows = [];
        try {
            $stmt = $pdo->query("SELECT * FROM tbl_m_config");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];

        foreach ($rows as $r) {
            // Struktur utama table
            $group = $r['f_group'] ?? $r['group'] ?? $r['config_group'] ?? null;
            $key   = $r['f_key']   ?? $r['config_key'] ?? $r['key'] ?? $r['name'] ?? $r['param'] ?? $r['k'] ?? null;
            $val   = $r['f_value'] ?? $r['config_value'] ?? $r['value'] ?? $r['val'] ?? $r['v'] ?? null;

            if ($key !== null) {
                $k = strtolower(trim((string)$key));

                if ($group) {
                    $g = strtolower(trim((string)$group));
                    // Jika group 'smtp', duplicate dengan prefix smtp_
                    if ($g === 'smtp'  && strncmp($k, 'smtp_', 5) !== 0) {
                        $map['smtp_' . $k] = (string)($val ?? '');
                    }
                    // Jika group 'email', duplicate dengan prefix mail_
                    if ($g === 'email' && strncmp($k, 'mail_', 5) !== 0) {
                        $map['mail_' . $k] = (string)($val ?? '');
                    }
                }

                $map[$k] = (string)($val ?? '');
            } else {
                // Fallback: masukkan semua string/numeric supaya tetap boleh dicapai
                foreach ($r as $kk => $vv) {
                    if (is_string($vv) || is_numeric($vv)) $map[strtolower($kk)] = (string)$vv;
                }
            }
        }

        // Helper dapatkan kunci: direct -> smtp_* -> mail_*
        $get = function (string $k, $def = '') use ($map) {
            $k = strtolower($k);
            return $map[$k] ?? $map["smtp_$k"] ?? $map["mail_$k"] ?? $def;
        };

        // Parser boolean betul
        $toBool = fn($v) => self::toBool($v);

        return [
            'host'               => (string)$get('host', 'localhost'),
            'port'               => (int)($get('port', 25)),
            'secure'             => strtolower((string)$get('secure', $get('encryption', ''))), // '', tls, ssl
            'user'               => (string)$get('user', $get('username', '')),
            'pass'               => (string)$get('pass', $get('password', '')),

            // 'auth' dari DB atau auto true jika ada 'user'
            'auth'               => $toBool($get('auth', '')) ?: ($get('user', '') !== ''),

            // penting: cover pelbagai nama from (email group biasanya 'mail_from_address')
            'from_email'         => (string)$get('from_email',
                                        $get('from_address',
                                        $get('from', 'noreply@localhost'))),
            'from_name'          => (string)$get('from_name', 'e-PrestaSO'),
            'reply_to'           => (string)$get('reply_to', ''),

            'allow_self_signed'  => $toBool($get('allow_self_signed', '0')),
            'force_from_username'=> $toBool($get('force_from_username', $get('force_from_user',''))),
        ];
    }

    /** Normalizer boolean: '1','true','yes','on','y','t' => true */
    private static function toBool($v): bool
    {
        $v = strtolower(trim((string)$v));
        if ($v === '') return false;
        return in_array($v, ['1', 'true', 'yes', 'on', 'y', 't'], true);
    }

    /** Validasi emel ringkas */
    private static function isValidEmail($v): bool
    {
        return filter_var((string)$v, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Cuba dapat domain wajar daripada host SMTP:
     * - buang prefix lazim (smtp.|mail.|email.)
     * - pulangkan kosong jika tiada titik (contoh 'localhost')
     */
    private static function guessDomain(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost') return '';
        $host = preg_replace('~^(smtp|mail|email)\.~', '', $host) ?? $host;
        return (strpos($host, '.') !== false) ? $host : '';
    }

    /** Ambil last error dari send() */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLastExternalServiceException(): ?ExternalServiceException
    {
        return $this->lastExternalServiceException;
    }

    public function lastFailureAsExternalServiceException(?string $fallbackMessage = null): ExternalServiceException
    {
        return $this->lastExternalServiceException
            ?? $this->buildExternalServiceException($fallbackMessage ?: ($this->lastError ?: 'Email could not be sent.'));
    }

    private function buildExternalServiceException(string $message, ?\Throwable $previous = null): ExternalServiceException
    {
        return self::externalServiceExceptionForSmtpFailure($message, $this->smtpEndpoint(), $previous);
    }

    public static function externalServiceExceptionForSmtpFailure(string $message, ?string $endpoint = null, ?\Throwable $previous = null): ExternalServiceException
    {
        $message = trim($message) !== '' ? trim($message) : 'Email could not be sent.';
        $endpoint = trim((string)$endpoint) !== '' ? trim((string)$endpoint) : 'smtp://unknown';
        $lower = strtolower($message);
        $context = ['transport' => 'smtp'];

        if (
            str_contains($lower, 'authenticate')
            || str_contains($lower, 'authentication')
            || str_contains($lower, 'username')
            || str_contains($lower, 'password')
            || str_contains($lower, 'credentials')
            || str_contains($lower, '535')
        ) {
            return new ExternalServiceAuthenticationException($message, 'SMTP', $endpoint, null, $context, $previous);
        }

        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return new ExternalServiceTimeoutException($message, 'SMTP', $endpoint, null, $context, $previous);
        }

        if (
            str_contains($lower, 'connection refused')
            || str_contains($lower, 'could not connect')
            || str_contains($lower, 'failed to connect')
            || str_contains($lower, 'network')
            || str_contains($lower, 'dns')
            || str_contains($lower, 'getaddrinfo')
            || str_contains($lower, 'ssl')
            || str_contains($lower, 'tls')
            || str_contains($lower, 'certificate')
        ) {
            return new ExternalServiceUnavailableException($message, 'SMTP', $endpoint, null, $context, $previous);
        }

        return new ExternalServiceException($message, 'SMTP', $endpoint, null, 'smtp_delivery', true, $context, 0, $previous);
    }

    private function smtpEndpoint(): string
    {
        $host = trim((string)($this->m->Host ?? ''));
        $port = (int)($this->m->Port ?? 0);
        if ($host === '') {
            return 'smtp://unknown';
        }

        return 'smtp://' . $host . ($port > 0 ? ':' . $port : '');
    }
}
