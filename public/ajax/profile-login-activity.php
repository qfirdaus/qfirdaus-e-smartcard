<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../controllers/ProfileController.php';

header('Content-Type: application/json; charset=utf-8');

$controller = new ProfileController();
$limit = 30;
$rows = $controller->getLoginActivity($limit);

// Helper functions (small, inline to keep file self-contained)
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function safeDateTime(?string $s): ?DateTime { if (empty($s)) return null; try { return new DateTime($s); } catch (Throwable $e) { return null; } }
function formatDuration(?int $seconds): string {
  if ($seconds === null) return '—';
  if ($seconds < 60) return $seconds . (string)__('profile_duration_seconds_short');
  if ($seconds < 3600) return floor($seconds/60) . (string)__('profile_duration_minutes_short');
  if ($seconds < 86400) return floor($seconds/3600) . (string)__('profile_duration_hours_short') . ' ' . floor(($seconds%3600)/60) . (string)__('profile_duration_minutes_short');
  return floor($seconds/86400) . (string)__('profile_duration_days_short') . ' ' . floor(($seconds%86400)/3600) . (string)__('profile_duration_hours_short');
}

$currentSessionId = session_id() ?: '';

$data = [];
foreach ($rows as $r) {
  $started = safeDateTime($r['started_at'] ?? null);
  $startedText = $started ? $started->format('d/m/Y H:i:s') : '—';
  $ip = $r['ip_address'] ?: '—';
  $ua = $r['user_agent'] ?? '';

  // Basic device parsing (keep simple — same icons used in profile page)
  $icon = 'ri-device-line'; $deviceLabel = (string)__('profile_device_unknown');
  if (stripos($ua, 'iphone') !== false || stripos($ua, 'ipod') !== false) { $icon = 'ri-smartphone-line'; $deviceLabel = (string)__('profile_device_iphone'); }
  elseif (stripos($ua, 'ipad') !== false) { $icon = 'ri-tablet-line'; $deviceLabel = (string)__('profile_device_ipad'); }
  elseif (stripos($ua, 'android') !== false) { $icon = 'ri-smartphone-line'; $deviceLabel = (string)__('profile_device_android'); }
  elseif (stripos($ua, 'windows') !== false) { $icon = 'ri-computer-line'; $deviceLabel = (string)__('profile_device_windows'); }
  elseif (stripos($ua, 'mac') !== false) { $icon = 'ri-macbook-line'; $deviceLabel = (string)__('profile_device_macos'); }
  elseif (stripos($ua, 'cros') !== false) { $icon = 'ri-computer-line'; $deviceLabel = (string)__('profile_device_chromeos'); }
  elseif (stripos($ua, 'linux') !== false) { $icon = 'ri-ubuntu-line'; $deviceLabel = (string)__('profile_device_linux'); }

  $durationText = formatDuration(isset($r['duration_seconds']) ? (int)$r['duration_seconds'] : null);
  $isActive = !empty($r['is_active']);
  $isCurrent = (($r['session_id'] ?? '') === $currentSessionId);

  // Status HTML
  if ($isCurrent) {
    $statusHtml = '<span class="badge bg-primary"><i class="ri-user-line me-1"></i>' . h((__('profile_login_current') ?: 'Semasa')) . '</span>';
  } elseif ($isActive) {
    $statusHtml = '<span class="badge bg-success"><i class="ri-checkbox-circle-line me-1"></i>' . h((__('profile_login_active') ?: 'Aktif')) . '</span>';
  } else {
    $statusHtml = '<span class="badge bg-secondary"><i class="ri-logout-box-line me-1"></i>' . h((__('profile_login_ended') ?: 'Tamat')) . '</span>';
  }

  // Actions HTML (same as server rendered)
  if ($isCurrent) {
    $actions = '<span class="text-muted small">—</span>';
  } elseif ($isActive) {
    $sid = h((string)($r['session_id'] ?? ''));
    $actions = '<button class="btn btn-sm btn-outline-danger btn-kill-session" type="button" data-session-id="' . $sid . '" aria-label="' . h((__('profile_login_kill_session') ?: 'Tamatkan sesi')) . '" title="' . h((__('profile_login_kill_session') ?: 'Tamatkan sesi')) . '"><i class="ri-close-circle-line"></i></button>';
  } else {
    $actions = '<span class="text-muted small">—</span>';
  }

  $deviceHtml = '<i class="' . h($icon) . ' me-1"></i> ' . h($deviceLabel);

  $data[] = [
    'started' => $startedText,
    'ip' => '<code class="text-primary">' . h($ip) . '</code>',
    'device' => $deviceHtml,
    'duration' => $durationText,
    'status' => $statusHtml,
    'actions' => $actions
  ];
}

// Pastikan tiada output lain (notis/whitespace) sebelum JSON — bersihkan buffer
while (ob_get_level()) { ob_end_clean(); }

http_response_code(200);
$out = json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
if ($out === false) {
  // Jika json_encode gagal, kembalikan ralat terstruktur supaya DataTables tidak terkeliru
  $err = json_encode(['error' => 'json_encode_failed', 'message' => json_last_error_msg()]);
  if ($err === false) { $err = '{"error":"json_encode_failed","message":"unknown"}'; }
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo $err;
  exit;
}

header('Content-Type: application/json; charset=utf-8');
echo $out;
exit;
