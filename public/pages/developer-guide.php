<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_once __DIR__ . '/../ajax/_helpers.php';
require_once __DIR__ . '/../classes/Database.php';

$pdoPerm = Database::getInstance('mysql')->getConnection();
ensurePageGroupManagePermission($pdoPerm, (string)(__('developerGuide_forbidden') ?: 'You do not have permission to view the developer guide.'));

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function dg(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === $key || $value === null || $value === '') ? $fallback : (string)$value;
}

$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? '1');
$PAGE_TITLE = dg('developerGuide_page_title', 'Developer Guide');
$notificationDeveloperSamples = require __DIR__ . '/../includes/notification-developer-samples.php';

$guideTabs = [
    'overview' => ['icon' => 'ri-compass-3-line', 'label' => dg('developerGuide_tab_overview', 'Overview')],
    'page' => ['icon' => 'ri-pages-line', 'label' => dg('developerGuide_tab_page', 'Page Skeleton')],
    'service' => ['icon' => 'ri-service-line', 'label' => dg('developerGuide_tab_service', 'Service Pattern')],
    'database' => ['icon' => 'ri-database-2-line', 'label' => dg('developerGuide_tab_database', 'Database')],
    'ajax' => ['icon' => 'ri-terminal-box-line', 'label' => dg('developerGuide_tab_ajax', 'AJAX & CSRF')],
    'notification' => ['icon' => 'ri-notification-3-line', 'label' => dg('developerGuide_tab_notification', 'Notification')],
    'language' => ['icon' => 'ri-translate-2', 'label' => dg('developerGuide_tab_language', 'Language')],
    'menu' => ['icon' => 'ri-menu-2-line', 'label' => dg('developerGuide_tab_menu', 'Menu & Access')],
    'audit' => ['icon' => 'ri-shield-check-line', 'label' => dg('developerGuide_tab_audit', 'Audit')],
    'email' => ['icon' => 'ri-mail-send-line', 'label' => dg('developerGuide_tab_email', 'Email')],
    'ui' => ['icon' => 'ri-layout-4-line', 'label' => dg('developerGuide_tab_ui', 'UI Patterns')],
    'checklist' => ['icon' => 'ri-list-check-3', 'label' => dg('developerGuide_tab_checklist', 'Checklist')],
];
$guideUi = [
    'rules' => dg('developerGuide_ui_rules', 'Rules'),
    'references' => dg('developerGuide_ui_references', 'References'),
    'core_boundary' => dg('developerGuide_ui_core_boundary', 'Core Boundary'),
    'page_file' => dg('developerGuide_ui_page_file', 'Page File'),
    'controller' => dg('developerGuide_ui_controller', 'Controller'),
    'separation' => dg('developerGuide_ui_separation', 'Separation'),
    'sample' => dg('developerGuide_ui_sample', 'Sample'),
    'main_mysql' => dg('developerGuide_ui_main_mysql', 'Main MySQL'),
    'additional_db' => dg('developerGuide_ui_additional_db', 'Additional DB'),
    'transaction' => dg('developerGuide_ui_transaction', 'Transaction'),
    'endpoint' => dg('developerGuide_ui_endpoint', 'Endpoint'),
    'external_service' => dg('developerGuide_ui_external_service', 'External Service'),
    'event' => dg('developerGuide_ui_event', 'Event'),
    'workflow' => dg('developerGuide_ui_workflow', 'Workflow'),
    'admin_samples' => dg('developerGuide_ui_admin_samples', 'Admin Samples'),
    'custom_keys' => dg('developerGuide_ui_custom_keys', 'Custom Keys'),
    'setup_flow' => dg('developerGuide_ui_setup_flow', 'Setup Flow'),
    'reference' => dg('developerGuide_ui_reference', 'Reference'),
    'audit_event' => dg('developerGuide_ui_audit_event', 'Audit Event'),
    'view_as' => dg('developerGuide_ui_view_as', 'View As'),
    'template' => dg('developerGuide_ui_template', 'Template'),
    'components' => dg('developerGuide_ui_components', 'Components'),
    'boundary' => dg('developerGuide_ui_boundary', 'Boundary'),
    'before_coding' => dg('developerGuide_ui_before_coding', 'Before Coding'),
    'before_handover' => dg('developerGuide_ui_before_handover', 'Before Handover'),
];

$docLinks = [
    ['icon' => 'ri-notification-3-line', 'title' => 'Notification Standard', 'path' => 'docs/notification-developer-standard-2026-05-04.md'],
    ['icon' => 'ri-code-box-line', 'title' => 'Notification Examples', 'path' => 'docs/notification-developer-examples-2026-05-03.md'],
    ['icon' => 'ri-database-2-line', 'title' => 'Additional Database', 'path' => 'docs/additional-database-platform-implementation-2026-04-27.md'],
    ['icon' => 'ri-translate-2', 'title' => 'Language Core/Custom', 'path' => 'docs/language-core-custom-split-audit-2026-05-02.md'],
    ['icon' => 'ri-menu-2-line', 'title' => 'Sidebar Subgroup', 'path' => 'docs/sidebar-menu-subgroup-blueprint-2026-05-06.md'],
    ['icon' => 'ri-layout-grid-line', 'title' => 'Page Generator Roadmap', 'path' => 'docs/page-template-generator-roadmap-2026-03-27.md'],
    ['icon' => 'ri-shield-check-line', 'title' => 'Core File Protection', 'path' => 'docs/core-file-protection-standard-2026-06-04.md'],
    ['icon' => 'ri-file-shield-2-line', 'title' => 'Protected Files Registry', 'path' => 'docs/core-protected-files.md'],
    ['icon' => 'ri-cloud-line', 'title' => 'External Service Failures', 'path' => 'docs/external-service-failure-handling-2026-06-23.md'],
];

$coreBoundaries = [
    ['area' => 'Core file protection', 'core' => 'IQS FRAMEWORK CORE FILE marker', 'use' => 'Read for reference only. Put project changes in generated/custom files or approved extension points.'],
    ['area' => 'Runtime bootstrap', 'core' => 'public/includes/init.php', 'use' => 'Call require_login(), __(), base_url(), and shared helpers.'],
    ['area' => 'Sidebar/topbar', 'core' => 'public/includes/sidebar.php, public/includes/topbar.php', 'use' => 'Register menus and access through kumpulan-pengguna.php.'],
    ['area' => 'Database runtime', 'core' => 'public/classes/Database*.php', 'use' => 'Call Database::pdoAdditional(), pdoSybaseStaff(), or main MySQL helper.'],
    ['area' => 'Notification', 'core' => 'public/classes/Notification*.php', 'use' => 'Call NotificationPublisher or NotificationWorkflowService.'],
    ['area' => 'Audit', 'core' => 'public/classes/AuditLogger.php', 'use' => 'Call audit_event(), audit_begin_change(), and audit_change().'],
    ['area' => 'Language', 'core' => 'public/lang/core/*.php', 'use' => 'Add project keys to public/lang/custom/*.php.'],
];

