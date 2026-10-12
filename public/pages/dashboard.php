<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// pages/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

require_once __DIR__ . '/../controllers/DashboardController.php';

$controller = new DashboardController();

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function t(string $key, string $fallback): string {
  $v = __($key);
  return ($v === $key || $v === null || $v === '') ? $fallback : (string)$v;
}

$PAGE_TITLE = t('dashboard_title', 'Dashboard');

$profile = $controller->profile ?? ($_SESSION['profile'] ?? []);
$userName = $profile['f_nama'] ?? $profile['nama'] ?? ($_SESSION['user_name'] ?? 'User');
$avatarUrl = $profile['avatar_url'] ?? $profile['avatar'] ?? base_url('assets/images/no-image.jpg');
$jawatan = $profile['jawatan'] ?? $profile['f_jawatan'] ?? '';
$jabatan = $profile['jabatan'] ?? $profile['f_jabatan'] ?? '';
$lastLoginRaw = $profile['last_login'] ?? ($_SESSION['last_login'] ?? '');

// Fill jabatan/jawatan from tbl_m_user, prefer f_loginID then fallback f_stafID
try {
  $lookupLoginID = trim((string)($_SESSION['f_loginID'] ?? ($profile['f_loginID'] ?? '')));
  $lookupStafID = trim((string)($_SESSION['f_stafID'] ?? ($profile['f_stafID'] ?? '')));
  if (($jabatan === '' || $jawatan === '') && ($lookupLoginID !== '' || $lookupStafID !== '')) {
    $pdo = Database::getInstance('mysql')->getConnection();
    $stmt = $pdo->prepare("
      SELECT f_jawatan, f_namajabatan
      FROM tbl_m_user
      WHERE " . ($lookupLoginID !== '' ? "TRIM(COALESCE(f_loginID, '')) = :loginID" : "1=0") .
      ($lookupLoginID !== '' && $lookupStafID !== '' ? " OR " : "") .
      ($lookupStafID !== '' ? "f_stafID = :sid" : "") . "
      LIMIT 1
    ");
    if ($lookupLoginID !== '') {
      $stmt->bindValue(':loginID', $lookupLoginID, PDO::PARAM_STR);
    }
    if ($lookupStafID !== '') {
      $stmt->bindValue(':sid', $lookupStafID, PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($jabatan === '' && !empty($row['f_namajabatan'])) $jabatan = (string)$row['f_namajabatan'];
    if ($jawatan === '' && !empty($row['f_jawatan'])) $jawatan = (string)$row['f_jawatan'];
  }
} catch (Throwable $e) {
  // keep fallback
}

// Last login from audit_session (if available)
try {
  $pdo = Database::getInstance('mysql')->getConnection();
  $loginId = trim((string)($profile['f_loginID'] ?? $_SESSION['f_loginID'] ?? ''));
  $userId = (int)($profile['f_userID'] ?? $profile['user_id'] ?? 0);
  $nopek = (string)($profile['f_nopekerja'] ?? $profile['nopekerja'] ?? $_SESSION['f_nopekerja'] ?? '');
  $currentSessionId = session_id() ?: '';
  if ($loginId !== '' || $userId > 0 || $nopek !== '') {
    $sql = "SELECT started_at FROM audit_session WHERE (" .
           ($loginId !== '' ? "login_id = :login_id OR " : "") .
           ($userId > 0 ? "user_id = :uid OR " : "") .
           "user_nopekerja = :nopek)";
    if ($currentSessionId !== '') {
      $sql .= " AND session_id <> :sid";
    }
    $sql .= " ORDER BY started_at DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    if ($loginId !== '') $stmt->bindValue(':login_id', $loginId, PDO::PARAM_STR);
    if ($userId > 0) $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':nopek', $nopek, PDO::PARAM_STR);
    if ($currentSessionId !== '') $stmt->bindValue(':sid', $currentSessionId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['started_at'])) {
      $lastLoginRaw = (string)$row['started_at'];
    }
  }
} catch (Throwable $e) {
  // keep fallback
}

function fmt_dt($v): string {
  if (!$v) return '-';
  $ts = is_numeric($v) ? (int)$v : strtotime((string)$v);
  if (!$ts) return (string)$v;
  return date('d/m/Y h:i A', $ts);
}
$lastLogin = fmt_dt($lastLoginRaw);

$dashboard = [];
if (method_exists($controller, 'getBaseDashboardData')) {
  try { $dashboard = $controller->getBaseDashboardData() ?? []; } catch (Throwable $e) { $dashboard = []; }
} elseif (property_exists($controller, 'dashboard')) {
  $dashboard = $controller->dashboard ?? [];
}

$kpis = $dashboard['kpis'] ?? [];
$quickActions = $dashboard['actions'] ?? [];
$activity = $dashboard['activity'] ?? [];
$health = $dashboard['health'] ?? [];
$announcements = $dashboard['announcements'] ?? [];

$activeRoleId = (int)($_SESSION['group_active_id'] ?? ($profile['f_groupID'] ?? 0));
$activeRoleName = (string)($_SESSION['group_active_name'] ?? ($profile['f_groupName'] ?? $profile['group_name'] ?? ''));
if ($activeRoleId > 0) {
  try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $stmtAct = $pdo->prepare("SELECT f_groupName FROM tbl_m_group WHERE f_groupID = :gid LIMIT 1");
    $stmtAct->execute([':gid' => $activeRoleId]);
    $rowAct = $stmtAct->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty($rowAct['f_groupName'])) {
      $activeRoleName = (string)$rowAct['f_groupName'];
    }
  } catch (Throwable $e) {
    // keep fallback
  }
}

