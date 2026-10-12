<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/group-create.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../setting/constants/prestasi_constants.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => true, 'message' => (string)__('userGroup_method_not_allowed')], JSON_UNESCAPED_UNICODE);
  exit;
}

function normalizeGroupCategory(string $value): string {
  $normalized = strtoupper(trim($value));
  $allowed = ['STAF', 'PELAJAR', 'UMUM'];
  return in_array($normalized, $allowed, true) ? $normalized : '';
}

function deriveGroupRowClass(string $groupKod): string {
  $slug = strtolower(trim($groupKod));
  $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
  $slug = trim($slug, '-');
  return $slug !== '' ? ('row-group-' . $slug) : '';
}

function deriveBadgeClassFromColor(string $color): string {
  $c = strtolower(trim($color));
  if ($c === '') return 'bg-secondary';

  $named = [
    'red' => 'bg-danger',
    'crimson' => 'bg-danger',
    'maroon' => 'bg-danger',
    'blue' => 'bg-primary',
    'navy' => 'bg-primary',
    'teal' => 'bg-info text-dark',
    'cyan' => 'bg-info text-dark',
    'aqua' => 'bg-info text-dark',
    'green' => 'bg-success',
    'lime' => 'bg-success',
    'yellow' => 'bg-warning text-dark',
    'gold' => 'bg-warning text-dark',
    'orange' => 'bg-warning text-dark',
    'purple' => 'bg-primary',
    'violet' => 'bg-primary',
    'black' => 'bg-dark',
    'gray' => 'bg-secondary',
    'grey' => 'bg-secondary',
    'silver' => 'bg-secondary',
  ];
  if (isset($named[$c])) return $named[$c];

  if (!preg_match('/^#([0-9a-f]{6})$/i', $c, $m)) {
    if (preg_match('/^#([0-9a-f]{3})$/i', $c, $m3)) {
      $h = $m3[1];
      $c = '#' . $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    } else {
      return 'bg-secondary';
    }
  }

  $hex = ltrim($c, '#');
  $r = hexdec(substr($hex, 0, 2));
  $g = hexdec(substr($hex, 2, 2));
  $b = hexdec(substr($hex, 4, 2));

  $max = max($r, $g, $b);
  $min = min($r, $g, $b);
  $delta = $max - $min;
  $hue = 0.0;
  if ($delta > 0) {
    if ($max === $r) $hue = fmod((($g - $b) / $delta), 6.0);
    elseif ($max === $g) $hue = (($b - $r) / $delta) + 2.0;
    else $hue = (($r - $g) / $delta) + 4.0;
    $hue *= 60.0;
    if ($hue < 0) $hue += 360.0;
  }
  $sat = ($max == 0) ? 0 : ($delta / $max);
  $lum = (0.2126*$r + 0.7152*$g + 0.0722*$b) / 255.0;

  if ($sat < 0.16) return ($lum < 0.35) ? 'bg-dark' : 'bg-secondary';
  if ($hue < 20 || $hue >= 345) return 'bg-danger';
  if ($hue < 50) return 'bg-warning text-dark';
  if ($hue < 75) return 'bg-warning text-dark';
  if ($hue < 165) return 'bg-success';
  if ($hue < 215) return 'bg-info text-dark';
  if ($hue < 275) return 'bg-primary';
  return 'bg-primary';
}