$samples = [
    'page-basic' => [
        'title' => 'Authenticated Page Skeleton',
        'code' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$lang = (string)($_SESSION['lang'] ?? 'ms');
$PAGE_TITLE = __('myModule_page_title');
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php include __DIR__ . '/../includes/head.php'; ?>
</head>
<body data-layout="vertical" data-sidebar-size="default" class="loading">
<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="content-page">
    <div class="content">
      <div class="container-fluid">
        <!-- Page content here -->
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/script.php'; ?>
</body>
</html>
PHP,
    ],
    'controller-basic' => [
        'title' => 'Controller Standard',
        'code' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';

final class MyModuleController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getInstance('mysql')->getConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getSummary(): array
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM my_table');
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0];
    }
}
PHP,
    ],
    'service-repository' => [
        'title' => 'Service + Repository Split',
        'code' => <<<'PHP'
final class MyRecordRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM my_record WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

final class MyRecordService
{
    public function __construct(private MyRecordRepository $records) {}

    public function view(int $id): array
    {
        $record = $this->records->findById($id);
        if (!$record) {
            throw new RuntimeException('Record not found.');
        }
        return $record;
    }
}
PHP,
    ],
    'ajax-basic' => [
        'title' => 'AJAX Endpoint Standard',
        'code' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_page_access('pages/my-module.php');
require_once __DIR__ . '/_helpers.php';

checkRateLimit('my_module_save', 30, 60);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonErrorResponse('Method not allowed.', 405);
}

validateCsrfToken();

