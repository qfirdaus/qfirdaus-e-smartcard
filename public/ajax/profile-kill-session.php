<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/profile-kill-session.php
// Kill/end a user session from audit_session table
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Clean output buffers
while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    
    // Check rate limit
    if (!checkRateLimit('kill_session', 10, 60)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_rate_limit')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Check method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_method_invalid')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_json_invalid')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Validate CSRF token
    if (!isValidCsrfToken((string)($data['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => (string)__('userGroup_csrf_invalid')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Validate session_id
    $sessionId = trim($data['session_id'] ?? '');
    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_id_required')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Prevent killing current session
    $currentSessionId = session_id();
    if ($sessionId === $currentSessionId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_current_denied')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get database connection
    require_once __DIR__ . '/../classes/Database.php';
    $pdo = Database::getInstance('mysql')->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user info for validation
    $stafID = trim($_SESSION['f_stafID'] ?? '');
    $loginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ''));
    if (empty($stafID)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_login_required')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get user_nopekerja for validation
    $sqlUser = "SELECT f_nopekerja FROM tbl_m_user WHERE f_stafID = :stafID LIMIT 1";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([':stafID' => $stafID]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$userRow) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_user_not_found')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $userNopekerja = $userRow['f_nopekerja'] ?? '';
    
    // Verify session belongs to current user
    // Use distinct placeholders to avoid "Invalid parameter number" on some PDO drivers
    $sqlCheck = "
        SELECT id, session_id, user_nopekerja, ended_at
        FROM audit_session
        WHERE session_id = :sid
        AND (
            " . ($loginId !== '' ? "login_id = :login_id OR " : "") . "
            user_nopekerja = :nopek OR user_id = :uid
        )
        LIMIT 1
    ";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $paramsCheck = [
        ':sid' => $sessionId,
        ':nopek' => $userNopekerja,
        ':uid' => $userNopekerja
    ];
    if ($loginId !== '') {
        $paramsCheck[':login_id'] = $loginId;
    }
    $stmtCheck->execute($paramsCheck);
    $sessionRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$sessionRow) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_not_owned')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Check if session already ended
    if (!empty($sessionRow['ended_at'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_already_ended')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Update session to end it
    $sqlUpdate = "
        UPDATE audit_session
        SET ended_at = NOW(6)
        WHERE session_id = :sid
        AND ended_at IS NULL
    ";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([':sid' => $sessionId]);
    
    if ($stmtUpdate->rowCount() > 0) {
        // Log audit event
        try {
            if (function_exists('audit_event')) {
                $userId = null;
                if (!empty($_SESSION['f_nopekerja']) && is_numeric($_SESSION['f_nopekerja'])) {
                    $userId = (int)$_SESSION['f_nopekerja'];
                }
                
                // ✅ FIX: Format actor_label dengan nostaf full: "[nama] (nostaf)"
                $nama = $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null;
                $nostaf = $_SESSION['f_nopekerja'] ?? $_SESSION['user']['f_nopekerja'] ?? null;
                $actorLabel = null;
                if (function_exists('audit_format_actor_label')) {
                    $actorLabel = audit_format_actor_label($nama, $nostaf);
                } else {
                    // Fallback: guna nama sahaja jika helper tidak available
                    $actorLabel = $nama;
                }
                
                // Build message safely (audit helper may not be available in all contexts)
                if (function_exists('audit_format_message')) {
                    $message = audit_format_message('Session terminated', $actorLabel);
                } else {
                    $message = 'Session terminated' . ($actorLabel ? ' by ' . $actorLabel : '');
                }
                
                audit_event([
                    'event_type'  => 'LOGOUT',
                    'severity'    => 'INFO',
                    'outcome'     => 'SUCCESS',
                    'target_type' => 'session',
                    'target_id'   => $sessionId,
                    'target_label' => 'Session terminated by user',
                    'message'     => $message,
                    'session_id'  => $currentSessionId,
                    'user_id'     => $userId,
                    'login_id'    => $loginId !== '' ? $loginId : null,
                    'actor_label' => $actorLabel,
                    'meta'        => [
                        'login_id' => $loginId !== '' ? $loginId : null,
                        'terminated_session_id' => $sessionId,
                        'terminated_by' => $stafID,
                        'hard_kill_mode' => 'bootstrap_enforced'
                    ]
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[profile-kill-session] audit_event error: ' . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => (string)__('profile_session_terminate_success')
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => (string)__('profile_session_terminate_failed')
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (PDOException $e) {
    error_log('[profile-kill-session] PDO Exception: ' . $e->getMessage());
    http_response_code(500);
    $resp = [
        'success' => false,
        'message' => (string)__('profile_session_database_error')
    ];
    if (function_exists('is_development_mode') && is_development_mode()) {
        $resp['debug'] = $e->getMessage();
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[profile-kill-session] Error: ' . $e->getMessage());
    http_response_code(500);
    $resp = [
        'success' => false,
        'message' => (string)__('profile_session_system_error')
    ];
    if (function_exists('is_development_mode') && is_development_mode()) {
        $resp['debug'] = $e->getMessage();
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
}
