<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
// ======================================
// ✅ Controller: Tetapan Sistem (Clean, no-legacy)
// ======================================

declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Config.php';
require_once __DIR__ . '/../classes/SystemConfigConstants.php';
require_once __DIR__ . '/../classes/DatabaseConnectionRepository.php';
require_once __DIR__ . '/../classes/DatabaseConnectionValidator.php';
require_once __DIR__ . '/../classes/DatabaseConnectionFactory.php';
require_once __DIR__ . '/../classes/DatabaseConnectionDiagnostics.php';
require_once __DIR__ . '/../classes/DatabasePreviewSanitizer.php';
require_once __DIR__ . '/../classes/DatabaseErrorRedactor.php';
require_once __DIR__ . '/../classes/DatabaseErrorClassifier.php';
require_once __DIR__ . '/../classes/AiChatbotService.php';
require_once __DIR__ . '/../classes/SystemInformationService.php';
require_once __DIR__ . '/../setting/constants/prestasi_constants.php';
require_once __DIR__ . '/../setting/helper/config_helper.php';
require_once __DIR__ . '/../includes/functions-db.php';
require_once __DIR__ . '/../includes/sso-config.php';
require_once __DIR__ . '/../ajax/_helpers.php';

class TetapanSistemController {
  public string $lang;
  public array $profile;
  public array $db_configs;
  public array $active_db_flags;

  /** @var ?string legacy compatibility base key, cth: 'sybase_ehrmdb' */
  public ?string $active_sybase_name = null;

  private PDO $pdo;
  private Config $configModel;
  private DatabaseConnectionRepository $additionalConnectionRepository;
  private DatabaseConnectionValidator $additionalConnectionValidator;
  private DatabaseConnectionFactory $additionalConnectionFactory;
  private DatabaseConnectionDiagnostics $additionalConnectionDiagnostics;
  private DatabasePreviewSanitizer $additionalPreviewSanitizer;

  public function __construct() {
    $this->lang = $_SESSION['lang'] ?? SystemConfigConstants::DEFAULT_LANGUAGE;

    // ✅ MySQL untuk user/profile & config
    $pdo_mysql         = Database::getInstance('mysql')->getConnection();
    $this->pdo         = $pdo_mysql;
    $this->configModel = new Config($this->pdo);
    $this->additionalConnectionRepository = new DatabaseConnectionRepository($this->pdo);
    $this->additionalConnectionValidator = new DatabaseConnectionValidator();
    $this->additionalConnectionFactory = new DatabaseConnectionFactory();
    $this->additionalConnectionDiagnostics = new DatabaseConnectionDiagnostics();
    $this->additionalPreviewSanitizer = new DatabasePreviewSanitizer();

    // ✅ Profil user
    $userModel  = new User($pdo_mysql);
    $f_stafID   = $_SESSION['f_stafID'] ?? null;
    $this->profile = $f_stafID ? $userModel->getProfile($f_stafID) : [];

    // ✅ Config DB (senarai sambungan tersedia) + flag aktif (ehrmdb/ehrmdb_dev)
    $this->db_configs         = require __DIR__ . '/../configuration/db_config.php';
    $this->active_db_flags    = $this->getActiveDBConfig();
    $this->active_sybase_name = $this->findActiveSybaseName(); // compatibility base key

    // Selaraskan flags dengan sumber SSoT (session/constant/DB)
    if ($this->active_sybase_name) {
      $logical = $this->baseToLogical($this->active_sybase_name);
      if ($logical) {
        $this->active_db_flags = ['ehrmdb'=>false,'ehrmdb_dev'=>false];
        $this->active_db_flags[$logical] = true;
      }
    }
  }

  /**
   * Handle POST requests - dipanggil dari page
   */
  public function handleRequest(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      return; // Hanya proses POST
    }

    $this->enforceRequestRateLimit();

    if ($this->isAjaxRequest()) {
      $this->handleAjaxRequest();
      return;
    }

    $formType = $_POST['form_type'] ?? '';
    