try {
    $pdo = Database::getInstance('mysql')->getConnection();
    $name = trim((string)($_POST['name'] ?? ''));

    if ($name === '') {
        jsonErrorResponse('Name is required.', 422);
    }

    $stmt = $pdo->prepare('INSERT INTO my_table (name) VALUES (:name)');
    $stmt->execute([':name' => $name]);

    jsonSuccessResponse(['id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    error_log('[my-module] ' . $e->getMessage());
    jsonErrorResponse('Unable to save record.', 500);
}
PHP,
    ],
    'ajax-external-service' => [
        'title' => 'External Service Failure Handling',
        'code' => <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();
require_page_access('pages/my-module.php');
require_once __DIR__ . '/_helpers.php';

try {
    $client = new ExternalHttpClient('PaymentGateway', 15);
    $response = $client->postJson(
        'https://provider.example/api/lookup',
        ['reference_no' => $referenceNo],
        ['Authorization' => 'Bearer ' . $apiKey],
        15
    );

    $data = $response->json();
    if ($data === []) {
        throw new ExternalServiceInvalidResponseException(
            'PaymentGateway returned invalid JSON.',
            'PaymentGateway',
            'https://provider.example/api/lookup',
            $response->statusCode()
        );
    }

    jsonSuccessResponse(['provider_data' => $data]);
} catch (ExternalServiceException $e) {
    jsonExceptionResponse($e, 'Perkhidmatan luaran tidak tersedia buat masa ini.', [
        'endpoint' => 'my-module-provider-lookup',
    ]);
} catch (Throwable $e) {
    error_log('[my-module-provider-lookup] ' . $e->getMessage());
    jsonErrorResponse('Ralat sistem semasa memproses permintaan.', 500);
}
PHP,
    ],
    'database-transaction' => [
        'title' => 'Transaction Boundary',
        'code' => <<<'PHP'
$pdo = Database::getInstance('mysql')->getConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE my_record SET status = :status WHERE id = :id');
    $stmt->execute([':status' => 'approved', ':id' => $recordId]);

    $pdo->commit();

    audit_event([
        'event_type' => 'MY_RECORD_APPROVED',
        'outcome' => 'SUCCESS',
        'severity' => 'INFO',
        'message' => 'Record approved after transaction commit.',
        'target_type' => 'my_record',
        'target_id' => (string)$recordId,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
PHP,
    ],
    'database-main' => [
        'title' => 'Main MySQL Access',
        'code' => <<<'PHP'
$pdo = Database::getInstance('mysql')->getConnection();

$stmt = $pdo->prepare('SELECT * FROM my_table WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
PHP,
    ],
    'frontend-fetch' => [
        'title' => 'Frontend Fetch Standard',
        'code' => <<<'JS'
async function saveRecord(payload) {
  const form = new FormData();
  form.set('csrf_token', window.csrfToken || '');
  Object.keys(payload).forEach((key) => form.set(key, payload[key]));

  const response = await fetch('../ajax/my-module-save.php', {
    method: 'POST',
    body: form,
    credentials: 'same-origin'
  });

  const data = await response.json();
  if (!response.ok || data.success !== true) {
    throw new Error(data.message || 'Save failed.');
  }
  return data;
}
JS,
    ],
    'datatable-ajax' => [
        'title' => 'DataTables AJAX Response Shape',
        'code' => <<<'PHP'
// ajax/my-module-list.php
require_once __DIR__ . '/../includes/init.php';
require_login();
require_page_access('pages/my-module.php');
require_once __DIR__ . '/_helpers.php';

$pdo = Database::getInstance('mysql')->getConnection();
$rows = $pdo->query('SELECT id, name, status FROM my_table ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

jsonSuccessResponse([
    'data' => array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'status' => (string)$row['status'],
    ], $rows),
]);
PHP,
    ],
    'database-additional' => [
        'title' => 'Additional Database Access',
        'code' => <<<'PHP'
try {
    $external = Database::pdoAdditional('dbx_reporting', 'production');
    $stmt = $external->query('SELECT TOP 20 * FROM dbo.ReferenceTable');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[additional-db:dbx_reporting] ' . $e->getMessage());
    $rows = [];
}
PHP,
    ],
    'notification-event' => [
        'title' => 'Event Notification',
        'code' => <<<'PHP'
require_once __DIR__ . '/../classes/NotificationPublisher.php';

NotificationPublisher::default()->publish([
    'event_code' => 'module.record.updated',
    'module_code' => 'MY_MODULE',
    'type' => 'event',
    'category' => 'module',
    'severity' => 'success',
    'priority' => 'normal',
    'title_ms' => 'Rekod Dikemaskini',
    'body_ms' => 'Rekod ' . $referenceNo . ' telah dikemaskini.',
    'source_type' => 'my_record',
    'source_id' => (string)$recordId,
    'audience' => [
        'login_ids' => [$targetLoginId],
    ],
    'dedupe_key' => 'my_record:' . $recordId . ':updated',
], [
    'dedupe' => 'update',
]);
PHP,
    ],
    'notification-workflow' => [
        'title' => 'Workflow Task Notification',
        'code' => <<<'PHP'
require_once __DIR__ . '/../classes/NotificationWorkflowService.php';

$workflow = NotificationWorkflowService::default();

$workflow->completeSourceStep('application', (string)$applicationId, 'officer_review');

$workflow->publishTask([
    'event_code' => 'application.pending.hod_approval',
    'module_code' => 'APPLICATION',
    'source_type' => 'application',
    'source_id' => (string)$applicationId,
    'title_ms' => 'Permohonan Menunggu Kelulusan',
    'body_ms' => 'Permohonan ' . $referenceNo . ' memerlukan kelulusan.',
    'action_url' => 'pages/application-approval.php?id=' . urlencode((string)$applicationId),
    'action_label_ms' => 'Semak Permohonan',
    'due_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
    'dedupe_key' => 'application:' . $applicationId . ':hod_approval',
    'audience' => [
        'resolved_login_ids' => $approverLoginIds,
    ],
], [
    'dedupe' => 'update',
]);
PHP,
    ],
    'language-custom' => [
        'title' => 'Project Language Keys',
        'code' => <<<'PHP'
// public/lang/custom/ms.php
return [
    'myModule_page_title' => 'Modul Saya',
    'myModule_save_success' => 'Rekod berjaya disimpan.',
];

// public/lang/custom/en.php
return [
    'myModule_page_title' => 'My Module',
    'myModule_save_success' => 'Record saved successfully.',
];

// Usage
echo __('myModule_page_title');
PHP,
    ],
    'audit-event' => [
        'title' => 'Audit Event',
        'code' => <<<'PHP'
audit_event([
    'event_type' => 'MY_MODULE_RECORD_UPDATE',
    'outcome' => 'SUCCESS',
    'severity' => 'INFO',
    'message' => 'Record updated by module workflow.',
    'target_type' => 'my_record',
    'target_id' => (string)$recordId,
    'meta' => [
        'reference_no' => $referenceNo,
    ],
]);
PHP,
    ],
    'impersonation-context' => [
        'title' => 'View As: Real Actor vs Effective User',
        'code' => <<<'PHP'
$actor = function_exists('impersonation_current_actor_context')
    ? impersonation_current_actor_context()
    : ['login_id' => $_SESSION['f_loginID'] ?? ''];

$effectiveUser = function_exists('impersonation_current_effective_user_context')
    ? impersonation_current_effective_user_context()
    : ['login_id' => $_SESSION['f_loginID'] ?? ''];

audit_event([
    'event_type' => 'MY_MODULE_SUPPORT_ACTION',
    'outcome' => 'SUCCESS',
    'severity' => 'INFO',
    'message' => 'Support action completed.',
    'target_type' => 'my_record',
    'target_id' => (string)$recordId,
    'meta' => [
        'actor_login_id' => $actor['login_id'] ?? null,
        'effective_login_id' => $effectiveUser['login_id'] ?? null,
        'is_view_as' => function_exists('impersonation_is_active') && impersonation_is_active(),
    ],
]);

if (function_exists('impersonation_should_mask_sensitive_data')
    && impersonation_should_mask_sensitive_data('api_key')) {
    $apiKeyForDisplay = '********';
}
PHP,
    ],
    'email-template' => [
        'title' => 'Email Template Direction',
        'code' => <<<'PHP'
// Prefer DB-managed templates from template-emel.php.
// Keep subject/body in the template registry, not hardcoded in business pages.
// Use project placeholders and pass variables from your service/controller.

$variables = [
    'reference_no' => $referenceNo,
    'applicant_name' => $applicantName,
    'action_url' => base_url('pages/my-approval.php?id=' . urlencode((string)$id)),
];
PHP,
    ],
];

function renderCodeCard(string $sampleId, array $samples): void
{
    if (!isset($samples[$sampleId])) {
        return;
    }
    $sample = $samples[$sampleId];
    $code = (string)$sample['code'];
    ?>
    <div class="dg-code-card">
        <div class="dg-code-header">
            <div>
                <span class="dg-code-kicker">Sample Code</span>
                <h6><?= h((string)$sample['title']) ?></h6>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary dg-copy-btn" aria-label="<?= h(dg('developerGuide_copy_code', 'Copy code sample')) ?>">
                <i class="ri-file-copy-line me-1"></i><?= h(dg('developerGuide_copy', 'Copy')) ?>
            </button>
        </div>
        <pre><code><?= h($code) ?></code></pre>
    </div>
    <?php
}

function renderNotificationSampleCards(array $notificationDeveloperSamples): void
{
    foreach ($notificationDeveloperSamples as $sample) {
        $code = (string)($sample['code'] ?? '');
        ?>
        <div class="dg-code-card">
            <div class="dg-code-header">
                <div>
                    <span class="dg-code-kicker">Notification Sample</span>
                    <h6><?= h((string)($sample['title'] ?? 'Notification Sample')) ?></h6>
                    <?php if (!empty($sample['description'])): ?>
                        <p class="mb-0 text-muted small"><?= h((string)$sample['description']) ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary dg-copy-btn" aria-label="<?= h(dg('developerGuide_copy_code', 'Copy code sample')) ?>">
                    <i class="ri-file-copy-line me-1"></i><?= h(dg('developerGuide_copy', 'Copy')) ?>
                </button>
            </div>
            <pre><code><?= h($code) ?></code></pre>
        </div>
        <?php
    }
}
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
    <?php
    $NEED_DATERANGE = false;
    $NEED_VECTORMAP = false;
    $NEED_DATATABLES = false;
    $NEED_SELECT2 = false;
    include __DIR__ . '/../includes/head.php';
    ?>
    <meta name="referrer" content="no-referrer">
    <style>
        .developer-guide-shell { width: 100%; }
        .dg-hero {
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(37,99,235,.08), rgba(20,184,166,.08));
            padding: 1.35rem;
        }
        .dg-hero h4 { margin: 0; font-weight: 800; color: #0f172a; }
        .dg-hero p { margin: .45rem 0 0; color: #64748b; max-width: 920px; }
        .dg-tab-card, .dg-panel, .dg-rule-card, .dg-code-card {
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15,23,42,.04);
        }
        .dg-tab-card { padding: .8rem; position: sticky; top: 90px; }
        .dg-tab-card .nav-link {
            display: flex;
            align-items: center;
            gap: .55rem;
            border-radius: 7px;
            color: #475569;
            font-weight: 700;
            padding: .68rem .75rem;
        }
        .dg-tab-card .nav-link.active {
            background: linear-gradient(135deg, #2563eb, #14b8a6);
            color: #fff;
        }
        .dg-subtabs {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            margin: 1rem 0;
            padding-bottom: .75rem;
            border-bottom: 1px solid rgba(15,23,42,.08);
        }
        .dg-subtabs .nav-link {
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 999px;
            background: #f8fafc;
            color: #475569;
            font-weight: 800;
            font-size: .82rem;
            padding: .42rem .75rem;
        }
        .dg-subtabs .nav-link.active {
            border-color: rgba(37,99,235,.28);
            background: rgba(37,99,235,.1);
            color: #1d4ed8;
        }
        .dg-panel { padding: 1.15rem; }
        .dg-panel-title { display: flex; align-items: center; gap: .55rem; margin-bottom: .35rem; }
        .dg-panel-title h5 { margin: 0; font-weight: 800; }
        .dg-panel-title i { color: #2563eb; font-size: 1.25rem; }
        .dg-muted { color: #64748b; }
        .dg-rule-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        .dg-rule-card { padding: .95rem; }
        .dg-rule-card h6 { margin-bottom: .4rem; font-weight: 800; }
        .dg-rule-card p { margin-bottom: 0; color: #64748b; font-size: .9rem; }
        .dg-doc-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .dg-doc-card {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 8px;
            padding: .85rem;
            background: #f8fafc;
        }
        .dg-doc-card i { color: #2563eb; font-size: 1.25rem; line-height: 1.2; }
        .dg-doc-card h6 { margin: 0 0 .2rem; font-weight: 800; }
        .dg-doc-card code {
            display: block;
            white-space: normal;
            color: #64748b;
            background: transparent;
            padding: 0;
            font-size: .78rem;
        }
        .dg-boundary-table {
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 8px;
            overflow: hidden;
        }
        .dg-boundary-row {
            display: grid;
            grid-template-columns: 22% 34% 44%;
            border-top: 1px solid rgba(15,23,42,.08);
        }
        .dg-boundary-row:first-child { border-top: 0; }
        .dg-boundary-row > div { padding: .75rem .85rem; font-size: .88rem; }
        .dg-boundary-head { background: #f8fafc; color: #475569; font-weight: 800; text-transform: uppercase; font-size: .75rem; letter-spacing: .04em; }
        .dg-pattern-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .dg-step-card {
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 8px;
            padding: .95rem;
            background: #fff;
        }
        .dg-step-card .dg-step-no {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: rgba(37,99,235,.1);
            color: #1d4ed8;
            font-weight: 800;
            margin-bottom: .55rem;
        }
        .dg-step-card h6 { margin-bottom: .35rem; font-weight: 800; }
        .dg-step-card p { margin: 0; color: #64748b; font-size: .9rem; }
        .dg-list { padding-left: 1.1rem; margin-bottom: 0; }
        .dg-list li { margin-bottom: .45rem; }
        .dg-code-card { overflow: hidden; margin-top: 1rem; }
        .dg-code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .85rem 1rem;
            border-bottom: 1px solid rgba(15,23,42,.08);
            background: #f8fafc;
        }
        .dg-code-kicker { display: block; color: #64748b; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 800; }
        .dg-code-header h6 { margin: .1rem 0 0; font-weight: 800; }
        .dg-code-card pre {
            margin: 0;
            padding: 1rem;
            max-height: 430px;
            overflow: auto;
            background: #0f172a;
            color: #e2e8f0;
            font-size: .83rem;
            line-height: 1.55;
        }
        .dg-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border: 1px solid rgba(37,99,235,.18);
            background: rgba(37,99,235,.08);
            color: #1d4ed8;
            border-radius: 999px;
            padding: .3rem .55rem;
            font-weight: 800;
            font-size: .75rem;
        }
        .dg-callout {
            border-left: 4px solid #14b8a6;
            background: rgba(20,184,166,.08);
            border-radius: 8px;
            padding: .85rem 1rem;
            color: #334155;
        }
        html[data-bs-theme="dark"] .dg-hero,
        html[data-bs-theme="dark"] .dg-tab-card,
        html[data-bs-theme="dark"] .dg-panel,
        html[data-bs-theme="dark"] .dg-rule-card,
        html[data-bs-theme="dark"] .dg-step-card,
        html[data-bs-theme="dark"] .dg-doc-card,
        html[data-bs-theme="dark"] .dg-code-card {
            background: #111827;
            border-color: rgba(148,163,184,.18);
        }
        html[data-bs-theme="dark"] .dg-hero h4,
        html[data-bs-theme="dark"] .dg-panel-title h5,
        html[data-bs-theme="dark"] .dg-rule-card h6,
        html[data-bs-theme="dark"] .dg-step-card h6,
        html[data-bs-theme="dark"] .dg-doc-card h6,
        html[data-bs-theme="dark"] .dg-code-header h6 { color: #f8fafc; }
        html[data-bs-theme="dark"] .dg-code-header,
        html[data-bs-theme="dark"] .dg-boundary-head { background: #0f172a; }
        html[data-bs-theme="dark"] .dg-boundary-table,
        html[data-bs-theme="dark"] .dg-boundary-row { border-color: rgba(148,163,184,.18); }
        html[data-bs-theme="dark"] .dg-subtabs { border-color: rgba(148,163,184,.18); }
        html[data-bs-theme="dark"] .dg-subtabs .nav-link {
            background: #0f172a;
            border-color: rgba(148,163,184,.18);
            color: #cbd5e1;
        }
        html[data-bs-theme="dark"] .dg-subtabs .nav-link.active {
            background: rgba(37,99,235,.22);
            border-color: rgba(96,165,250,.35);
            color: #bfdbfe;
        }
        @media (max-width: 991.98px) {
            .dg-tab-card { position: static; }
            .dg-rule-grid, .dg-doc-grid, .dg-pattern-grid { grid-template-columns: 1fr; }
            .dg-boundary-row { grid-template-columns: 1fr; }
        }
    </style>
    <link href="<?= h(base_url('assets/css/pages/developer-guide.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
      data-menu-color="<?= h($_SESSION['theme.menu'] ?? $_SESSION['theme.sidebar'] ?? 'dark') ?>"
      data-layout="vertical"
      data-sidebar-size="default"
      class="loading">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-page">
        <div class="content">
            <div class="container-fluid developer-guide-shell">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0"><i class="ri-code-s-slash-line me-1"></i><?= h($PAGE_TITLE) ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(dg('common_dashboard', 'Dashboard')) ?></a></li>
                                    <li class="breadcrumb-item active"><?= h($PAGE_TITLE) ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dg-hero mb-3">
                    <div class="dg-hero__content">
                        <span class="dg-badge"><i class="ri-shield-keyhole-line"></i><?= h(dg('developerGuide_core_safe_badge', 'Core-safe reference')) ?></span>
                        <h4 class="mt-3"><?= h(dg('developerGuide_heading', 'Central developer guide for IQS Framework modules')) ?></h4>
                        <p><?= h(dg('developerGuide_intro', 'Use this page as the standard reference for building project modules without editing protected framework core files. Copy the samples, keep module logic in project files, and configure access through the system UI.')) ?></p>
                    </div>
                    <div class="dg-hero__status"><i class="ri-checkbox-circle-line"></i><div><strong><?= h(dg('developerGuide_current_title', 'Framework aligned')) ?></strong><span><?= h(dg('developerGuide_current_text', 'Samples follow current security and audit patterns.')) ?></span></div></div>
                </div>

                <div class="dg-stats mb-3">
                    <label class="dg-search-card" for="dg-guide-search"><span><?= h(dg('developerGuide_search_label', 'Search this guide')) ?></span><div><i class="ri-search-line"></i><input type="search" id="dg-guide-search" placeholder="<?= h(dg('developerGuide_search_placeholder', 'Search topics, rules or samples...')) ?>" autocomplete="off"><kbd>/</kbd></div></label>
                </div>

                <div class="dg-search-summary mb-3 d-none" id="dg-search-summary" aria-live="polite">
                    <span id="dg-search-result-text"></span>
                    <button type="button" class="btn btn-sm" id="dg-search-clear"><i class="ri-close-line"></i> <?= h(dg('developerGuide_clear_search', 'Clear search')) ?></button>
                </div>

                <div class="row g-3">
                    <div class="col-lg-3">
                        <div class="dg-tab-card">
                            <div class="nav flex-column nav-pills" id="developerGuideTabs" role="tablist" aria-orientation="vertical">
                                <?php $first = true; foreach ($guideTabs as $tabId => $tab): ?>
                                    <button class="nav-link <?= $first ? 'active' : '' ?>"
                                            id="dg-tab-<?= h($tabId) ?>"
                                            data-bs-toggle="pill"
                                            data-bs-target="#dg-pane-<?= h($tabId) ?>"
                                            type="button"
                                            role="tab"
                                            aria-controls="dg-pane-<?= h($tabId) ?>"
                                            aria-selected="<?= $first ? 'true' : 'false' ?>">
                                        <i class="<?= h((string)$tab['icon']) ?>"></i><?= h((string)$tab['label']) ?>
                                    </button>
                                    <?php $first = false; endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="dg-pane-overview" role="tabpanel" aria-labelledby="dg-tab-overview">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-compass-3-line"></i><h5><?= h(dg('developerGuide_overview_title', 'Core-safe development rules')) ?></h5></div>
                                    <p class="dg-muted"><?= h(dg('developerGuide_overview_text', 'Programmers should build project modules by consuming framework APIs and UI-managed configuration, not by editing core runtime files.')) ?></p>
                                    <div class="nav dg-subtabs" id="dg-overview-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-overview-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-overview-pane-rules" type="button" role="tab" aria-controls="dg-overview-pane-rules" aria-selected="true"><?= h($guideUi['rules']) ?></button>
                                        <button class="nav-link" id="dg-overview-subtab-docs" data-bs-toggle="pill" data-bs-target="#dg-overview-pane-docs" type="button" role="tab" aria-controls="dg-overview-pane-docs" aria-selected="false"><?= h($guideUi['references']) ?></button>
                                        <button class="nav-link" id="dg-overview-subtab-boundary" data-bs-toggle="pill" data-bs-target="#dg-overview-pane-boundary" type="button" role="tab" aria-controls="dg-overview-pane-boundary" aria-selected="false"><?= h($guideUi['core_boundary']) ?></button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-overview-pane-rules" role="tabpanel" aria-labelledby="dg-overview-subtab-rules">
                                            <div class="dg-rule-grid mt-3">
                                                <div class="dg-rule-card"><h6><?= h(dg('developerGuide_rule_do_title', 'Do')) ?></h6><p><?= h(dg('developerGuide_rule_do_text', 'Use services, controllers, AJAX helpers, custom language files, and UI-managed menu/access setup.')) ?></p></div>
                                                <div class="dg-rule-card"><h6><?= h(dg('developerGuide_rule_dont_title', 'Do Not')) ?></h6><p><?= h(dg('developerGuide_rule_dont_text', 'Do not edit sidebar, topbar, init, Database core, Notification core, Audit core, or core language files for project needs.')) ?></p></div>
                                                <div class="dg-rule-card"><h6><?= h(dg('developerGuide_rule_register_title', 'Register')) ?></h6><p><?= h(dg('developerGuide_rule_register_text', 'After creating a page, register module/menu/subgroup and group access from User Groups, not from source code.')) ?></p></div>
                                            </div>
                                            <div class="dg-callout mt-3"><?= h(dg('developerGuide_recommended_flow', 'Recommended flow: generate or create page files, add controller/service/AJAX, add language keys in custom files, register menu/access in UI, then test authorization and audit behavior.')) ?></div>
                                        </div>
                                        <div class="tab-pane fade" id="dg-overview-pane-docs" role="tabpanel" aria-labelledby="dg-overview-subtab-docs">
                                            <div class="dg-doc-grid">
                                                <?php foreach ($docLinks as $doc): ?>
                                                    <div class="dg-doc-card">
                                                        <i class="<?= h((string)$doc['icon']) ?>"></i>
                                                        <div>
                                                            <h6><?= h((string)$doc['title']) ?></h6>
                                                            <code><?= h((string)$doc['path']) ?></code>
                                                        </div>
                                                        <button type="button" class="btn btn-sm dg-copy-text" data-copy-text="<?= h((string)$doc['path']) ?>" aria-label="<?= h(dg('developerGuide_copy_path', 'Copy document path')) ?>"><i class="ri-file-copy-line"></i></button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="dg-overview-pane-boundary" role="tabpanel" aria-labelledby="dg-overview-subtab-boundary">
                                            <div class="dg-boundary-table">
                                                <div class="dg-boundary-row dg-boundary-head">
                                                    <div>Area</div>
                                                    <div>Core File</div>
                                                    <div>Programmer Usage</div>
                                                </div>
                                                <?php foreach ($coreBoundaries as $boundary): ?>
                                                    <div class="dg-boundary-row">
                                                        <div><strong><?= h((string)$boundary['area']) ?></strong></div>
                                                        <div><code><?= h((string)$boundary['core']) ?></code></div>
                                                        <div><?= h((string)$boundary['use']) ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-page" role="tabpanel" aria-labelledby="dg-tab-page">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-pages-line"></i><h5><?= h($guideTabs['page']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-page-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-page-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-page-pane-rules" type="button" role="tab" aria-controls="dg-page-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-page-subtab-page" data-bs-toggle="pill" data-bs-target="#dg-page-pane-page" type="button" role="tab" aria-controls="dg-page-pane-page" aria-selected="false">Page File</button>
                                        <button class="nav-link" id="dg-page-subtab-controller" data-bs-toggle="pill" data-bs-target="#dg-page-pane-controller" type="button" role="tab" aria-controls="dg-page-pane-controller" aria-selected="false">Controller</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-page-pane-rules" role="tabpanel" aria-labelledby="dg-page-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Use `template-generator.php` where possible for baseline files.</li>
                                                <li>Keep page-specific orchestration in a controller or service.</li>
                                                <li>Use shared includes for head, topbar, sidebar, footer, and scripts.</li>
                                            </ul>
                                        </div>
                                        <div class="tab-pane fade" id="dg-page-pane-page" role="tabpanel" aria-labelledby="dg-page-subtab-page">
                                            <?php renderCodeCard('page-basic', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-page-pane-controller" role="tabpanel" aria-labelledby="dg-page-subtab-controller">
                                            <?php renderCodeCard('controller-basic', $samples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-service" role="tabpanel" aria-labelledby="dg-tab-service">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-service-line"></i><h5><?= h($guideTabs['service']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-service-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-service-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-service-pane-rules" type="button" role="tab" aria-controls="dg-service-pane-rules" aria-selected="true">Separation</button>
                                        <button class="nav-link" id="dg-service-subtab-sample" data-bs-toggle="pill" data-bs-target="#dg-service-pane-sample" type="button" role="tab" aria-controls="dg-service-pane-sample" aria-selected="false">Sample</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-service-pane-rules" role="tabpanel" aria-labelledby="dg-service-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Use a controller for page/request orchestration.</li>
                                                <li>Use a service for business rules such as approval flow, validation, notification, audit, and integration calls.</li>
                                                <li>Use a repository for database read/write details when a module grows beyond one simple page.</li>
                                            </ul>
                                            <div class="dg-callout mt-3">Rule of thumb: pages should display, controllers should coordinate, services should decide, repositories should query.</div>
                                        </div>
                                        <div class="tab-pane fade" id="dg-service-pane-sample" role="tabpanel" aria-labelledby="dg-service-subtab-sample">
                                            <?php renderCodeCard('service-repository', $samples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-database" role="tabpanel" aria-labelledby="dg-tab-database">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-database-2-line"></i><h5><?= h($guideTabs['database']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-database-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-database-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-database-pane-rules" type="button" role="tab" aria-controls="dg-database-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-database-subtab-main" data-bs-toggle="pill" data-bs-target="#dg-database-pane-main" type="button" role="tab" aria-controls="dg-database-pane-main" aria-selected="false">Main MySQL</button>
                                        <button class="nav-link" id="dg-database-subtab-additional" data-bs-toggle="pill" data-bs-target="#dg-database-pane-additional" type="button" role="tab" aria-controls="dg-database-pane-additional" aria-selected="false">Additional DB</button>
                                        <button class="nav-link" id="dg-database-subtab-transaction" data-bs-toggle="pill" data-bs-target="#dg-database-pane-transaction" type="button" role="tab" aria-controls="dg-database-pane-transaction" aria-selected="false">Transaction</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-database-pane-rules" role="tabpanel" aria-labelledby="dg-database-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Main MySQL is for framework/application data.</li>
                                                <li>Additional DB is for project integrations, reports, lookups, and external transactions.</li>
                                                <li>Never hardcode DSN, username, or password in module code.</li>
                                            </ul>
                                        </div>
                                        <div class="tab-pane fade" id="dg-database-pane-main" role="tabpanel" aria-labelledby="dg-database-subtab-main">
                                            <?php renderCodeCard('database-main', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-database-pane-additional" role="tabpanel" aria-labelledby="dg-database-subtab-additional">
                                            <?php renderCodeCard('database-additional', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-database-pane-transaction" role="tabpanel" aria-labelledby="dg-database-subtab-transaction">
                                            <?php renderCodeCard('database-transaction', $samples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-ajax" role="tabpanel" aria-labelledby="dg-tab-ajax">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-terminal-box-line"></i><h5><?= h($guideTabs['ajax']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-ajax-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-ajax-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-ajax-pane-rules" type="button" role="tab" aria-controls="dg-ajax-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-ajax-subtab-endpoint" data-bs-toggle="pill" data-bs-target="#dg-ajax-pane-endpoint" type="button" role="tab" aria-controls="dg-ajax-pane-endpoint" aria-selected="false">Endpoint</button>
                                        <button class="nav-link" id="dg-ajax-subtab-external" data-bs-toggle="pill" data-bs-target="#dg-ajax-pane-external" type="button" role="tab" aria-controls="dg-ajax-pane-external" aria-selected="false">External Service</button>
                                        <button class="nav-link" id="dg-ajax-subtab-fetch" data-bs-toggle="pill" data-bs-target="#dg-ajax-pane-fetch" type="button" role="tab" aria-controls="dg-ajax-pane-fetch" aria-selected="false">Fetch</button>
                                        <button class="nav-link" id="dg-ajax-subtab-datatable" data-bs-toggle="pill" data-bs-target="#dg-ajax-pane-datatable" type="button" role="tab" aria-controls="dg-ajax-pane-datatable" aria-selected="false">DataTable</button>
                                    </div>
                                    <div class="tab-content">
        <div class="tab-pane fade show active" id="dg-ajax-pane-rules" role="tabpanel" aria-labelledby="dg-ajax-subtab-rules">
            <ul class="dg-list">
                                                <li>Every AJAX endpoint must include `init.php` and call `require_login()`.</li>
                                                <li>Page-specific AJAX endpoints must call `require_page_access('pages/parent-page.php')` so direct AJAX access follows the same menu/group permission as the parent page.</li>
                                                <li>Every write endpoint must use CSRF validation.</li>
                                                <li>Return JSON through shared response helpers.</li>
                                                <li>Use `jsonExceptionResponse()` for `ExternalServiceException` so provider/API failures are not reported as HTTP 500.</li>
                                                <li>Log internal errors, but return safe user-facing messages.</li>
                                            </ul>
                                        </div>
                                        <div class="tab-pane fade" id="dg-ajax-pane-endpoint" role="tabpanel" aria-labelledby="dg-ajax-subtab-endpoint">
                                            <?php renderCodeCard('ajax-basic', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-ajax-pane-external" role="tabpanel" aria-labelledby="dg-ajax-subtab-external">
                                            <?php renderCodeCard('ajax-external-service', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-ajax-pane-fetch" role="tabpanel" aria-labelledby="dg-ajax-subtab-fetch">
                                            <?php renderCodeCard('frontend-fetch', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-ajax-pane-datatable" role="tabpanel" aria-labelledby="dg-ajax-subtab-datatable">
                                            <?php renderCodeCard('datatable-ajax', $samples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-notification" role="tabpanel" aria-labelledby="dg-tab-notification">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-notification-3-line"></i><h5><?= h($guideTabs['notification']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-notification-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-notification-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-notification-pane-rules" type="button" role="tab" aria-controls="dg-notification-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-notification-subtab-event" data-bs-toggle="pill" data-bs-target="#dg-notification-pane-event" type="button" role="tab" aria-controls="dg-notification-pane-event" aria-selected="false">Event</button>
                                        <button class="nav-link" id="dg-notification-subtab-workflow" data-bs-toggle="pill" data-bs-target="#dg-notification-pane-workflow" type="button" role="tab" aria-controls="dg-notification-pane-workflow" aria-selected="false">Workflow</button>
                                        <button class="nav-link" id="dg-notification-subtab-samples" data-bs-toggle="pill" data-bs-target="#dg-notification-pane-samples" type="button" role="tab" aria-controls="dg-notification-pane-samples" aria-selected="false">Admin Samples</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-notification-pane-rules" role="tabpanel" aria-labelledby="dg-notification-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Use `NotificationPublisher` for event/information notifications.</li>
                                                <li>Use `NotificationWorkflowService` for approval/action tasks.</li>
                                                <li>Do not insert directly into notification tables.</li>
                                            </ul>
                                        </div>
                                        <div class="tab-pane fade" id="dg-notification-pane-event" role="tabpanel" aria-labelledby="dg-notification-subtab-event">
                                            <?php renderCodeCard('notification-event', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-notification-pane-workflow" role="tabpanel" aria-labelledby="dg-notification-subtab-workflow">
                                            <?php renderCodeCard('notification-workflow', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-notification-pane-samples" role="tabpanel" aria-labelledby="dg-notification-subtab-samples">
                                            <?php renderNotificationSampleCards($notificationDeveloperSamples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-language" role="tabpanel" aria-labelledby="dg-tab-language">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-translate-2"></i><h5><?= h($guideTabs['language']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-language-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-language-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-language-pane-rules" type="button" role="tab" aria-controls="dg-language-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-language-subtab-sample" data-bs-toggle="pill" data-bs-target="#dg-language-pane-sample" type="button" role="tab" aria-controls="dg-language-pane-sample" aria-selected="false">Custom Keys</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-language-pane-rules" role="tabpanel" aria-labelledby="dg-language-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Project/module keys belong in `public/lang/custom/*.php`.</li>
                                                <li>Do not edit `public/lang/core/*.php` for project-specific text.</li>
                                                <li>Run `php tools/language-split-tool.php validate` after language changes.</li>
                                            </ul>
                                        </div>
                                        <div class="tab-pane fade" id="dg-language-pane-sample" role="tabpanel" aria-labelledby="dg-language-subtab-sample">
                                            <?php renderCodeCard('language-custom', $samples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-menu" role="tabpanel" aria-labelledby="dg-tab-menu">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-menu-2-line"></i><h5><?= h($guideTabs['menu']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-menu-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-menu-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-menu-pane-rules" type="button" role="tab" aria-controls="dg-menu-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-menu-subtab-flow" data-bs-toggle="pill" data-bs-target="#dg-menu-pane-flow" type="button" role="tab" aria-controls="dg-menu-pane-flow" aria-selected="false">Setup Flow</button>
                                        <button class="nav-link" id="dg-menu-subtab-reference" data-bs-toggle="pill" data-bs-target="#dg-menu-pane-reference" type="button" role="tab" aria-controls="dg-menu-pane-reference" aria-selected="false">Reference</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-menu-pane-rules" role="tabpanel" aria-labelledby="dg-menu-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Register pages through `kumpulan-pengguna.php` Menu Access.</li>
                                                <li>Use optional menu subgroups when a parent module needs grouped child menus.</li>
                                                <li>Do not hardcode sidebar links in `public/includes/sidebar.php`.</li>
                                                <li>Use Access Matrix to review effective access.</li>
                                            </ul>
                                        </div>
                                        <div class="tab-pane fade" id="dg-menu-pane-flow" role="tabpanel" aria-labelledby="dg-menu-subtab-flow">
                                            <div class="dg-pattern-grid mt-3">
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no">1</span>
                                                    <h6>Create Module/Menu</h6>
                                                    <p>Register page path such as `pages/my-module.php`, icon, order, and visibility from Menu Access.</p>
                                                </div>
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no">2</span>
                                                    <h6>Add Subgroup If Needed</h6>
                                                    <p>Use subgroup only when one parent menu contains multiple logical sections.</p>
                                                </div>
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no">3</span>
                                                    <h6>Assign Group Access</h6>
                                                    <p>Enable the menu for the correct user group and confirm the expected status.</p>
                                                </div>
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no">4</span>
                                                    <h6>Verify Effective Access</h6>
                                                    <p>Use Access Matrix and test direct URL access with an unauthorized account.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="dg-menu-pane-reference" role="tabpanel" aria-labelledby="dg-menu-subtab-reference">
                                            <div class="dg-callout mt-3">Reference: `docs/sidebar-menu-subgroup-blueprint-2026-05-06.md`.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-audit" role="tabpanel" aria-labelledby="dg-tab-audit">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-shield-check-line"></i><h5><?= h($guideTabs['audit']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-audit-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-audit-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-audit-pane-rules" type="button" role="tab" aria-controls="dg-audit-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-audit-subtab-event" data-bs-toggle="pill" data-bs-target="#dg-audit-pane-event" type="button" role="tab" aria-controls="dg-audit-pane-event" aria-selected="false">Audit Event</button>
                                        <button class="nav-link" id="dg-audit-subtab-viewas" data-bs-toggle="pill" data-bs-target="#dg-audit-pane-viewas" type="button" role="tab" aria-controls="dg-audit-pane-viewas" aria-selected="false">View As</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-audit-pane-rules" role="tabpanel" aria-labelledby="dg-audit-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Use audit helpers for sensitive module actions.</li>
                                                <li>Never log passwords, DSNs with credentials, tokens, or full sensitive payloads.</li>
                                                <li>Use clear `event_type`, `target_type`, and `target_id` values.</li>
                                                <li>During Admin View As, use actor/effective-user helpers when module code needs to explain support actions.</li>
                                            </ul>
                                        </div>
                                        <div class="tab-pane fade" id="dg-audit-pane-event" role="tabpanel" aria-labelledby="dg-audit-subtab-event">
                                            <?php renderCodeCard('audit-event', $samples); ?>
                                        </div>
                                        <div class="tab-pane fade" id="dg-audit-pane-viewas" role="tabpanel" aria-labelledby="dg-audit-subtab-viewas">
                                            <?php renderCodeCard('impersonation-context', $samples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-email" role="tabpanel" aria-labelledby="dg-tab-email">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-mail-send-line"></i><h5><?= h($guideTabs['email']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-email-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-email-subtab-rules" data-bs-toggle="pill" data-bs-target="#dg-email-pane-rules" type="button" role="tab" aria-controls="dg-email-pane-rules" aria-selected="true">Rules</button>
                                        <button class="nav-link" id="dg-email-subtab-template" data-bs-toggle="pill" data-bs-target="#dg-email-pane-template" type="button" role="tab" aria-controls="dg-email-pane-template" aria-selected="false">Template</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-email-pane-rules" role="tabpanel" aria-labelledby="dg-email-subtab-rules">
                                            <ul class="dg-list">
                                                <li>Manage reusable templates through `template-emel.php`.</li>
                                                <li>Keep module-specific variables in service/controller code.</li>
                                                <li>Avoid hardcoded long email bodies inside business pages.</li>
                                                <li>Handle SMTP delivery failures with `lastFailureAsExternalServiceException()` and `jsonExceptionResponse()` in HTTP-facing code.</li>
                                            </ul>
                                            <div class="dg-callout mt-3">Reference: `docs/external-service-failure-handling-2026-06-23.md`.</div>
                                        </div>
                                        <div class="tab-pane fade" id="dg-email-pane-template" role="tabpanel" aria-labelledby="dg-email-subtab-template">
                                            <?php renderCodeCard('email-template', $samples); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-ui" role="tabpanel" aria-labelledby="dg-tab-ui">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-layout-4-line"></i><h5><?= h($guideTabs['ui']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-ui-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-ui-subtab-components" data-bs-toggle="pill" data-bs-target="#dg-ui-pane-components" type="button" role="tab" aria-controls="dg-ui-pane-components" aria-selected="true">Components</button>
                                        <button class="nav-link" id="dg-ui-subtab-boundary" data-bs-toggle="pill" data-bs-target="#dg-ui-pane-boundary" type="button" role="tab" aria-controls="dg-ui-pane-boundary" aria-selected="false">Boundary</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-ui-pane-components" role="tabpanel" aria-labelledby="dg-ui-subtab-components">
                                            <div class="dg-pattern-grid">
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no"><i class="ri-table-line"></i></span>
                                                    <h6>DataTables</h6>
                                                    <p>Use the same table structure, paging, search spacing, and top-aligned cells as existing admin pages.</p>
                                                </div>
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no"><i class="ri-window-line"></i></span>
                                                    <h6>Modals</h6>
                                                    <p>Tabbed modals should open top-aligned; simple confirmation or single-form modals can remain centered.</p>
                                                </div>
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no"><i class="ri-information-line"></i></span>
                                                    <h6>Field Help</h6>
                                                    <p>Use small info icons with tooltips for detailed guidance instead of long inline descriptions.</p>
                                                </div>
                                                <div class="dg-step-card">
                                                    <span class="dg-step-no"><i class="ri-palette-line"></i></span>
                                                    <h6>Theme Safety</h6>
                                                    <p>Use existing CSS variables and theme classes. Do not hardcode page colors that fight user theme settings.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="dg-ui-pane-boundary" role="tabpanel" aria-labelledby="dg-ui-subtab-boundary">
                                            <div class="dg-callout mt-3">Keep page UI inside the module page or module stylesheet. Avoid changing shared includes unless the feature is truly framework-level.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="dg-pane-checklist" role="tabpanel" aria-labelledby="dg-tab-checklist">
                                <div class="dg-panel">
                                    <div class="dg-panel-title"><i class="ri-list-check-3"></i><h5><?= h($guideTabs['checklist']['label']) ?></h5></div>
                                    <div class="nav dg-subtabs" id="dg-checklist-subtabs" role="tablist">
                                        <button class="nav-link active" id="dg-checklist-subtab-before" data-bs-toggle="pill" data-bs-target="#dg-checklist-pane-before" type="button" role="tab" aria-controls="dg-checklist-pane-before" aria-selected="true">Before Coding</button>
                                        <button class="nav-link" id="dg-checklist-subtab-handover" data-bs-toggle="pill" data-bs-target="#dg-checklist-pane-handover" type="button" role="tab" aria-controls="dg-checklist-pane-handover" aria-selected="false">Before Handover</button>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="dg-checklist-pane-before" role="tabpanel" aria-labelledby="dg-checklist-subtab-before">
                                            <div class="dg-rule-card h-100">
                                                <h6>Before Coding</h6>
                                                <ul class="dg-list">
                                                    <li>Confirm module scope and page path.</li>
                                                    <li>Choose main/additional database usage.</li>
                                                    <li>Plan notification/audit needs.</li>
                                                    <li>Define language key prefix.</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="dg-checklist-pane-handover" role="tabpanel" aria-labelledby="dg-checklist-subtab-handover">
                                            <div class="dg-rule-card h-100">
                                                <h6>Before Handover</h6>
                                                <ul class="dg-list">
                                                    <li>Run PHP syntax checks.</li>
                                                    <li>Validate language split.</li>
                                                    <li>Run `php tools/core-file-protection-audit.php --strict` before collecting framework updates.</li>
                                                    <li>Register menu/access in UI.</li>
                                                    <li>Test unauthorized direct URL and AJAX access.</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/script.php'; ?>
<script>
window.DeveloperGuideI18n = <?= json_encode([
    'copied' => dg('developerGuide_copied', 'Copied'),
    'result' => dg('developerGuide_search_result', '%d topics match your search'),
    'noResult' => dg('developerGuide_search_no_result', 'No topics match your search.'),
    'ui' => array_merge($guideUi, [
        'fetch' => 'Fetch',
        'datatable' => 'DataTable',
    ]),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= h(base_url('assets/js/pages/developer-guide.js')) ?>?v=<?= h($version) ?>"></script>
</body>
</html>
