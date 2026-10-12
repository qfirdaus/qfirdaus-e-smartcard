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
require_once __DIR__ . '/controllers/LogoutController.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$loginMethod = strtoupper(trim((string)($_SESSION['auth_login_method'] ?? ($_SESSION['user']['auth_login_method'] ?? 'MANUAL'))));
$shouldCloseWindow = ($loginMethod === 'SSO');

LogoutController::performLogoutNoRedirect();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'ms', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string)(__('logout_title') ?: 'Logged Out'), ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
<script>
(function () {
  var shouldCloseWindow = <?= $shouldCloseWindow ? 'true' : 'false' ?>;
  var loginUrl = '<?= htmlspecialchars(base_url('index.php'), ENT_QUOTES, 'UTF-8') ?>';

  if (!shouldCloseWindow) {
    window.location.replace(loginUrl);
    return;
  }

  try {
    window.open('', '_self');
    window.close();
  } catch (e) {}

  setTimeout(function () {
    try {
      if (!window.closed) {
        window.location.replace(loginUrl);
      }
    } catch (e) {
      window.location.href = loginUrl;
    }
  }, 200);
})();
</script>
<noscript>
  <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars(base_url('index.php'), ENT_QUOTES, 'UTF-8') ?>">
</noscript>
</body>
</html>
