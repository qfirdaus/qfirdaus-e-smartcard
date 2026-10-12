<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 *//**
 * save_theme.php
 * AJAX endpoint untuk menyimpan tema pengguna ke database
 * 
 * Security improvements:
 * - CSRF protection
 * - Rate limiting
 * - Input validation
 * - Error message sanitization
 */

// ✅ Start output buffering untuk elak whitespace bocor
if (!headers_sent()) {
    @ob_start();
}

// ✅ Use init.php to ensure proper session and CSRF token setup
// But we need to be careful not to output anything from init.php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../ajax/_helpers.php';

// ✅ Clean output buffers sebelum JSON response
while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ✅ Sahkan sesi login
$loginID = trim((string)($_SESSION['f_loginID'] ?? ''));
$staffID = trim((string)($_SESSION['f_stafID'] ?? ''));
if ($loginID === '' && $staffID === '') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sesi tamat. Sila log masuk semula.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Sahkan method POST sahaja
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Kaedah akses tidak dibenarkan. Hanya POST dibenarkan.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ CSRF Protection
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$postedToken  = '';

// Check dalam JSON body (untuk fetch API)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Check CSRF token dalam JSON body atau header
if (is_array($data) && isset($data['csrf_token'])) {
    $postedToken = (string)$data['csrf_token'];
    unset($data['csrf_token']); // Remove token dari data
} else {
    // Fallback: check dalam header (untuk compatibility)
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $csrfHdr = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    if ($csrfHdr !== '') {
        $postedToken = (string)$csrfHdr;
    }
}

if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'CSRF token tidak sah. Sila muat semula halaman dan cuba lagi.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Rate Limiting (30 requests per 60 seconds)
if (!checkRateLimit('save_theme', 30, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Terlalu banyak permintaan. Sila cuba lagi selepas beberapa saat.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Validate JSON data
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Data tidak sah. Format JSON tidak betul.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Input Validation - Valid theme values
$allowedLayoutValues = SystemConfigConstants::ALLOWED_THEME_MODES; // Layout mode hanya light/dark
$allowedColorValues = SystemConfigConstants::ALLOWED_THEME_COLORS; // Topbar dan Sidebar ikut allowed color constants

$sidebarColor = isset($data['sidebarColor']) ? trim((string)$data['sidebarColor']) : '';
$topbarColor  = isset($data['topbarColor']) ? trim((string)$data['topbarColor']) : '';
$layoutMode   = isset($data['layoutMode']) ? trim((string)$data['layoutMode']) : '';
$topbarCustomSeed = strtoupper(trim((string)($data['topbarCustomSeed'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED)));
$sidebarCustomSeed = strtoupper(trim((string)($data['sidebarCustomSeed'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED)));

// Validate sidebarColor (light, dark, atau brand)
if ($sidebarColor !== '' && !in_array($sidebarColor, $allowedColorValues, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nilai sidebarColor tidak sah. Hanya "' . implode('", "', $allowedColorValues) . '" dibenarkan.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate topbarColor (light, dark, atau brand)
if ($topbarColor !== '' && !in_array($topbarColor, $allowedColorValues, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nilai topbarColor tidak sah. Hanya "' . implode('", "', $allowedColorValues) . '" dibenarkan.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate layoutMode (hanya light atau dark)
if ($layoutMode !== '' && !in_array($layoutMode, $allowedLayoutValues, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nilai layoutMode tidak sah. Hanya "light" atau "dark" dibenarkan.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach (['topbarCustomSeed' => $topbarCustomSeed, 'sidebarCustomSeed' => $sidebarCustomSeed] as $field => $seed) {
    if (!preg_match('/^#[0-9A-F]{6}$/', $seed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $field . ' mesti menggunakan format hex seperti #4254BA.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ✅ Set defaults jika kosong
$themeData = [
    'sidebarColor' => $sidebarColor !== '' ? $sidebarColor : 'dark',
    'topbarColor'  => $topbarColor !== '' ? $topbarColor : 'light',
    'layoutMode'   => $layoutMode !== '' ? $layoutMode : 'light',
    'topbarCustomSeed' => $topbarCustomSeed,
    'sidebarCustomSeed' => $sidebarCustomSeed,
];

$themeJson = json_encode($themeData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ✅ Sambungan DB guna Singleton
try {
    $pdo_mysql = Database::getInstance('mysql')->getConnection();
    $userModel = new User($pdo_mysql);

    // Resolve the account from the authenticated session, including public users.
    $identityColumn = $loginID !== '' ? 'TRIM(f_loginID)' : 'f_stafID';
    $identity = $loginID !== '' ? $loginID : $staffID;
    $stmt = $pdo_mysql->prepare("UPDATE tbl_m_user SET f_themeSetting = :setting WHERE {$identityColumn} = :id LIMIT 1");
    $stmt->execute([
        'setting' => $themeJson,
        'id' => $identity,
    ]);

    // A repeated save may affect zero rows. Verify persisted values instead.
    $profile = $loginID !== ''
        ? $userModel->getProfileByLoginID($loginID)
        : $userModel->getProfile($staffID);
    $themeSetting = json_decode($profile['f_themeSetting'] ?? '{}', true);
    foreach ($themeData as $key => $value) {
        if (!is_array($themeSetting) || ($themeSetting[$key] ?? null) !== $value) {
            throw new RuntimeException('Theme persistence verification failed.');
        }
    }

    $_SESSION['theme.menu']   = $themeSetting['sidebarColor'] ?? $themeData['sidebarColor'];
    $_SESSION['theme.topbar'] = $themeSetting['topbarColor'] ?? $themeData['topbarColor'];
    $_SESSION['theme.layout'] = $themeSetting['layoutMode'] ?? $themeData['layoutMode'];
    $_SESSION['theme.sidebar'] = $_SESSION['theme.menu'];
    $_SESSION['theme.topbar_custom_seed'] = $themeSetting['topbarCustomSeed'] ?? $topbarCustomSeed;
    $_SESSION['theme.sidebar_custom_seed'] = $themeSetting['sidebarCustomSeed'] ?? $sidebarCustomSeed;

    echo json_encode([
        'success' => true,
        'message' => 'Tema berjaya disimpan.'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    // ✅ Sanitize error message - jangan expose database details
    error_log('[save_theme] PDO Error: ' . $e->getMessage() . ' | login_id: ' . ($loginID !== '' ? $loginID : $staffID));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ralat semasa menyimpan tema. Sila cuba lagi atau hubungi pentadbir sistem.'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // ✅ Catch semua exceptions lain
    error_log('[save_theme] Unexpected Error: ' . $e->getMessage() . ' | login_id: ' . ($loginID !== '' ? $loginID : $staffID));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ralat tidak dijangka. Sila cuba lagi atau hubungi pentadbir sistem.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
