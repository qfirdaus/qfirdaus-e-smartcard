<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$redirectPath = function_exists('app_config')
    ? (string)app_config('site.default_home', 'pages/dashboard.php')
    : 'pages/dashboard.php';
$redirectUrl = function_exists('base_url')
    ? base_url($redirectPath)
    : '/' . ltrim($redirectPath, '/');

$systemName = trim((string)(function_exists('app_config')
    ? app_config('system.name', 'IQS Framework')
    : 'IQS Framework'));
$siteTitle = trim((string)(function_exists('app_config')
    ? app_config('site.title', $systemName)
    : $systemName));
$faviconPath = trim((string)(function_exists('app_config')
    ? app_config('site.favicon', 'assets/images/default.ico')
    : 'assets/images/default.ico'));
$sidebarLogo = trim((string)(function_exists('app_config')
    ? app_config('branding.sidebar_logo', 'assets/images/new-logo.png')
    : 'assets/images/new-logo.png'));

$pageLang = htmlspecialchars((string)($_SESSION['lang'] ?? 'ms'), ENT_QUOTES, 'UTF-8');
$pageTitle = (string)(__('access_notice_title') ?: 'Makluman Sistem');
$pageMessage = (string)(__('access_missing_page_text') ?: 'Halaman yang diminta tidak wujud atau tidak lagi tersedia.');
$redirectLabel = $pageLang === 'en' ? 'Continue now' : 'Teruskan sekarang';
$helperLabel = $pageLang === 'en'
    ? 'You will be redirected automatically shortly.'
    : 'Anda akan dibawa semula secara automatik sebentar lagi.';
$countdownLabel = $pageLang === 'en' ? 'Redirecting in' : 'Pengalihan dalam';
$destinationLabel = $pageLang === 'en' ? 'Return to dashboard' : 'Kembali ke dashboard';
$secondsLabel = $pageLang === 'en' ? 'seconds' : 'saat';
$delayMs = 5000;
$delaySeconds = (int)ceil($delayMs / 1000);
?>
<!DOCTYPE html>
<html lang="<?= $pageLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ' | ' . $siteTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="<?= htmlspecialchars(base_url($faviconPath), ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">
    <style>
        :root {
            --surface: #ffffff;
            --surface-soft: rgba(255, 255, 255, 0.72);
            --border: rgba(148, 163, 184, 0.24);
            --text: #10213a;
            --muted: #64748b;
            --primary: #0f4fd6;
            --primary-strong: #0b3caa;
            --accent: #f59e0b;
            --bg-start: #edf4ff;
            --bg-end: #dbeafe;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(15, 79, 214, 0.14), transparent 34%),
                radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.15), transparent 28%),
                linear-gradient(160deg, var(--bg-start) 0%, var(--bg-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .notice-shell {
            width: min(100%, 920px);
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr);
            border: 1px solid var(--border);
            border-radius: 28px;
            overflow: hidden;
            background: var(--surface-soft);
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow);
        }

        .notice-panel {
            padding: 42px 40px;
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0.88));
        }

        .notice-side {
            position: relative;
            padding: 42px 34px;
            background: linear-gradient(165deg, var(--primary) 0%, var(--primary-strong) 100%);
            color: #f8fbff;
            overflow: hidden;
        }

        .notice-side::before,
        .notice-side::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
        }

        .notice-side::before {
            width: 220px;
            height: 220px;
            right: -80px;
            top: -70px;
        }

        .notice-side::after {
            width: 160px;
            height: 160px;
            left: -50px;
            bottom: -45px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 12px;
            background: rgba(255,255,255,0.12);
            padding: 6px;
        }

        .brand-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .brand-label {
            font-size: 12px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #93c5fd;
        }

        .brand-name {
            font-size: 17px;
            font-weight: 700;
            line-height: 1.2;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(15, 79, 214, 0.08);
            color: var(--primary-strong);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 8px rgba(245, 158, 11, 0.12);
        }

        h1 {
            margin: 24px 0 14px;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.08;
            letter-spacing: -0.03em;
        }

        .lead {
            margin: 0 0 18px;
            font-size: 16px;
            line-height: 1.75;
            color: var(--muted);
            max-width: 46ch;
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 24px;
        }

        .meta-card {
            min-width: 180px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: #fff;
        }

        .meta-card .label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta-card .value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .notice-side-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .loader-wrap {
            width: 108px;
            height: 108px;
            margin-bottom: 24px;
            border-radius: 28px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.14);
        }

        .loader {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.2);
            border-top-color: #fff;
            animation: spin 1s linear infinite;
        }

        .side-title {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
        }

        .side-copy {
            margin: 0;
            max-width: 28ch;
            line-height: 1.7;
            color: rgba(241, 245, 249, 0.88);
        }

        .countdown {
            display: inline-flex;
            align-items: baseline;
            gap: 8px;
            margin-top: 22px;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.14);
            width: fit-content;
        }

        .countdown strong {
            font-size: 28px;
            line-height: 1;
        }

        .countdown span {
            font-size: 13px;
            color: rgba(241, 245, 249, 0.88);
        }

        .action-row {
            margin-top: auto;
            padding-top: 28px;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            color: var(--primary-strong);
            background: #fff;
            box-shadow: 0 14px 30px rgba(2, 6, 23, 0.18);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .action-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(2, 6, 23, 0.24);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 860px) {
            .notice-shell {
                grid-template-columns: 1fr;
            }

            .notice-panel,
            .notice-side {
                padding: 30px 24px;
            }

            .action-row {
                margin-top: 28px;
            }
        }
    </style>