try {
  $rawBody = file_get_contents('php://input');
  $json = json_decode($rawBody, true) ?: [];
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  $csrf = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if (!is_string($csrf) || trim($csrf) === '') {
    $csrf = (string)($json['csrf_token'] ?? '');
  }
  if (!$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(400);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_csrf_invalid')], JSON_UNESCAPED_UNICODE); exit;
  }

  if (!checkRateLimit('group_create', 10, 60)) {
    http_response_code(429);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_rate_limit_text')], JSON_UNESCAPED_UNICODE); exit;
  }

  $db = Database::getInstance('mysql')->getConnection();
  if (!hasGroupManagePermission($db)) {
    http_response_code(403);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_create_permission_denied')], JSON_UNESCAPED_UNICODE); exit;
  }

  $groupID = (int)($json['groupID'] ?? 0);
  $kod = trim((string)($json['groupKod'] ?? ''));
  $nama = trim((string)($json['groupName'] ?? ''));
  $categoryUser = normalizeGroupCategory((string)($json['categoryUser'] ?? ''));
  // Legacy columns kept for compatibility only. Force stable defaults to avoid UI confusion.
  $priority = 0;
  $mod = 0;
  $color = trim((string)($json['color'] ?? ''));
  $badgeClass = trim((string)($json['badgeClass'] ?? ''));
  $rowClass = trim((string)($json['rowClass'] ?? ''));
  // allow modul/menu selections (arrays or CSV)
  $modulAccessArr = [];
  if (!empty($json['modulAccess'])) {
    if (is_array($json['modulAccess'])) $modulAccessArr = array_values(array_filter($json['modulAccess'], fn($v)=>trim((string)$v) !== ''));
    else $modulAccessArr = array_filter(array_map('trim', explode(',', (string)$json['modulAccess'])), fn($v)=>$v !== '');
  }
  $menuAccessArr = [];
  if (!empty($json['menuAccess'])) {
    if (is_array($json['menuAccess'])) $menuAccessArr = array_values(array_filter($json['menuAccess'], fn($v)=>trim((string)$v) !== ''));
    else $menuAccessArr = array_filter(array_map('trim', explode(',', (string)$json['menuAccess'])), fn($v)=>$v !== '');
  }
  $modulAccessCsv = $modulAccessArr ? implode(',', $modulAccessArr) : '';
  $menuAccessCsv = $menuAccessArr ? implode(',', $menuAccessArr) : '';

  if ($kod === '' || $nama === '' || $categoryUser === '') {
    http_response_code(422);
    echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_create_required')], JSON_UNESCAPED_UNICODE); exit;
  }

  // Server-safe fallback:
  // If update intent arrives without groupID (common in stale cached JS / partial deploy),
  // and exactly one existing group matches the same code, treat it as update.
  if ($groupID <= 0) {
    $byKod = $db->prepare("SELECT f_groupID, f_groupName FROM tbl_m_group WHERE TRIM(f_groupKod) = TRIM(:kod) LIMIT 2");
    $byKod->execute([':kod' => $kod]);
    $rowsByKod = $byKod->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($rowsByKod) === 1) {
      $groupID = (int)($rowsByKod[0]['f_groupID'] ?? 0);
    } elseif (count($rowsByKod) > 1) {
      http_response_code(409);
      echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_code_conflict')], JSON_UNESCAPED_UNICODE); exit;
    }
  }

  // Defaults for visual styling if user only selects color.
  if ($rowClass === '') $rowClass = deriveGroupRowClass($kod);
  if ($badgeClass === '') $badgeClass = deriveBadgeClassFromColor($color);

  // Save (create or update)
  try {
    if ($groupID > 0) {
      $dup = $db->prepare("SELECT 1 FROM tbl_m_group WHERE TRIM(f_groupKod) = TRIM(:kod) AND f_groupID <> :gid LIMIT 1");
      $dup->execute([':kod' => $kod, ':gid' => $groupID]);
      if ($dup->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_code_duplicate')], JSON_UNESCAPED_UNICODE); exit;
      }

      $stmt = $db->prepare("
        UPDATE tbl_m_group
        SET f_groupKod=:kod,
            f_groupName=:nama,
            f_modulAccess=:modulAccess,
            f_menuAccess=:menuAccess,
            f_categoryUser=:categoryUser,
            f_priority=:prio,
            f_mod=:mod,
            f_color=:color,
            f_badge_class=:badgeClass,
            f_row_class=:rowClass,
            f_updatedt=NOW(),
            f_updateby=:updateby
        WHERE f_groupID=:gid
      ");
      $stmt->execute([
        ':kod'=>$kod, ':nama'=>$nama, ':modulAccess'=>$modulAccessCsv, ':menuAccess'=>$menuAccessCsv, ':categoryUser'=>$categoryUser,
        ':prio'=>$priority, ':mod'=>$mod, ':color'=>$color, ':badgeClass'=>$badgeClass, ':rowClass'=>$rowClass,
        ':updateby'=>(string)($_SESSION['f_stafID'] ?? ''), ':gid'=>$groupID
      ]);
      $newId = $groupID;
    } else {
      $stmt = $db->prepare("
        INSERT INTO tbl_m_group
        (f_groupKod, f_groupName, f_modulAccess, f_menuAccess, f_categoryUser, f_priority, f_mod, f_color, f_badge_class, f_row_class, f_insertdt)
        VALUES
        (:kod, :nama, :modulAccess, :menuAccess, :categoryUser, :prio, :mod, :color, :badgeClass, :rowClass, NOW())
      ");
      $stmt->execute([
        ':kod'=>$kod, ':nama'=>$nama, ':modulAccess'=>$modulAccessCsv, ':menuAccess'=>$menuAccessCsv, ':categoryUser'=>$categoryUser, ':prio'=>$priority, ':mod'=>$mod,
        ':color'=>$color, ':badgeClass'=>$badgeClass, ':rowClass'=>$rowClass
      ]);
      $newId = (int)$db->lastInsertId();
    }
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') {
      http_response_code(409);
      echo json_encode(['error'=>true,'message'=>(string)__('userGroup_group_code_duplicate')], JSON_UNESCAPED_UNICODE); exit;
    }
    throw $e;
  }

  // Invalidate group-related caches (group list/style maps/access summaries)
  clearGroupUiCaches($newId);
  clearSidebarNavigationCaches();

  $userCountStmt = $db->prepare('SELECT COUNT(*) FROM tbl_m_user WHERE f_groupID = :gid');
  $userCountStmt->execute([':gid' => $newId]);
  $userCount = (int)$userCountStmt->fetchColumn();
  $saId = defined('PRESTASI_ROLE_ID_ADM_SA') ? (int)PRESTASI_ROLE_ID_ADM_SA : 0;
  $saCode = defined('PRESTASI_ROLE_KOD_ADM_SA')
    ? strtoupper(trim((string)PRESTASI_ROLE_KOD_ADM_SA))
    : (defined('PRESTASI_ROLE_ADM_SA') ? strtoupper(trim((string)PRESTASI_ROLE_ADM_SA)) : 'ADM-SA');
  $isProtectedGroup = ($saId > 0 && $newId === $saId)
    || (strtoupper(trim($kod)) === $saCode);

  echo json_encode([
    'error'=>false,
    'group'=>[
      'id'=>$newId,
      'kod'=>$kod,
      'nama'=>$nama,
      'categoryUser'=>$categoryUser,
      'color'=>$color,
      'badgeClass'=>$badgeClass,
      'rowClass'=>$rowClass,
      'priority'=>$priority,
      'mod'=>$mod,
      'modulAccess'=>$modulAccessArr,
      'menuAccess'=>$menuAccessArr,
      'userCount'=>$userCount,
      'canDelete'=>empty($modulAccessArr) && empty($menuAccessArr) && $userCount === 0 && !$isProtectedGroup,
    ]
  ], JSON_UNESCAPED_UNICODE);

  // Audit: GROUP_CREATE (non-blocking)
  try {
    if (!function_exists('audit_event')) {
      $auditHelperPath = __DIR__ . '/../setting/helper/audit_helper.php';
      if (file_exists($auditHelperPath)) {
        require_once $auditHelperPath;
      }
    }
    if (function_exists('audit_event')) {
      $actorLabel = null;
      if (function_exists('audit_format_actor_label')) {
        $namaUser = $profile['f_nama'] ?? null;
        $noStaf = $profile['f_stafID'] ?? ($_SESSION['f_stafID'] ?? null);
        $actorLabel = audit_format_actor_label($namaUser, $noStaf);
      }
      $msg = function_exists('audit_format_message')
        ? audit_format_message('Group created', $actorLabel)
        : 'Group created';
      audit_event([
        'action' => ($groupID > 0 ? 'GROUP_UPDATE' : 'GROUP_CREATE'),
        'message' => $msg,
        'target_type' => 'group',
        'target_id' => (string)$newId,
        'target_label' => $nama !== '' ? $nama : $kod,
        'meta' => [
          'group_id' => $newId,
          'group_code' => $kod,
          'group_name' => $nama,
          'group_category' => $categoryUser,
        ],
      ]);
    }
  } catch (Throwable $e) {
    // non-blocking: ignore audit failures
  }

} catch (Throwable $e) {
  http_response_code(500);
  error_log('[group-create] ' . $e->getMessage());
  echo json_encode(['error'=>true,'message'=>(string)__('userGroup_err_server')], JSON_UNESCAPED_UNICODE);
}
