<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */// ajax/user-sync-sybase.php
declare(strict_types=1);

// Aggressive output buffering
while (ob_get_level() > 0) {
  @ob_end_clean();
}

// Set custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
  error_log("[user-sync-sybase] PHP Error: $errstr in $errfile:$errline");
  return true;
}, E_ALL);

// Set exception handler
set_exception_handler(function($e) {
  error_log('[user-sync-sybase] Uncaught Exception: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
  while (ob_get_level() > 0) {
    @ob_end_clean();
  }
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>true, 'message'=>'Ralat server. Sila hubungi pentadbir sistem.'], JSON_UNESCAPED_UNICODE);
  exit;
});

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json; charset=utf-8');

// Helper functions
function json_ok($data = []) {
  while (ob_get_level() > 0) {
    @ob_end_clean();
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array_merge(['error' => false], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

function json_fail($message, $code = 400) {
  while (ob_get_level() > 0) {
    @ob_end_clean();
  }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = Database::getInstance('mysql')->getConnection();
  ensureAjaxGroupManagePermission($pdo);
  require_once __DIR__ . '/../controllers/UserListController.php';
  
  $controller = new UserListController();
  
  // Call manual sync method
  $result = $controller->syncUsersFromSybaseManual();
  
  if ($result['success']) {
    json_ok([
      'message' => $result['message'],
      'updated' => $result['updated'] ?? 0,
      'skipped' => $result['skipped'] ?? 0,
      'errors' => $result['errors'] ?? 0,
      'total' => $result['total'] ?? 0
    ]);
  } else {
    json_fail($result['message'] ?? 'Gagal sync data dari Sybase.', 500);
  }
  
} catch (Throwable $e) {
  error_log('[user-sync-sybase] Exception: ' . $e->getMessage());
  error_log('[user-sync-sybase] Trace: ' . $e->getTraceAsString());
  json_fail('Ralat sistem semasa sync data.', 500);
}