</head>
<body>
    <main class="notice-shell" aria-live="polite">
        <section class="notice-panel">
            <div class="status-badge">
                <span class="status-dot" aria-hidden="true"></span>
                <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <h1><?= htmlspecialchars($pageMessage, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="lead"><?= htmlspecialchars($helperLabel, ENT_QUOTES, 'UTF-8') ?></p>

            <div class="meta-row">
                <div class="meta-card">
                    <span class="label"><?= htmlspecialchars($countdownLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="value"><span id="countdown-value"><?= $delaySeconds ?></span> <?= htmlspecialchars($secondsLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="meta-card">
                    <span class="label"><?= htmlspecialchars($destinationLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="value"><?= htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </section>

        <aside class="notice-side">
            <div class="notice-side-inner">
                <div class="brand">
                    <img src="<?= htmlspecialchars(base_url($sidebarLogo), ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
                    <div class="brand-meta">
                        <span class="brand-label">System Redirect</span>
                        <span class="brand-name"><?= htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <div class="loader-wrap" aria-hidden="true">
                    <div class="loader"></div>
                </div>

                <h2 class="side-title"><?= htmlspecialchars($destinationLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="side-copy"><?= htmlspecialchars($pageMessage, ENT_QUOTES, 'UTF-8') ?></p>

                <div class="countdown">
                    <strong id="countdown-pill"><?= $delaySeconds ?></strong>
                    <span><?= htmlspecialchars($secondsLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="action-row">
                    <a class="action-link" href="<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($redirectLabel, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </div>
        </aside>
    </main>

    <script>
        (function () {
            const redirectUrl = <?= json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            const totalMs = <?= $delayMs ?>;
            const startedAt = Date.now();
            const nodes = [
                document.getElementById('countdown-value'),
                document.getElementById('countdown-pill')
            ].filter(Boolean);

            const updateCountdown = () => {
                const remainingMs = Math.max(0, totalMs - (Date.now() - startedAt));
                const remainingSeconds = Math.max(1, Math.ceil(remainingMs / 1000));
                nodes.forEach((node) => {
                    node.textContent = String(remainingSeconds);
                });

                if (remainingMs <= 0) {
                    window.location.replace(redirectUrl);
                    return;
                }

                window.setTimeout(updateCountdown, 200);
            };

            updateCountdown();
        }());
    </script>
</body>
</html>