    // Proses berdasarkan form type
    if (isset($_POST['submit_db'])) {
      $this->handleDatabaseUpdate();
    } elseif ($formType === 'general_settings') {
      $this->handleGeneralSettingsUpdate();
    } elseif ($formType === 'auth_settings') {
      $this->handleAuthSettingsUpdate();
    } elseif ($formType === 'email_settings') {
      $this->handleEmailUpdate();
    } elseif ($formType === 'update_languages') {
      $this->handleLanguageUpdate();
    } elseif ($formType === 'theme_settings') {
      $this->handleThemeUpdate();
    } elseif ($formType === 'ai_chatbot_settings') {
      $this->handleAiChatbotUpdate();
    }
  }

  /** Enforce halaman konfigurasi sebagai Super Admin untuk GET dan POST. */
  public function authorizePageAccess(): void {
    $this->checkAuthorization();
  }

  private function enforceRequestRateLimit(): void {
    $formType = trim((string)($_POST['form_type'] ?? 'unknown'));
    $limits = [
      'db_additional_test' => [8, 60],
      'db_additional_inspect' => [12, 60],
      'db_additional_schema_preview' => [10, 60],
      'db_additional_object_preview' => [20, 60],
      'db_additional_list' => [40, 60],
    ];
    [$maxAttempts, $windowSeconds] = $limits[$formType] ?? [20, 60];
    if (checkRateLimit('system_settings_' . preg_replace('/[^a-z0-9_]+/i', '_', $formType), $maxAttempts, $windowSeconds)) {
      return;
    }

    $title = $this->tr('config_rate_limit_title', 'Terlalu Banyak Permintaan');
    $message = $this->tr('config_rate_limit_text', 'Terlalu banyak permintaan. Sila cuba lagi sebentar.');
    if ($this->isAjaxRequest()) {
      $this->sendJsonResponse([
        'success' => false,
        'title' => $title,
        'message' => $message,
        'errors' => [$message],
      ], 429);
    }
    set_alert(['title' => $title, 'text' => $message, 'icon' => 'warning']);
    header('Location: tetapan-sistem.php');
    exit;
  }

  private function isAjaxRequest(): bool {
    $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower(trim((string)($_SERVER['HTTP_ACCEPT'] ?? '')));
    $ajaxFlag = trim((string)($_POST['ajax'] ?? ''));

    return $ajaxFlag === '1'
      || $requestedWith === 'xmlhttprequest'
      || str_contains($accept, 'application/json');
  }

  private function normalizeAuthSsoSiteId($value): string {
    return function_exists('sso_config_normalize_site_id')
      ? sso_config_normalize_site_id((string)$value)
      : trim((string)$value);
  }

  private function normalizeAuthSsoIdpDomain($value): string {
    return function_exists('sso_config_normalize_idp_domain')
      ? sso_config_normalize_idp_domain((string)$value)
      : rtrim(trim((string)$value), '/') . '/';
  }

  private function handleAjaxRequest(): void {
    $formType = $_POST['form_type'] ?? '';

    try {
      $response = null;

      if (isset($_POST['submit_db'])) {
        $response = $this->processDatabaseUpdate();
      } elseif ($formType === 'general_settings') {
        $response = $this->processGeneralSettingsUpdate();
      } elseif ($formType === 'auth_settings') {
        $response = $this->processAuthSettingsUpdate();
      } elseif ($formType === 'email_settings') {
        $response = $this->processEmailUpdate();
      } elseif ($formType === 'update_languages') {
        $response = $this->processLanguageUpdate();
      } elseif ($formType === 'theme_settings') {
        $response = $this->processThemeUpdate();
      } elseif ($formType === 'ai_chatbot_settings') {
        $response = $this->processAiChatbotUpdate();
      } elseif ($formType === 'db_additional_list') {
        $response = $this->processAdditionalConnectionList();
      } elseif ($formType === 'db_additional_create') {
        $response = $this->processAdditionalConnectionCreate();
      } elseif ($formType === 'db_additional_update') {
        $response = $this->processAdditionalConnectionUpdate();
      } elseif ($formType === 'db_additional_toggle') {
        $response = $this->processAdditionalConnectionToggle();
      } elseif ($formType === 'db_additional_test') {
        $response = $this->processAdditionalConnectionTest();
      } elseif ($formType === 'db_additional_inspect') {
        $response = $this->processAdditionalConnectionInspect();
      } elseif ($formType === 'db_additional_schema_preview') {
        $response = $this->processAdditionalConnectionSchemaPreview();
      } elseif ($formType === 'db_additional_object_preview') {
        $response = $this->processAdditionalConnectionObjectPreview();
      }

      if (!$response) {
        $this->sendJsonResponse([
          'success' => false,
          'title' => $this->tr('config_invalid_request_title', 'Ralat Permintaan'),
          'message' => $this->tr('config_invalid_request_text', 'Permintaan tetapan sistem tidak sah.'),
          'errors' => [$this->tr('config_invalid_request_text', 'Permintaan tetapan sistem tidak sah.')],
        ], 400);
      }

      $statusCode = !empty($response['success']) ? 200 : (($response['status'] ?? 422));
      unset($response['status']);
      $this->sendJsonResponse($response, $statusCode);
    } catch (\Throwable $e) {
      error_log('[TetapanSistem] AJAX request failed: ' . $e->getMessage());
      $this->sendJsonResponse([
        'success' => false,
        'title' => $this->tr('config_general_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_general_system_error_text', 'Ralat berlaku semasa memproses tetapan sistem.'),
        'errors' => [$this->tr('config_general_system_error_text', 'Ralat berlaku semasa memproses tetapan sistem.')],
      ], 500);
    }
  }

  private function sendJsonResponse(array $payload, int $statusCode = 200): void {
    if (!headers_sent()) {
      http_response_code($statusCode);
      header('Content-Type: application/json; charset=UTF-8');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
      http_response_code(500);
      $json = json_encode([
        'success' => false,
        'title' => $this->tr('config_general_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_json_response_error', 'Respons sistem tidak dapat dijana.'),
        'errors' => [$this->tr('config_json_response_error', 'Respons sistem tidak dapat dijana.')],
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    echo $json;
    exit;
  }

  /**
   * Handle general settings update
   */
  private function handleGeneralSettingsUpdate(): void {
    $response = $this->processGeneralSettingsUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=general');
    exit;
  }

  private function handleAuthSettingsUpdate(): void {
    $response = $this->processAuthSettingsUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=auth');
    exit;
  }

  private function processGeneralSettingsUpdate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $generalData = [];
    foreach ($this->getGeneralSettingsWhitelist() as $key => $meta) {
      $generalData[$key] = trim((string)($_POST[$meta['field']] ?? ''));
    }

    $validationErrors = $this->validateGeneralSettings($generalData);
    if (!empty($validationErrors)) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'general',
        'title' => $this->tr('config_general_validation_title', 'Ralat Validasi'),
        'message' => implode("\n", $validationErrors),
        'errors' => $validationErrors,
      ];
    }

    try {
      $oldSettings = $this->getGeneralSettings();
      $result = $this->saveGeneralSettings($generalData);
      if ($result) {
        $this->invalidateTsCache('general');
        $this->auditGeneralSettingsUpdate($oldSettings, $generalData);
        $summaryLabels = $this->getGeneralSettingsChangeSummary($oldSettings, $generalData);
        $summaryText = !empty($summaryLabels)
          ? sprintf(
              $this->tr('config_general_success_text_summary', 'Tetapan umum berjaya disimpan. Perubahan: %s.'),
              implode(', ', $summaryLabels)
            )
          : $this->tr('config_general_success_text', 'Tetapan umum berjaya disimpan.');
        return [
          'success' => true,
          'tab' => 'general',
          'title' => $this->tr('config_general_success_title', 'Berjaya'),
          'message' => $summaryText,
          'data' => [
            'generalSettings' => $this->getGeneralSettings(),
          ],
        ];
      } else {
        return [
          'success' => false,
          'status' => 500,
          'tab' => 'general',
          'title' => $this->tr('config_general_save_error_title', 'Ralat Menyimpan'),
          'message' => $this->tr('config_general_save_error_text', 'Gagal menyimpan tetapan umum. Sila cuba lagi atau hubungi pentadbir sistem.'),
          'errors' => [$this->tr('config_general_save_error_text', 'Gagal menyimpan tetapan umum. Sila cuba lagi atau hubungi pentadbir sistem.')],
        ];
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Save general settings failed: " . $e->getMessage());
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'general',
        'title' => $this->tr('config_general_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_general_system_error_text', 'Ralat berlaku semasa menyimpan tetapan umum. Sila semak log sistem untuk maklumat lanjut.'),
        'errors' => [$this->tr('config_general_system_error_text', 'Ralat berlaku semasa menyimpan tetapan umum. Sila semak log sistem untuk maklumat lanjut.')],
      ];
    }
  }

  private function processAuthSettingsUpdate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $authData = [];
    foreach ($this->getAuthSettingsWhitelist() as $key => $meta) {
      $field = (string)($meta['field'] ?? '');
      if (($meta['type'] ?? 'string') === 'bool') {
        $authData[$key] = $_POST[$field] ?? '0';
        continue;
      }
      $authData[$key] = trim((string)($_POST[$field] ?? ''));
    }

    $validation = $this->validateAuthSettings($authData);
    if (!empty($validation['errors'])) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'auth',
        'title' => $this->tr('config_auth_validation_title', 'Ralat Validasi'),
        'message' => implode("\n", $validation['errors']),
        'errors' => $validation['errors'],
        'warnings' => $validation['warnings'] ?? [],
      ];
    }

    $normalizedData = $this->normalizeAuthSettingsForStorage($authData);

    try {
      $oldSettings = $this->getAuthSettings();
      $result = $this->saveAuthSettings($normalizedData);
      if ($result) {
        $this->invalidateTsCache('auth');
        $newSettings = $this->getAuthSettings();
        $this->auditAuthSettingsUpdate($oldSettings, $newSettings);
        $summaryLabels = $this->getAuthSettingsChangeSummary($oldSettings, $newSettings);
        $summaryText = !empty($summaryLabels)
          ? sprintf(
              $this->tr('config_auth_success_text_summary', 'Tetapan polisi login berjaya disimpan. Perubahan: %s.'),
              implode(', ', $summaryLabels)
            )
          : $this->tr('config_auth_success_text', 'Tetapan polisi login berjaya disimpan.');

        return [
          'success' => true,
          'tab' => 'auth',
          'title' => $this->tr('config_auth_success_title', 'Berjaya'),
          'message' => $summaryText,
          'warnings' => $newSettings['warnings'] ?? [],
          'data' => [
            'authSettings' => $newSettings,
          ],
        ];
      }

      return [
        'success' => false,
        'status' => 500,
        'tab' => 'auth',
        'title' => $this->tr('config_auth_save_error_title', 'Ralat Menyimpan'),
        'message' => $this->tr('config_auth_save_error_text', 'Gagal menyimpan tetapan polisi login. Sila cuba lagi atau hubungi pentadbir sistem.'),
        'errors' => [$this->tr('config_auth_save_error_text', 'Gagal menyimpan tetapan polisi login. Sila cuba lagi atau hubungi pentadbir sistem.')],
      ];
    } catch (\Throwable $e) {
      error_log('[TetapanSistem] Save auth settings failed: ' . $e->getMessage());
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'auth',
        'title' => $this->tr('config_auth_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_auth_system_error_text', 'Ralat berlaku semasa menyimpan tetapan polisi login. Sila semak log sistem untuk maklumat lanjut.'),
        'errors' => [$this->tr('config_auth_system_error_text', 'Ralat berlaku semasa menyimpan tetapan polisi login. Sila semak log sistem untuk maklumat lanjut.')],
      ];
    }
  }

  /**
   * Handle database update
   */
  private function handleDatabaseUpdate(): void {
    $this->checkAuthorization();
    $this->validateCSRF();
    $response = $this->processDatabaseUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=db');
    exit;
  }

  /**
   * Handle email settings update
   */
  private function handleEmailUpdate(): void {
    $response = $this->processEmailUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=email');
    exit;
  }

  private function processEmailUpdate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();
    
    // Ambil password lama jika password baru kosong
    $existingSettings = $this->getEmailSettings();
    $newPassword = trim($_POST['mail_password'] ?? '');
    
    $emailData = [
      'mail_driver'       => trim($_POST['mail_driver'] ?? ''),
      'mail_host'         => trim($_POST['mail_host'] ?? ''),
      'mail_port'         => trim($_POST['mail_port'] ?? ''),
      'mail_username'     => trim($_POST['mail_username'] ?? ''),
      'mail_password'     => $newPassword !== '' ? $newPassword : ($existingSettings['mail_password'] ?? ''),
      'mail_encryption'   => trim($_POST['mail_encryption'] ?? ''),
      'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
      'mail_from_name'    => trim($_POST['mail_from_name'] ?? ''),
    ];
    
    // Validate input
    $validationErrors = $this->validateEmailSettings($emailData);
    if (!empty($validationErrors)) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'email',
        'title' => $this->tr('config_email_validation_title', 'Ralat Validasi'),
        'message' => implode("\n", $validationErrors),
        'errors' => $validationErrors,
      ];
    }
    
    // Save settings with audit logging
    try {
      $oldSettings = $this->getEmailSettings();
      $result = $this->saveEmailSettings($emailData);
      if ($result) {
        $this->invalidateTsCache('email');
        $this->auditEmailUpdate($oldSettings, $emailData);
        $summaryLabels = $this->getEmailChangeSummary($oldSettings, $emailData);
        $summaryText = !empty($summaryLabels)
          ? sprintf(
              $this->tr('config_email_success_text_summary', 'Tetapan emel berjaya disimpan. Perubahan: %s.'),
              implode(', ', $summaryLabels)
            )
          : $this->tr('emel_title_save', 'Tetapan emel berjaya disimpan');
        return [
          'success' => true,
          'tab' => 'email',
          'title' => $this->tr('emel_title', 'Tetapan Emel'),
          'message' => $summaryText,
          'data' => [
            'emailSettings' => $this->sanitizeEmailSettingsForClient($this->getEmailSettings()),
          ],
        ];
      } else {
        return [
          'success' => false,
          'status' => 500,
          'tab' => 'email',
          'title' => $this->tr('config_email_save_error_title', 'Ralat Menyimpan'),
          'message' => $this->tr('config_email_save_error_text', 'Gagal menyimpan tetapan emel. Sila cuba lagi atau hubungi pentadbir sistem.'),
          'errors' => [$this->tr('config_email_save_error_text', 'Gagal menyimpan tetapan emel. Sila cuba lagi atau hubungi pentadbir sistem.')],
        ];
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Save email settings failed: " . $e->getMessage());
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'email',
        'title' => $this->tr('config_email_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_email_system_error_text', 'Ralat berlaku semasa menyimpan tetapan emel. Sila semak log sistem untuk maklumat lanjut.'),
        'errors' => [$this->tr('config_email_system_error_text', 'Ralat berlaku semasa menyimpan tetapan emel. Sila semak log sistem untuk maklumat lanjut.')],
      ];
    }
  }

  /**
   * Handle language update
   */
  private function handleLanguageUpdate(): void {
    $response = $this->processLanguageUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=lang');
    exit;
  }

  private function processLanguageUpdate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();
    $languages = array_values(array_unique(array_filter(
      (array)($_POST['languages'] ?? []),
      static fn($lang) => is_string($lang) && trim($lang) !== ''
    )));
    $defaultLanguage = trim((string)($_POST['default_language'] ?? ''));
    
    // Validate input
    $validationErrors = $this->validateLanguageSettings($languages, $defaultLanguage);
    if (!empty($validationErrors)) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'lang',
        'title' => $this->tr('config_language_validation_title', 'Ralat Validasi'),
        'message' => implode("\n", $validationErrors),
        'errors' => $validationErrors,
      ];
    }
    
    // Save languages with audit logging
    try {
      $oldLanguages = $this->configModel->getBahasaAktif();
      $oldDefaultLanguage = $this->configModel->getDefaultBahasa($oldLanguages[0] ?? SystemConfigConstants::DEFAULT_LANGUAGE);
      $result = $this->configModel->saveLanguageSettings($languages, $defaultLanguage);
      if ($result) {
        $this->invalidateTsCache('lang');
        $this->auditLanguageUpdate($oldLanguages, $languages, $oldDefaultLanguage, $defaultLanguage);
        $_SESSION['lang'] = $defaultLanguage;
        $summaryText = sprintf(
          $this->tr('config_language_success_text_summary', 'Tetapan bahasa berjaya disimpan. Aktif: %s. Lalai: %s.'),
          implode(', ', $languages),
          $defaultLanguage
        );
        return [
          'success' => true,
          'tab' => 'lang',
          'title' => $this->tr('bahasa_title', 'Tetapan Bahasa'),
          'message' => $summaryText,
          'data' => [
            'languageData' => $this->getLanguageList(),
          ],
        ];
      } else {
        return [
          'success' => false,
          'status' => 500,
          'tab' => 'lang',
          'title' => $this->tr('config_language_save_error_title', 'Ralat Menyimpan'),
          'message' => $this->tr('config_language_save_error_text', 'Gagal menyimpan tetapan bahasa. Sila cuba lagi atau hubungi pentadbir sistem.'),
          'errors' => [$this->tr('config_language_save_error_text', 'Gagal menyimpan tetapan bahasa. Sila cuba lagi atau hubungi pentadbir sistem.')],
        ];
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Save languages failed: " . $e->getMessage());
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'lang',
        'title' => $this->tr('config_language_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_language_system_error_text', 'Ralat berlaku semasa menyimpan tetapan bahasa. Sila semak log sistem untuk maklumat lanjut.'),
        'errors' => [$this->tr('config_language_system_error_text', 'Ralat berlaku semasa menyimpan tetapan bahasa. Sila semak log sistem untuk maklumat lanjut.')],
      ];
    }
  }

  private function sanitizeEmailSettingsForClient(array $settings): array {
    unset($settings['mail_password']);
    return $settings;
  }

  /**
   * Handle theme update
   */
  private function handleThemeUpdate(): void {
    $response = $this->processThemeUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=theme');
    exit;
  }

  private function processThemeUpdate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();
    $topbar  = trim($_POST['topbar_color'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR);
    $sidebar = trim($_POST['sidebar_color'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR);
    $layout  = trim($_POST['layout_mode']   ?? SystemConfigConstants::DEFAULT_THEME_LAYOUT);
    $topbarCustomSeed = strtoupper(trim((string)($_POST['topbar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED)));
    $sidebarCustomSeed = strtoupper(trim((string)($_POST['sidebar_custom_seed'] ?? SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED)));
    $themeSetting = [
      'topbarColor' => $topbar,
      'sidebarColor' => $sidebar,
      'layoutMode' => $layout,
      'topbarCustomSeed' => $topbarCustomSeed,
      'sidebarCustomSeed' => $sidebarCustomSeed,
    ];

    // Validate input
    $validationErrors = $this->validateThemeSettings($themeSetting);
    if (!empty($validationErrors)) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'theme',
        'title' => $this->tr('config_theme_validation_title', 'Ralat Validasi'),
        'message' => implode("\n", $validationErrors),
        'errors' => $validationErrors,
      ];
    }

    // Save theme with audit logging
    try {
      $oldTheme = $this->configModel->getTema();
      $result = $this->configModel->saveTema($themeSetting);
      if ($result) {
        $this->invalidateTsCache('theme');
        $this->auditThemeUpdate($oldTheme, $themeSetting);
        $_SESSION['theme.topbar'] = $topbar;
        $_SESSION['theme.menu']   = $sidebar;
        $_SESSION['theme.layout'] = $layout;
        $_SESSION['theme.sidebar'] = $sidebar;
        $_SESSION['theme.topbar_custom_seed'] = $topbarCustomSeed;
        $_SESSION['theme.sidebar_custom_seed'] = $sidebarCustomSeed;
        $summaryLabels = $this->getThemeChangeSummary($oldTheme, $themeSetting);
        $summaryText = !empty($summaryLabels)
          ? sprintf(
              $this->tr('config_theme_success_text_summary', 'Tetapan tema berjaya dikemas kini. Perubahan: %s.'),
              implode(', ', $summaryLabels)
            )
          : $this->tr('tema_title_save', 'Tetapan tema berjaya dikemaskini');
        return [
          'success' => true,
          'tab' => 'theme',
          'title' => $this->tr('tema_title', 'Tetapan Tema'),
          'message' => $summaryText,
          'data' => [
            'themeSettings' => $this->getThemeSettings(),
          ],
        ];
      } else {
        return [
          'success' => false,
          'status' => 500,
          'tab' => 'theme',
          'title' => $this->tr('config_theme_save_error_title', 'Ralat Menyimpan'),
          'message' => $this->tr('config_theme_save_error_text', 'Gagal menyimpan tetapan tema. Sila cuba lagi atau hubungi pentadbir sistem.'),
          'errors' => [$this->tr('config_theme_save_error_text', 'Gagal menyimpan tetapan tema. Sila cuba lagi atau hubungi pentadbir sistem.')],
        ];
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Save theme failed: " . $e->getMessage());
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'theme',
        'title' => $this->tr('config_theme_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_theme_system_error_text', 'Ralat berlaku semasa menyimpan tetapan tema. Sila semak log sistem untuk maklumat lanjut.'),
        'errors' => [$this->tr('config_theme_system_error_text', 'Ralat berlaku semasa menyimpan tetapan tema. Sila semak log sistem untuk maklumat lanjut.')],
      ];
    }
  }

  private function handleAiChatbotUpdate(): void {
    $response = $this->processAiChatbotUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=ai-chatbot');
    exit;
  }

  private function processAiChatbotUpdate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $data = [];
    $existingSettings = $this->configModel->getGroup(SystemConfigConstants::CONFIG_GROUP_AI_CHATBOT);
    foreach ($this->getAiChatbotSettingsWhitelist() as $key => $meta) {
      $field = (string)($meta['field'] ?? '');
      if (($meta['type'] ?? 'string') === 'bool') {
        $data[$key] = !empty($_POST[$field]) ? '1' : '0';
        continue;
      }
      $data[$key] = trim((string)($_POST[$field] ?? ''));
    }
    if ($data['api_key'] === '') {
      $data['api_key'] = (string)($existingSettings['api_key'] ?? '');
    }

    $validationErrors = $this->validateAiChatbotSettings($data);
    if (!empty($validationErrors)) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'ai-chatbot',
        'title' => $this->tr('config_ai_chatbot_validation_title', 'Ralat Validasi'),
        'message' => implode("\n", $validationErrors),
        'errors' => $validationErrors,
      ];
    }

    try {
      $oldSettings = $this->getAiChatbotSettings();
      $result = $this->saveAiChatbotSettings($data);
      if ($result) {
        $this->invalidateTsCache('ai-chatbot');
        $newSettings = $this->getAiChatbotSettings();
        $this->auditAiChatbotUpdate($oldSettings, $newSettings);
        $summaryLabels = $this->getAiChatbotChangeSummary($oldSettings, $newSettings);
        $summaryText = !empty($summaryLabels)
          ? sprintf(
              $this->tr('config_ai_chatbot_success_text_summary', 'Tetapan AI Chatbot berjaya disimpan. Perubahan: %s.'),
              implode(', ', $summaryLabels)
            )
          : $this->tr('config_ai_chatbot_success_text', 'Tetapan AI Chatbot berjaya disimpan.');

        return [
          'success' => true,
          'tab' => 'ai-chatbot',
          'title' => $this->tr('config_ai_chatbot_success_title', 'Berjaya'),
          'message' => $summaryText,
          'data' => [
            'aiChatbotSettings' => $this->sanitizeAiChatbotSettingsForClient($newSettings),
          ],
        ];
      }

      return [
        'success' => false,
        'status' => 500,
        'tab' => 'ai-chatbot',
        'title' => $this->tr('config_ai_chatbot_save_error_title', 'Ralat Menyimpan'),
        'message' => $this->tr('config_ai_chatbot_save_error_text', 'Gagal menyimpan tetapan AI Chatbot. Sila cuba lagi atau hubungi pentadbir sistem.'),
        'errors' => [$this->tr('config_ai_chatbot_save_error_text', 'Gagal menyimpan tetapan AI Chatbot. Sila cuba lagi atau hubungi pentadbir sistem.')],
      ];
    } catch (\Throwable $e) {
      error_log('[TetapanSistem] Save AI chatbot settings failed: ' . $e->getMessage());
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'ai-chatbot',
        'title' => $this->tr('config_ai_chatbot_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_ai_chatbot_system_error_text', 'Ralat berlaku semasa menyimpan tetapan AI Chatbot. Sila semak log sistem untuk maklumat lanjut.'),
        'errors' => [$this->tr('config_ai_chatbot_system_error_text', 'Ralat berlaku semasa menyimpan tetapan AI Chatbot. Sila semak log sistem untuk maklumat lanjut.')],
      ];
    }
  }

  /** Pastikan sesi terbuka sebelum tulis $_SESSION / set_alert */
  private function ensureSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      try {
        session_start();
      } catch (\Throwable $e) {
        error_log("[TetapanSistem] Session start failed: " . $e->getMessage());
        // Continue - session might already be started
      }
    }
  }

  /** Validate CSRF token */
  private function validateCSRF(): void {
    $this->ensureSession();
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
      if ($this->isAjaxRequest()) {
        $this->sendJsonResponse([
          'success' => false,
          'title' => $this->tr('config_security_error_title', 'Ralat Keselamatan'),
          'message' => $this->tr('config_csrf_invalid_text', 'CSRF token tidak sah. Sila muat semula halaman dan cuba lagi.'),
          'errors' => [$this->tr('config_csrf_invalid_text', 'CSRF token tidak sah. Sila muat semula halaman dan cuba lagi.')],
        ], 419);
      }
      set_alert([
        'title' => $this->tr('config_security_error_title', 'Ralat Keselamatan'),
        'text' => $this->tr('config_csrf_invalid_text', 'CSRF token tidak sah. Sila muat semula halaman dan cuba lagi.'),
        'icon' => 'error'
      ]);
      header('Location: tetapan-sistem.php');
      exit;
    }
  }

  /** Check authorization - hanya Super Admin */
  private function checkAuthorization(): void {
    $f_stafID = $_SESSION['f_stafID'] ?? null;
    if (!$f_stafID) {
      if ($this->isAjaxRequest()) {
        $this->sendJsonResponse([
          'success' => false,
          'title' => $this->tr('config_access_denied_title', 'Akses Ditolak'),
          'message' => $this->tr('config_login_required_text', 'Sila log masuk terlebih dahulu.'),
          'errors' => [$this->tr('config_login_required_text', 'Sila log masuk terlebih dahulu.')],
        ], 401);
      }
      set_alert(['title'=>$this->tr('config_access_denied_title', 'Akses Ditolak'),'text'=>$this->tr('config_login_required_text', 'Sila log masuk terlebih dahulu.'),'icon'=>'error']);
      header('Location: ../index.php');
      exit;
    }
    
    $userGroupId = (int)($this->profile['f_groupID'] ?? 0);
    if ($userGroupId !== PRESTASI_ROLE_ID_ADM_SA) {
      if ($this->isAjaxRequest()) {
        $this->sendJsonResponse([
          'success' => false,
          'title' => $this->tr('config_access_denied_title', 'Akses Ditolak'),
          'message' => $this->tr('config_super_admin_only_text', 'Hanya Super Admin dibenarkan mengakses halaman Konfigurasi Sistem.'),
          'errors' => [$this->tr('config_super_admin_only_text', 'Hanya Super Admin dibenarkan mengakses halaman Konfigurasi Sistem.')],
        ], 403);
      }
      set_alert([
        'title' => $this->tr('config_access_denied_title', 'Akses Ditolak'),
        'text' => $this->tr('config_super_admin_only_text', 'Hanya Super Admin dibenarkan mengakses halaman Konfigurasi Sistem.'),
        'icon' => 'error'
      ]);
      header('Location: dashboard.php');
      exit;
    }
  }

  // ---------------------------
  // 🔧 Helpers & DB settings
  // ---------------------------

  /** logical -> base (tanpa _dsn/_dblib) */
  private function logicalToBase(string $logical): ?string {
    return match (strtolower($logical)) {
      'ehrmdb'     => 'sybase_ehrmdb',
      'ehrmdb_dev' => 'sybase_ehrmdb_dev',
      default      => null,
    };
  }

  /** Baca flags untuk paparan berdasarkan environment semasa. */
  public function getActiveDBConfig(): array {
    $environment = $this->getSelectedEnvironment();
    return [
      'ehrmdb' => $environment === 'production',
      'ehrmdb_dev' => $environment === 'development',
    ];
  }

  /** Normalise pilihan POST */
  private function normalizeSelected(string $selected): string {
    return match (strtolower(trim($selected))) {
      'ehrmdb','ehrmdb_dev' => strtolower(trim($selected)),
      default => '',
    };
  }

  private function normalizeEnvironment(string $environment): string {
    $environment = strtolower(trim($environment));
    return in_array($environment, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)
      ? $environment
      : '';
  }

  private function normalizeMainDbEnvironment(string $environment): string {
    $environment = strtolower(trim($environment));
    return in_array($environment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)
      ? $environment
      : '';
  }

  private function normalizeOperationalMode(string $mode): string {
    $mode = strtolower(trim($mode));
    return in_array($mode, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)
      ? $mode
      : '';
  }

  /** Runtime environment semasa, utamakan session kemudian config. */
  private function getSelectedEnvironment(): string {
    $sessionEnvironment = strtolower(trim((string)($_SESSION['SYBASE_ENVIRONMENT'] ?? '')));
    if (in_array($sessionEnvironment, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)) {
      return $sessionEnvironment;
    }

    $configEnvironment = (string)$this->configModel->getSybaseEnvironment(SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT);
    return in_array($configEnvironment, SystemConfigConstants::ALLOWED_SYBASE_ENVIRONMENTS, true)
      ? $configEnvironment
      : SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT;
  }

  /** Runtime operational mode semasa, utamakan session kemudian config. */
  private function getSelectedOperationalMode(): string {
    $sessionMode = strtolower(trim((string)($_SESSION['SYBASE_OPERATIONAL_MODE'] ?? '')));
    if (in_array($sessionMode, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)) {
      return $sessionMode;
    }

    $configMode = (string)$this->configModel->getSybaseOperationalMode(SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE);
    return in_array($configMode, SystemConfigConstants::ALLOWED_SYBASE_OPERATIONAL_MODES, true)
      ? $configMode
      : SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE;
  }

  /** Compatibility shim: flags kini derive dari environment, tidak lagi disimpan ke JSON. */
  public function saveActiveDBConfig(array $config): bool {
    $this->active_db_flags = [
      'ehrmdb'     => (bool)($config['ehrmdb'] ?? false),
      'ehrmdb_dev' => (bool)($config['ehrmdb_dev'] ?? false),
    ];
    return true;
  }

  /**
   * Uji sambungan Sybase berdasarkan **base key** (suffix auto oleh Database::getInstance)
   */
  public function testSybaseConnection(string $logicalName): bool {
    $logicalName = $this->normalizeSelected($logicalName);
    if ($logicalName === '') return false;
    $base = $this->logicalToBase($logicalName);
    if (!$base) return false;
    try {
      $pdo  = Database::getInstance($base)->getConnection(); // auto _dsn/_dblib
      $stmt = $pdo->query('SELECT getdate()');
      return $stmt !== false;
    } catch (Throwable $e) {
      return false;
    }
  }

  public function getMysqlInfo(): array {
    $environment = $this->getSelectedMainDbEnvironment();
    $key = $environment === 'development' ? 'mysql_dev' : 'mysql_prod';
    return $this->db_configs[$key] ?? ($this->db_configs['mysql'] ?? []);
  }

  private function parseMysqlConfigSummary(array $config): array {
    $dsn = trim((string)($config['dsn'] ?? ''));
    $host = '-';
    $database = '-';

    if ($dsn !== '') {
      if (preg_match('/host=([^;]+)/i', $dsn, $matches)) {
        $host = trim((string)$matches[1]);
      }
      if (preg_match('/dbname=([^;]+)/i', $dsn, $matches)) {
        $database = trim((string)$matches[1]);
      }
    }

    return [
      'driver' => trim((string)($config['driver'] ?? 'mysql')),
      'dsn' => $dsn,
      'host' => $host,
      'database' => $database,
      'user' => trim((string)($config['user'] ?? '-')),
    ];
  }

  private function mysqlEnvGroupConfigured(string $prefix): bool {
    $required = [
      $prefix . '_HOST',
      $prefix . '_NAME',
      $prefix . '_USER',
      $prefix . '_PASS',
    ];

    foreach ($required as $key) {
      $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
      if ($value === false || $value === null || trim((string)$value) === '') {
        return false;
      }
    }

    return true;
  }

  private function getMysqlRuntimeDiagnostics(string $mainMysqlEnvironment): array {
    $prodConfig = $this->db_configs['mysql_prod'] ?? ($this->db_configs['mysql'] ?? []);
    $devConfig = $this->db_configs['mysql_dev'] ?? ($this->db_configs['mysql_prod'] ?? ($this->db_configs['mysql'] ?? []));
    $prod = $this->parseMysqlConfigSummary(is_array($prodConfig) ? $prodConfig : []);
    $dev = $this->parseMysqlConfigSummary(is_array($devConfig) ? $devConfig : []);
    $sameTarget = $prod['dsn'] !== ''
      && $prod['dsn'] === $dev['dsn']
      && $prod['user'] === $dev['user'];

    return [
      'activeResolvedKey' => $mainMysqlEnvironment === 'development' ? 'mysql_dev' : 'mysql_prod',
      'prod' => $prod,
      'dev' => $dev,
      'prodDedicated' => $this->mysqlEnvGroupConfigured('DB_MYSQL_MAIN_PROD'),
      'devDedicated' => $this->mysqlEnvGroupConfigured('DB_MYSQL_MAIN_DEV'),
      'sameTarget' => $sameTarget,
    ];
  }

  private function getSelectedMainDbEnvironment(): string {
    $sessionEnvironment = strtolower(trim((string)($_SESSION['MAIN_DB_ENVIRONMENT'] ?? '')));
    if (in_array($sessionEnvironment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)) {
      return $sessionEnvironment;
    }

    $configEnvironment = (string)$this->configModel->getMainDbEnvironment(SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT);
    return in_array($configEnvironment, SystemConfigConstants::ALLOWED_MAIN_DB_ENVIRONMENTS, true)
      ? $configEnvironment
      : SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT;
  }

  public function getThemeSettings(): array {
    $theme = $this->configModel->getTema();

    $layout = strtolower(trim((string)($theme['layoutMode'] ?? '')));
    if (!in_array($layout, SystemConfigConstants::ALLOWED_THEME_MODES, true)) {
      $layout = SystemConfigConstants::DEFAULT_THEME_LAYOUT;
    }

    $topbar = strtolower(trim((string)($theme['topbarColor'] ?? '')));
    if (!in_array($topbar, SystemConfigConstants::ALLOWED_THEME_COLORS, true)) {
      $topbar = SystemConfigConstants::DEFAULT_THEME_TOPBAR;
    }

    $sidebar = strtolower(trim((string)($theme['sidebarColor'] ?? '')));
    if (!in_array($sidebar, SystemConfigConstants::ALLOWED_THEME_COLORS, true)) {
      $sidebar = SystemConfigConstants::DEFAULT_THEME_SIDEBAR;
    }

    return [
      'layoutMode' => $layout,
      'topbarColor' => $topbar,
      'sidebarColor' => $sidebar,
      'topbarCustomSeed' => $this->normalizeThemeHex($theme['topbarCustomSeed'] ?? '', SystemConfigConstants::DEFAULT_THEME_TOPBAR_CUSTOM_SEED),
      'sidebarCustomSeed' => $this->normalizeThemeHex($theme['sidebarCustomSeed'] ?? '', SystemConfigConstants::DEFAULT_THEME_SIDEBAR_CUSTOM_SEED),
    ];
  }

  public function getAiChatbotSettings(): array {
    $settings = AiChatbotService::loadConfig();
    $dbSettings = $this->configModel->getGroup(SystemConfigConstants::CONFIG_GROUP_AI_CHATBOT);

    $settings['api_key_configured'] = trim((string)($settings['api_key'] ?? '')) !== '';
    $settings['config_source'] = $dbSettings !== []
      ? 'database'
      : 'defaults';

    return $settings;
  }

  private function sanitizeAiChatbotSettingsForClient(array $settings): array {
    unset($settings['api_key']);
    return $settings;
  }

  public function saveAiChatbotSettings(array $data): bool {
    return $this->configModel->saveGroup(SystemConfigConstants::CONFIG_GROUP_AI_CHATBOT, $data);
  }

  public function getDatabaseRuntimeViewModel(): array {
    $environment = $this->getSelectedEnvironment();
    $operationalMode = $this->getSelectedOperationalMode();
    $mainMysqlEnvironment = $this->getSelectedMainDbEnvironment();
    $mysqlInfo = $this->getMysqlInfo();
    $mysqlDiagnostics = $this->getMysqlRuntimeDiagnostics($mainMysqlEnvironment);

    $mysqlDriver = trim((string)($mysqlInfo['driver'] ?? 'mysql'));
    $mysqlDsn = trim((string)($mysqlInfo['dsn'] ?? ''));
    $mysqlUser = trim((string)($mysqlInfo['user'] ?? '-'));
    $mysqlHost = '-';
    $mysqlDatabase = '-';

    if ($mysqlDsn !== '') {
      if (preg_match('/host=([^;]+)/i', $mysqlDsn, $matches)) {
        $mysqlHost = trim((string)$matches[1]);
      }
      if (preg_match('/dbname=([^;]+)/i', $mysqlDsn, $matches)) {
        $mysqlDatabase = trim((string)$matches[1]);
      }
    }

    $runtimeStudentBase = ($environment === 'development') ? 'sybase_student_dev' : 'sybase_student_prod';

    return [
      'dbRenderEnvironment' => $environment,
      'dbRenderOperationalMode' => $operationalMode,
      'activeLogical' => ($environment === 'development') ? 'ehrmdb_dev' : 'ehrmdb',
      'activeBase' => ($environment === 'development') ? 'sybase_ehrmdb_dev' : 'sybase_ehrmdb',
      'runtimeStaffBase' => ($environment === 'development') ? 'sybase_staff_dev' : 'sybase_staff_prod',
      'runtimeStudentBase' => $runtimeStudentBase,
      'studentRuntimeLabel' => $operationalMode === 'staff_student'
        ? $runtimeStudentBase
        : $this->tr('config_tab_db_runtime_disabled', 'Disabled'),
      'mainMysqlEnvironment' => $mainMysqlEnvironment,
      'mysqlDriver' => $mysqlDriver,
      'mysqlDsn' => $mysqlDsn,
      'mysqlUser' => $mysqlUser,
      'mysqlHost' => $mysqlHost,
      'mysqlDatabase' => $mysqlDatabase,
      'mysqlActiveResolvedKey' => (string)($mysqlDiagnostics['activeResolvedKey'] ?? '-'),
      'mysqlProdTarget' => $mysqlDiagnostics['prod'] ?? [],
      'mysqlDevTarget' => $mysqlDiagnostics['dev'] ?? [],
      'mysqlProdDedicated' => (bool)($mysqlDiagnostics['prodDedicated'] ?? false),
      'mysqlDevDedicated' => (bool)($mysqlDiagnostics['devDedicated'] ?? false),
      'mysqlSameTarget' => (bool)($mysqlDiagnostics['sameTarget'] ?? false),
    ];
  }

  public function getPageViewData(bool $refreshLanguageData = false, bool $includeSystemInformation = false): array {
    $dbAktif = $this->getCachedValue(
      'dbcfg',
      SystemConfigConstants::CACHE_TTL_DB_CONFIG,
      fn(): array => $this->getActiveDBConfig()
    );

    $mysqlInfo = $this->getCachedValue(
      'mysqlinfo',
      SystemConfigConstants::CACHE_TTL_MYSQL_INFO,
      fn(): array => $this->getMysqlInfo()
    );

    $emailSettings = $this->getCachedValue(
      'email',
      SystemConfigConstants::CACHE_TTL_EMAIL,
      fn(): array => $this->getEmailSettings()
    );

    $generalSettings = $this->getCachedValue(
      'general',
      SystemConfigConstants::CACHE_TTL_LANGUAGE,
      fn(): array => $this->getGeneralSettings()
    );

    $authSettings = $this->getCachedValue(
      'auth',
      SystemConfigConstants::CACHE_TTL_LANGUAGE,
      fn(): array => $this->getAuthSettings()
    );

    $languageData = $refreshLanguageData
      ? $this->getLanguageList()
      : $this->getCachedValue(
          'lang',
          SystemConfigConstants::CACHE_TTL_LANGUAGE,
          fn(): array => $this->getLanguageList()
        );

    if ($refreshLanguageData) {
      $this->setTsCache('lang', $languageData, SystemConfigConstants::CACHE_TTL_LANGUAGE);
    }

    $dbRuntime = $this->getCachedValue(
      'db-runtime',
      SystemConfigConstants::CACHE_TTL_DB_CONFIG,
      fn(): array => $this->getDatabaseRuntimeViewModel()
    );

    $additionalConnections = $this->getCachedValue(
      'db-additional',
      SystemConfigConstants::CACHE_TTL_DB_CONFIG,
      fn(): array => $this->safeGetAdditionalConnections()
    );
    $additionalDiagnostics = $this->additionalConnectionDiagnostics->analyze($additionalConnections);

    $themeSettings = $this->getCachedValue(
      'theme',
      SystemConfigConstants::CACHE_TTL_DB_CONFIG,
      fn(): array => $this->getThemeSettings()
    );

    $aiChatbotSettings = $this->getCachedValue(
      'ai-chatbot',
      SystemConfigConstants::CACHE_TTL_DB_CONFIG,
      fn(): array => $this->getAiChatbotSettings()
    );

    $sidebarSmallImages = $this->getSidebarSmallImageOptions();
    $systemInformation = [];
    if ($includeSystemInformation) {
      try {
        $systemInformation = (new SystemInformationService(
          $this->pdo,
          dirname(__DIR__, 2)
        ))->build();
      } catch (Throwable $e) {
        error_log('[TetapanSistem] System information collection failed: ' . $e->getMessage());
        $systemInformation = [
          'error' => $this->tr('config_system_info_collection_error', 'System information could not be collected.'),
        ];
      }
    }

    return [
      'dbAktif' => $dbAktif,
      'mysqlInfo' => $mysqlInfo,
      'emailSettings' => $emailSettings,
      'generalSettings' => $generalSettings,
      'authSettings' => $authSettings,
      'languageData' => $languageData,
      'dbRuntime' => $dbRuntime,
      'additionalConnections' => $additionalConnections,
      'additionalDiagnostics' => $additionalDiagnostics,
      'themeSettings' => $themeSettings,
      'aiChatbotSettings' => $aiChatbotSettings,
      'sidebarSmallImages' => $sidebarSmallImages,
      'systemInformation' => $systemInformation,
    ];
  }

  private function safeGetAdditionalConnections(): array {
    try {
      return $this->additionalConnectionRepository->findAllAdditional();
    } catch (Throwable $e) {
      return [];
    }
  }

  private function buildAdditionalConnectionsResponseData(array $extra = []): array {
    $connections = $this->additionalConnectionRepository->findAllAdditional();
    return array_merge($extra, [
      'additionalConnections' => $connections,
      'additionalDiagnostics' => $this->additionalConnectionDiagnostics->analyze($connections),
    ]);
  }

  /**
   * Legacy compatibility layer.
   * Runtime kini derive active base daripada environment semasa.
   */
  public function activateSybaseBase(string $logical): bool {
    $this->ensureSession();

    $selected = $this->normalizeSelected($logical);
    if ($selected === '') {
      throw new \RuntimeException('Pilihan DB tidak sah.');
    }
    if (!$this->testSybaseConnection($selected)) {
      throw new \RuntimeException('Sambungan ke '.$selected.' gagal.');
    }

    $base = $this->logicalToBase($selected); // 'sybase_ehrmdb' | 'sybase_ehrmdb_dev'

    // Flags dalaman untuk compatibility display sahaja
    $flags = ['ehrmdb'=>false,'ehrmdb_dev'=>false];
    $flags[$selected] = true;
    $this->saveActiveDBConfig($flags);

    // Refresh state dalaman
    $this->active_db_flags    = $flags;
    $this->active_sybase_name = $base;

    // Bersih micro-cache page tetapan (kalau ada)
    $this->invalidateTsCache('dbcfg');

    return true;
  }

  /** Proses POST tab DB */
  private function prosesSimpananDB(): void {
    $response = $this->processDatabaseUpdate();

    set_alert([
      'title' => $response['title'] ?? ($response['success'] ? 'Berjaya' : 'Ralat'),
      'text' => $response['message'] ?? '',
      'icon' => !empty($response['success']) ? 'success' : 'error',
      'confirm' => true,
      'confirmText' => 'config_js_btn_tutup'
    ]);

    header('Location: tetapan-sistem.php?tab=db');
    exit;
  }

  private function processDatabaseUpdate(): array {
    $this->ensureSession();
    $selectedEnvironment = $this->normalizeEnvironment((string)($_POST['sybase_environment'] ?? ''));
    $selectedOperationalMode = $this->normalizeOperationalMode((string)($_POST['sybase_operational_mode'] ?? ''));
    $selectedMainDbEnvironment = $this->normalizeMainDbEnvironment((string)($_POST['main_db_environment'] ?? ''));

    if ($selectedEnvironment === '' || $selectedOperationalMode === '' || $selectedMainDbEnvironment === '') {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => $this->tr('config_db_validation_title', 'Ralat Validasi'),
        'message' => $this->tr('config_db_validation_required', 'Sila lengkapkan pilihan konfigurasi pangkalan data.'),
        'errors' => [$this->tr('config_db_validation_required', 'Sila lengkapkan pilihan konfigurasi pangkalan data.')],
      ];
    }

    try {
      $oldBase = $this->active_sybase_name;
      $oldMainDbEnvironment = $this->configModel->getMainDbEnvironment(SystemConfigConstants::DEFAULT_MAIN_DB_ENVIRONMENT);
      $oldEnvironment = $this->configModel->getSybaseEnvironment(SystemConfigConstants::DEFAULT_SYBASE_ENVIRONMENT);
      $oldOperationalMode = $this->configModel->getSybaseOperationalMode(SystemConfigConstants::DEFAULT_SYBASE_OPERATIONAL_MODE);

      $this->configModel->setMainDbEnvironment($selectedMainDbEnvironment);
      $this->configModel->setSybaseEnvironment($selectedEnvironment);
      $this->configModel->setSybaseOperationalMode($selectedOperationalMode);
      $_SESSION['MAIN_DB_ENVIRONMENT'] = $selectedMainDbEnvironment;
      $_SESSION['SYBASE_ENVIRONMENT'] = $selectedEnvironment;
      $_SESSION['SYBASE_OPERATIONAL_MODE'] = $selectedOperationalMode;

      Database::clearInstance('mysql');
      Database::clearInstance('mysql_prod');
      Database::clearInstance('mysql_dev');

      $legacyLogical = ($selectedEnvironment === 'development') ? 'ehrmdb_dev' : 'ehrmdb';
      $this->activateSybaseBase($legacyLogical);
      $this->active_sybase_name = $this->getCompatibilityBaseForEnvironment($selectedEnvironment);
      $this->invalidateTsCache('db-runtime');

      $environmentLabel = $this->tr(
        $selectedEnvironment === 'development' ? 'config_tab_db_environment_development' : 'config_tab_db_environment_production',
        ucfirst($selectedEnvironment)
      );
      $modeLabel = $this->tr(
        $selectedOperationalMode === 'staff_student' ? 'config_tab_db_mode_staff_student' : 'config_tab_db_mode_staff_only',
        $selectedOperationalMode
      );
      $mainMysqlEnvironmentLabel = $this->tr(
        $selectedMainDbEnvironment === 'development' ? 'config_tab_db_environment_development' : 'config_tab_db_environment_production',
        ucfirst($selectedMainDbEnvironment)
      );
      
      // Audit logging
      $this->auditDatabaseUpdate($oldBase, $this->active_sybase_name, (string)$oldEnvironment, (string)$selectedEnvironment, (string)$oldOperationalMode, (string)$selectedOperationalMode);
      
      return [
        'success' => true,
        'tab' => 'db',
        'title' => $this->tr('config_db_success_title', 'Berjaya'),
        'message' => sprintf(
          $this->tr('config_db_success_text_summary', 'Tetapan pangkalan data berjaya disimpan. MySQL: %s. Sybase environment: %s. Mode: %s.'),
          $mainMysqlEnvironmentLabel,
          $environmentLabel,
          $modeLabel
        ),
        'data' => [
          'dbRuntime' => $this->getDatabaseRuntimeViewModel(),
        ],
      ];
    } catch (\RuntimeException $e) {
      $message = match(true) {
        str_contains($e->getMessage(), 'tidak sah') => $this->tr('config_db_validation_invalid', 'Pilihan konfigurasi pangkalan data tidak sah.'),
        str_contains($e->getMessage(), 'gagal') => $this->tr('config_db_connection_error_text', 'Sambungan ke pangkalan data gagal. Sila semak konfigurasi sambungan database atau hubungi pentadbir sistem.'),
        default => $this->tr('config_db_runtime_error_text', 'Ralat berlaku semasa mengaktifkan pangkalan data.')
      };
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => $this->tr('config_db_connection_error_title', 'Ralat Sambungan Database'),
        'message' => $message,
        'errors' => [$message],
      ];
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Database activation error: " . $e->getMessage());
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => $this->tr('config_db_system_error_title', 'Ralat Sistem'),
        'message' => $this->tr('config_db_system_error_text', 'Ralat berlaku semasa mengaktifkan pangkalan data. Sila cuba lagi atau hubungi pentadbir sistem.'),
        'errors' => [$this->tr('config_db_system_error_text', 'Ralat berlaku semasa mengaktifkan pangkalan data. Sila cuba lagi atau hubungi pentadbir sistem.')],
      ];
    }
  }

  private function processAdditionalConnectionList(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    try {
      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Berjaya',
        'message' => 'Senarai sambungan tambahan berjaya dimuatkan.',
        'data' => $this->buildAdditionalConnectionsResponseData(),
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sistem',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function processAdditionalConnectionCreate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $payload = $this->collectAdditionalConnectionPayload();
    $envRows = $this->collectAdditionalConnectionEnvRows();
    $errors = $this->additionalConnectionValidator->validateAdditionalPayload($payload, $envRows, false);

    try {
      if ($payload['f_code'] !== '' && $this->additionalConnectionRepository->connectionCodeExists((string)$payload['f_code'])) {
        $errors[] = 'Kod sambungan tambahan sudah wujud.';
      }
    } catch (Throwable $e) {
      $errors[] = $this->safeAdditionalDatabaseError($e);
    }

    if ($errors !== []) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => implode("\n", $errors),
        'errors' => $errors,
      ];
    }

    try {
      $code = $this->additionalConnectionRepository->createAdditional($payload, $envRows);
      $this->invalidateTsCache('db-additional');
      $this->auditAdditionalConnectionAction('CREATE', $code, ['payload' => $payload]);

      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Berjaya',
        'message' => 'Sambungan tambahan berjaya ditambah.',
        'data' => $this->buildAdditionalConnectionsResponseData([
          'connection' => $this->additionalConnectionRepository->findAdditionalByCode($code),
        ]),
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      $this->auditAdditionalConnectionAction('CREATE_FAILED', (string)($payload['f_code'] ?? ''), [
        'payload' => $payload,
        'error_category' => DatabaseErrorClassifier::classify($e),
        'error' => $safeError,
      ]);
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sistem',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function processAdditionalConnectionUpdate(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $code = strtolower(trim((string)($_POST['existing_code'] ?? $_POST['connection_code'] ?? $_POST['f_code'] ?? '')));
    $payload = $this->collectAdditionalConnectionPayload($code);
    $envRows = $this->collectAdditionalConnectionEnvRows();
    $errors = $this->additionalConnectionValidator->validateAdditionalPayload($payload, $envRows, true);

    if ($code === '') {
      $errors[] = 'Kod sambungan tambahan sedia ada wajib diisi.';
    }

    if ($errors !== []) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => implode("\n", $errors),
        'errors' => $errors,
      ];
    }

    try {
      $existing = $this->additionalConnectionRepository->findAdditionalByCode($code);
      if (!$existing) {
        throw new RuntimeException('Sambungan tambahan tidak ditemui.');
      }

      $this->additionalConnectionRepository->updateAdditional(
        $code,
        $payload,
        $envRows,
        trim((string)($_POST['f_registry_revision'] ?? '')) ?: null
      );
      $this->invalidateTsCache('db-additional');
      $this->auditAdditionalConnectionAction('UPDATE', $code, [
        'old' => $existing,
        'new' => $payload,
      ]);

      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Berjaya',
        'message' => 'Sambungan tambahan berjaya dikemas kini.',
        'data' => $this->buildAdditionalConnectionsResponseData([
          'connection' => $this->additionalConnectionRepository->findAdditionalByCode($code),
        ]),
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      $this->auditAdditionalConnectionAction('UPDATE_FAILED', $code, [
        'new' => $payload,
        'error_category' => DatabaseErrorClassifier::classify($e),
        'error' => $safeError,
      ]);
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sistem',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function processAdditionalConnectionToggle(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $code = strtolower(trim((string)($_POST['connection_code'] ?? '')));
    $enabled = in_array(strtolower(trim((string)($_POST['enabled'] ?? ''))), ['1', 'true', 'yes', 'on'], true);

    if ($code === '') {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => 'Kod sambungan tambahan wajib diisi.',
        'errors' => ['Kod sambungan tambahan wajib diisi.'],
      ];
    }

    try {
      $existing = $this->additionalConnectionRepository->findAdditionalByCode($code);
      if (!$existing) {
        throw new RuntimeException('Sambungan tambahan tidak ditemui.');
      }

      if (in_array($code, SystemConfigConstants::RESERVED_DATABASE_CODES, true)) {
        throw new RuntimeException('Kod sambungan ini dikhaskan untuk sistem utama dan tidak boleh diubah melalui registry tambahan.');
      }

      $this->additionalConnectionRepository->setEnabled($code, $enabled);
      $this->invalidateTsCache('db-additional');
      $this->auditAdditionalConnectionAction($enabled ? 'ENABLE' : 'DISABLE', $code, ['enabled' => $enabled]);

      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Berjaya',
        'message' => $enabled ? 'Sambungan tambahan berjaya diaktifkan.' : 'Sambungan tambahan berjaya dinyahaktifkan.',
        'data' => $this->buildAdditionalConnectionsResponseData([
          'connection' => $this->additionalConnectionRepository->findAdditionalByCode($code),
        ]),
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      $this->auditAdditionalConnectionAction('TOGGLE_FAILED', $code, [
        'enabled' => $enabled,
        'error_category' => DatabaseErrorClassifier::classify($e),
        'error' => $safeError,
      ]);
      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sistem',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function processAdditionalConnectionTest(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $code = strtolower(trim((string)($_POST['connection_code'] ?? '')));
    if ($code === '') {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => 'Kod sambungan tambahan wajib diisi.',
        'errors' => ['Kod sambungan tambahan wajib diisi.'],
      ];
    }

    try {
      $connection = $this->additionalConnectionRepository->findAdditionalByCode($code, true);
      if (!$connection) {
        throw new RuntimeException('Sambungan tambahan tidak ditemui.');
      }

      if (empty($connection['f_is_enabled'])) {
        throw new RuntimeException('Sambungan tambahan ini sedang dinyahaktifkan. Aktifkan semula sebelum menjalankan ujian.');
      }

      $testTarget = $this->selectAdditionalConnectionEnvRowForTesting($connection, $_POST);
      $pdoConfig = $this->buildAdditionalTestPdoConfig($testTarget, (string)($connection['f_family'] ?? ''));
      $pdo = $this->additionalConnectionFactory->make($pdoConfig);
      $pdo->query('select 1');

      $this->additionalConnectionRepository->saveTestResult(
        $code,
        (string)$testTarget['f_environment'],
        (string)$testTarget['f_os_family'],
        'SUCCESS',
        'Connection test passed.',
        (string)($testTarget['f_driver'] ?? 'auto')
      );
      $this->auditAdditionalConnectionAction('TEST', $code, [
        'environment' => $testTarget['f_environment'],
        'os_family' => $testTarget['f_os_family'],
        'driver' => $testTarget['f_driver'],
        'status' => 'SUCCESS',
      ]);

      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Berjaya',
        'message' => 'Ujian sambungan tambahan berjaya.',
        'data' => [
          'connection_code' => $code,
          'environment' => $testTarget['f_environment'],
          'os_family' => $testTarget['f_os_family'],
          'driver' => $testTarget['f_driver'],
          'status' => 'SUCCESS',
        ],
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      try {
        if ($code !== '') {
          $this->additionalConnectionRepository->saveTestResult(
            $code,
            strtolower(trim((string)($_POST['environment'] ?? 'production'))),
            strtolower(trim((string)($_POST['os_family'] ?? (PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux')))),
            'ERROR',
            $safeError,
            strtolower(trim((string)($_POST['driver'] ?? 'auto')))
          );
        }
      } catch (Throwable $ignored) {
      }

      $this->auditAdditionalConnectionAction('TEST_FAILED', $code, [
        'environment' => strtolower(trim((string)($_POST['environment'] ?? 'production'))),
        'os_family' => strtolower(trim((string)($_POST['os_family'] ?? (PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux')))),
        'driver' => strtolower(trim((string)($_POST['driver'] ?? 'auto'))),
        'status' => 'ERROR',
        'error_category' => DatabaseErrorClassifier::classify($e),
        'error' => $safeError,
      ]);

      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sambungan Database',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function processAdditionalConnectionInspect(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $code = strtolower(trim((string)($_POST['connection_code'] ?? '')));
    if ($code === '') {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => 'Kod sambungan tambahan wajib diisi.',
        'errors' => ['Kod sambungan tambahan wajib diisi.'],
      ];
    }

    try {
      $connection = $this->additionalConnectionRepository->findAdditionalByCode($code, true);
      if (!$connection) {
        throw new RuntimeException('Sambungan tambahan tidak ditemui.');
      }

      if (empty($connection['f_is_enabled'])) {
        throw new RuntimeException('Sambungan tambahan ini sedang dinyahaktifkan. Aktifkan semula sebelum melihat butiran sambungan.');
      }

      $target = $this->selectAdditionalConnectionEnvRowForTesting($connection, $_POST);
      $environment = (string)($target['f_environment'] ?? 'production');

      $manager = new DatabaseManager();
      $pdo = $manager->additional($code, $environment);
      $probe = $this->buildAdditionalConnectionProbe($pdo, $connection, $target);

      $this->auditAdditionalConnectionAction('INSPECT', $code, [
        'environment' => $target['f_environment'],
        'os_family' => $target['f_os_family'],
        'driver' => $target['f_driver'],
        'status' => 'SUCCESS',
      ]);

      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Butiran Sambungan Tambahan',
        'message' => 'Maklumat sambungan tambahan berjaya dimuatkan.',
        'data' => [
          'probe' => $probe,
        ],
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      $this->auditAdditionalConnectionAction('INSPECT_FAILED', $code, [
        'environment' => strtolower(trim((string)($_POST['environment'] ?? 'production'))),
        'os_family' => strtolower(trim((string)($_POST['os_family'] ?? (PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux')))),
        'driver' => strtolower(trim((string)($_POST['driver'] ?? 'auto'))),
        'status' => 'ERROR',
        'error_category' => DatabaseErrorClassifier::classify($e),
        'error' => $safeError,
      ]);

      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sambungan Database',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function processAdditionalConnectionSchemaPreview(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $code = strtolower(trim((string)($_POST['connection_code'] ?? '')));
    if ($code === '') {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => 'Kod sambungan tambahan wajib diisi.',
        'errors' => ['Kod sambungan tambahan wajib diisi.'],
      ];
    }

    try {
      $connection = $this->additionalConnectionRepository->findAdditionalByCode($code, true);
      if (!$connection) {
        throw new RuntimeException('Sambungan tambahan tidak ditemui.');
      }

      if (empty($connection['f_is_enabled'])) {
        throw new RuntimeException('Sambungan tambahan ini sedang dinyahaktifkan. Aktifkan semula sebelum melihat schema preview.');
      }

      $target = $this->selectAdditionalConnectionEnvRowForTesting($connection, $_POST);
      $environment = (string)($target['f_environment'] ?? 'production');

      $manager = new DatabaseManager();
      $pdo = $manager->additional($code, $environment);
      $schemaPreview = $this->buildAdditionalConnectionSchemaPreview($pdo, $connection, $target);

      $this->auditAdditionalConnectionAction('SCHEMA_PREVIEW', $code, [
        'environment' => $target['f_environment'],
        'os_family' => $target['f_os_family'],
        'driver' => $target['f_driver'],
        'status' => 'SUCCESS',
      ]);

      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Schema Preview Sambungan Tambahan',
        'message' => 'Schema preview berjaya dimuatkan.',
        'data' => [
          'schemaPreview' => $schemaPreview,
        ],
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      $this->auditAdditionalConnectionAction('SCHEMA_PREVIEW_FAILED', $code, [
        'environment' => strtolower(trim((string)($_POST['environment'] ?? 'production'))),
        'os_family' => strtolower(trim((string)($_POST['os_family'] ?? (PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux')))),
        'driver' => strtolower(trim((string)($_POST['driver'] ?? 'auto'))),
        'status' => 'ERROR',
        'error_category' => DatabaseErrorClassifier::classify($e),
        'error' => $safeError,
      ]);

      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sambungan Database',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function processAdditionalConnectionObjectPreview(): array {
    $this->checkAuthorization();
    $this->validateCSRF();
    $this->ensureSession();

    $code = strtolower(trim((string)($_POST['connection_code'] ?? '')));
    $objectName = trim((string)($_POST['object_name'] ?? ''));

    if ($code === '' || $objectName === '') {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => 'Kod sambungan tambahan dan nama objek wajib diisi.',
        'errors' => ['Kod sambungan tambahan dan nama objek wajib diisi.'],
      ];
    }

    if (preg_match('/^[A-Za-z0-9_.$#]+$/', $objectName) !== 1) {
      return [
        'success' => false,
        'status' => 422,
        'tab' => 'db',
        'title' => 'Ralat Validasi',
        'message' => 'Nama objek schema preview mengandungi aksara yang tidak dibenarkan.',
        'errors' => ['Nama objek schema preview mengandungi aksara yang tidak dibenarkan.'],
      ];
    }

    try {
      $connection = $this->additionalConnectionRepository->findAdditionalByCode($code, true);
      if (!$connection) {
        throw new RuntimeException('Sambungan tambahan tidak ditemui.');
      }

      if (empty($connection['f_is_enabled'])) {
        throw new RuntimeException('Sambungan tambahan ini sedang dinyahaktifkan. Aktifkan semula sebelum melihat data preview.');
      }

      $target = $this->selectAdditionalConnectionEnvRowForTesting($connection, $_POST);
      $environment = (string)($target['f_environment'] ?? 'production');

      $manager = new DatabaseManager();
      $pdo = $manager->additional($code, $environment);
      $preview = $this->buildAdditionalConnectionObjectPreview($pdo, $connection, $target, $objectName);

      $this->auditAdditionalConnectionAction('OBJECT_PREVIEW', $code, [
        'environment' => $target['f_environment'],
        'os_family' => $target['f_os_family'],
        'driver' => $target['f_driver'],
        'object_name' => $objectName,
        'status' => 'SUCCESS',
      ]);

      return [
        'success' => true,
        'tab' => 'db',
        'title' => 'Data Preview',
        'message' => 'Data preview berjaya dimuatkan.',
        'data' => [
          'objectPreview' => $preview,
        ],
      ];
    } catch (Throwable $e) {
      $safeError = $this->safeAdditionalDatabaseError($e);
      $this->auditAdditionalConnectionAction('OBJECT_PREVIEW_FAILED', $code, [
        'environment' => strtolower(trim((string)($_POST['environment'] ?? 'production'))),
        'os_family' => strtolower(trim((string)($_POST['os_family'] ?? (PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux')))),
        'driver' => strtolower(trim((string)($_POST['driver'] ?? 'auto'))),
        'object_name' => $objectName,
        'status' => 'ERROR',
        'error_category' => DatabaseErrorClassifier::classify($e),
        'error' => $safeError,
      ]);

      return [
        'success' => false,
        'status' => 500,
        'tab' => 'db',
        'title' => 'Ralat Sambungan Database',
        'message' => $safeError,
        'errors' => [$safeError],
      ];
    }
  }

  private function safeAdditionalDatabaseError(Throwable $error): string {
    return DatabaseErrorRedactor::redact($error->getMessage());
  }

  private function collectAdditionalConnectionPayload(?string $forcedCode = null): array {
    $code = $forcedCode !== null && $forcedCode !== ''
      ? strtolower(trim($forcedCode))
      : strtolower(trim((string)($_POST['f_code'] ?? $_POST['connection_code'] ?? '')));

    return [
      'f_code' => $code,
      'f_name' => trim((string)($_POST['f_name'] ?? $_POST['connection_name'] ?? $code)),
      'f_family' => strtolower(trim((string)($_POST['f_family'] ?? $_POST['connection_family'] ?? ''))),
      'f_purpose' => trim((string)($_POST['f_purpose'] ?? $_POST['connection_purpose'] ?? 'reference')),
      'f_driver_mode' => strtolower(trim((string)($_POST['f_driver_mode'] ?? $_POST['connection_driver_mode'] ?? 'auto'))),
      'f_is_enabled' => in_array(strtolower(trim((string)($_POST['f_is_enabled'] ?? $_POST['connection_enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true),
      'f_supports_prod' => in_array(strtolower(trim((string)($_POST['f_supports_prod'] ?? '1'))), ['1', 'true', 'yes', 'on'], true),
      'f_supports_dev' => in_array(strtolower(trim((string)($_POST['f_supports_dev'] ?? '1'))), ['1', 'true', 'yes', 'on'], true),
      'f_notes' => trim((string)($_POST['f_notes'] ?? $_POST['connection_notes'] ?? '')),
      'f_created_by' => (string)($_SESSION['f_loginID'] ?? $_SESSION['f_stafID'] ?? ''),
      'f_updated_by' => (string)($_SESSION['f_loginID'] ?? $_SESSION['f_stafID'] ?? ''),
    ];
  }

  private function collectAdditionalConnectionEnvRows(): array {
    $raw = $_POST['env_rows'] ?? null;
    $rows = [];

    if (is_string($raw) && trim($raw) !== '') {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) {
        $rows = $decoded;
      }
    } elseif (is_array($raw)) {
      $rows = $raw;
    }

    $normalized = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $normalized[] = [
        'f_environment' => strtolower(trim((string)($row['f_environment'] ?? $row['environment'] ?? 'production'))),
        'f_os_family' => strtolower(trim((string)($row['f_os_family'] ?? $row['os_family'] ?? 'any'))),
        'f_driver' => strtolower(trim((string)($row['f_driver'] ?? $row['driver'] ?? ''))),
        'f_host' => trim((string)($row['f_host'] ?? $row['host'] ?? '')),
        'f_port' => trim((string)($row['f_port'] ?? $row['port'] ?? '')),
        'f_database_name' => trim((string)($row['f_database_name'] ?? $row['database_name'] ?? $row['dbname'] ?? '')),
        'f_dsn_name' => trim((string)($row['f_dsn_name'] ?? $row['dsn_name'] ?? $row['dsn'] ?? '')),
        'f_username' => trim((string)($row['f_username'] ?? $row['username'] ?? '')),
        'f_password_ciphertext' => (string)($row['f_password_ciphertext'] ?? $row['password'] ?? ''),
        'f_charset' => trim((string)($row['f_charset'] ?? $row['charset'] ?? '')),
        'f_extra_json' => $row['f_extra_json'] ?? $row['extra_json'] ?? null,
        'f_is_active' => in_array(strtolower(trim((string)($row['f_is_active'] ?? $row['is_active'] ?? '1'))), ['1', 'true', 'yes', 'on'], true),
      ];
    }

    return $normalized;
  }

  private function selectAdditionalConnectionEnvRowForTesting(array $connection, array $input): array {
    $envRows = is_array($connection['env_rows'] ?? null) ? $connection['env_rows'] : [];
    if ($envRows === []) {
      throw new RuntimeException('Tiada konfigurasi environment untuk sambungan tambahan ini.');
    }

    $targetEnvironment = strtolower(trim((string)($input['environment'] ?? 'production')));
    // Live actions must always resolve against the server runtime. Do not trust
    // stale client-supplied OS/driver values, as they can select a Linux DBLIB
    // row on Windows (or vice versa).
    $targetOsFamily = PHP_OS_FAMILY === 'Windows' ? 'windows' : 'linux';
    $targetDriver = '';
    $supportsProd = !empty($connection['f_supports_prod']);
    $supportsDev = !empty($connection['f_supports_dev']);

    if ($targetEnvironment === 'production' && !$supportsProd) {
      throw new RuntimeException('Sambungan tambahan ini tidak menyokong environment production.');
    }

    if ($targetEnvironment === 'development' && !$supportsDev) {
      throw new RuntimeException('Sambungan tambahan ini tidak menyokong environment development.');
    }

    $family = strtolower(trim((string)($connection['f_family'] ?? '')));
    $availableDrivers = array_map('strtolower', PDO::getAvailableDrivers());
    $preferences = match ($family) {
      'sybase' => $targetOsFamily === 'windows' ? ['odbc', 'dblib'] : ['dblib', 'odbc'],
      'mssql' => $targetOsFamily === 'windows' ? ['sqlsrv', 'odbc', 'dblib'] : ['odbc', 'dblib', 'sqlsrv'],
      default => ['mysql'],
    };
    if ($targetDriver !== '') {
      $preferences = [$targetDriver];
    }

    $matches = [];
    foreach ($envRows as $row) {
      $rowEnvironment = strtolower(trim((string)($row['f_environment'] ?? '')));
      $rowOsFamily = strtolower(trim((string)($row['f_os_family'] ?? 'any')));
      $rowDriver = strtolower(trim((string)($row['f_driver'] ?? '')));
      $rowIsActive = !empty($row['f_is_active']);

      if (!$rowIsActive) {
        continue;
      }

      if ($rowEnvironment !== $targetEnvironment) {
        continue;
      }
      if (!in_array($rowOsFamily, [$targetOsFamily, 'any'], true)) {
        continue;
      }
      if (!in_array($rowDriver, $preferences, true) || !in_array($rowDriver, $availableDrivers, true)) {
        continue;
      }
      $osRank = $rowOsFamily === $targetOsFamily ? 0 : 1;
      $driverRank = array_search($rowDriver, $preferences, true);
      $matches[] = ['row' => $row, 'rank' => [$osRank, $driverRank === false ? 999 : $driverRank]];
    }
    usort($matches, static fn(array $left, array $right): int => $left['rank'] <=> $right['rank']);
    if ($matches !== []) {
      return $matches[0]['row'];
    }

    throw new RuntimeException(sprintf(
      'Tiada env row aktif untuk environment %s dan OS %s dengan driver runtime yang tersedia.',
      $targetEnvironment,
      $targetOsFamily
    ));
  }

  private function buildAdditionalTestPdoConfig(array $envRow, string $family = ''): array {
    $family = strtolower(trim($family));
    $driver = strtolower(trim((string)($envRow['f_driver'] ?? '')));
    $host = trim((string)($envRow['f_host'] ?? ''));
    $port = trim((string)($envRow['f_port'] ?? ''));
    $databaseName = trim((string)($envRow['f_database_name'] ?? ''));
    $dsnName = trim((string)($envRow['f_dsn_name'] ?? ''));
    $charset = trim((string)($envRow['f_charset'] ?? 'utf8mb4'));
    $dblibDefaultPort = $family === 'mssql' ? '1433' : '5000';
    $extra = $this->decodeAdditionalExtraOptions($envRow['f_extra_json'] ?? null);
    $sqlServerOptions = $this->buildSqlServerDsnOptions($extra);

    $dsn = match ($driver) {
      'mysql' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port !== '' ? $port : '3306', $databaseName, $charset !== '' ? $charset : 'utf8mb4'),
      'dblib' => sprintf('dblib:host=%s:%s;dbname=%s', $host, $port !== '' ? $port : $dblibDefaultPort, $databaseName),
      'sqlsrv' => sprintf('sqlsrv:Server=%s%s;Database=%s%s', $host, $port !== '' ? ',' . $port : '', $databaseName, $sqlServerOptions),
      'odbc' => 'odbc:' . $this->appendOdbcSqlServerOptions($dsnName, $extra),
      default => throw new RuntimeException('Driver tambahan tidak disokong untuk ujian sambungan.'),
    };

    return [
      'driver' => $driver,
      'dsn' => $dsn,
      'user' => (string)($envRow['f_username'] ?? ''),
      'pass' => (string)($envRow['f_password_ciphertext'] ?? ''),
    ];
  }

  private function decodeAdditionalExtraOptions(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }
    if (!is_string($value) || trim($value) === '') {
      return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
  }

  private function additionalExtraBool(array $extra, array $keys, bool $default = false): bool {
    foreach ($keys as $key) {
      if (!array_key_exists($key, $extra)) {
        continue;
      }
      $value = $extra[$key];
      if (is_bool($value)) {
        return $value;
      }
      return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
    return $default;
  }

  private function buildSqlServerDsnOptions(array $extra): string {
    $parts = [];
    if ($this->additionalExtraBool($extra, ['encrypt', 'Encrypt'], false)) {
      $parts[] = 'Encrypt=yes';
    }
    if ($this->additionalExtraBool($extra, ['trust_server_certificate', 'TrustServerCertificate'], false)) {
      $parts[] = 'TrustServerCertificate=yes';
    }
    return $parts !== [] ? ';' . implode(';', $parts) : '';
  }

  private function appendOdbcSqlServerOptions(string $dsnName, array $extra): string {
    $options = ltrim($this->buildSqlServerDsnOptions($extra), ';');
    if ($options === '') {
      return $dsnName;
    }
    return rtrim($dsnName, ';') . ';' . $options;
  }

  private function buildAdditionalConnectionProbe(PDO $pdo, array $connection, array $envRow): array {
    $driverName = 'unknown';
    $serverVersion = null;
    $connectionStatus = null;

    try {
      $driverName = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $e) {
    }

    try {
      $serverVersion = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch (Throwable $e) {
    }

    try {
      $connectionStatus = (string)$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    } catch (Throwable $e) {
    }

    $pingValue = null;
    try {
      $pingValue = $pdo->query('SELECT 1 AS ping')->fetch(PDO::FETCH_ASSOC)['ping'] ?? null;
    } catch (Throwable $e) {
    }

    $snapshot = [
      'connection_code' => (string)($connection['f_code'] ?? ''),
      'connection_name' => (string)($connection['f_name'] ?? ''),
      'family' => strtolower(trim((string)($connection['f_family'] ?? ''))),
      'purpose' => (string)($connection['f_purpose'] ?? ''),
      'environment' => (string)($envRow['f_environment'] ?? ''),
      'os_family' => (string)($envRow['f_os_family'] ?? ''),
      'configured_driver' => (string)($envRow['f_driver'] ?? ''),
      'active_driver' => $driverName,
      'host' => (string)($envRow['f_host'] ?? ''),
      'port' => (string)($envRow['f_port'] ?? ''),
      'database_name' => (string)($envRow['f_database_name'] ?? ''),
      'dsn_name' => (string)($envRow['f_dsn_name'] ?? ''),
      'username' => (string)($envRow['f_username'] ?? ''),
      'server_version' => $serverVersion,
      'connection_status' => $connectionStatus,
      'ping' => $pingValue,
    ];

    $family = $snapshot['family'];
    $infoQuery = null;

    if ($family === 'mysql') {
      $infoQuery = 'SELECT DATABASE() AS current_database, CURRENT_USER() AS current_user, NOW() AS server_time';
    } elseif (in_array($family, ['sybase', 'mssql'], true)) {
      $infoQuery = 'SELECT db_name() AS current_database, suser_name() AS current_user, getdate() AS server_time';
    }

    if ($infoQuery !== null) {
      try {
        $info = $pdo->query($infoQuery)->fetch(PDO::FETCH_ASSOC);
        if (is_array($info)) {
          $snapshot['current_database'] = (string)($info['current_database'] ?? $snapshot['database_name']);
          $snapshot['current_user'] = (string)($info['current_user'] ?? $snapshot['username']);
          $snapshot['server_time'] = (string)($info['server_time'] ?? '');
        }
      } catch (Throwable $e) {
        $snapshot['current_database'] = $snapshot['database_name'];
        $snapshot['current_user'] = $snapshot['username'];
        $snapshot['server_time'] = null;
      }
    }

    return $snapshot;
  }

  private function buildAdditionalConnectionSchemaPreview(PDO $pdo, array $connection, array $envRow): array {
    $family = strtolower(trim((string)($connection['f_family'] ?? '')));
    $databaseName = trim((string)($envRow['f_database_name'] ?? ''));
    $rows = [];

    if ($family === 'mysql') {
      $stmt = $pdo->prepare("
        SELECT TABLE_NAME AS object_name, TABLE_TYPE AS object_type
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = COALESCE(NULLIF(:database_name, ''), DATABASE())
        ORDER BY TABLE_NAME ASC
        LIMIT 30
      ");
      $stmt->execute([':database_name' => $databaseName]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif (in_array($family, ['sybase', 'mssql'], true)) {
      $stmt = $pdo->query("
        SELECT TOP 30 name AS object_name,
               CASE type WHEN 'U' THEN 'TABLE' WHEN 'V' THEN 'VIEW' ELSE type END AS object_type
        FROM sysobjects
        WHERE type IN ('U', 'V')
        ORDER BY name ASC
      ");
      $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } else {
      throw new RuntimeException('Family database ini belum disokong untuk schema preview.');
    }

    return [
      'connection_code' => (string)($connection['f_code'] ?? ''),
      'connection_name' => (string)($connection['f_name'] ?? ''),
      'family' => $family,
      'environment' => (string)($envRow['f_environment'] ?? ''),
      'os_family' => (string)($envRow['f_os_family'] ?? ''),
      'driver' => (string)($envRow['f_driver'] ?? ''),
      'database_name' => $databaseName,
      'objects' => array_map(static function (array $row): array {
        return [
          'object_name' => (string)($row['object_name'] ?? ''),
          'object_type' => strtoupper(trim((string)($row['object_type'] ?? ''))),
        ];
      }, $rows),
    ];
  }

  private function buildAdditionalConnectionObjectPreview(PDO $pdo, array $connection, array $envRow, string $objectName): array {
    $family = strtolower(trim((string)($connection['f_family'] ?? '')));
    $safeObjectName = $this->quoteAdditionalPreviewObjectName($family, $objectName);

    if ($family === 'mysql') {
      $sql = "SELECT * FROM {$safeObjectName} LIMIT 20";
    } elseif (in_array($family, ['sybase', 'mssql'], true)) {
      $sql = "SELECT TOP 20 * FROM {$safeObjectName}";
    } else {
      throw new RuntimeException('Family database ini belum disokong untuk data preview.');
    }

    $this->applyAdditionalPreviewTimeout($pdo, $family);
    $stmt = $pdo->query($sql);
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $columns = $rows !== [] ? array_keys($rows[0]) : [];
    $sanitized = $this->additionalPreviewSanitizer->sanitize($rows);

    return [
      'connection_code' => (string)($connection['f_code'] ?? ''),
      'connection_name' => (string)($connection['f_name'] ?? ''),
      'family' => $family,
      'environment' => (string)($envRow['f_environment'] ?? ''),
      'database_name' => (string)($envRow['f_database_name'] ?? ''),
      'object_name' => $objectName,
      'columns' => array_values($columns),
      'rows' => $sanitized['rows'],
      'masked_columns' => $sanitized['masked_columns'],
      'truncated_values' => $sanitized['truncated_values'],
      'binary_values' => $sanitized['binary_values'],
      'row_limit' => 20,
      'query_timeout_seconds' => SystemConfigConstants::DB_TEST_CONNECTION_TIMEOUT,
    ];
  }

  private function applyAdditionalPreviewTimeout(PDO $pdo, string $family): void {
    try {
      $pdo->setAttribute(PDO::ATTR_TIMEOUT, SystemConfigConstants::DB_TEST_CONNECTION_TIMEOUT);
    } catch (Throwable $e) {
    }

    if ($family === 'mysql') {
      try {
        $pdo->exec('SET SESSION MAX_EXECUTION_TIME = ' . (SystemConfigConstants::DB_TEST_CONNECTION_TIMEOUT * 1000));
      } catch (Throwable $e) {
      }
    }
  }

  private function quoteAdditionalPreviewObjectName(string $family, string $objectName): string {
    $parts = array_values(array_filter(array_map('trim', explode('.', $objectName)), static fn(string $part): bool => $part !== ''));
    if ($parts === []) {
      throw new RuntimeException('Nama objek preview tidak sah.');
    }

    return implode('.', array_map(static function (string $part) use ($family): string {
      if (preg_match('/^[A-Za-z0-9_#$]+$/', $part) !== 1) {
        throw new RuntimeException('Nama objek preview mengandungi aksara yang tidak dibenarkan.');
      }

      if ($family === 'mysql') {
        return '`' . str_replace('`', '``', $part) . '`';
      }

      return '[' . str_replace(']', ']]', $part) . ']';
    }, $parts));
  }

  /**
   * Cari compatibility base key untuk paparan/runtime legacy.
   * Priority baharu: environment -> old active base -> JSON.
   */
  private function findActiveSybaseName(): ?string {
    $this->ensureSession();

    $environment = $this->getSelectedEnvironment();
    if ($environment !== '') {
      return $environment === 'development' ? 'sybase_ehrmdb_dev' : 'sybase_ehrmdb';
    }

    // Legacy fallback: internal flags sahaja
    foreach (['ehrmdb','ehrmdb_dev'] as $k) {
      if (!empty($this->active_db_flags[$k])) {
        return $this->logicalToBase($k);
      }
    }

    return null;
  }

  /** base -> logical helper */
  private function baseToLogical(string $base): ?string {
    $base = strtolower($base);
    return match (true) {
      str_contains($base, 'ehrmdb_dev') => 'ehrmdb_dev',
      str_contains($base, 'ehrmdb')     => 'ehrmdb',
      default                           => null,
    };
  }

  private function getCompatibilityBaseForEnvironment(string $environment): string {
    return $environment === 'development' ? 'sybase_ehrmdb_dev' : 'sybase_ehrmdb';
  }

  // ---------------------------
  // Validation
  // ---------------------------

  /**
   * Validate email settings
   * @return array Array of error messages, empty if valid
   */
  private function validateEmailSettings(array $data): array {
    $errors = [];
    $labels = $this->getEmailFieldLabels();
    
    // Mail Host validation
    if (!empty($data['mail_host'])) {
      $host = trim($data['mail_host']);
      // Check if it's a valid domain or IP
      if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && 
          !filter_var($host, FILTER_VALIDATE_IP)) {
        $errors[] = sprintf($this->tr('config_email_validation_host', '%s tidak sah. Sila masukkan domain atau alamat IP yang sah.'), $labels['mail_host']);
      }
      if (strlen($host) > SystemConfigConstants::MAX_STRING_LENGTH) {
        $errors[] = sprintf($this->tr('config_email_validation_max', '%s terlalu panjang (maksimum %d aksara).'), $labels['mail_host'], SystemConfigConstants::MAX_STRING_LENGTH);
      }
    }
    
    // Mail Port validation
    if (!empty($data['mail_port'])) {
      $port = trim($data['mail_port']);
      if (!is_numeric($port)) {
        $errors[] = sprintf($this->tr('config_email_validation_port_numeric', '%s mesti nombor.'), $labels['mail_port']);
      } else {
        $portNum = (int)$port;
        if ($portNum < SystemConfigConstants::MIN_PORT || $portNum > SystemConfigConstants::MAX_PORT) {
          $errors[] = sprintf($this->tr('config_email_validation_port_range', '%s mesti antara %d hingga %d.'), $labels['mail_port'], SystemConfigConstants::MIN_PORT, SystemConfigConstants::MAX_PORT);
        }
      }
    }
    
    // Mail Username validation
    if (!empty($data['mail_username'])) {
      $username = trim($data['mail_username']);
      if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $errors[] = sprintf($this->tr('config_email_validation_email', '%s tidak sah. Sila masukkan alamat emel yang sah.'), $labels['mail_username']);
      }
      if (strlen($username) > SystemConfigConstants::MAX_STRING_LENGTH) {
        $errors[] = sprintf($this->tr('config_email_validation_max', '%s terlalu panjang (maksimum %d aksara).'), $labels['mail_username'], SystemConfigConstants::MAX_STRING_LENGTH);
      }
    }
    
    // Mail From Address validation
    if (!empty($data['mail_from_address'])) {
      $fromAddr = trim($data['mail_from_address']);
      if (!filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) {
        $errors[] = sprintf($this->tr('config_email_validation_email', '%s tidak sah. Sila masukkan alamat emel yang sah.'), $labels['mail_from_address']);
      }
      if (strlen($fromAddr) > SystemConfigConstants::MAX_STRING_LENGTH) {
        $errors[] = sprintf($this->tr('config_email_validation_max', '%s terlalu panjang (maksimum %d aksara).'), $labels['mail_from_address'], SystemConfigConstants::MAX_STRING_LENGTH);
      }
    }
    
    // Mail Encryption validation
    if (!empty($data['mail_encryption'])) {
      $encryption = strtolower(trim($data['mail_encryption']));
      if (!in_array($encryption, SystemConfigConstants::ALLOWED_MAIL_ENCRYPTION, true)) {
        $errors[] = sprintf($this->tr('config_email_validation_encryption', '%s tidak sah. Hanya %s dibenarkan.'), $labels['mail_encryption'], implode(' / ', SystemConfigConstants::ALLOWED_MAIL_ENCRYPTION));
      }
    }
    
    // Mail Driver validation
    if (!empty($data['mail_driver'])) {
      $driver = strtolower(trim($data['mail_driver']));
      if (!in_array($driver, SystemConfigConstants::ALLOWED_MAIL_DRIVERS, true)) {
        $errors[] = sprintf($this->tr('config_email_validation_driver', '%s tidak sah. Hanya %s dibenarkan.'), $labels['mail_driver'], implode(', ', SystemConfigConstants::ALLOWED_MAIL_DRIVERS));
      }
    }
    
    // Mail From Name validation
    if (!empty($data['mail_from_name']) && strlen($data['mail_from_name']) > SystemConfigConstants::MAX_STRING_LENGTH) {
      $errors[] = sprintf($this->tr('config_email_validation_max', '%s terlalu panjang (maksimum %d aksara).'), $labels['mail_from_name'], SystemConfigConstants::MAX_STRING_LENGTH);
    }
    
    return $errors;
  }

  /**
   * Validate theme settings
   * @return array Array of error messages, empty if valid
   */
  private function validateThemeSettings(array $data): array {
    $errors = [];
    $labels = $this->getThemeFieldLabels();
    
    if (!empty($data['topbarColor']) && !in_array($data['topbarColor'], SystemConfigConstants::ALLOWED_THEME_COLORS, true)) {
      $errors[] = sprintf($this->tr('config_theme_validation_invalid', '%s tidak sah. Hanya %s dibenarkan.'), $labels['topbarColor'], implode(', ', SystemConfigConstants::ALLOWED_THEME_COLORS));
    }
    
    if (!empty($data['sidebarColor']) && !in_array($data['sidebarColor'], SystemConfigConstants::ALLOWED_THEME_COLORS, true)) {
      $errors[] = sprintf($this->tr('config_theme_validation_invalid', '%s tidak sah. Hanya %s dibenarkan.'), $labels['sidebarColor'], implode(', ', SystemConfigConstants::ALLOWED_THEME_COLORS));
    }
    
    if (!empty($data['layoutMode']) && !in_array($data['layoutMode'], SystemConfigConstants::ALLOWED_THEME_MODES, true)) {
      $errors[] = sprintf($this->tr('config_theme_validation_invalid', '%s tidak sah. Hanya %s dibenarkan.'), $labels['layoutMode'], implode(', ', SystemConfigConstants::ALLOWED_THEME_MODES));
    }

    foreach (['topbarCustomSeed', 'sidebarCustomSeed'] as $seedKey) {
      if (!isset($data[$seedKey]) || !preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$data[$seedKey])) {
        $errors[] = sprintf($this->tr('config_theme_custom_invalid', '%s mesti menggunakan format warna hex seperti #4254BA.'), $labels[$seedKey] ?? $seedKey);
      }
    }
    
    return $errors;
  }

  private function normalizeThemeHex(mixed $value, string $fallback): string {
    $hex = strtoupper(trim((string)$value));
    return preg_match('/^#[0-9A-F]{6}$/', $hex) ? $hex : $fallback;
  }

  /**
   * Validate language settings
   * @return array Array of error messages, empty if valid
   */
  private function validateLanguageSettings(array $languages, string $defaultLanguage): array {
    $errors = [];
    
    if (empty($languages) || !is_array($languages)) {
      $errors[] = $this->tr('config_language_validation_required', 'Sila pilih sekurang-kurangnya satu bahasa untuk diaktifkan.');
      return $errors;
    }
    
    foreach ($languages as $lang) {
      if (!in_array($lang, SystemConfigConstants::SUPPORTED_LANGUAGES, true)) {
        $errors[] = sprintf($this->tr('config_language_validation_invalid', 'Bahasa "%s" tidak sah. Hanya %s dibenarkan.'), $lang, implode(', ', SystemConfigConstants::SUPPORTED_LANGUAGES));
      }
    }

    if ($defaultLanguage === '') {
      $errors[] = $this->tr('config_language_validation_default_required', 'Sila pilih satu bahasa lalai untuk sistem.');
      return $errors;
    }

    if (!in_array($defaultLanguage, SystemConfigConstants::SUPPORTED_LANGUAGES, true)) {
      $errors[] = sprintf($this->tr('config_language_validation_default_invalid', 'Bahasa lalai "%s" tidak sah.'), $defaultLanguage);
      return $errors;
    }

    if (!in_array($defaultLanguage, $languages, true)) {
      $errors[] = $this->tr('config_language_validation_default_not_active', 'Bahasa lalai mesti berada dalam senarai bahasa aktif.');
    }
    
    return $errors;
  }

  private function validateGeneralSettings(array $data): array {
    $errors = [];
    $allowedSidebarImages = array_map(
      static fn(array $item): string => (string)($item['path'] ?? ''),
      $this->getSidebarSmallImageOptions()
    );

    foreach ($this->getGeneralSettingsWhitelist() as $key => $meta) {
      $value = trim((string)($data[$key] ?? ''));
      $max = (int)($meta['max'] ?? SystemConfigConstants::MAX_STRING_LENGTH);
      $label = $this->getGeneralSettingsFieldLabels()[$key] ?? $key;

      if ($value !== '' && mb_strlen($value) > $max) {
        $errors[] = sprintf(
          $this->tr('config_general_validation_max', '%s terlalu panjang (maksimum %d aksara).'),
          $label,
          $max
        );
      }

      if ($value === '') {
        continue;
      }

      if (($meta['type'] ?? 'string') === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = sprintf(
          $this->tr('config_general_validation_email', '%s mesti alamat emel yang sah.'),
          $label
        );
      }

      if (($meta['type'] ?? 'string') === 'url' && $value !== '#' && !filter_var($value, FILTER_VALIDATE_URL)) {
        $errors[] = sprintf(
          $this->tr('config_general_validation_url', '%s mesti URL yang sah atau #.'),
          $label
        );
      }

      if (($meta['type'] ?? 'string') === 'int') {
        if (!ctype_digit($value)) {
          $errors[] = sprintf(
            $this->tr('config_general_validation_int', '%s mesti nombor bulat yang sah.'),
            $label
          );
          continue;
        }

        $intValue = (int)$value;
        $min = isset($meta['min']) ? (int)$meta['min'] : null;
        $maxInt = isset($meta['max_value']) ? (int)$meta['max_value'] : null;

        if ($min !== null && $maxInt !== null && ($intValue < $min || $intValue > $maxInt)) {
          $errors[] = sprintf(
            $this->tr('config_general_validation_int_range', '%s mesti antara %d hingga %d.'),
            $label,
            $min,
            $maxInt
          );
        }
      }

      if ($key === 'branding.sidebar_user_image' && $value !== '' && !in_array($value, $allowedSidebarImages, true)) {
        $errors[] = sprintf(
          $this->tr('config_general_validation_sidebar_user_image', '%s mesti dipilih daripada senarai imej yang dibenarkan.'),
          $label
        );
      }
    }

    return $errors;
  }

  private function validateAuthSettings(array $data): array {
    $errors = [];
    $labels = $this->getAuthSettingsFieldLabels();

    foreach ($this->getAuthSettingsWhitelist() as $key => $meta) {
      $value = $data[$key] ?? null;
      $type = (string)($meta['type'] ?? 'string');
      $label = $labels[$key] ?? $key;

      if ($type === 'bool' && !auth_raw_bool_is_valid($value)) {
        $errors[] = sprintf(
          $this->tr('config_auth_validation_bool', '%s mesti nilai hidup atau mati yang sah.'),
          $label
        );
      }

      if ($type === 'enum' && !in_array(strtoupper(trim((string)$value)), (array)($meta['allowed'] ?? []), true)) {
        $errors[] = sprintf(
          $this->tr('config_auth_validation_enum', '%s mesti salah satu nilai yang dibenarkan: %s.'),
          $label,
          implode(', ', (array)($meta['allowed'] ?? []))
        );
      }

      if ($type === 'int') {
        $min = (int)($meta['min'] ?? 0);
        $max = (int)($meta['max_value'] ?? PHP_INT_MAX);
        if (!auth_raw_int_is_valid($value, $min, $max)) {
          $errors[] = sprintf(
            $this->tr('config_auth_validation_int_range', '%s mesti nombor antara %d hingga %d.'),
            $label,
            $min,
            $max
          );
        }
      }
    }

    $ssoEnabled = auth_normalize_bool($data['auth.sso_enabled'] ?? null, false);
    if ($ssoEnabled) {
      $siteId = (string)($data['auth.sso_site_id'] ?? '');
      $normalizedSiteId = $this->normalizeAuthSsoSiteId($siteId);
      if ($normalizedSiteId === '') {
        $errors[] = trim($siteId) === ''
          ? $this->tr('config_auth_sso_site_id_required', 'OneID Site ID tidak boleh dibiarkan kosong.')
          : $this->tr('config_auth_sso_site_id_invalid', 'OneID Site ID hanya boleh mengandungi huruf, nombor, garis bawah (_) atau tanda sengkang (-).');
      }

      $idpDomain = (string)($data['auth.sso_idp_domain'] ?? '');
      $trimmedIdpDomain = trim($idpDomain);
      if ($trimmedIdpDomain === '') {
        $errors[] = $this->tr('config_auth_sso_idp_domain_required', 'OneID IdP Domain tidak boleh dibiarkan kosong.');
      } else {
        $normalizedIdpDomain = $this->normalizeAuthSsoIdpDomain($trimmedIdpDomain);
        if ($normalizedIdpDomain === '') {
          $scheme = strtolower((string)(parse_url($trimmedIdpDomain, PHP_URL_SCHEME) ?: ''));
          $errors[] = in_array($scheme, ['http', 'https'], true)
            ? $this->tr('config_auth_sso_idp_domain_invalid', 'OneID IdP Domain mesti dalam format URL yang sah.')
            : $this->tr('config_auth_sso_idp_domain_scheme_invalid', 'OneID IdP Domain mesti menggunakan http:// atau https://');
        }
      }
    }

    $rawConfig = $this->toAuthPolicyRawConfig($data);
    $validation = validate_auth_policy_raw_config($rawConfig);
    foreach ((array)($validation['errors'] ?? []) as $message) {
      $errors[] = (string)$message;
    }

    return [
      'errors' => array_values(array_unique($errors)),
      'warnings' => array_values(array_unique((array)($validation['warnings'] ?? []))),
    ];
  }

  private function validateAiChatbotSettings(array $data): array {
    $errors = [];
    $labels = $this->getAiChatbotFieldLabels();
    $allowedProviders = ['ollama', 'openai', 'gemini', 'grok', 'anthropic', 'openrouter', 'openai_compatible', 'groq'];
    $allowedAccessModes = ['super_admin_only', 'selected_groups', 'all_authenticated'];

    if (!in_array((string)($data['provider'] ?? ''), $allowedProviders, true)) {
      $errors[] = sprintf('%s tidak sah.', $labels['provider'] ?? 'Provider');
    }

    if (!in_array((string)($data['access_mode'] ?? ''), $allowedAccessModes, true)) {
      $errors[] = sprintf('%s tidak sah.', $labels['access_mode'] ?? 'Access mode');
    }

    foreach ($this->getAiChatbotSettingsWhitelist() as $key => $meta) {
      $value = trim((string)($data[$key] ?? ''));
      $type = (string)($meta['type'] ?? 'string');
      $label = $labels[$key] ?? $key;

      if ($type === 'string' && isset($meta['max']) && mb_strlen($value) > (int)$meta['max']) {
        $errors[] = sprintf('%s terlalu panjang. Maksimum %d aksara.', $label, (int)$meta['max']);
      }

      if ($type === 'int') {
        if ($value === '' || !ctype_digit($value)) {
          $errors[] = sprintf('%s mesti nombor positif.', $label);
          continue;
        }

        $number = (int)$value;
        if (isset($meta['min']) && $number < (int)$meta['min']) {
          $errors[] = sprintf('%s mesti sekurang-kurangnya %d.', $label, (int)$meta['min']);
        }
        if (isset($meta['max']) && $number > (int)$meta['max']) {
          $errors[] = sprintf('%s tidak boleh melebihi %d.', $label, (int)$meta['max']);
        }
      }
    }

    $baseUrl = trim((string)($data['base_url'] ?? ''));
    if ($baseUrl !== '' && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
      $errors[] = sprintf('%s mesti URL yang sah.', $labels['base_url'] ?? 'Provider base URL');
    }

    $appUrl = trim((string)($data['app_url'] ?? ''));
    if ($appUrl !== '' && !filter_var($appUrl, FILTER_VALIDATE_URL)) {
      $errors[] = sprintf('%s mesti URL yang sah.', $labels['app_url'] ?? 'App URL');
    }

    return $errors;
  }

  // ---------------------------
  // Emel / Bahasa / Tema / Umum
  // ---------------------------

  public function getGeneralSettingsWhitelist(): array {
    return [
      'site.title' => ['field' => 'site_title', 'type' => 'string', 'max' => 150],
      'site.favicon' => ['field' => 'site_favicon', 'type' => 'string', 'max' => 255],
      'site.default_home' => ['field' => 'site_default_home', 'type' => 'string', 'max' => 255],
      'system.name' => ['field' => 'system_name', 'type' => 'string', 'max' => 150],
      'system.meta_author' => ['field' => 'system_meta_author', 'type' => 'string', 'max' => 150],
      'system.support' => ['field' => 'system_support', 'type' => 'email', 'max' => 255],
      'branding.login_header_logo' => ['field' => 'branding_login_header_logo', 'type' => 'string', 'max' => 255],
      'branding.login_panel_logo' => ['field' => 'branding_login_panel_logo', 'type' => 'string', 'max' => 255],
      'branding.topbar_logo_light' => ['field' => 'branding_topbar_logo_light', 'type' => 'string', 'max' => 255],
      'branding.topbar_logo_dark' => ['field' => 'branding_topbar_logo_dark', 'type' => 'string', 'max' => 255],
      'branding.topbar_logo_sm' => ['field' => 'branding_topbar_logo_sm', 'type' => 'string', 'max' => 255],
      'branding.sidebar_logo' => ['field' => 'branding_sidebar_logo', 'type' => 'string', 'max' => 255],
      'branding.sidebar_user_image' => ['field' => 'branding_sidebar_user_image', 'type' => 'string', 'max' => 255],
      'session.idle_timeout_minutes' => ['field' => 'session_idle_timeout_minutes', 'type' => 'int', 'max' => 3, 'min' => 1, 'max_value' => 240],
      'impersonation.timeout_minutes' => ['field' => 'impersonation_timeout_minutes', 'type' => 'int', 'max' => 3, 'min' => 5, 'max_value' => 240],
      'upload.manual_max_mb' => ['field' => 'upload_manual_max_mb', 'type' => 'int', 'max' => 3, 'min' => 1, 'max_value' => 100],
      'organization.name' => ['field' => 'organization_name', 'type' => 'string', 'max' => 150],
      'organization.short' => ['field' => 'organization_short', 'type' => 'string', 'max' => 50],
      'organization.website' => ['field' => 'organization_website', 'type' => 'url', 'max' => 255],
      'footer.text.ms' => ['field' => 'footer_text_ms', 'type' => 'string', 'max' => 255],
      'footer.text.en' => ['field' => 'footer_text_en', 'type' => 'string', 'max' => 255],
      'mail.system_name' => ['field' => 'mail_system_name', 'type' => 'string', 'max' => 150],
      'mail.default_action_url' => ['field' => 'mail_default_action_url', 'type' => 'url', 'max' => 255],
      'mail.footer_note.ms' => ['field' => 'mail_footer_note_ms', 'type' => 'string', 'max' => 255],
      'mail.footer_note.en' => ['field' => 'mail_footer_note_en', 'type' => 'string', 'max' => 255],
    ];
  }

  public function getAuthSettingsWhitelist(): array {
    return [
      'auth.maintenance_mode' => ['field' => 'auth_maintenance_mode', 'type' => 'bool'],
      'auth.login_enable_staf' => ['field' => 'auth_login_enable_staf', 'type' => 'bool'],
      'auth.login_enable_pelajar' => ['field' => 'auth_login_enable_pelajar', 'type' => 'bool'],
      'auth.login_enable_umum' => ['field' => 'auth_login_enable_umum', 'type' => 'bool'],
      'auth.auto_provision_staf_sso' => ['field' => 'auth_auto_provision_staf_sso', 'type' => 'bool'],
      'auth.auto_provision_pelajar_sso' => ['field' => 'auth_auto_provision_pelajar_sso', 'type' => 'bool'],
      'auth.default_group_staff_code' => ['field' => 'auth_default_group_staff_code', 'type' => 'string'],
      'auth.default_group_student_code' => ['field' => 'auth_default_group_student_code', 'type' => 'string'],
      'auth.password_min_length' => ['field' => 'auth_password_min_length', 'type' => 'int', 'min' => 8, 'max_value' => 128],
      'auth.password_expiry_days' => ['field' => 'auth_password_expiry_days', 'type' => 'int', 'min' => 1, 'max_value' => 365],
      'auth.password_history_count' => ['field' => 'auth_password_history_count', 'type' => 'int', 'min' => 0, 'max_value' => 24],
      'auth.password_reset_token_minutes' => ['field' => 'auth_password_reset_token_minutes', 'type' => 'int', 'min' => 5, 'max_value' => 180],
      'auth.password_require_uppercase' => ['field' => 'auth_password_require_uppercase', 'type' => 'bool'],
      'auth.password_require_lowercase' => ['field' => 'auth_password_require_lowercase', 'type' => 'bool'],
      'auth.password_require_number' => ['field' => 'auth_password_require_number', 'type' => 'bool'],
      'auth.password_require_symbol' => ['field' => 'auth_password_require_symbol', 'type' => 'bool'],
      'auth.password_block_loginid_variants' => ['field' => 'auth_password_block_loginid_variants', 'type' => 'bool'],
      'auth.login_max_attempts' => ['field' => 'auth_login_max_attempts', 'type' => 'int', 'min' => 1, 'max_value' => 10],
      'auth.login_lock_seconds' => ['field' => 'auth_login_lock_seconds', 'type' => 'int', 'min' => 30, 'max_value' => 3600],
      'auth.login_identifier_ip_max_attempts' => ['field' => 'auth_login_identifier_ip_max_attempts', 'type' => 'int', 'min' => 1, 'max_value' => 20],
      'auth.login_identifier_ip_lock_seconds' => ['field' => 'auth_login_identifier_ip_lock_seconds', 'type' => 'int', 'min' => 30, 'max_value' => 3600],
      'auth.login_ip_max_attempts' => ['field' => 'auth_login_ip_max_attempts', 'type' => 'int', 'min' => 1, 'max_value' => 50],
      'auth.login_ip_lock_seconds' => ['field' => 'auth_login_ip_lock_seconds', 'type' => 'int', 'min' => 30, 'max_value' => 3600],
      'auth.sso_enabled' => ['field' => 'auth_sso_enabled', 'type' => 'bool'],
      'auth.sso_site_id' => ['field' => 'auth_sso_site_id', 'type' => 'string'],
      'auth.sso_idp_domain' => ['field' => 'auth_sso_idp_domain', 'type' => 'string'],
      'auth.sso_mode' => ['field' => 'auth_sso_mode', 'type' => 'enum', 'allowed' => ['ALL', 'MANUAL', 'HYBRID']],
      'auth.sso_hybrid_staf' => ['field' => 'auth_sso_hybrid_staf', 'type' => 'enum', 'allowed' => ['SSO', 'MANUAL']],
      'auth.sso_hybrid_pelajar' => ['field' => 'auth_sso_hybrid_pelajar', 'type' => 'enum', 'allowed' => ['SSO', 'MANUAL']],
      'auth.sso_hybrid_umum' => ['field' => 'auth_sso_hybrid_umum', 'type' => 'enum', 'allowed' => ['SSO', 'MANUAL']],
    ];
  }

  public function getAiChatbotSettingsWhitelist(): array {
    return [
      'enabled' => ['field' => 'ai_chatbot_enabled', 'type' => 'bool'],
      'access_mode' => ['field' => 'ai_chatbot_access_mode', 'type' => 'string', 'max' => 64],
      'allowed_groups' => ['field' => 'ai_chatbot_allowed_groups', 'type' => 'string', 'max' => 255],
      'provider' => ['field' => 'ai_chatbot_provider', 'type' => 'string', 'max' => 64],
      'model' => ['field' => 'ai_chatbot_model', 'type' => 'string', 'max' => 191],
      'base_url' => ['field' => 'ai_chatbot_base_url', 'type' => 'string', 'max' => 255],
      'api_key' => ['field' => 'ai_chatbot_api_key', 'type' => 'string', 'max' => 1000],
      'timeout_seconds' => ['field' => 'ai_chatbot_timeout_seconds', 'type' => 'int', 'min' => 1, 'max' => 120],
      'max_input_chars' => ['field' => 'ai_chatbot_max_input_chars', 'type' => 'int', 'min' => 100, 'max' => 20000],
      'max_output_tokens' => ['field' => 'ai_chatbot_max_output_tokens', 'type' => 'int', 'min' => 64, 'max' => 8192],
      'rate_limit_per_minute' => ['field' => 'ai_chatbot_rate_limit_per_minute', 'type' => 'int', 'min' => 1, 'max' => 120],
      'user_daily_request_limit' => ['field' => 'ai_chatbot_user_daily_request_limit', 'type' => 'int', 'min' => 0, 'max' => 10000],
      'global_daily_request_limit' => ['field' => 'ai_chatbot_global_daily_request_limit', 'type' => 'int', 'min' => 0, 'max' => 100000],
      'persist_usage' => ['field' => 'ai_chatbot_persist_usage', 'type' => 'bool'],
      'store_conversations' => ['field' => 'ai_chatbot_store_conversations', 'type' => 'bool'],
      'log_message_content' => ['field' => 'ai_chatbot_log_message_content', 'type' => 'bool'],
      'character_name' => ['field' => 'ai_chatbot_character_name', 'type' => 'string', 'max' => 100],
      'character_avatar' => ['field' => 'ai_chatbot_character_avatar', 'type' => 'string', 'max' => 255],
      'welcome_message' => ['field' => 'ai_chatbot_welcome_message', 'type' => 'string', 'max' => 500],
      'app_url' => ['field' => 'ai_chatbot_app_url', 'type' => 'string', 'max' => 255],
      'app_title' => ['field' => 'ai_chatbot_app_title', 'type' => 'string', 'max' => 150],
    ];
  }

  private function getGeneralSettingsFieldLabels(): array {
    return [
      'site.title' => $this->tr('config_general_site_title', 'Site Title'),
      'site.favicon' => $this->tr('config_general_site_favicon', 'Favicon Path'),
      'site.default_home' => $this->tr('config_general_site_default_home', 'Default Home Route'),
      'system.name' => $this->tr('config_general_system_name', 'System Name'),
      'system.meta_author' => $this->tr('config_general_meta_author', 'Meta Author'),
      'system.support' => $this->tr('config_general_support_email', 'Support Email'),
      'branding.login_header_logo' => $this->tr('config_general_branding_login_header_logo', 'Login Header Logo'),
      'branding.login_panel_logo' => $this->tr('config_general_branding_login_panel_logo', 'Login Panel Logo'),
      'branding.topbar_logo_light' => $this->tr('config_general_branding_topbar_logo_light', 'Topbar Logo Light'),
      'branding.topbar_logo_dark' => $this->tr('config_general_branding_topbar_logo_dark', 'Topbar Logo Dark'),
      'branding.topbar_logo_sm' => $this->tr('config_general_branding_topbar_logo_sm', 'Topbar Logo Small'),
      'branding.sidebar_logo' => $this->tr('config_general_branding_sidebar_logo', 'Sidebar Logo'),
      'branding.sidebar_user_image' => $this->tr('config_general_branding_sidebar_user_image', 'Sidebar User Image'),
      'session.idle_timeout_minutes' => $this->tr('config_general_session_idle_timeout_minutes', 'Idle Timeout (Minutes)'),
      'impersonation.timeout_minutes' => $this->tr('config_general_impersonation_timeout_minutes', 'View As Timeout (Minutes)'),
      'upload.manual_max_mb' => $this->tr('config_general_upload_manual_max_mb', 'Manual Upload Max Size (MB)'),
      'organization.name' => $this->tr('config_general_org_name', 'Organization Name'),
      'organization.short' => $this->tr('config_general_org_short', 'Organization Short Code'),
      'organization.website' => $this->tr('config_general_org_website', 'Organization Website'),
      'footer.text.ms' => $this->tr('config_general_footer_text_ms', 'Footer Text (BM)'),
      'footer.text.en' => $this->tr('config_general_footer_text_en', 'Footer Text (EN)'),
      'mail.system_name' => $this->tr('config_general_mail_system_name', 'Mail System Name'),
      'mail.default_action_url' => $this->tr('config_general_mail_action_url', 'Default Action URL'),
      'mail.footer_note.ms' => $this->tr('config_general_mail_footer_note_ms', 'Mail Footer Note (BM)'),
      'mail.footer_note.en' => $this->tr('config_general_mail_footer_note_en', 'Mail Footer Note (EN)'),
    ];
  }

  private function getAuthSettingsFieldLabels(): array {
    return [
      'auth.maintenance_mode' => $this->tr('config_auth_maintenance_mode', 'Maintenance Mode'),
      'auth.login_enable_staf' => $this->tr('config_auth_login_enable_staf', 'Enable Staff Login'),
      'auth.login_enable_pelajar' => $this->tr('config_auth_login_enable_pelajar', 'Enable Student Login'),
      'auth.login_enable_umum' => $this->tr('config_auth_login_enable_umum', 'Enable Public Login'),
      'auth.auto_provision_staf_sso' => $this->tr('config_auth_auto_provision_staf_sso', 'Enable Staff SSO Auto Provision'),
      'auth.auto_provision_pelajar_sso' => $this->tr('config_auth_auto_provision_pelajar_sso', 'Enable Student SSO Auto Provision'),
      'auth.default_group_staff_code' => $this->tr('config_auth_default_group_staff_code', 'Default Staff Group Code'),
      'auth.default_group_student_code' => $this->tr('config_auth_default_group_student_code', 'Default Student Group Code'),
      'auth.password_min_length' => $this->tr('config_auth_password_min_length', 'Minimum Password Length'),
      'auth.password_expiry_days' => $this->tr('config_auth_password_expiry_days', 'Password Expiry (Days)'),
      'auth.password_history_count' => $this->tr('config_auth_password_history_count', 'Password History Count'),
      'auth.password_reset_token_minutes' => $this->tr('config_auth_password_reset_token_minutes', 'Reset Link Expiry (Minutes)'),
      'auth.password_require_uppercase' => $this->tr('config_auth_password_require_uppercase', 'Require Uppercase Letter'),
      'auth.password_require_lowercase' => $this->tr('config_auth_password_require_lowercase', 'Require Lowercase Letter'),
      'auth.password_require_number' => $this->tr('config_auth_password_require_number', 'Require Number'),
      'auth.password_require_symbol' => $this->tr('config_auth_password_require_symbol', 'Require Symbol'),
      'auth.password_block_loginid_variants' => $this->tr('config_auth_password_block_loginid_variants', 'Block Login ID Variants'),
      'auth.login_max_attempts' => $this->tr('config_auth_login_max_attempts', 'Maximum Failed Attempts'),
      'auth.login_lock_seconds' => $this->tr('config_auth_login_lock_seconds', 'Lockout Duration (Seconds)'),
      'auth.login_identifier_ip_max_attempts' => $this->tr('config_auth_login_identifier_ip_max_attempts', 'Login ID + IP Failed Attempts'),
      'auth.login_identifier_ip_lock_seconds' => $this->tr('config_auth_login_identifier_ip_lock_seconds', 'Login ID + IP Lockout Duration (Seconds)'),
      'auth.login_ip_max_attempts' => $this->tr('config_auth_login_ip_max_attempts', 'IP Failed Attempts'),
      'auth.login_ip_lock_seconds' => $this->tr('config_auth_login_ip_lock_seconds', 'IP Lockout Duration (Seconds)'),
      'auth.sso_enabled' => $this->tr('config_auth_sso_enabled', 'Enable SSO'),
      'auth.sso_site_id' => $this->tr('config_auth_sso_site_id', 'OneID Site ID'),
      'auth.sso_idp_domain' => $this->tr('config_auth_sso_idp_domain', 'OneID IdP Domain'),
      'auth.sso_mode' => $this->tr('config_auth_sso_mode', 'SSO Mode'),
      'auth.sso_hybrid_staf' => $this->tr('config_auth_sso_hybrid_staf', 'Staff Login Method'),
      'auth.sso_hybrid_pelajar' => $this->tr('config_auth_sso_hybrid_pelajar', 'Student Login Method'),
      'auth.sso_hybrid_umum' => $this->tr('config_auth_sso_hybrid_umum', 'Public Login Method'),
    ];
  }

  private function getEmailFieldLabels(): array {
    return [
      'mail_driver' => $this->tr('config_tab_emel_driver', 'Mail Driver'),
      'mail_host' => $this->tr('config_tab_emel_host', 'Mail Host'),
      'mail_port' => $this->tr('config_tab_emel_port', 'Port'),
      'mail_username' => $this->tr('config_tab_emel_account_emel', 'Email Account (Username)'),
      'mail_encryption' => $this->tr('config_tab_emel_encryption', 'Encryption'),
      'mail_from_address' => $this->tr('config_tab_emel_from', 'Email From'),
      'mail_from_name' => $this->tr('config_tab_emel_from_name', 'Sender Name'),
    ];
  }

  private function getThemeFieldLabels(): array {
    return [
      'layoutMode' => $this->tr('config_tab_tema_komponen_layout', 'Layout Mode'),
      'topbarColor' => $this->tr('config_tab_tema_komponen_topbar', 'Topbar Color'),
      'sidebarColor' => $this->tr('config_tab_tema_komponen_sidebar', 'Sidebar Color'),
      'topbarCustomSeed' => $this->tr('config_theme_topbar_custom_seed', 'Topbar Custom Colour'),
      'sidebarCustomSeed' => $this->tr('config_theme_sidebar_custom_seed', 'Sidebar Custom Colour'),
    ];
  }

  private function getAiChatbotFieldLabels(): array {
    return [
      'enabled' => 'Status aktif',
      'access_mode' => 'Access mode',
      'allowed_groups' => 'Allowed groups',
      'provider' => 'Provider',
      'model' => 'Model',
      'base_url' => 'Provider base URL',
      'api_key' => 'API key',
      'timeout_seconds' => 'Timeout',
      'max_input_chars' => 'Max input chars',
      'max_output_tokens' => 'Max output tokens',
      'rate_limit_per_minute' => 'Rate limit per minute',
      'user_daily_request_limit' => 'User daily limit',
      'global_daily_request_limit' => 'Global daily limit',
      'persist_usage' => 'Persist usage',
      'store_conversations' => 'Store conversations',
      'log_message_content' => 'Log message content',
      'character_name' => 'Character name',
      'character_avatar' => 'Character avatar',
      'welcome_message' => 'Welcome message',
      'app_url' => 'App URL',
      'app_title' => 'App title',
    ];
  }

  private function getEmailChangeSummary(array $oldSettings, array $newSettings): array {
    $labels = [];
    $fieldLabels = $this->getEmailFieldLabels();

    foreach ($newSettings as $key => $value) {
      if ((string)($oldSettings[$key] ?? '') !== (string)$value) {
        $labels[] = $fieldLabels[$key] ?? $key;
      }
    }

    return $labels;
  }

  private function getThemeChangeSummary(array $oldTheme, array $newTheme): array {
    $labels = [];
    $fieldLabels = $this->getThemeFieldLabels();

    foreach ($newTheme as $key => $value) {
      if ((string)($oldTheme[$key] ?? '') !== (string)$value) {
        $labels[] = $fieldLabels[$key] ?? $key;
      }
    }

    return $labels;
  }

  private function getGeneralSettingsChangeSummary(array $oldSettings, array $newSettings): array {
    $labels = [];
    $fieldLabels = $this->getGeneralSettingsFieldLabels();

    foreach ($newSettings as $key => $value) {
      if ((string)($oldSettings[$key] ?? '') !== (string)$value) {
        $labels[] = $fieldLabels[$key] ?? $key;
      }
    }

    return $labels;
  }

  private function getAiChatbotChangeSummary(array $oldSettings, array $newSettings): array {
    $labels = [];
    $fieldLabels = $this->getAiChatbotFieldLabels();

    foreach ($this->getAiChatbotSettingsWhitelist() as $key => $_meta) {
      if ((string)($oldSettings[$key] ?? '') !== (string)($newSettings[$key] ?? '')) {
        $labels[] = $fieldLabels[$key] ?? $key;
      }
    }

    return $labels;
  }

  private function auditAiChatbotUpdate(array $oldSettings, array $newSettings): void {
    if (!function_exists('audit_event')) return;

    try {
      $changedFieldLabels = $this->getAiChatbotChangeSummary($oldSettings, $newSettings);
      $summary = !empty($changedFieldLabels)
        ? implode(', ', $changedFieldLabels)
        : 'tiada perubahan medan';

      $nama = $this->profile['f_nama'] ?? null;
      $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
      $actorLabel = function_exists('audit_format_actor_label')
        ? audit_format_actor_label($nama, $nostaf)
        : $nama;

      $message = function_exists('audit_format_message')
        ? audit_format_message(sprintf('Tetapan AI Chatbot dikemas kini (%d medan): %s', count($changedFieldLabels), $summary), $actorLabel)
        : sprintf('Tetapan AI Chatbot dikemas kini (%d medan): %s', count($changedFieldLabels), $summary);

      audit_event([
        'event_type' => SystemConfigConstants::AUDIT_EVENT_AI_CHATBOT_UPDATE,
        'severity' => 'WARN',
        'outcome' => 'SUCCESS',
        'target_type' => SystemConfigConstants::AUDIT_TARGET_AI_CHATBOT,
        'target_id' => SystemConfigConstants::CONFIG_GROUP_AI_CHATBOT,
        'target_label' => 'AI Chatbot Settings',
        'message' => $message,
        'user_id' => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
        'actor_label' => $actorLabel,
        'meta' => [
          'changed_field_labels' => $changedFieldLabels,
          'changed_count' => count($changedFieldLabels),
          'change_summary' => $summary,
        ],
      ]);
    } catch (Throwable $e) {
      error_log('[TetapanSistem] AI chatbot audit logging failed: ' . $e->getMessage());
    }
  }

  private function auditAdditionalConnectionAction(string $action, string $code, array $meta = []): void {
    if (!function_exists('audit_event')) return;

    try {
      $normalizedAction = strtoupper(trim($action));
      $isFailureAction = str_contains($normalizedAction, 'FAILED') || str_contains($normalizedAction, 'ERROR');
      $nama = $this->profile['f_nama'] ?? null;
      $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
      $actorLabel = function_exists('audit_format_actor_label')
        ? audit_format_actor_label($nama, $nostaf)
        : $nama;

      $summary = sprintf('Additional DB [%s] action=%s', $code, $normalizedAction);
      $message = function_exists('audit_format_message')
        ? audit_format_message('Tetapan sambungan tambahan dikemas kini: ' . $summary, $actorLabel)
        : ('Tetapan sambungan tambahan dikemas kini: ' . $summary);

      audit_event([
        'event_type' => $normalizedAction,
        'severity' => $isFailureAction ? 'ERROR' : 'WARN',
        'outcome' => $isFailureAction ? 'FAILURE' : 'SUCCESS',
        'target_type' => SystemConfigConstants::AUDIT_TARGET_DB,
        'target_id' => $code,
        'target_label' => 'Additional Database Connection',
        'message' => $message,
        'user_id' => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
        'actor_label' => $actorLabel,
        'meta' => array_merge([
          'connection_code' => $code,
          'action' => $normalizedAction,
        ], $meta),
      ]);
    } catch (Throwable $e) {
      error_log('[TetapanSistem] Additional DB audit logging failed: ' . $this->safeAdditionalDatabaseError($e));
    }
  }

  private function tr(string $key, string $fallback): string {
    $translated = __('' . $key);
    if (!is_string($translated) || $translated === '' || $translated === $key) {
      return $fallback;
    }
    return $translated;
  }

  public function getGeneralSettings(): array {
    $settings = [];
    foreach ($this->getGeneralSettingsWhitelist() as $key => $meta) {
      if ($key === 'branding.sidebar_user_image') {
        $settings[$key] = (string) app_config($key, $this->getDefaultSidebarUserImagePath());
        continue;
      }
      if ($key === 'footer.text.ms' || $key === 'footer.text.en') {
        $settings[$key] = (string) app_config($key, app_config('footer.text', ''));
        continue;
      }
      if ($key === 'mail.footer_note.ms' || $key === 'mail.footer_note.en') {
        $settings[$key] = (string) app_config($key, app_config('mail.footer_note', ''));
        continue;
      }
      $settings[$key] = (string) app_config($key, '');
    }
    return $settings;
  }

  public function getAuthSettings(): array {
    $settings = get_auth_policy_config();
    $ssoConfig = function_exists('sso_shared_config') ? sso_shared_config() : [];
    $settings['integration'] = [
      'site_id' => (string)($ssoConfig['site_id'] ?? ''),
      'idp_domain' => (string)($ssoConfig['idp_domain'] ?? ''),
    ];

    return $settings;
  }

  public function saveGeneralSettings(array $data): bool {
    return $this->configModel->saveGroup(SystemConfigConstants::CONFIG_GROUP_APP_SETTINGS, $data);
  }

  public function saveAuthSettings(array $data): bool {
    return $this->configModel->saveGroup(SystemConfigConstants::CONFIG_GROUP_APP_SETTINGS, $data);
  }

  private function normalizeAuthSettingsForStorage(array $data): array {
    return [
      'auth.maintenance_mode' => auth_normalize_bool($data['auth.maintenance_mode'] ?? null, false) ? '1' : '0',
      'auth.login_enable_staf' => auth_normalize_bool($data['auth.login_enable_staf'] ?? null, true) ? '1' : '0',
      'auth.login_enable_pelajar' => auth_normalize_bool($data['auth.login_enable_pelajar'] ?? null, true) ? '1' : '0',
      'auth.login_enable_umum' => auth_normalize_bool($data['auth.login_enable_umum'] ?? null, true) ? '1' : '0',
      'auth.auto_provision_staf_sso' => auth_normalize_bool($data['auth.auto_provision_staf_sso'] ?? null, false) ? '1' : '0',
      'auth.auto_provision_pelajar_sso' => auth_normalize_bool($data['auth.auto_provision_pelajar_sso'] ?? null, false) ? '1' : '0',
      'auth.default_group_staff_code' => auth_normalize_group_code($data['auth.default_group_staff_code'] ?? null, 'ADM-STAF'),
      'auth.default_group_student_code' => auth_normalize_group_code($data['auth.default_group_student_code'] ?? null, 'ADM-STUDENT'),
      'auth.password_min_length' => (string)auth_normalize_int($data['auth.password_min_length'] ?? null, 8, 8, 128),
      'auth.password_expiry_days' => (string)auth_normalize_int($data['auth.password_expiry_days'] ?? null, 90, 1, 365),
      'auth.password_history_count' => (string)auth_normalize_int($data['auth.password_history_count'] ?? null, 5, 0, 24),
      'auth.password_reset_token_minutes' => (string)auth_normalize_int($data['auth.password_reset_token_minutes'] ?? null, 30, 5, 180),
      'auth.password_require_uppercase' => auth_normalize_bool($data['auth.password_require_uppercase'] ?? null, true) ? '1' : '0',
      'auth.password_require_lowercase' => auth_normalize_bool($data['auth.password_require_lowercase'] ?? null, true) ? '1' : '0',
      'auth.password_require_number' => auth_normalize_bool($data['auth.password_require_number'] ?? null, true) ? '1' : '0',
      'auth.password_require_symbol' => auth_normalize_bool($data['auth.password_require_symbol'] ?? null, false) ? '1' : '0',
      'auth.password_block_loginid_variants' => auth_normalize_bool($data['auth.password_block_loginid_variants'] ?? null, true) ? '1' : '0',
      'auth.login_max_attempts' => (string)auth_normalize_int($data['auth.login_max_attempts'] ?? null, 3, 1, 10),
      'auth.login_lock_seconds' => (string)auth_normalize_int($data['auth.login_lock_seconds'] ?? null, 60, 30, 3600),
      'auth.login_identifier_ip_max_attempts' => (string)auth_normalize_int($data['auth.login_identifier_ip_max_attempts'] ?? null, 5, 1, 20),
      'auth.login_identifier_ip_lock_seconds' => (string)auth_normalize_int($data['auth.login_identifier_ip_lock_seconds'] ?? null, 300, 30, 3600),
      'auth.login_ip_max_attempts' => (string)auth_normalize_int($data['auth.login_ip_max_attempts'] ?? null, 10, 1, 50),
      'auth.login_ip_lock_seconds' => (string)auth_normalize_int($data['auth.login_ip_lock_seconds'] ?? null, 300, 30, 3600),
      'auth.sso_enabled' => auth_normalize_bool($data['auth.sso_enabled'] ?? null, false) ? '1' : '0',
      'auth.sso_site_id' => $this->normalizeAuthSsoSiteId((string)($data['auth.sso_site_id'] ?? '')),
      'auth.sso_idp_domain' => $this->normalizeAuthSsoIdpDomain((string)($data['auth.sso_idp_domain'] ?? '')),
      'auth.sso_mode' => auth_normalize_sso_mode($data['auth.sso_mode'] ?? null, 'MANUAL'),
      'auth.sso_hybrid_staf' => auth_normalize_hybrid_mode($data['auth.sso_hybrid_staf'] ?? null, 'SSO'),
      'auth.sso_hybrid_pelajar' => auth_normalize_hybrid_mode($data['auth.sso_hybrid_pelajar'] ?? null, 'SSO'),
      'auth.sso_hybrid_umum' => auth_normalize_hybrid_mode($data['auth.sso_hybrid_umum'] ?? null, 'MANUAL'),
    ];
  }

  private function toAuthPolicyRawConfig(array $data): array {
    return [
      'maintenance_mode' => $data['auth.maintenance_mode'] ?? null,
      'login_enable_staf' => $data['auth.login_enable_staf'] ?? null,
      'login_enable_pelajar' => $data['auth.login_enable_pelajar'] ?? null,
      'login_enable_umum' => $data['auth.login_enable_umum'] ?? null,
      'auto_provision_staf_sso' => $data['auth.auto_provision_staf_sso'] ?? null,
      'auto_provision_pelajar_sso' => $data['auth.auto_provision_pelajar_sso'] ?? null,
      'default_group_staff_code' => $data['auth.default_group_staff_code'] ?? null,
      'default_group_student_code' => $data['auth.default_group_student_code'] ?? null,
      'password_min_length' => $data['auth.password_min_length'] ?? null,
      'password_expiry_days' => $data['auth.password_expiry_days'] ?? null,
      'password_history_count' => $data['auth.password_history_count'] ?? null,
      'password_reset_token_minutes' => $data['auth.password_reset_token_minutes'] ?? null,
      'password_require_uppercase' => $data['auth.password_require_uppercase'] ?? null,
      'password_require_lowercase' => $data['auth.password_require_lowercase'] ?? null,
      'password_require_number' => $data['auth.password_require_number'] ?? null,
      'password_require_symbol' => $data['auth.password_require_symbol'] ?? null,
      'password_block_loginid_variants' => $data['auth.password_block_loginid_variants'] ?? null,
      'login_max_attempts' => $data['auth.login_max_attempts'] ?? null,
      'login_lock_seconds' => $data['auth.login_lock_seconds'] ?? null,
      'login_identifier_ip_max_attempts' => $data['auth.login_identifier_ip_max_attempts'] ?? null,
      'login_identifier_ip_lock_seconds' => $data['auth.login_identifier_ip_lock_seconds'] ?? null,
      'login_ip_max_attempts' => $data['auth.login_ip_max_attempts'] ?? null,
      'login_ip_lock_seconds' => $data['auth.login_ip_lock_seconds'] ?? null,
      'sso_enabled' => $data['auth.sso_enabled'] ?? null,
      'sso_mode' => $data['auth.sso_mode'] ?? null,
      'sso_hybrid_staf' => $data['auth.sso_hybrid_staf'] ?? null,
      'sso_hybrid_pelajar' => $data['auth.sso_hybrid_pelajar'] ?? null,
      'sso_hybrid_umum' => $data['auth.sso_hybrid_umum'] ?? null,
    ];
  }

  private function flattenAuthSettingsForComparison(array $settings): array {
    $sso = (array)($settings['sso'] ?? []);
    $hybrid = (array)($sso['hybrid'] ?? []);
    $provisioning = (array)($settings['provisioning'] ?? []);
    $integration = (array)($settings['integration'] ?? []);
    $password = (array)($settings['password'] ?? []);
    $loginSecurity = (array)($settings['login_security'] ?? []);

    return [
      'auth.maintenance_mode' => !empty($settings['maintenance_mode']) ? '1' : '0',
      'auth.login_enable_staf' => !empty($settings['categories']['staf']) ? '1' : '0',
      'auth.login_enable_pelajar' => !empty($settings['categories']['pelajar']) ? '1' : '0',
      'auth.login_enable_umum' => !empty($settings['categories']['umum']) ? '1' : '0',
      'auth.auto_provision_staf_sso' => !empty($provisioning['staf_sso_enabled']) ? '1' : '0',
      'auth.auto_provision_pelajar_sso' => !empty($provisioning['pelajar_sso_enabled']) ? '1' : '0',
      'auth.default_group_staff_code' => (string)($provisioning['default_group_staff_code'] ?? 'ADM-STAF'),
      'auth.default_group_student_code' => (string)($provisioning['default_group_student_code'] ?? 'ADM-STUDENT'),
      'auth.password_min_length' => (string)($password['min_length'] ?? 8),
      'auth.password_expiry_days' => (string)($password['expiry_days'] ?? 90),
      'auth.password_history_count' => (string)($password['history_count'] ?? 5),
      'auth.password_reset_token_minutes' => (string)($password['reset_token_minutes'] ?? 30),
      'auth.password_require_uppercase' => !empty($password['require_uppercase']) ? '1' : '0',
      'auth.password_require_lowercase' => !empty($password['require_lowercase']) ? '1' : '0',
      'auth.password_require_number' => !empty($password['require_number']) ? '1' : '0',
      'auth.password_require_symbol' => !empty($password['require_symbol']) ? '1' : '0',
      'auth.password_block_loginid_variants' => !empty($password['block_loginid_variants']) ? '1' : '0',
      'auth.login_max_attempts' => (string)($loginSecurity['max_attempts'] ?? 3),
      'auth.login_lock_seconds' => (string)($loginSecurity['lock_seconds'] ?? 60),
      'auth.login_identifier_ip_max_attempts' => (string)($loginSecurity['identifier_ip_max_attempts'] ?? 5),
      'auth.login_identifier_ip_lock_seconds' => (string)($loginSecurity['identifier_ip_lock_seconds'] ?? 300),
      'auth.login_ip_max_attempts' => (string)($loginSecurity['ip_max_attempts'] ?? 10),
      'auth.login_ip_lock_seconds' => (string)($loginSecurity['ip_lock_seconds'] ?? 300),
      'auth.sso_enabled' => !empty($sso['enabled']) ? '1' : '0',
      'auth.sso_site_id' => (string)($integration['site_id'] ?? ''),
      'auth.sso_idp_domain' => (string)($integration['idp_domain'] ?? ''),
      'auth.sso_mode' => (string)($sso['mode'] ?? 'MANUAL'),
      'auth.sso_hybrid_staf' => (string)($hybrid['staf'] ?? 'SSO'),
      'auth.sso_hybrid_pelajar' => (string)($hybrid['pelajar'] ?? 'SSO'),
      'auth.sso_hybrid_umum' => (string)($hybrid['umum'] ?? 'MANUAL'),
    ];
  }

  private function getAuthSettingsChangeSummary(array $oldSettings, array $newSettings): array {
    $labels = [];
    $fieldLabels = $this->getAuthSettingsFieldLabels();
    $oldFlat = $this->flattenAuthSettingsForComparison($oldSettings);
    $newFlat = $this->flattenAuthSettingsForComparison($newSettings);

    foreach ($newFlat as $key => $value) {
      if ((string)($oldFlat[$key] ?? '') !== (string)$value) {
        $labels[] = $fieldLabels[$key] ?? $key;
      }
    }

    return $labels;
  }

  private function getDefaultSidebarUserImagePath(): string {
    return 'assets/images/small/small-5.jpg';
  }

  public function getSidebarSmallImageOptions(): array {
    $dir = __DIR__ . '/../assets/images/small';
    $options = [];

    if (is_dir($dir)) {
      $files = scandir($dir);
      if (is_array($files)) {
        foreach ($files as $file) {
          if ($file === '.' || $file === '..') {
            continue;
          }

          if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file)) {
            continue;
          }

          $options[] = [
            'file' => $file,
            'path' => 'assets/images/small/' . $file,
            'label' => pathinfo($file, PATHINFO_FILENAME),
          ];
        }
      }
    }

    usort($options, static fn(array $a, array $b): int => strnatcasecmp((string)$a['file'], (string)$b['file']));

    if (empty($options)) {
      $fallback = $this->getDefaultSidebarUserImagePath();
      $options[] = [
        'file' => basename($fallback),
        'path' => $fallback,
        'label' => pathinfo($fallback, PATHINFO_FILENAME),
      ];
    }

    return $options;
  }

  public function getEmailSettings(): array {
    return $this->configModel->getGroup('email');
  }

  public function saveEmailSettings(array $data): bool {
    return $this->configModel->saveGroup('email', $data);
  }

  public function getLanguageList(): array {
    $dir = __DIR__ . '/../lang/';
    $languages = [];
    
    try {
      if (!is_dir($dir)) {
        error_log("[TetapanSistem] Language directory not found: {$dir}");
        return ['list' => [], 'active' => [], 'default' => SystemConfigConstants::DEFAULT_LANGUAGE];
      }
      
      $files = scandir($dir);
      if ($files === false) {
        error_log("[TetapanSistem] Failed to scan language directory: {$dir}");
        return ['list' => [], 'active' => [], 'default' => SystemConfigConstants::DEFAULT_LANGUAGE];
      }
      
      foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
          $code = basename($file, '.php');
          try {
            $languages[$code] = include $dir . $file;
          } catch (\Throwable $e) {
            error_log("[TetapanSistem] Failed to include language file {$file}: " . $e->getMessage());
          }
        }
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Error reading language files: " . $e->getMessage());
      return ['list' => [], 'active' => []];
    }
    
    try {
      $aktif = $this->configModel->getBahasaAktif();
      $default = $this->configModel->getDefaultBahasa($aktif[0] ?? SystemConfigConstants::DEFAULT_LANGUAGE);
      if (!in_array($default, $aktif, true)) {
        $default = $aktif[0] ?? SystemConfigConstants::DEFAULT_LANGUAGE;
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Failed to get active languages: " . $e->getMessage());
      $aktif = [];
      $default = SystemConfigConstants::DEFAULT_LANGUAGE;
    }
    
    return ['list'=>array_keys($languages),'active'=>$aktif,'default'=>$default];
  }

  // ---------------------------
  // Audit Logging
  // ---------------------------

  /**
   * Audit email settings update
   */
  private function auditEmailUpdate(array $oldSettings, array $newSettings): void {
    if (!function_exists('audit_event')) return;
    
    try {
      // ✅ FIX: Format actor_label dengan nostaf full: "[nama] (nostaf)"
      $nama = $this->profile['f_nama'] ?? null;
      $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
      $actorLabel = null;
      if (function_exists('audit_format_actor_label')) {
        $actorLabel = audit_format_actor_label($nama, $nostaf);
      } else {
        // Fallback: guna nama sahaja jika helper tidak available
        $actorLabel = $nama;
      }
      
      $changedFields = [];
      $changedFieldLabels = [];
      $fieldLabels = $this->getEmailFieldLabels();
      foreach ($newSettings as $key => $value) {
        if ((string)($oldSettings[$key] ?? '') !== (string)$value) {
          $changedFields[] = $key;
          $changedFieldLabels[] = $fieldLabels[$key] ?? $key;
        }
      }
      $summary = !empty($changedFieldLabels)
        ? implode(', ', $changedFieldLabels)
        : $this->tr('config_email_audit_no_changes', 'tiada perubahan medan');
      $message = audit_format_message(
        sprintf(
          $this->tr('config_email_audit_message', 'Tetapan emel dikemas kini (%d medan): %s'),
          count($changedFieldLabels),
          $summary
        ),
        $actorLabel
      );
      
      $eventId = audit_event([
        'event_type'  => 'UPDATE',
        'severity'    => 'INFO',
        'outcome'     => 'SUCCESS',
        'target_type' => SystemConfigConstants::AUDIT_TARGET_EMAIL,
        'target_id'   => 'email_config',
        'target_label' => $this->tr('emel_title', 'Email Settings'),
        'message'     => $message,
        'user_id'     => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
        'actor_label' => $actorLabel,
        'meta'        => [
          'changed_fields' => $changedFields,
          'changed_field_labels' => $changedFieldLabels,
          'changed_count' => count($changedFieldLabels),
          'change_summary' => $summary
        ]
      ]);
      
      if ($eventId) {
        $changeSetId = audit_begin_change(
          $eventId,
          SystemConfigConstants::AUDIT_TARGET_EMAIL,
          'email_config',
          null,
          [
            'form' => 'emailSettings',
            'action' => 'update',
            'changed_fields' => $changedFields,
            'changed_field_labels' => $changedFieldLabels,
            'changed_count' => count($changedFieldLabels),
            'change_summary' => $summary,
            'source_page' => strtok($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/'), '?') ?: '/'
          ]
        );
        if ($changeSetId) {
          foreach ($newSettings as $key => $value) {
            $oldValue = $oldSettings[$key] ?? null;
            if ((string)$oldValue !== (string)$value) {
              $sensitive = ($key === 'mail_password');
              audit_change($changeSetId, $key, $oldValue, $value, 'string', $sensitive);
            }
          }
        }
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Audit logging failed: " . $e->getMessage());
    }
  }

  /**
   * Audit database update
   */
  private function auditDatabaseUpdate(?string $oldBase, ?string $newBase, ?string $oldEnvironment = null, ?string $newEnvironment = null, ?string $oldOperationalMode = null, ?string $newOperationalMode = null): void {
    if (!function_exists('audit_event')) return;
    
    try {
      // ✅ FIX: Format actor_label dengan nostaf full: "[nama] (nostaf)"
      $nama = $this->profile['f_nama'] ?? null;
      $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
      $actorLabel = null;
      if (function_exists('audit_format_actor_label')) {
        $actorLabel = audit_format_actor_label($nama, $nostaf);
      } else {
        // Fallback: guna nama sahaja jika helper tidak available
        $actorLabel = $nama;
      }
      
      $oldLogical = $this->baseToLogical((string)$oldBase) ?? (string)$oldBase;
      $newLogical = $this->baseToLogical((string)$newBase) ?? (string)$newBase;
      $summary = sprintf(
        $this->tr('config_db_audit_summary', 'Staff DB: %s -> %s | Environment: %s -> %s | Mode: %s -> %s'),
        $oldLogical ?: '-',
        $newLogical ?: '-',
        $oldEnvironment ?: '-',
        $newEnvironment ?: '-',
        $oldOperationalMode ?: '-',
        $newOperationalMode ?: '-'
      );
      $message = audit_format_message(
        sprintf($this->tr('config_db_audit_message', 'Tetapan pangkalan data dikemas kini: %s'), $summary),
        $actorLabel
      );

      $auditPayload = [
        'event_type'  => 'UPDATE',
        'severity'    => 'WARN',
        'outcome'     => 'SUCCESS',
        'target_type' => SystemConfigConstants::AUDIT_TARGET_DB,
        'target_id'   => 'active_database',
        'target_label' => $this->tr('config_tab_db', 'Database'),
        'message'     => $message,
        'user_id'     => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
        'actor_label' => $actorLabel,
        'meta'        => [
          'old_base' => $oldBase,
          'new_base' => $newBase,
          'old_logical' => $oldLogical,
          'new_logical' => $newLogical,
          'old_environment' => $oldEnvironment,
          'new_environment' => $newEnvironment,
          'old_operational_mode' => $oldOperationalMode,
          'new_operational_mode' => $newOperationalMode,
          'change_summary' => $summary
        ]
      ];

      $eventId = audit_event($auditPayload);
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Audit logging failed: " . $e->getMessage());
    }
  }

  /**
   * Audit theme update
   */
  private function auditThemeUpdate(array $oldTheme, array $newTheme): void {
    if (!function_exists('audit_event')) return;
    
    try {
      // ✅ FIX: Format actor_label dengan nostaf full: "[nama] (nostaf)"
      $nama = $this->profile['f_nama'] ?? null;
      $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
      $actorLabel = null;
      if (function_exists('audit_format_actor_label')) {
        $actorLabel = audit_format_actor_label($nama, $nostaf);
      } else {
        // Fallback: guna nama sahaja jika helper tidak available
        $actorLabel = $nama;
      }
      
      $changedFields = [];
      $changedFieldLabels = [];
      $fieldLabels = $this->getThemeFieldLabels();
      foreach ($newTheme as $key => $value) {
        if ((string)($oldTheme[$key] ?? '') !== (string)$value) {
          $changedFields[] = $key;
          $changedFieldLabels[] = $fieldLabels[$key] ?? $key;
        }
      }
      $summary = !empty($changedFieldLabels)
        ? implode(', ', $changedFieldLabels)
        : $this->tr('config_theme_audit_no_changes', 'tiada perubahan medan');
      $message = audit_format_message(
        sprintf(
          $this->tr('config_theme_audit_message', 'Tetapan tema dikemas kini (%d medan): %s'),
          count($changedFieldLabels),
          $summary
        ),
        $actorLabel
      );
      
      $eventId = audit_event([
        'event_type'  => 'UPDATE',
        'severity'    => 'INFO',
        'outcome'     => 'SUCCESS',
        'target_type' => SystemConfigConstants::AUDIT_TARGET_THEME,
        'target_id'   => 'default_theme',
        'target_label' => $this->tr('tema_title', 'Theme Settings'),
        'message'     => $message,
        'user_id'     => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
        'actor_label' => $actorLabel,
        'meta' => [
          'changed_fields' => $changedFields,
          'changed_field_labels' => $changedFieldLabels,
          'changed_count' => count($changedFieldLabels),
          'change_summary' => $summary
        ]
      ]);
      
      if ($eventId) {
        $changeSetId = audit_begin_change(
          $eventId,
          SystemConfigConstants::AUDIT_TARGET_THEME,
          'default_theme',
          null,
          [
            'form' => 'themeSettings',
            'action' => 'update',
            'changed_fields' => $changedFields,
            'changed_field_labels' => $changedFieldLabels,
            'changed_count' => count($changedFieldLabels),
            'change_summary' => $summary,
            'source_page' => strtok($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/'), '?') ?: '/'
          ]
        );
        if ($changeSetId) {
          foreach ($newTheme as $key => $value) {
            $oldValue = $oldTheme[$key] ?? null;
            if ((string)$oldValue !== (string)$value) {
              audit_change($changeSetId, $key, $oldValue, $value, 'string', false);
            }
          }
        }
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Audit logging failed: " . $e->getMessage());
    }
  }

  /**
   * Audit language update
   */
  private function auditLanguageUpdate(array $oldLanguages, array $newLanguages, ?string $oldDefaultLanguage, string $newDefaultLanguage): void {
    if (!function_exists('audit_event')) return;
    
    try {
      // ✅ FIX: Format actor_label dengan nostaf full: "[nama] (nostaf)"
      $nama = $this->profile['f_nama'] ?? null;
      $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
      $actorLabel = null;
      if (function_exists('audit_format_actor_label')) {
        $actorLabel = audit_format_actor_label($nama, $nostaf);
      } else {
        // Fallback: guna nama sahaja jika helper tidak available
        $actorLabel = $nama;
      }
      
      $summary = sprintf(
        $this->tr('config_language_audit_message_summary', 'Aktif: %s | Lalai: %s'),
        implode(', ', $newLanguages),
        $newDefaultLanguage
      );
      $message = audit_format_message(
        sprintf($this->tr('config_language_audit_message', 'Tetapan bahasa dikemas kini: %s'), $summary),
        $actorLabel
      );
      
      audit_event([
        'event_type'  => 'UPDATE',
        'severity'    => 'INFO',
        'outcome'     => 'SUCCESS',
        'target_type' => SystemConfigConstants::AUDIT_TARGET_LANGUAGE,
        'target_id'   => 'active_languages',
        'target_label' => $this->tr('bahasa_title', 'Language Settings'),
        'message'     => $message,
        'user_id'     => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
        'actor_label' => $actorLabel,
        'meta'        => [
          'old_languages' => $oldLanguages,
          'new_languages' => $newLanguages,
          'old_default_language' => $oldDefaultLanguage,
          'new_default_language' => $newDefaultLanguage,
          'added' => array_diff($newLanguages, $oldLanguages),
          'removed' => array_diff($oldLanguages, $newLanguages),
          'change_summary' => $summary
        ]
      ]);
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] Audit logging failed: " . $e->getMessage());
    }
  }

  private function auditGeneralSettingsUpdate(array $oldSettings, array $newSettings): void {
    if (!function_exists('audit_event')) return;

    try {
      $nama = $this->profile['f_nama'] ?? null;
      $nostaf = $this->profile['f_nopekerja'] ?? $_SESSION['f_nopekerja'] ?? null;
      $actorLabel = function_exists('audit_format_actor_label')
        ? audit_format_actor_label($nama, $nostaf)
        : $nama;

      $changedFields = [];
      $changedFieldLabels = [];
      $fieldLabels = $this->getGeneralSettingsFieldLabels();
      foreach ($newSettings as $key => $value) {
        if ((string)($oldSettings[$key] ?? '') !== (string)$value) {
          $changedFields[] = $key;
          $changedFieldLabels[] = $fieldLabels[$key] ?? $key;
        }
      }

      $summary = !empty($changedFieldLabels)
        ? implode(', ', $changedFieldLabels)
        : $this->tr('config_general_audit_no_changes', 'no field changes');
      $baseMessage = sprintf(
        $this->tr('config_general_audit_message', 'General settings updated (%d fields): %s'),
        count($changedFieldLabels),
        $summary
      );
      $message = audit_format_message($baseMessage, $actorLabel);

      $eventId = audit_event([
        'event_type'  => 'UPDATE',
        'severity'    => 'INFO',
        'outcome'     => 'SUCCESS',
        'target_type' => SystemConfigConstants::AUDIT_TARGET_GENERAL,
        'target_id'   => 'app_settings',
        'target_label' => $this->tr('config_general_success_title', 'General Settings'),
        'message'     => $message,
        'user_id'     => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
        'actor_label' => $actorLabel,
        'meta'        => [
          'changed_fields' => $changedFields,
          'changed_field_labels' => $changedFieldLabels,
          'changed_count' => count($changedFieldLabels),
          'change_summary' => $summary
        ]
      ]);

      if ($eventId && function_exists('audit_begin_change')) {
        $changeSetId = audit_begin_change(
          $eventId,
          SystemConfigConstants::AUDIT_TARGET_GENERAL,
          'app_settings',
          null,
          [
            'form' => 'generalSettings',
            'action' => 'update',
            'changed_fields' => $changedFields,
            'changed_field_labels' => $changedFieldLabels,
            'changed_count' => count($changedFieldLabels),
            'change_summary' => $summary,
            'source_page' => strtok($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/'), '?') ?: '/'
          ]
        );

        if ($changeSetId && function_exists('audit_change')) {
          foreach ($newSettings as $key => $value) {
            $oldValue = $oldSettings[$key] ?? null;
            if ((string)$oldValue !== (string)$value) {
              audit_change($changeSetId, $key, $oldValue, $value, 'string', false);
            }
          }
        }
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] General settings audit failed: " . $e->getMessage());
    }
  }

  private function auditAuthSettingsUpdate(array $oldSettings, array $newSettings): void {
    if (!function_exists('audit_event')) return;

    try {
      $nama = $this->profile['f_nama'] ?? null;
      $loginId = $this->profile['f_loginID'] ?? ($_SESSION['f_loginID'] ?? null);
      $nostaf = $this->profile['f_nopekerja'] ?? ($_SESSION['f_nopekerja'] ?? $_SESSION['f_stafID'] ?? null);
      $actorLabel = function_exists('audit_format_actor_label')
        ? audit_format_actor_label($nama, $loginId ?: $nostaf)
        : ($nama ?: (string)$loginId);

      $changedFieldLabels = $this->getAuthSettingsChangeSummary($oldSettings, $newSettings);
      $changedFields = [];
      $fieldLabels = $this->getAuthSettingsFieldLabels();
      $oldFlat = $this->flattenAuthSettingsForComparison($oldSettings);
      $newFlat = $this->flattenAuthSettingsForComparison($newSettings);
      foreach ($newFlat as $key => $value) {
        if ((string)($oldFlat[$key] ?? '') !== (string)$value) {
          $changedFields[] = $key;
        }
      }

      $summary = !empty($changedFieldLabels)
        ? implode(', ', $changedFieldLabels)
        : $this->tr('config_auth_audit_no_changes', 'no field changes');
      $baseMessage = sprintf(
        $this->tr('config_auth_audit_message', 'Login policy settings updated (%d fields): %s'),
        count($changedFieldLabels),
        $summary
      );
      $message = function_exists('audit_format_message')
        ? audit_format_message($baseMessage, $actorLabel)
        : $baseMessage;

      audit_event([
        'module' => 'SYSTEM_CONFIGURATION',
        'action' => 'AUTH_POLICY_UPDATE',
        'message' => $message,
        'status' => 'success',
        'user_id' => (int)($_SESSION['f_userID'] ?? 0),
        'meta' => [
          'changed_fields' => $changedFields,
          'changed_field_labels' => $changedFieldLabels,
          'old' => $oldFlat,
          'new' => $newFlat,
          'warnings' => array_values((array)($newSettings['warnings'] ?? [])),
          'valid' => (bool)($newSettings['valid'] ?? false),
        ],
      ]);
    } catch (\Throwable $e) {
      error_log('[TetapanSistem] Auth settings audit failed: ' . $e->getMessage());
    }
  }

  // ---------------------------
  // Micro-cache invalidation
  // ---------------------------
  private function tsCacheSupported(): bool {
    return function_exists('apcu_fetch') || (function_exists('apcu_enabled') && apcu_enabled());
  }

  private function tsCacheKey(string $name): string {
    return 'tetapan-sistem:v1:' . $name;
  }

  private function tsCacheDir(): string {
    $cacheDir = realpath(__DIR__ . '/../cache/ts') ?: (__DIR__ . '/../cache/ts');
    if (!is_dir($cacheDir)) {
      @mkdir($cacheDir, 0777, true);
    }
    return $cacheDir;
  }

  private function getTsCache(string $name, int $ttl = 600): mixed {
    $key = $this->tsCacheKey($name);

    if ($this->tsCacheSupported() && function_exists('apcu_fetch')) {
      try {
        $ok = false;
        $value = apcu_fetch($key, $ok);
        if ($ok) {
          return $value;
        }
      } catch (\Throwable $e) {
        error_log("[TetapanSistem] APCu fetch failed for key {$key}: " . $e->getMessage());
      }
    }

    $file = rtrim($this->tsCacheDir(), DIRECTORY_SEPARATOR) . '/ts-cache-' . md5($key) . '.json';
    if (is_file($file) && filemtime($file) > time() - $ttl) {
      try {
        $raw = file_get_contents($file);
        if ($raw !== false) {
          $decoded = json_decode($raw, true);
          if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
          }
        }
      } catch (\Throwable $e) {
        error_log("[TetapanSistem] File cache read failed for key {$key}: " . $e->getMessage());
      }
    }

    return null;
  }

  private function setTsCache(string $name, mixed $value, int $ttl = 600): void {
    $key = $this->tsCacheKey($name);

    if ($this->tsCacheSupported() && function_exists('apcu_store')) {
      try {
        apcu_store($key, $value, $ttl);
        return;
      } catch (\Throwable $e) {
        error_log("[TetapanSistem] APCu store failed for key {$key}: " . $e->getMessage());
      }
    }

    $file = rtrim($this->tsCacheDir(), DIRECTORY_SEPARATOR) . '/ts-cache-' . md5($key) . '.json';
    try {
      $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($json !== false) {
        file_put_contents($file, $json, LOCK_EX);
        if (file_exists($file)) {
          @chmod($file, 0600);
        }
      }
    } catch (\Throwable $e) {
      error_log("[TetapanSistem] File cache write failed for key {$key}: " . $e->getMessage());
    }
  }

  private function getCachedValue(string $name, int $ttl, callable $resolver): array {
    $value = $this->getTsCache($name, $ttl);
    if (is_array($value)) {
      return $value;
    }

    $value = $resolver();
    $this->setTsCache($name, $value, $ttl);
    return $value;
  }

  private function invalidateTsCache(string $name): void {
    $key = $this->tsCacheKey($name);
    
    // Try to delete from APCu
    if (function_exists('apcu_delete')) {
      try {
        apcu_delete($key);
      } catch (\Throwable $e) {
        error_log("[TetapanSistem] APCu delete failed: " . $e->getMessage());
      }
    }
    
    // Delete file cache (gunakan cache dir projek)
    $file = rtrim($this->tsCacheDir(), DIRECTORY_SEPARATOR) . '/ts-cache-' . md5($key) . '.json';
    if (is_file($file)) {
      try {
        unlink($file);
      } catch (\Throwable $e) {
        error_log("[TetapanSistem] File delete failed: " . $e->getMessage());
      }
    }

    // app_config() menyimpan override DB dalam static cache sepanjang request.
    // Tanpa reset ini, respons AJAX selepas save boleh membawa nilai lama dan
    // menyebabkan form kelihatan kembali ke keadaan sebelum disimpan.
    if (function_exists('app_config_reset_runtime_cache')) {
      app_config_reset_runtime_cache();
    }
  }
}