// ===== System Resources (OPTIONAL, admin-only) =====
// SECURITY CRITICAL – DO NOT MODIFY: admin-only gating for optional resources panel
$roleAdminSaId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
$roleAdminPeId = defined('PRESTASI_ROLE_ID_ADM_PE') ? (int)PRESTASI_ROLE_ID_ADM_PE : 0;
$isSystemAdmin = ($activeRoleId > 0) && in_array($activeRoleId, array_filter([$roleAdminSaId, $roleAdminPeId]), true);
$showSystemResources = (defined('ENABLE_SYSTEM_RESOURCES') && ENABLE_SYSTEM_RESOURCES) && $isSystemAdmin;

function resourceStatus(float $pct, float $okMax, float $warnMax): string {
  if ($pct < $okMax) return 'OK';
  if ($pct < $warnMax) return 'Warning';
  return 'Critical';
}

$systemResources = [];
$cpuUsagePct = null;
$memUsagePct = null;
if ($showSystemResources) {
  // CPU Usage (best-effort)
$cpuInfo = ['usage' => null, 'status' => 'Unknown'];
  if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    $load1 = is_array($load) ? (float)$load[0] : null;
    $cpuCores = null;
    $cpuInfoFile = '/proc/cpuinfo';
    if (is_file($cpuInfoFile) && is_readable($cpuInfoFile)) {
      $cpuCores = preg_match_all('/^processor\\s*:/m', (string)@file_get_contents($cpuInfoFile));
    }
    if ($load1 !== null && $cpuCores && $cpuCores > 0) {
      $pct = min(100, max(0, ($load1 / $cpuCores) * 100));
      $cpuInfo = ['usage' => $pct, 'status' => resourceStatus($pct, 70, 85)];
      $cpuUsagePct = $pct;
    }
  }

  // Memory Usage (best-effort)
  $memInfo = ['usage' => null, 'status' => 'Unknown'];
  $memInfoFile = '/proc/meminfo';
  if (is_file($memInfoFile) && is_readable($memInfoFile)) {
    $data = (string)@file_get_contents($memInfoFile);
    if (preg_match('/MemTotal:\\s+(\\d+)/', $data, $m1) && preg_match('/MemAvailable:\\s+(\\d+)/', $data, $m2)) {
      $total = (float)$m1[1];
      $avail = (float)$m2[1];
      if ($total > 0) {
        $usedPct = min(100, max(0, (1 - ($avail / $total)) * 100));
        $memInfo = ['usage' => $usedPct, 'status' => resourceStatus($usedPct, 75, 90)];
        $memUsagePct = $usedPct;
      }
    }
  }

  // Disk Usage
  $basePath = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
  $free = @disk_free_space($basePath);
  $total = @disk_total_space($basePath);
  $diskInfo = ['usage' => null, 'status' => 'Unknown'];
  if ($free !== false && $total !== false && $total > 0) {
    $usedPct = min(100, max(0, (1 - ($free / $total)) * 100));
    $diskInfo = ['usage' => $usedPct, 'status' => resourceStatus($usedPct, 80, 90)];
  }

  $systemResources = [
    ['name' => 'CPU', 'usage' => $cpuInfo['usage'], 'status' => $cpuInfo['status']],
    ['name' => 'Memory', 'usage' => $memInfo['usage'], 'status' => $memInfo['status']],
    ['name' => 'Disk', 'usage' => $diskInfo['usage'], 'status' => $diskInfo['status']],
  ];
}

$statusLabelMap = [
  'OK' => t('dashboard_status_ok','OK'),
  'Warning' => t('dashboard_status_warning','Warning'),
  'Critical' => t('dashboard_status_critical','Critical'),
  'Unknown' => t('dashboard_status_unknown','Unknown'),
  'Degraded' => t('dashboard_status_degraded','Degraded'),
];
$resourceLabelMap = [
  'CPU' => t('dashboard_resource_cpu','CPU'),
  'Memory' => t('dashboard_resource_memory','Memory'),
  'Disk' => t('dashboard_resource_disk','Disk'),
];

// CPU sparkline history (last 5 minutes, 10 points max)
$cpuHistory = [];
if ($showSystemResources) {
  $now = time();
  $hist = $_SESSION['sysres_cpu_history'] ?? [];
  if (is_array($hist)) {
    $hist = array_filter($hist, function($p) use ($now) {
      return is_array($p) && isset($p['t'], $p['v']) && ($now - (int)$p['t'] <= 300);
    });
  } else {
    $hist = [];
  }
  if ($cpuUsagePct !== null) {
    $hist[] = ['t' => $now, 'v' => (float)$cpuUsagePct];
  }
  // keep last 10 points
  $hist = array_slice($hist, -10);
  $_SESSION['sysres_cpu_history'] = $hist;
  $cpuHistory = array_map(fn($p) => (float)$p['v'], $hist);
}

// ===== System Health Check (read-only, safe) =====
$healthChecks = [];
function addHealthCheck(array &$list, string $name, string $status, string $info): void {
  $list[] = ['name' => $name, 'status' => $status, 'info' => $info];
}

// 1) Database Connection
try {
  $pdo = Database::getInstance('mysql')->getConnection();
  $pdo->query('SELECT 1');
  addHealthCheck($healthChecks, t('dashboard_health_db','Database'), 'OK', t('dashboard_health_connected','Connected'));
} catch (Throwable $e) {
  addHealthCheck($healthChecks, t('dashboard_health_db','Database'), 'Critical', t('dashboard_health_conn_failed','Connection failed'));
}

// 2) Application Status (config + bootstrap)
$appOk = class_exists('Config') && function_exists('base_url');
addHealthCheck(
  $healthChecks,
  t('dashboard_health_app','Application'),
  $appOk ? 'OK' : 'Degraded',
  $appOk ? t('dashboard_health_bootstrap_ok','Bootstrap loaded') : t('dashboard_health_config_incomplete','Configuration incomplete')
);

// 3) Storage / Disk Space
$basePath = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
$free = @disk_free_space($basePath);
$total = @disk_total_space($basePath);
if ($free !== false && $total !== false && $total > 0) {
  $pct = (int)floor(($free / $total) * 100);
  if ($pct < 10) $st = 'Critical';
  elseif ($pct < 20) $st = 'Warning';
  else $st = 'OK';
  $freeTpl = t('dashboard_health_storage_free','%s%% free');
  addHealthCheck($healthChecks, t('dashboard_health_storage','Storage'), $st, sprintf($freeTpl, $pct));
} else {
  addHealthCheck($healthChecks, t('dashboard_health_storage','Storage'), 'Unknown', t('dashboard_health_unavailable','Unavailable'));
}

// 4) Cache Status (folder accessibility)
$cacheDir = realpath(__DIR__ . '/../cache') ?: (__DIR__ . '/../cache');
if (is_dir($cacheDir)) {
  $cacheWritable = is_writable($cacheDir);
  addHealthCheck(
    $healthChecks,
    t('dashboard_health_cache','Cache'),
    $cacheWritable ? 'OK' : 'Warning',
    $cacheWritable ? t('dashboard_health_enabled','Enabled') : t('dashboard_health_readonly','Read-only')
  );
} else {
  addHealthCheck($healthChecks, t('dashboard_health_cache','Cache'), 'Warning', t('dashboard_health_disabled','Disabled'));
}

// 5) Environment / Debug Safety
$env = function_exists('is_development_mode') && is_development_mode() ? 'development' : 'production';
$debugOn = (ini_get('display_errors') === '1' || ini_get('display_errors') === 1);
$envStatus = ($env === 'production' && $debugOn) ? 'Warning' : 'OK';
$envLabel = ($env === 'production') ? t('dashboard_env_production', 'production') : t('dashboard_env_development', 'development');
$debugLabel = $debugOn ? t('dashboard_env_debug_on', 'debug ON') : t('dashboard_env_debug_off', 'debug OFF');
$envInfo = $envLabel . ' (' . $debugLabel . ')';
addHealthCheck($healthChecks, 'Environment', $envStatus, $envInfo);

// 6) Audit / Log Writable
$logDir = realpath(__DIR__ . '/../log') ?: (__DIR__ . '/../log');
$logWritable = is_dir($logDir) && is_writable($logDir);
addHealthCheck(
  $healthChecks,
  t('dashboard_health_audit','Audit/Log'),
  $logWritable ? 'OK' : 'Critical',
  $logWritable ? t('dashboard_health_writable','Writable') : t('dashboard_health_not_writable','Not writable')
);

// 7) Scheduled Jobs / Cron (best-effort)
$cronInfo = t('dashboard_health_unknown','Unknown');
$cronStatus = 'Unknown';
$cronFiles = [
  __DIR__ . '/../cache/cron_last_run.txt',
  __DIR__ . '/../cache/last_cron_run.txt',
  __DIR__ . '/../cache/cron.last'
];
foreach ($cronFiles as $f) {
  if (is_file($f)) {
    $ts = trim((string)@file_get_contents($f));
    if ($ts !== '') {
      $cronInfo = $ts;
      $cronStatus = 'OK';
    }
    break;
  }
}
addHealthCheck($healthChecks, t('dashboard_health_cron','Scheduled Jobs'), $cronStatus, $cronInfo);

// 8) Time & Timezone
$tzApp = date_default_timezone_get();
$tzIni = (string)ini_get('date.timezone');
$tzStatus = ($tzIni !== '' && $tzIni === $tzApp) ? 'OK' : 'Warning';
$tzInfo = $tzApp . ($tzIni ? (' / ini: ' . $tzIni) : '');
addHealthCheck($healthChecks, t('dashboard_health_tz','Time & Timezone'), $tzStatus, $tzInfo);
?>
<!DOCTYPE html>
<html lang="<?= h($controller->lang ?? 'ms') ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>
  <link href="<?= base_url('assets/css/datatables-standard.css') ?>?v=<?= h($_ENV['APP_ASSET_VER'] ?? date('ymdHis')) ?>" rel="stylesheet">
  <style>
    .profile-card { overflow: hidden; border-radius: 8px; }
    .profile-hero {
      position: relative;
      border-radius: 8px;
      background:
        radial-gradient(420px 180px at 0% 0%, rgba(125, 211, 252, .22), transparent 52%),
        radial-gradient(340px 180px at 100% 100%, rgba(20, 184, 166, .12), transparent 55%),
        linear-gradient(135deg, rgba(255,255,255,.98), rgba(244,248,255,.95) 52%, rgba(238,244,252,.94) 100%);
      padding: 1.35rem 1.35rem 1.15rem 1.35rem;
      border: 1px solid rgba(15, 23, 42, 0.06);
    }
    [data-bs-theme="dark"] .profile-hero {
      border-color: rgba(255,255,255,0.08);
      background: linear-gradient(135deg, rgba(59,130,246,.18), rgba(16,185,129,.12));
    }
    .profile-hero .avatar{
      width: 96px; height: 96px; border-radius: 50%;
      object-fit: cover; border: 3px solid rgba(255,255,255,.82);
      box-shadow: 0 14px 30px rgba(15,23,42,.12);
      background:#fff;
    }
    .dashboard-greeting{
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      padding:.32rem .72rem;
      border-radius:999px;
      background:rgba(255,255,255,.7);
      border:1px solid rgba(148,163,184,.18);
      color:#475569;
      font-size:.7rem;
      font-weight:700;
      letter-spacing:.08em;
      text-transform:uppercase;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.75);
    }
    .dashboard-identity{
      display:flex;
      flex-direction:column;
      gap:.35rem;
      min-width:0;
    }
    .dashboard-name{
      margin:0;
      font-size:1.28rem;
      line-height:1.15;
      font-weight:800;
      letter-spacing:-.03em;
      color:#0f172a;
    }
    .dashboard-roleline{
      display:flex;
      flex-wrap:wrap;
      gap:.5rem .7rem;
      color:#475569;
      font-size:.88rem;
    }
    .dashboard-roleline span{
      display:inline-flex;
      align-items:center;
      gap:.4rem;
      min-width:0;
    }
    .dashboard-roleline i{
      color:#2563eb;
      opacity:.9;
    }
    .display-name{ font-weight: 700; letter-spacing:.2px; }
    .subline{ color: var(--tb-muted, #6b7280); }
    .chip{
      display:inline-flex; align-items:center; gap:.4rem;
      padding:.35rem .6rem; border-radius:999px;
      border:1px solid rgba(0,0,0,.08);
      background: rgba(0,0,0,.03);
      font-size:.82rem;
    }
    .welcome-meta{
      display:flex;
      flex-wrap:wrap;
      gap:.55rem;
      margin-top:.75rem;
    }
    .welcome-sidepanel{
      min-width:220px;
      max-width:240px;
      padding:1rem 1rem .95rem;
      border-radius:8px;
      background:linear-gradient(180deg, rgba(255,255,255,.92), rgba(248,250,252,.9));
      border:1px solid rgba(148,163,184,.18);
      box-shadow:0 16px 30px rgba(15,23,42,.08);
      display:flex;
      flex-direction:column;
      gap:.85rem;
    }
    .welcome-sidepanel-label{
      font-size:.72rem;
      text-transform:uppercase;
      letter-spacing:.08em;
      color:#64748b;
      font-weight:700;
      margin-bottom:.18rem;
    }
    .welcome-sidepanel-value{
      font-size:.9rem;
      font-weight:700;
      color:#0f172a;
      line-height:1.35;
    }
    .welcome-status{
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      font-size:.78rem;
      font-weight:700;
      color:#0f766e;
      background:rgba(16,185,129,.10);
      border:1px solid rgba(16,185,129,.18);
      padding:.42rem .7rem;
      border-radius:999px;
      width:max-content;
    }
    .welcome-status-dot{
      width:8px;
      height:8px;
      border-radius:50%;
      background:#10b981;
      box-shadow:0 0 0 4px rgba(16,185,129,.14);
    }
    [data-bs-theme="dark"] .chip{
      border-color: rgba(255,255,255,.1);
      background: rgba(255,255,255,.06);
    }

    .dash-card {
      background: #fff;
      border: 1px solid rgba(15, 23, 42, 0.06);
      border-radius: 8px;
      padding: 1rem 1.25rem;
      height: 100%;
    }
    [data-bs-theme="dark"] .dash-card { background: #0f172a; border-color: rgba(255,255,255,0.08); }

    .kpi-card {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
      padding: 1rem;
      border-radius: 8px;
      background: #f8fafc;
      border: 1px solid rgba(15, 23, 42, 0.06);
      height: 100%;
    }
    [data-bs-theme="dark"] .kpi-card { background: #0b1220; }
    .kpi-label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
    .kpi-value { font-size: 1.6rem; font-weight: 700; color: #0f172a; }
    [data-bs-theme="dark"] .kpi-value { color: #e2e8f0; }
    .kpi-sub { font-size: 0.8rem; color: #94a3b8; }

    .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; }
    .action-card {
      border: 1px dashed rgba(15, 23, 42, 0.15);
      border-radius: 8px;
      padding: 0.9rem 1rem;
      text-decoration: none;
      color: inherit;
      display: flex;
      gap: 0.6rem;
      align-items: center;
      background: #ffffff;
    }
    .action-card:hover { border-style: solid; box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08); }
    [data-bs-theme="dark"] .action-card { background: #0f172a; }
    .action-icon { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(2, 132, 199, 0.12); color: #0369a1; }

    .table-neutral th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
    .table-neutral td { vertical-align: top; }

    .muted-empty { color: #94a3b8; font-size: 0.9rem; }
    .resource-panel { position: sticky; top: 1rem; }
    .sparkline { display: inline-flex; align-items: flex-end; gap: 2px; height: 24px; vertical-align: middle; }
    .sparkline .bar { width: 5px; min-height: 6px; background: #94a3b8; border-radius: 2px; opacity: .9; display: inline-block; }
    .sparkline.ok .bar { background: #16a34a; }
    .sparkline.warn .bar { background: #f59e0b; }
    .sparkline.crit .bar { background: #ef4444; }
    .mini-progress { height: 6px; background: rgba(15,23,42,.08); border-radius: 999px; overflow: hidden; width: 80px; display: inline-block; vertical-align: middle; }
    .mini-progress .fill { height: 100%; background: #16a34a; display: block; }
    .mini-progress.warn .fill { background: #f59e0b; }
    .mini-progress.crit .fill { background: #ef4444; }
    .sysres-loader{
      display: inline-flex;
      align-items: center;
    }
    .profile-card{
      position: relative;
      border: 1px solid rgba(148,163,184,.16);
      background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(247,250,254,.92));
      box-shadow: 0 22px 48px rgba(15,23,42,.07);
    }
    .profile-card::after{
      content:"";
      position:absolute;
      inset:0;
      pointer-events:none;
      background:
        linear-gradient(180deg, rgba(255,255,255,.34), rgba(255,255,255,0) 32%),
        radial-gradient(520px 180px at 0% 0%, rgba(125, 211, 252, .10), transparent 45%);
      opacity:.9;
    }
    .profile-hero{
      overflow:hidden;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.55);
    }
    .chip{
      background: rgba(255,255,255,.6);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
    }
    .dash-card{
      position: relative;
      overflow:hidden;
      border-radius: 8px;
      box-shadow: 0 18px 38px rgba(15,23,42,.06);
      background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.9));
    }
    .dash-card::before{
      content:"";
      position:absolute;
      inset:0 0 auto 0;
      height:1px;
      background: linear-gradient(90deg, rgba(255,255,255,.7), rgba(255,255,255,0));
      pointer-events:none;
    }
    .kpi-card{
      border-radius: 8px;
      background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
      box-shadow: 0 14px 32px rgba(15,23,42,.05);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .kpi-card:hover{
      transform: translateY(-2px);
      box-shadow: 0 20px 40px rgba(15,23,42,.08);
      border-color: rgba(59,130,246,.18);
    }
    .kpi-value{
      letter-spacing:-.03em;
    }
    .action-card{
      border-style: solid;
      border-color: rgba(148,163,184,.16);
      border-radius: 8px;
      background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
      box-shadow: 0 12px 28px rgba(15,23,42,.05);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
    }
    .action-card:hover{
      transform: translateY(-2px);
      border-color: rgba(59,130,246,.22);
      box-shadow: 0 18px 36px rgba(15,23,42,.08);
      background: linear-gradient(180deg, rgba(255,255,255,1), rgba(241,245,249,.94));
    }
    .action-icon{
      box-shadow: inset 0 1px 0 rgba(255,255,255,.55);
    }
    .resource-panel{
      top: 1.15rem;
    }
    .resource-panel .table-responsive{
      border-radius: 8px;
      background: rgba(255,255,255,.55);
      padding: .2rem;
    }
    .resource-panel .table thead th{
      background: transparent;
    }
    .resource-panel .badge{
      border-radius: 999px;
      box-shadow: 0 8px 18px rgba(15,23,42,.08);
    }
    [data-bs-theme="dark"] .profile-card{
      border-color: rgba(255,255,255,.08);
      background: linear-gradient(180deg, rgba(15,23,42,.96), rgba(15,23,42,.92));
      box-shadow: 0 24px 52px rgba(2,6,23,.24);
    }
    [data-bs-theme="dark"] .profile-hero{
      background:
        radial-gradient(420px 180px at 0% 0%, rgba(59,130,246,.20), transparent 52%),
        radial-gradient(340px 180px at 100% 100%, rgba(20,184,166,.14), transparent 55%),
        linear-gradient(135deg, rgba(15,23,42,.90), rgba(15,23,42,.84) 52%, rgba(2,6,23,.88) 100%);
    }
    [data-bs-theme="dark"] .dashboard-greeting{
      background:rgba(15,23,42,.58);
      border-color:rgba(148,163,184,.18);
      color:#cbd5e1;
      box-shadow:none;
    }
    [data-bs-theme="dark"] .dashboard-name,
    [data-bs-theme="dark"] .welcome-sidepanel-value{
      color:#e2e8f0;
    }
    [data-bs-theme="dark"] .dashboard-roleline{
      color:#cbd5e1;
    }
    [data-bs-theme="dark"] .dashboard-roleline i{
      color:#7dd3fc;
    }
    [data-bs-theme="dark"] .welcome-sidepanel{
      background:linear-gradient(180deg, rgba(15,23,42,.85), rgba(2,6,23,.82));
      border-color:rgba(148,163,184,.16);
      box-shadow:0 18px 34px rgba(2,6,23,.2);
    }
    [data-bs-theme="dark"] .welcome-sidepanel-label{
      color:#94a3b8;
    }
    [data-bs-theme="dark"] .welcome-status{
      background:rgba(16,185,129,.14);
      border-color:rgba(16,185,129,.22);
      color:#6ee7b7;
    }
    [data-bs-theme="dark"] .dash-card{
      background: linear-gradient(180deg, rgba(15,23,42,.96), rgba(2,6,23,.94));
      box-shadow: 0 20px 42px rgba(2,6,23,.2);
    }
    [data-bs-theme="dark"] .kpi-card{
      background: linear-gradient(180deg, rgba(15,23,42,.96), rgba(15,23,42,.9));
      border-color: rgba(255,255,255,.08);
    }
    [data-bs-theme="dark"] .action-card{
      background: linear-gradient(180deg, rgba(15,23,42,.98), rgba(15,23,42,.92));
      border-color: rgba(255,255,255,.08);
      box-shadow: 0 16px 34px rgba(2,6,23,.18);
    }
    @media (max-width: 991.98px){
      .profile-hero > .d-flex{
        flex-direction:column !important;
      }
      .welcome-sidepanel{
        width:100%;
        max-width:none;
        min-width:0;
      }
    }
  </style>
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>" data-menu-color="<?= h($_SESSION['theme.menu'] ?? 'light') ?>" data-layout="vertical">

<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">

        <div class="row mb-2">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title mb-0"><i class="ri-dashboard-line me-1"></i> <?= h(t('dashboard_title','Dashboard')) ?></h4>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item active"><?= h(t('dashboard_breadcrumb','Dashboard')) ?></li>
                </ol>
              </nav>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="<?= $showSystemResources ? 'col-lg-8' : 'col-12' ?>">
            <div class="profile-card">
              <div class="profile-hero">
                <div class="d-flex align-items-start gap-3 justify-content-between">
                  <div class="d-flex align-items-start gap-3 flex-grow-1 min-w-0">
                    <div class="position-relative flex-shrink-0">
                      <img src="<?= h($avatarUrl) ?>"
                           alt="Avatar"
                           class="avatar"
                           onerror="this.onerror=null;this.src='<?= h(base_url('assets/images/no-image.jpg')) ?>';">
                    </div>
                    <div class="dashboard-identity flex-grow-1">
                      <span class="dashboard-greeting">
                        <i class="ri-sparkling-2-line"></i>
                        <?= h(t('dashboard_welcome','Welcome')) ?>
                      </span>
                      <h3 class="dashboard-name"><?= h($userName) ?></h3>
                      <div class="dashboard-roleline">
                        <?php if ($jawatan !== ''): ?>
                          <span><i class="ri-briefcase-2-line"></i><?= h($jawatan) ?></span>
                        <?php endif; ?>
                        <?php if ($jabatan !== ''): ?>
                          <span><i class="ri-building-2-line"></i><?= h($jabatan) ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="welcome-meta">
                        <?php if ($activeRoleId > 0): ?>
                          <span class="chip"><i class="ri-shield-user-line"></i> <?= h($activeRoleName !== '' ? $activeRoleName : 'Active Role') ?></span>
                        <?php endif; ?>
                        <span class="chip"><i class="ri-time-line"></i> <?= h(t('dashboard_last_login','Last login')) ?>: <?= h($lastLogin) ?></span>
                      </div>
                    </div>
                  </div>
                  <div class="welcome-sidepanel ms-lg-3">
                    <div>
                      <div class="welcome-sidepanel-label"><?= h(t('dashboard_last_login','Last login')) ?></div>
                      <div class="welcome-sidepanel-value"><?= h($lastLogin) ?></div>
                    </div>
                    <div>
                      <div class="welcome-sidepanel-label"><?= h(t('dashboard_breadcrumb','Dashboard')) ?></div>
                      <div class="welcome-status">
                        <span class="welcome-status-dot"></span>
                        <?= h(t('dashboard_status_ok','Active Session')) ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
          <?php if ($showSystemResources): ?>
          <div class="col-lg-4">
              <div class="dash-card resource-panel">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0"><?= h(t('dashboard_resources_title','System Resources')) ?></h5>
                <div class="d-flex align-items-center gap-2">
                  <span class="sysres-loader d-none">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                  </span>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSysResRefresh" title="<?= h(t('dashboard_refresh','Refresh')) ?>">
                    <i class="ri-refresh-line"></i>
                  </button>
                </div>
              </div>
              <div class="table-responsive dt-standard-shell">
                <table class="table table-sm table-hover table-neutral mb-0" id="sysResTable">
                  <thead>
                    <tr>
                      <th><?= h(t('dashboard_resources_col_resource','Resource')) ?></th>
                      <th><?= h(t('dashboard_resources_col_usage','Usage')) ?></th>
                      <th><?= h(t('dashboard_resources_col_status','Status')) ?></th>
                    </tr>
                  </thead>
                  <tbody id="sysResBody">
                    <?php foreach ($systemResources as $r):
                      $status = $r['status'] ?? 'Unknown';
                        $badgeClass = 'bg-secondary';
                        if ($status === 'OK') $badgeClass = 'bg-success';
                        elseif ($status === 'Warning') $badgeClass = 'bg-warning';
                        elseif ($status === 'Critical') $badgeClass = 'bg-danger';
                      ?>
                    <tr>
                      <td><?= h($resourceLabelMap[$r['name'] ?? ''] ?? ($r['name'] ?? '-')) ?></td>
                      <td>
                        <span class="d-inline-flex align-items-center gap-2">
                          <span>
                            <?php
                          $usageText = $r['usage'] === null ? t('dashboard_status_unknown','Unknown') : h(number_format((float)$r['usage'], 0)) . '%';
                              echo $usageText;
                            ?>
                          </span>
                          <?php if (($r['name'] ?? '') === 'CPU'): ?>
                          <?php
                            $sparkClass = ($status === 'OK') ? 'ok' : (($status === 'Warning') ? 'warn' : (($status === 'Critical') ? 'crit' : ''));
                            $points = $cpuHistory ?: [];
                            if (empty($points) && $cpuUsagePct !== null) {
                              $points = array_fill(0, 5, (float)$cpuUsagePct);
                            }
                            if (!empty($points) && count($points) < 5) {
                              $last = (float)end($points);
                              while (count($points) < 5) { $points[] = $last; }
                            }
                          ?>
                          <span class="sparkline <?= h($sparkClass) ?>">
                            <?php if (!empty($points)): ?>
                              <?php foreach ($points as $v):
                                $h = max(8, min(24, (int)round(($v / 100) * 24)));
                              ?>
                                <span class="bar" style="height: <?= $h ?>px;"></span>
                              <?php endforeach; ?>
                            <?php else: ?>
                              <span class="bar" style="height: 6px;"></span>
                              <span class="bar" style="height: 6px;"></span>
                              <span class="bar" style="height: 6px;"></span>
                            <?php endif; ?>
                          </span>
                          <?php elseif (($r['name'] ?? '') === 'Memory'): ?>
                          <?php
                            $memPct = $memUsagePct;
                            $pClass = ($status === 'OK') ? '' : (($status === 'Warning') ? 'warn' : (($status === 'Critical') ? 'crit' : ''));
                            $fill = $memPct === null ? 0 : (int)round(max(0, min(100, $memPct)));
                          ?>
                          <span class="mini-progress <?= h($pClass) ?>">
                            <span class="fill" style="width: <?= $fill ?>%;"></span>
                          </span>
                          <?php endif; ?>
                        </span>
                      </td>
                      <td><span class="badge <?= h($badgeClass) ?>"><?= h($statusLabelMap[$status] ?? $status) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              </div>
          </div>
          <?php endif; ?>
        </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
  (function(){
    const btn = document.getElementById('btnSysResRefresh');
    const body = document.getElementById('sysResBody');
    const loader = document.querySelector('.sysres-loader');
    const i18nStatus = {
      OK: <?= json_encode(t('dashboard_status_ok','OK')) ?>,
      Warning: <?= json_encode(t('dashboard_status_warning','Warning')) ?>,
      Critical: <?= json_encode(t('dashboard_status_critical','Critical')) ?>,
      Unknown: <?= json_encode(t('dashboard_status_unknown','Unknown')) ?>,
      Degraded: <?= json_encode(t('dashboard_status_degraded','Degraded')) ?>
    };
    const i18nUnknown = <?= json_encode(t('dashboard_status_unknown','Unknown')) ?>;
    const i18nResource = {
      CPU: <?= json_encode(t('dashboard_resource_cpu','CPU')) ?>,
      Memory: <?= json_encode(t('dashboard_resource_memory','Memory')) ?>,
      Disk: <?= json_encode(t('dashboard_resource_disk','Disk')) ?>
    };
    const badgeClass = (status) => {
      if (status === 'OK') return 'bg-success';
      if (status === 'Warning') return 'bg-warning';
      if (status === 'Critical') return 'bg-danger';
      return 'bg-secondary';
    };

    function renderSparkline(points, status){
      const cls = status === 'OK' ? 'ok' : (status === 'Warning' ? 'warn' : (status === 'Critical' ? 'crit' : ''));
      if (!Array.isArray(points) || points.length === 0) {
        points = [0,0,0];
      }
      if (points.length < 5) {
        const last = points[points.length - 1] || 0;
        while (points.length < 5) points.push(last);
      }
      const bars = points.map(v => {
        const h = Math.max(8, Math.min(24, Math.round((v / 100) * 24)));
        return `<span class="bar" style="height:${h}px;"></span>`;
      }).join('');
      return `<span class="sparkline ${cls}">${bars}</span>`;
    }

    function renderProgress(pct, status){
      const cls = status === 'Warning' ? 'warn' : (status === 'Critical' ? 'crit' : '');
      const fill = pct === null ? 0 : Math.round(Math.max(0, Math.min(100, pct)));
      return `<span class="mini-progress ${cls}"><span class="fill" style="width:${fill}%;"></span></span>`;
    }

    async function refreshResources(){
      if (!btn || !body) return;
      btn.disabled = true;
      if (loader) loader.classList.remove('d-none');
      try {
        const r = await fetch('<?= h(base_url('ajax/system-resources.php')) ?>', {
          method: 'POST',
          headers: { 'Accept': 'application/json' }
        });
        const j = await r.json();
        if (!r.ok || !j || j.error) return;
        const cpuHistory = Array.isArray(j.cpu_history) ? j.cpu_history : [];
        const rows = (j.resources || []).map(res => {
          const name = res.name || '-';
          const status = res.status || 'Unknown';
          const usageText = res.usage === null ? i18nUnknown : `${Math.round(res.usage)}%`;
          let usageHtml = `<span class="d-inline-flex align-items-center gap-2"><span>${usageText}</span>`;
          if (name === 'CPU') {
            usageHtml += renderSparkline(cpuHistory, status);
          } else if (name === 'Memory') {
            usageHtml += renderProgress(res.usage, status);
          }
          usageHtml += `</span>`;
          return `<tr>
            <td>${i18nResource[name] || name}</td>
            <td>${usageHtml}</td>
            <td><span class="badge ${badgeClass(status)}">${i18nStatus[status] || status}</span></td>
          </tr>`;
        }).join('');
        body.innerHTML = rows || '';
      } catch (e) {
        // silent fail
      } finally {
        if (loader) loader.classList.add('d-none');
        btn.disabled = false;
      }
    }

    btn?.addEventListener('click', function(e){
      e.preventDefault();
      refreshResources();
    });
  })();
</script>
</body>
</html>
