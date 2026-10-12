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
require_once __DIR__ . '/../classes/AiChatbotKnowledgeService.php';
require_once __DIR__ . '/../classes/AiChatbotKnowledgeSourceService.php';
require_once __DIR__ . '/../classes/AiChatbotKnowledgeChunkService.php';
require_once __DIR__ . '/../classes/AiChatbotPdfTextExtractor.php';

$pdo = Database::getInstance('mysql')->getConnection();
$permissionMessage = __('aiChatbotKnowledge_permission_denied');
ensurePageGroupManagePermission(
    $pdo,
    ($permissionMessage === null || $permissionMessage === '' || $permissionMessage === 'aiChatbotKnowledge_permission_denied')
        ? 'Anda tidak mempunyai kebenaran untuk mengurus AI Chatbot knowledge.'
        : (string)$permissionMessage
);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function ai_chatbot_knowledge_label(string $key, string $fallback): string
{
    $value = __($key);
    return ($value === null || $value === '' || $value === $key) ? $fallback : (string)$value;
}

function ai_chatbot_knowledge_is_ajax(): bool
{
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

/**
 * @param array<string,mixed> $payload
 */
function ai_chatbot_knowledge_json_response(array $payload, int $statusCode = 200): never
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function ai_chatbot_knowledge_row_payload(array $row): array
{
    $allowedGroups = array_values(array_filter(array_map('trim', explode(',', (string)($row['f_allowedGroups'] ?? '')))));

    return [
        'public_id' => (string)($row['f_publicID'] ?? ''),
        'title' => (string)($row['f_title'] ?? ''),
        'question' => (string)($row['f_question'] ?? ''),
        'answer' => (string)($row['f_answer'] ?? ''),
        'language' => (string)($row['f_language'] ?? 'ms'),
        'visibility' => (string)($row['f_visibility'] ?? 'selected_groups'),
        'allowed_groups' => $allowedGroups,
        'tags' => (string)($row['f_tags'] ?? ''),
        'source_title' => (string)($row['f_sourceTitle'] ?? ''),
        'version' => (string)($row['f_version'] ?? ''),
        'review_status' => (string)($row['f_reviewStatus'] ?? 'draft'),
        'effective_date' => (string)($row['f_effectiveDate'] ?? ''),
        'review_due_date' => (string)($row['f_reviewDueDate'] ?? ''),
        'status' => (string)($row['f_status'] ?? 'draft'),
        'priority' => (int)($row['f_priority'] ?? 100),
    ];
}

function ai_chatbot_knowledge_display_date(?string $date): string
{
    $date = trim((string)$date);
    if ($date === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return $date;
    }

    $months = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mac',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ogos',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Dis',
    ];

    return (int)$dt->format('j') . ' ' . $months[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

/**
 * @param array<string,mixed> $row
 * @return array{ready:bool,label:string,class:string,reason:string}
 */
function ai_chatbot_manual_retrieval_state(array $row): array
{
    $status = (string)($row['f_status'] ?? 'draft');
    $language = (string)($row['f_language'] ?? 'ms');
    $visibility = (string)($row['f_visibility'] ?? 'selected_groups');
    $allowedGroups = trim((string)($row['f_allowedGroups'] ?? ''));

    if ($status !== 'active') {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-secondary-subtle text-secondary', 'reason' => 'Status is not active.'];
    }
    if (!in_array($language, ['ms', 'en'], true)) {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-danger-subtle text-danger', 'reason' => 'Language is not supported.'];
    }
    if ($visibility === 'selected_groups' && $allowedGroups === '') {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-warning-subtle text-warning', 'reason' => 'Allowed groups are required.'];
    }

    return ['ready' => true, 'label' => 'Retrievable', 'class' => 'bg-success-subtle text-success', 'reason' => 'Eligible for language and visibility filtered retrieval.'];
}

/**
 * @param array<string,mixed> $row
 * @return array{allowed:bool,reason:string}
 */
function ai_chatbot_manual_activation_state(array $row): array
{
    $language = (string)($row['f_language'] ?? 'ms');
    $visibility = (string)($row['f_visibility'] ?? 'selected_groups');
    $allowedGroups = trim((string)($row['f_allowedGroups'] ?? ''));

    if (!in_array($language, ['ms', 'en'], true)) {
        return ['allowed' => false, 'reason' => 'Language is not supported.'];
    }
    if ($visibility === 'selected_groups' && $allowedGroups === '') {
        return ['allowed' => false, 'reason' => 'Allowed groups are required before activation.'];
    }

    return ['allowed' => true, 'reason' => 'Ready to activate.'];
}

/**
 * @param array<string,mixed> $row
 * @return array{ready:bool,label:string,class:string,reason:string}
 */
function ai_chatbot_pdf_retrieval_state(array $row, int $chunkCount): array
{
    $status = (string)($row['f_status'] ?? 'draft');
    $extractStatus = (string)($row['f_extractionStatus'] ?? 'pending');
    $language = (string)($row['f_language'] ?? 'ms');
    $visibility = (string)($row['f_visibility'] ?? 'selected_groups');
    $allowedGroups = trim((string)($row['f_allowedGroups'] ?? ''));

    if ($status !== 'active') {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-secondary-subtle text-secondary', 'reason' => 'PDF source is not active.'];
    }
    if ($extractStatus !== 'processed') {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-warning-subtle text-warning', 'reason' => 'PDF text is not processed.'];
    }
    if ($chunkCount < 1) {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-warning-subtle text-warning', 'reason' => 'No active chunk is available.'];
    }
    if (!in_array($language, ['ms', 'en'], true)) {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-danger-subtle text-danger', 'reason' => 'Language is not supported.'];
    }
    if ($visibility === 'selected_groups' && $allowedGroups === '') {
        return ['ready' => false, 'label' => 'Not retrievable', 'class' => 'bg-warning-subtle text-warning', 'reason' => 'Allowed groups are required.'];
    }

    return ['ready' => true, 'label' => 'Retrievable', 'class' => 'bg-success-subtle text-success', 'reason' => 'Chunks are eligible for filtered retrieval.'];
}

/**
 * @param array<string,mixed> $row
 * @return array{allowed:bool,reason:string}
 */
function ai_chatbot_pdf_activation_state(array $row, int $chunkCount): array
{
    $extractStatus = (string)($row['f_extractionStatus'] ?? 'pending');
    $language = (string)($row['f_language'] ?? 'ms');
    $visibility = (string)($row['f_visibility'] ?? 'selected_groups');
    $allowedGroups = trim((string)($row['f_allowedGroups'] ?? ''));

    if ($extractStatus !== 'processed') {
        return ['allowed' => false, 'reason' => 'PDF text must be processed before activation.'];
    }
    if ($chunkCount < 1) {
        return ['allowed' => false, 'reason' => 'At least one active chunk is required before activation.'];
    }
    if (!in_array($language, ['ms', 'en'], true)) {
        return ['allowed' => false, 'reason' => 'Language is not supported.'];
    }
    if ($visibility === 'selected_groups' && $allowedGroups === '') {
        return ['allowed' => false, 'reason' => 'Allowed groups are required before activation.'];
    }

    return ['allowed' => true, 'reason' => 'Ready to activate.'];
}

function ai_chatbot_knowledge_audit(string $action, string $publicId, ?array $oldRow, ?array $newRow): void
{
    try {
        if (!function_exists('audit_event')) {
            return;
        }

        $eventType = match ($action) {
            'create' => 'CREATE',
            'delete' => 'DELETE',
            default => 'UPDATE',
        };
        $actorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label()
            : ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null);
        $targetLabel = trim((string)($newRow['f_title'] ?? $oldRow['f_title'] ?? 'AI Chatbot Knowledge'));

        $eventId = audit_event([
            'event_type' => $eventType,
            'severity' => $action === 'delete' ? 'WARN' : 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'ai_chatbot_knowledge',
            'target_id' => $publicId,
            'target_label' => $targetLabel,
            'message' => function_exists('audit_format_message')
                ? audit_format_message('AI Chatbot knowledge ' . $action, $actorLabel)
                : 'AI Chatbot knowledge ' . $action,
            'actor_label' => $actorLabel,
            'meta' => [
                'action' => $action,
                'public_id' => $publicId,
                'status' => $newRow['f_status'] ?? $oldRow['f_status'] ?? null,
                'visibility' => $newRow['f_visibility'] ?? $oldRow['f_visibility'] ?? null,
            ],
        ]);

        if (!$eventId || !function_exists('audit_begin_change') || !function_exists('audit_change')) {
            return;
        }

        $changeSetId = audit_begin_change($eventId, 'ai_chatbot_knowledge', $publicId, 'AI Chatbot knowledge ' . $action, [
            'source' => 'ai-chatbot-knowledge',
            'action' => $action,
        ]);
        if (!$changeSetId) {
            return;
        }

        foreach ([
            'f_title' => 'string',
            'f_question' => 'string',
            'f_answer' => 'string',
            'f_language' => 'string',
            'f_visibility' => 'string',
            'f_allowedGroups' => 'string',
            'f_tags' => 'string',
            'f_sourceType' => 'string',
            'f_sourceTitle' => 'string',
            'f_version' => 'string',
            'f_reviewStatus' => 'string',
            'f_effectiveDate' => 'date',
            'f_reviewDueDate' => 'date',
            'f_status' => 'string',
            'f_priority' => 'integer',
        ] as $field => $type) {
            $oldValue = $oldRow[$field] ?? null;
            $newValue = $newRow[$field] ?? null;
            if ((string)$oldValue === (string)$newValue) {
                continue;
            }
            audit_change($changeSetId, $field, $oldValue, $newValue, $type, $field === 'f_answer');
        }
    } catch (Throwable $e) {
        error_log('[ai-chatbot-knowledge] audit: ' . $e->getMessage());
    }
}

function ai_chatbot_knowledge_source_audit(string $action, array $row): void
{
    try {
        if (!function_exists('audit_event')) {
            return;
        }

        $actorLabel = function_exists('audit_format_actor_label')
            ? audit_format_actor_label()
            : ($_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null);
        $publicId = (string)($row['f_publicID'] ?? '');
        $title = trim((string)($row['f_title'] ?? 'AI Chatbot Knowledge PDF'));

        audit_event([
            'event_type' => $action === 'upload' ? 'CREATE' : 'UPDATE',
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'ai_chatbot_knowledge_source',
            'target_id' => $publicId,
            'target_label' => $title,
            'message' => function_exists('audit_format_message')
                ? audit_format_message('AI Chatbot knowledge PDF ' . $action, $actorLabel)
                : 'AI Chatbot knowledge PDF ' . $action,
            'actor_label' => $actorLabel,
            'meta' => [
                'action' => $action,
                'public_id' => $publicId,
                'filename' => $row['f_originalFilename'] ?? null,
                'stored_path' => $row['f_storedPath'] ?? null,
                'file_size_bytes' => $row['f_fileSizeBytes'] ?? null,
                'file_hash_sha256' => $row['f_fileHashSha256'] ?? null,
                'language' => $row['f_language'] ?? null,
                'visibility' => $row['f_visibility'] ?? null,
                'extraction_status' => $row['f_extractionStatus'] ?? null,
                'status' => $row['f_status'] ?? null,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('[ai-chatbot-knowledge-source] audit: ' . $e->getMessage());
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$service = new AiChatbotKnowledgeService($pdo);
$sourceService = new AiChatbotKnowledgeSourceService($pdo);
$chunkService = new AiChatbotKnowledgeChunkService($pdo);
$tableAvailable = $service->tableExists();
$sourceTableAvailable = $sourceService->tableExists();
$chunkTableAvailable = $chunkService->tableExists();
$message = '';
$messageType = 'success';
$editPublicId = trim((string)($_GET['edit'] ?? ''));
$actor = (string)($_SESSION['f_loginID'] ?? $_SESSION['f_stafID'] ?? 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ai_chatbot_knowledge_is_ajax()) {
    $ajaxAction = strtolower(trim((string)($_GET['ajax'] ?? '')));
    if ($ajaxAction === 'knowledge_item') {
        $publicId = trim((string)($_GET['public_id'] ?? ''));
        $row = $publicId !== '' ? $service->findByPublicId($publicId) : null;
        if ($row === null) {
            ai_chatbot_knowledge_json_response([
                'ok' => false,
                'type' => 'error',
                'message' => 'Knowledge item tidak dijumpai.',
            ], 404);
        }

        ai_chatbot_knowledge_json_response([
            'ok' => true,
            'item' => ai_chatbot_knowledge_row_payload($row),
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjaxRequest = ai_chatbot_knowledge_is_ajax();
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
        $message = 'Sesi keselamatan tamat. Sila refresh halaman dan cuba semula.';
        $messageType = 'danger';
        if ($isAjaxRequest) {
            ai_chatbot_knowledge_json_response([
                'ok' => false,
                'type' => 'error',
                'message' => $message,
            ], 403);
        }
    } else {
        $action = strtolower(trim((string)($_POST['action'] ?? 'save')));
        $publicId = trim((string)($_POST['public_id'] ?? ''));
        try {
            $oldRow = $publicId !== '' ? $service->findByPublicId($publicId) : null;
            if ($action === 'save') {
                $savedPublicId = $service->save($_POST, $actor);
                $newRow = $service->findByPublicId($savedPublicId);
                ai_chatbot_knowledge_audit($oldRow ? 'update' : 'create', $savedPublicId, $oldRow, $newRow);
                $successMessage = $oldRow ? 'Knowledge item berjaya dikemas kini.' : 'Knowledge item berjaya ditambah.';
                if ($isAjaxRequest) {
                    ai_chatbot_knowledge_json_response([
                        'ok' => true,
                        'type' => 'success',
                        'message' => $successMessage,
                        'public_id' => $savedPublicId,
                    ]);
                }
                header('Location: ' . base_url('pages/ai-chatbot-knowledge.php'));
                exit;
            }
            if ($action === 'upload_pdf') {
                $newSource = $sourceService->uploadPdf($_POST, $_FILES['knowledge_pdf_file'] ?? [], $actor);
                ai_chatbot_knowledge_source_audit('upload', $newSource);
                $uploadMessage = '';
                $uploadType = 'success';
                try {
                    $extractor = new AiChatbotPdfTextExtractor();
                    $extraction = $extractor->extractToSidecar($sourceService->getStoredPdfPath($newSource));
                    $sourceService->markExtractionProcessed((string)$newSource['f_publicID'], (int)$extraction['char_count'], $actor);
                    $processedSource = $sourceService->findByPublicId((string)$newSource['f_publicID']) ?? $newSource;
                    $chunkCount = 0;
                    if ($chunkTableAvailable) {
                        $chunkCount = $chunkService->replaceChunksForSource($processedSource, (string)$extraction['text'], $actor);
                        $sourceService->updateChunkCount((string)$newSource['f_publicID'], $chunkCount, $actor);
                        $processedSource = $sourceService->findByPublicId((string)$newSource['f_publicID']) ?? $processedSource;
                    }
                    ai_chatbot_knowledge_source_audit('extract', $processedSource);
                    $uploadMessage = 'PDF source berjaya dimuat naik, text berjaya diekstrak' . ($chunkTableAvailable ? ', dan ' . $chunkCount . ' chunk draft berjaya dijana.' : '. Chunk table belum tersedia.');
                } catch (Throwable $extractError) {
                    $sourceService->markExtractionFailed((string)$newSource['f_publicID'], $extractError->getMessage(), $actor);
                    $failedSource = $sourceService->findByPublicId((string)$newSource['f_publicID']) ?? $newSource;
                    ai_chatbot_knowledge_source_audit('extract_failed', $failedSource);
                    $uploadType = 'warning';
                    $uploadMessage = 'PDF source berjaya dimuat naik, tetapi text belum dapat diekstrak: ' . $extractError->getMessage();
                }
                if ($isAjaxRequest) {
                    ai_chatbot_knowledge_json_response([
                        'ok' => true,
                        'type' => $uploadType,
                        'message' => $uploadMessage,
                    ]);
                }
                $_SESSION['flash_alert'] = [
                    'type' => $uploadType,
                    'message' => $uploadMessage,
                ];
                header('Location: ' . base_url('pages/ai-chatbot-knowledge.php'));
                exit;
            }
            if (in_array($action, ['pdf_draft', 'pdf_active', 'pdf_archived'], true)) {
                $sourceStatus = match ($action) {
                    'pdf_active' => 'active',
                    'pdf_archived' => 'archived',
                    default => 'draft',
                };
                $oldSource = $sourceService->findByPublicId($publicId);
                if ($oldSource === null) {
                    throw new RuntimeException('PDF source tidak dijumpai.');
                }

                $pdo->beginTransaction();
                try {
                    $sourceService->setStatus($publicId, $sourceStatus, $actor);
                    $updatedChunkCount = $chunkTableAvailable ? $chunkService->setStatusForSource($publicId, $sourceStatus, $actor) : 0;
                    $pdo->commit();
                } catch (Throwable $statusError) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $statusError;
                }

                $newSource = $sourceService->findByPublicId($publicId) ?? $oldSource;
                ai_chatbot_knowledge_source_audit('status_' . $sourceStatus, $newSource);
                $statusMessage = 'Status PDF source berjaya dikemas kini kepada ' . $sourceStatus . ' dengan ' . $updatedChunkCount . ' chunk dikemas kini.';
                if ($isAjaxRequest) {
                    ai_chatbot_knowledge_json_response([
                        'ok' => true,
                        'type' => 'success',
                        'message' => $statusMessage,
                    ]);
                }
                $_SESSION['flash_alert'] = [
                    'type' => 'success',
                    'message' => $statusMessage,
                ];
                header('Location: ' . base_url('pages/ai-chatbot-knowledge.php'));
                exit;
            }
            if (in_array($action, ['draft', 'active', 'archived'], true)) {
                $service->setStatus($publicId, $action, $actor);
                $newRow = $service->findByPublicId($publicId);
                ai_chatbot_knowledge_audit('status', $publicId, $oldRow, $newRow);
                $statusMessage = 'Status knowledge item berjaya dikemas kini.';
                if ($isAjaxRequest) {
                    ai_chatbot_knowledge_json_response([
                        'ok' => true,
                        'type' => 'success',
                        'message' => $statusMessage,
                    ]);
                }
                $_SESSION['flash_alert'] = ['type' => 'success', 'message' => $statusMessage];
                header('Location: ' . base_url('pages/ai-chatbot-knowledge.php'));
                exit;
            }
            if ($action === 'delete') {
                $service->delete($publicId);
                ai_chatbot_knowledge_audit('delete', $publicId, $oldRow, null);
                $deleteMessage = 'Knowledge item berjaya dipadam.';
                if ($isAjaxRequest) {
                    ai_chatbot_knowledge_json_response([
                        'ok' => true,
                        'type' => 'success',
                        'message' => $deleteMessage,
                    ]);
                }
                $_SESSION['flash_alert'] = ['type' => 'success', 'message' => $deleteMessage];
                header('Location: ' . base_url('pages/ai-chatbot-knowledge.php'));
                exit;
            }

            throw new InvalidArgumentException('Tindakan tidak sah.');
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
            if ($isAjaxRequest) {
                ai_chatbot_knowledge_json_response([
                    'ok' => false,
                    'type' => 'error',
                    'message' => $message,
                ], 422);
            }
        }
    }
}

if ($message === '' && isset($_SESSION['flash_alert']) && is_array($_SESSION['flash_alert'])) {
    $message = (string)($_SESSION['flash_alert']['message'] ?? '');
    $messageType = (string)($_SESSION['flash_alert']['type'] ?? 'success');
    unset($_SESSION['flash_alert']);
}

$records = $tableAvailable ? $service->getAll() : [];
$pdfSources = $sourceTableAvailable ? $sourceService->getAll() : [];
$pdfChunkCounts = $chunkTableAvailable ? $chunkService->countBySource() : [];
$summary = $service->summary();
$groups = $service->getGroupOptions();
$editRow = $editPublicId !== '' ? $service->findByPublicId($editPublicId) : null;
$languageOptions = ['ms' => 'Malay', 'en' => 'English'];
$reviewStatusOptions = [
    'draft' => 'Draft',
    'reviewed' => 'Reviewed',
    'approved' => 'Approved',
    'needs_update' => 'Needs update',
];
$selectedLanguage = (string)($editRow['f_language'] ?? 'ms');
if (!isset($languageOptions[$selectedLanguage])) {
    $selectedLanguage = 'ms';
}
$selectedReviewStatus = (string)($editRow['f_reviewStatus'] ?? 'draft');
if (!isset($reviewStatusOptions[$selectedReviewStatus])) {
    $selectedReviewStatus = 'draft';
}
$allowedGroupValues = array_filter(array_map('trim', explode(',', (string)($editRow['f_allowedGroups'] ?? ''))));
$pdfUploadMaxMb = function_exists('app_config') ? (int)app_config('upload.manual_max_mb', 10) : 10;
if ($pdfUploadMaxMb < 1) {
    $pdfUploadMaxMb = 1;
} elseif ($pdfUploadMaxMb > 100) {
    $pdfUploadMaxMb = 100;
}

$manualReadyCount = 0;
foreach ($records as $record) {
    if (ai_chatbot_manual_retrieval_state($record)['ready']) {
        $manualReadyCount++;
    }
}
$pdfReadyCount = 0;
foreach ($pdfSources as $source) {
    $sourcePublicId = (string)($source['f_publicID'] ?? '');
    $sourceChunkCount = (int)($pdfChunkCounts[$sourcePublicId] ?? (int)($source['f_chunkCount'] ?? 0));
    if (ai_chatbot_pdf_retrieval_state($source, $sourceChunkCount)['ready']) {
        $pdfReadyCount++;
    }
}
$manualNotReadyCount = max(0, count($records) - $manualReadyCount);
$pdfNotReadyCount = max(0, count($pdfSources) - $pdfReadyCount);

$lang = (string)($_SESSION['lang'] ?? 'ms');
$version = (string)($_ENV['APP_ASSET_VER'] ?? date('ymdHis'));
$PAGE_TITLE = ai_chatbot_knowledge_label('aiChatbotKnowledge_page_title', 'AI Chatbot Knowledge Manager');
$knowledgeI18n = [
    'createTitle' => ai_chatbot_knowledge_label('aiChatbotKnowledge_create_title', 'Create Knowledge Item'),
    'editTitle' => ai_chatbot_knowledge_label('aiChatbotKnowledge_edit_title', 'Edit Knowledge Item'),
    'show' => ai_chatbot_knowledge_label('aiChatbotKnowledge_show', 'Show'),
    'hide' => ai_chatbot_knowledge_label('aiChatbotKnowledge_hide', 'Hide'),
    'successTitle' => ai_chatbot_knowledge_label('aiChatbotKnowledge_swal_success_title', 'Berjaya'),
    'errorTitle' => ai_chatbot_knowledge_label('aiChatbotKnowledge_swal_error_title', 'Tidak berjaya'),
    'close' => ai_chatbot_knowledge_label('aiChatbotKnowledge_swal_close', 'Close'),
    'cancel' => ai_chatbot_knowledge_label('aiChatbotKnowledge_cancel', 'Cancel'),
    'deleteConfirmTitle' => ai_chatbot_knowledge_label('aiChatbotKnowledge_delete_confirm_title', 'Padam knowledge item?'),
    'deleteConfirmText' => ai_chatbot_knowledge_label('aiChatbotKnowledge_delete_confirm_text', 'Tindakan ini tidak boleh dibatalkan.'),
    'deleteConfirmButton' => ai_chatbot_knowledge_label('aiChatbotKnowledge_delete_confirm_button', 'Delete'),
    'opening' => ai_chatbot_knowledge_label('aiChatbotKnowledge_loading_opening', 'Opening'),
    'saving' => ai_chatbot_knowledge_label('aiChatbotKnowledge_loading_saving', 'Saving'),
    'uploading' => ai_chatbot_knowledge_label('aiChatbotKnowledge_loading_uploading', 'Uploading'),
    'reloadTableError' => ai_chatbot_knowledge_label('aiChatbotKnowledge_error_reload_table', 'Gagal memuat semula datatable.'),
    'updateTableError' => ai_chatbot_knowledge_label('aiChatbotKnowledge_error_update_table', 'Datatable tidak dapat dikemas kini.'),
    'actionFailed' => ai_chatbot_knowledge_label('aiChatbotKnowledge_error_action_failed', 'Tindakan tidak berjaya diproses.'),
    'openFailed' => ai_chatbot_knowledge_label('aiChatbotKnowledge_error_open_failed', 'Knowledge item tidak dapat dibuka.'),
    'saveFailed' => ai_chatbot_knowledge_label('aiChatbotKnowledge_error_save_failed', 'Knowledge item tidak berjaya disimpan.'),
    'pdfFailed' => ai_chatbot_knowledge_label('aiChatbotKnowledge_error_pdf_failed', 'PDF source tidak berjaya diproses.'),
    'actionSuccess' => ai_chatbot_knowledge_label('aiChatbotKnowledge_success_action', 'Tindakan berjaya diproses.'),
    'saveSuccess' => ai_chatbot_knowledge_label('aiChatbotKnowledge_success_save', 'Knowledge item berjaya disimpan.'),
    'pdfSuccess' => ai_chatbot_knowledge_label('aiChatbotKnowledge_success_pdf', 'PDF source berjaya diproses.'),
    'searchPlaceholder' => ai_chatbot_knowledge_label('aiChatbotKnowledge_search_placeholder', 'Search knowledge...'),
];
?>
<!doctype html>
<html lang="<?= h($lang) ?>" data-bs-theme="<?= h($_SESSION['theme.layout'] ?? 'light') ?>">
<head>
  <?php
    $NEED_DATERANGE = false;
    $NEED_VECTORMAP = false;
    $NEED_DATATABLES = true;
    $NEED_SELECT2 = false;
    include __DIR__ . '/../includes/head.php';
  ?>
  <meta name="csrf-token" content="<?= h((string)$_SESSION['csrf_token']) ?>">
  <link href="<?= h(base_url('assets/css/datatables-standard.css')) ?>?v=<?= h($version) ?>" rel="stylesheet">
  <script src="<?= h(base_url('assets/js/helpers/datatables-standard.js')) ?>?v=<?= h($version) ?>"></script>
  <style>
    .ai-knowledge-page { width: 100%; }
    .ai-knowledge-card {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(15,23,42,.06);
      overflow: hidden;
    }
    .ai-knowledge-card .card-header {
      border-bottom: 1px solid rgba(15,23,42,.08);
      background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(32,201,151,.08));
      padding: 1rem 1.15rem;
    }
    .ai-knowledge-card .card-body { padding: 1rem 1.15rem; }
    .ai-knowledge-icon {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 38px;
    }
    .ai-knowledge-stat {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 8px;
      padding: .72rem .82rem;
      background: var(--bs-body-bg);
      box-shadow: 0 6px 18px rgba(15,23,42,.045);
      min-height: 76px;
    }
    .ai-knowledge-stat-label {
      color: var(--bs-secondary-color);
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      font-weight: 700;
      line-height: 1.2;
    }
    .ai-knowledge-stat-value {
      font-size: 1.45rem;
      line-height: 1.1;
      font-weight: 750;
      color: var(--bs-heading-color);
    }
    .ai-knowledge-page .btn {
      border-radius: 8px !important;
      font-weight: 600;
    }
    .ai-knowledge-page .btn-sm {
      min-height: 31px;
      padding: .34rem .62rem;
      font-size: .78rem;
    }
    .ai-knowledge-page .form-control,
    .ai-knowledge-page .form-select {
      border-radius: 8px;
      border-color: #dbe4f0;
      min-height: 38px;
      font-size: .875rem;
    }
    .ai-knowledge-page .form-label {
      margin-bottom: .42rem;
      color: #334155;
      font-size: .82rem;
    }
    .ai-knowledge-table {
      width: 100%;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid rgba(148, 163, 184, .14);
      background: rgba(255, 255, 255, .96);
      margin-bottom: .15rem !important;
    }
    .ai-knowledge-table th,
    .ai-knowledge-table td,
    .ai-knowledge-table.table > :not(caption) > * > *,
    table.ai-knowledge-table.dataTable > thead > tr > th,
    table.ai-knowledge-table.dataTable > tbody > tr > th,
    table.ai-knowledge-table.dataTable > tbody > tr > td,
    table.ai-knowledge-table.dataTable tbody tr td,
    #aiKnowledgeTable tbody td,
    #aiKnowledgeTable tbody td.dtr-control {
      vertical-align: top !important;
      align-items: flex-start !important;
    }
    .ai-knowledge-table thead th {
      font-weight: 700;
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      padding: .82rem .78rem;
      border: 0;
      border-bottom: 1px solid rgba(148, 163, 184, .16);
      color: #334155;
      background: linear-gradient(180deg, rgba(248,250,252,.98) 0%, rgba(241,245,249,.95) 100%);
    }
    .ai-knowledge-table tbody td {
      padding: .82rem .78rem;
      border-color: rgba(226,232,240,.9);
      font-size: .845rem;
      vertical-align: top !important;
    }
    .ai-knowledge-table tbody td > *:first-child {
      margin-top: 0 !important;
    }
    .ai-knowledge-table tbody tr {
      background-color: transparent !important;
      transition: background-color .18s ease, box-shadow .18s ease;
    }
    .ai-knowledge-table tbody tr:hover {
      background: rgba(241,245,249,.88) !important;
      box-shadow: inset 0 0 0 999px rgba(241,245,249,.3);
    }
    .ai-knowledge-title-cell { min-width: 230px; max-width: 320px; }
    .ai-knowledge-answer { max-width: 460px; white-space: normal; }
    .ai-knowledge-clamp-wrap {
      position: relative;
      padding-right: 2.65rem;
    }
    .ai-knowledge-clamp {
      position: relative;
      overflow: hidden;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      line-height: 1.45;
      max-height: calc(1.45em * 2);
    }
    .ai-knowledge-clamp.is-expanded {
      display: block;
      max-height: none;
      -webkit-line-clamp: unset;
      line-clamp: unset;
    }
    .ai-knowledge-expand {
      position: absolute;
      right: 0;
      bottom: 0;
      border: 0;
      background: var(--bs-body-bg);
      color: #2563eb;
      font-size: .78rem;
      font-weight: 700;
      padding: 0 0 0 .3rem;
      margin: 0;
      line-height: 1.2;
    }
    .ai-knowledge-expand::before {
      content: "";
      position: absolute;
      right: 100%;
      bottom: 0;
      width: 1.25rem;
      height: 1.35rem;
      pointer-events: none;
      background: linear-gradient(90deg, rgba(var(--bs-body-bg-rgb), 0), var(--bs-body-bg));
    }
    .ai-knowledge-expand.d-none { display: none !important; }
    .ai-knowledge-action-btn {
      width: 31px;
      min-width: 31px;
      height: 31px;
      padding: 0 !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .ai-knowledge-action-btn i {
      font-size: .95rem;
      line-height: 1;
    }
    .ai-knowledge-action-btn .spinner-border {
      width: .92rem;
      height: .92rem;
      border-width: .14em;
    }
    .swal2-container {
      z-index: 20000 !important;
    }
    .ai-knowledge-group-list {
      max-height: 154px;
      overflow: auto;
      border: 1px solid var(--bs-border-color);
      border-radius: 8px;
      padding: .65rem .75rem;
      background: rgba(248,250,252,.5);
      font-size: .84rem;
    }
    .ai-knowledge-source-box {
      border: 1px solid rgba(148, 163, 184, .22) !important;
      background: rgba(248,250,252,.46);
    }
    .ai-knowledge-entry-tabs {
      gap: .3rem;
      border-bottom: 1px solid rgba(148, 163, 184, .18);
      margin-bottom: 1rem;
    }
    .ai-knowledge-entry-tabs .nav-link {
      border: 0;
      border-radius: 8px 8px 0 0;
      color: #475569;
      font-size: .84rem;
      font-weight: 700;
      padding: .62rem .78rem;
    }
    .ai-knowledge-entry-tabs .nav-link.active {
      color: #1d4ed8;
      background: rgba(59, 130, 246, .1);
    }
    .ai-knowledge-entry-modal .modal-dialog { max-width: min(1180px, calc(100vw - 2rem)); }
    .ai-knowledge-entry-modal .modal-content {
      border: 0;
      border-radius: 10px;
      box-shadow: none !important;
    }
    .ai-knowledge-entry-modal .modal-header {
      border-bottom: 1px solid rgba(148,163,184,.18);
      background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(32,201,151,.08));
      padding: 1rem 1.15rem;
    }
    .ai-knowledge-entry-modal .modal-body {
      padding: 1rem 1.15rem .85rem;
      background: rgba(248,250,252,.42);
    }
    .ai-knowledge-entry-modal .modal-footer {
      position: sticky;
      bottom: 0;
      z-index: 5;
      justify-content: space-between;
      gap: .75rem;
      border-top: 1px solid rgba(148,163,184,.18);
      background: var(--bs-body-bg);
      box-shadow: none !important;
      padding: .85rem 1.15rem;
    }
    .ai-knowledge-modal-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: .5rem;
    }
    .ai-knowledge-modal-subtabs {
      flex-direction: column;
      gap: .35rem;
      margin: 0;
      border: 0;
    }
    .ai-knowledge-modal-subtabs .nav-link {
      border: 1px solid rgba(148,163,184,.22);
      border-radius: 8px;
      color: #475569;
      background: var(--bs-body-bg);
      font-size: .82rem;
      font-weight: 700;
      padding: .52rem .72rem;
    }
    .ai-knowledge-modal-subtabs .nav-link.active {
      border-color: rgba(37,99,235,.25);
      color: #1d4ed8;
      background: rgba(37,99,235,.08);
    }
    .ai-knowledge-subtab-layout {
      display: grid;
      grid-template-columns: 260px minmax(0, 1fr);
      gap: 1rem;
      align-items: start;
    }
    .ai-knowledge-subtab-sidebar {
      position: sticky;
      top: 0;
      border: 1px solid rgba(148,163,184,.18);
      border-radius: 8px;
      background: var(--bs-body-bg);
      padding: .55rem;
    }
    .ai-knowledge-subtab-sidebar .nav-item,
    .ai-knowledge-subtab-sidebar .nav-link {
      width: 100%;
    }
    .ai-knowledge-subtab-sidebar .nav-link {
      justify-content: flex-start;
      text-align: left;
    }
    .ai-knowledge-subtab-content {
      min-width: 0;
    }
    .ai-knowledge-modal-section {
      border: 1px solid rgba(148,163,184,.18);
      border-radius: 8px;
      background: var(--bs-body-bg);
      padding: 1rem;
    }
    @media (max-width: 767.98px) {
      .ai-knowledge-subtab-layout {
        grid-template-columns: 1fr;
      }
      .ai-knowledge-subtab-sidebar {
        position: static;
      }
      .ai-knowledge-modal-subtabs {
        flex-direction: row;
      }
    }
    .ai-knowledge-upload-panel {
      border: 1px dashed rgba(59, 130, 246, .34);
      border-radius: 8px;
      padding: 1rem;
      background: rgba(59, 130, 246, .045);
    }
    .ai-knowledge-upload-note {
      border: 1px solid rgba(245, 158, 11, .26);
      border-radius: 8px;
      padding: .72rem .82rem;
      color: #92400e;
      background: rgba(245, 158, 11, .08);
      font-size: .84rem;
    }
    .ai-knowledge-actions {
      display: flex;
      justify-content: flex-end;
      flex-wrap: wrap;
      gap: .35rem;
      min-width: 150px;
    }
    #aiKnowledgeTable_wrapper {
      padding: 0 .45rem .15rem;
    }
    #aiKnowledgeTable_wrapper .row.mb-2 {
      align-items: center;
      margin-bottom: .55rem !important;
    }
    #aiKnowledgeTable_wrapper .dt-top-left,
    #aiKnowledgeTable_wrapper .dt-top-right {
      top: 0 !important;
      padding-left: .55rem !important;
      padding-right: .55rem !important;
      transform: translateY(8px);
    }
    #aiKnowledgeTable_wrapper .dataTables_filter {
      text-align: right;
      padding-right: .3rem;
      transform: none;
    }
    #aiKnowledgeTable_wrapper .dataTables_filter label {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      margin-bottom: 0;
      font-size: .875rem;
    }
    #aiKnowledgeTable_wrapper .dataTables_filter input {
      border: 2px solid #e9ecef;
      border-radius: 8px;
      min-height: 36px;
      padding: .45rem .72rem;
      font-size: .875rem;
      max-width: calc(100vw - 5rem);
    }
    #aiKnowledgeTable_wrapper .dataTables_length label {
      display: inline-flex !important;
      align-items: center;
      gap: .4rem;
      margin-bottom: 0;
      white-space: nowrap !important;
      font-size: .875rem !important;
    }
    #aiKnowledgeTable_wrapper .dataTables_length select {
      height: 36px !important;
      min-height: 36px !important;
      min-width: 70px !important;
      margin: 0 .35rem !important;
      border: 2px solid #e9ecef !important;
      border-radius: 8px !important;
      font-size: .875rem !important;
    }
    #aiKnowledgeTable_wrapper .dataTables_info {
      margin: 0;
      color: var(--bs-secondary-color);
      font-size: .875rem;
      line-height: 1.5;
      white-space: nowrap;
    }
    #aiKnowledgeTable_wrapper .dt-bottom-row {
      align-items: center !important;
      margin-top: 0 !important;
      padding: 0 .65rem 0 !important;
      transform: translateY(7px);
    }
    #aiKnowledgeTable_wrapper .dt-bottom-row > .dt-info-left {
      padding-left: .15rem !important;
      padding-right: .5rem !important;
    }
    #aiKnowledgeTable_wrapper .dt-bottom-row > .dt-paging-right {
      top: 0 !important;
      padding-right: .15rem !important;
    }
    #aiKnowledgeTable_wrapper .dataTables_paginate {
      margin-top: 0 !important;
      padding-top: 0 !important;
    }
    #aiKnowledgeTable_wrapper .dataTables_paginate .pagination {
      margin: 0 !important;
    }
    html[data-bs-theme="dark"] .ai-knowledge-table {
      background: rgba(15,23,42,.96);
      border-color: rgba(148,163,184,.18);
    }
    html[data-bs-theme="dark"] .ai-knowledge-table thead th {
      background: linear-gradient(180deg, rgba(30,41,59,.96) 0%, rgba(15,23,42,.94) 100%);
      color: #dbe4f0;
      border-bottom-color: rgba(148,163,184,.18);
    }
    html[data-bs-theme="dark"] .ai-knowledge-table tbody tr:hover {
      background: rgba(30,41,59,.76) !important;
      box-shadow: inset 0 0 0 999px rgba(30,41,59,.18);
    }
    html[data-bs-theme="dark"] .ai-knowledge-expand {
      background: #0f172a;
    }
    html[data-bs-theme="dark"] .ai-knowledge-expand::before {
      background: linear-gradient(90deg, rgba(15,23,42,0), #0f172a);
    }
  </style>
</head>
<body data-topbar-color="<?= h($_SESSION['theme.topbar'] ?? 'light') ?>"
      data-menu-color="<?= h($_SESSION['theme.menu'] ?? $_SESSION['theme.sidebar'] ?? 'dark') ?>"
      data-layout="vertical" data-sidebar-size="default" class="loading">
<div class="wrapper">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="content-page">
    <div class="content">
      <div class="container-fluid">
        <div class="row mb-3">
          <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
              <h4 class="page-title"><i class="ri-chat-quote-line me-1"></i><?= h($PAGE_TITLE) ?></h4>
              <div class="page-title-right">
                <ol class="breadcrumb m-0">
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/dashboard.php')) ?>"><?= h(ai_chatbot_knowledge_label('common_dashboard', 'Dashboard')) ?></a></li>
                  <li class="breadcrumb-item"><a href="<?= h(base_url('pages/tetapan-sistem.php?tab=ai-chatbot')) ?>"><?= h(ai_chatbot_knowledge_label('config_ai_chatbot_title', 'AI Chatbot')) ?></a></li>
                  <li class="breadcrumb-item active"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_breadcrumb_knowledge', 'Knowledge')) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <?php if ($message !== ''): ?>
          <div class="alert alert-<?= h($messageType) ?>"><?= h($message) ?></div>
        <?php endif; ?>

        <div class="ai-knowledge-page">
        <div class="row g-2 mb-3">
          <?php foreach ([
            ['label' => 'Total', 'value' => (int)($summary['total'] ?? 0), 'sub' => 'Manual text items', 'icon' => 'ri-database-2-line', 'text' => 'text-primary', 'bg' => 'bg-primary-subtle'],
            ['label' => 'Active', 'value' => (int)($summary['active'] ?? 0), 'sub' => 'Manual active status', 'icon' => 'ri-checkbox-circle-line', 'text' => 'text-success', 'bg' => 'bg-success-subtle'],
            ['label' => 'Manual Ready', 'value' => $manualReadyCount, 'sub' => $manualNotReadyCount . ' not ready', 'icon' => 'ri-check-double-line', 'text' => 'text-success', 'bg' => 'bg-success-subtle'],
            ['label' => 'PDF Ready', 'value' => $pdfReadyCount, 'sub' => $pdfNotReadyCount . ' not ready', 'icon' => 'ri-file-check-line', 'text' => 'text-info', 'bg' => 'bg-info-subtle'],
            ['label' => 'Draft', 'value' => (int)($summary['draft'] ?? 0), 'sub' => 'Manual draft status', 'icon' => 'ri-draft-line', 'text' => 'text-warning', 'bg' => 'bg-warning-subtle'],
            ['label' => 'Archived', 'value' => (int)($summary['archived'] ?? 0), 'sub' => 'Manual archived status', 'icon' => 'ri-archive-line', 'text' => 'text-secondary', 'bg' => 'bg-secondary-subtle'],
          ] as $meta): ?>
            <div class="col-sm-6 col-xl-2">
              <div class="ai-knowledge-stat d-flex align-items-center justify-content-between gap-2">
                <div>
                  <div class="ai-knowledge-stat-label"><?= h((string)$meta['label']) ?></div>
                  <div class="ai-knowledge-stat-value"><?= h((string)$meta['value']) ?></div>
                  <div class="small text-muted"><?= h((string)$meta['sub']) ?></div>
                </div>
                <span class="ai-knowledge-icon <?= h((string)$meta['bg']) ?> <?= h((string)$meta['text']) ?>">
                  <i class="<?= h((string)$meta['icon']) ?> fs-5"></i>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="row g-3">
          <div class="modal fade ai-knowledge-entry-modal" id="aiKnowledgeEntryModal" tabindex="-1" aria-labelledby="aiKnowledgeEntryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
              <div class="modal-content ai-knowledge-card">
              <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                  <span class="ai-knowledge-icon bg-primary-subtle text-primary"><i class="ri-edit-box-line fs-5"></i></span>
                  <div>
                    <h5 class="modal-title" id="aiKnowledgeEntryModalLabel"><?= h($editRow ? $knowledgeI18n['editTitle'] : $knowledgeI18n['createTitle']) ?></h5>
                    <div class="text-muted small"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_modal_subtitle', 'Curated context mengikut visibility dan group.')) ?></div>
                  </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_close', 'Close')) ?>"></button>
              </div>
              <div class="modal-body">

                <ul class="nav nav-tabs ai-knowledge-entry-tabs" id="aiKnowledgeEntryTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="manual-knowledge-tab" data-bs-toggle="tab" data-bs-target="#manual-knowledge-pane" type="button" role="tab" aria-controls="manual-knowledge-pane" aria-selected="true">
                      <i class="ri-edit-box-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_manual_tab', 'Manual Text')) ?>
                    </button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pdf-knowledge-tab" data-bs-toggle="tab" data-bs-target="#pdf-knowledge-pane" type="button" role="tab" aria-controls="pdf-knowledge-pane" aria-selected="false">
                      <i class="ri-file-pdf-2-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_pdf_tab', 'PDF Source')) ?>
                    </button>
                  </li>
                </ul>

                <div class="tab-content" id="aiKnowledgeEntryTabsContent">
                  <div class="tab-pane fade show active" id="manual-knowledge-pane" role="tabpanel" aria-labelledby="manual-knowledge-tab" tabindex="0">
                <form method="post" autocomplete="off" id="manualKnowledgeForm">
                  <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="save">
                  <input type="hidden" name="public_id" value="<?= h((string)($editRow['f_publicID'] ?? '')) ?>">
                  <input type="hidden" name="source_type" value="manual_text">

                  <div class="ai-knowledge-subtab-layout">
                    <div class="ai-knowledge-subtab-sidebar">
                  <ul class="nav nav-pills ai-knowledge-modal-subtabs" id="manualKnowledgeSubtabs" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="manual-content-tab" data-bs-toggle="tab" data-bs-target="#manual-content-pane" type="button" role="tab" aria-controls="manual-content-pane" aria-selected="true">
                        <i class="ri-file-text-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_content_tab', 'Content')) ?>
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="manual-access-tab" data-bs-toggle="tab" data-bs-target="#manual-access-pane" type="button" role="tab" aria-controls="manual-access-pane" aria-selected="false">
                        <i class="ri-shield-user-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_access_tab', 'Access')) ?>
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="manual-review-tab" data-bs-toggle="tab" data-bs-target="#manual-review-pane" type="button" role="tab" aria-controls="manual-review-pane" aria-selected="false">
                        <i class="ri-calendar-check-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_review_tab', 'Review')) ?>
                      </button>
                    </li>
                  </ul>
                    </div>

                    <div class="tab-content ai-knowledge-subtab-content">
                    <div class="tab-pane fade show active ai-knowledge-modal-section" id="manual-content-pane" role="tabpanel" aria-labelledby="manual-content-tab" tabindex="0">
                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="knowledge_title"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_title_label', 'Title')) ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="knowledge_title" name="title" maxlength="255" required value="<?= h((string)($editRow['f_title'] ?? '')) ?>">
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="knowledge_question"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_question_label', 'Question')) ?></label>
                    <textarea class="form-control" id="knowledge_question" name="question" rows="2" maxlength="500"><?= h((string)($editRow['f_question'] ?? '')) ?></textarea>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="knowledge_answer"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_answer_label', 'Answer')) ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="knowledge_answer" name="answer" rows="8" required><?= h((string)($editRow['f_answer'] ?? '')) ?></textarea>
                  </div>

                  <div>
                    <label class="form-label fw-semibold" for="knowledge_tags"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_tags_label', 'Tags')) ?></label>
                    <input type="text" class="form-control" id="knowledge_tags" name="tags" maxlength="500" placeholder="login, dashboard, sop" value="<?= h((string)($editRow['f_tags'] ?? '')) ?>">
                  </div>
                  </div>

                    <div class="tab-pane fade ai-knowledge-modal-section" id="manual-access-pane" role="tabpanel" aria-labelledby="manual-access-tab" tabindex="0">
                  <div class="row g-2">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="knowledge_language"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_language_label', 'Language')) ?></label>
                      <select class="form-select" id="knowledge_language" name="language">
                        <?php foreach ($languageOptions as $value => $label): ?>
                          <option value="<?= h($value) ?>" <?= ($selectedLanguage === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="knowledge_status"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_status_label', 'Status')) ?></label>
                      <select class="form-select" id="knowledge_status" name="status">
                        <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label): ?>
                          <option value="<?= h($value) ?>" <?= ((string)($editRow['f_status'] ?? 'draft') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="row g-2 mt-1">
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="knowledge_visibility"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_visibility_label', 'Visibility')) ?></label>
                      <select class="form-select" id="knowledge_visibility" name="visibility">
                        <?php foreach (['selected_groups' => 'Selected groups', 'all_authenticated' => 'All authenticated', 'super_admin_only' => 'Super admin only'] as $value => $label): ?>
                          <option value="<?= h($value) ?>" <?= ((string)($editRow['f_visibility'] ?? 'selected_groups') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="knowledge_priority"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_priority_label', 'Priority')) ?></label>
                      <input type="number" class="form-control" id="knowledge_priority" name="priority" min="1" max="9999" value="<?= h((string)($editRow['f_priority'] ?? 100)) ?>">
                    </div>
                  </div>

                  <div class="mb-3 mt-3">
                    <label class="form-label fw-semibold"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_allowed_groups_label', 'Allowed groups')) ?></label>
                    <div class="ai-knowledge-group-list">
                      <?php if ($groups === []): ?>
                        <div class="text-muted small">Tiada group ditemui.</div>
                      <?php endif; ?>
                      <?php foreach ($groups as $group): ?>
                        <?php
                          $groupId = (string)($group['f_groupID'] ?? '');
                          $groupCode = trim((string)($group['f_groupKod'] ?? ''));
                          $groupName = trim((string)($group['f_groupName'] ?? $groupCode));
                          $checked = in_array($groupId, $allowedGroupValues, true) || ($groupCode !== '' && in_array($groupCode, $allowedGroupValues, true));
                        ?>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="allowed_groups[]" id="kg_<?= h($groupId) ?>" value="<?= h($groupId) ?>" <?= $checked ? 'checked' : '' ?>>
                          <label class="form-check-label" for="kg_<?= h($groupId) ?>"><?= h($groupName) ?><?= $groupCode !== '' ? ' (' . h($groupCode) . ')' : '' ?></label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <div class="form-text"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_allowed_groups_help', 'Wajib jika visibility ialah selected groups.')) ?></div>
                  </div>
                    </div>

                    <div class="tab-pane fade ai-knowledge-modal-section" id="manual-review-pane" role="tabpanel" aria-labelledby="manual-review-tab" tabindex="0">
                  <div class="ai-knowledge-source-box rounded-3 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="ai-knowledge-icon bg-info-subtle text-info"><i class="ri-file-list-3-line fs-5"></i></span>
                      <div>
                        <div class="fw-semibold"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_source_review_title', 'Source & Review')) ?></div>
                        <div class="text-muted small"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_source_review_help', 'Metadata untuk versioning dan semakan berkala.')) ?></div>
                      </div>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-semibold" for="knowledge_source_title"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_source_title_label', 'Source title')) ?></label>
                      <input type="text" class="form-control" id="knowledge_source_title" name="source_title" maxlength="255" placeholder="Contoh: Polisi Login Pengguna v1" value="<?= h((string)($editRow['f_sourceTitle'] ?? '')) ?>">
                    </div>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label fw-semibold" for="knowledge_version"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_version_label', 'Version')) ?></label>
                        <input type="text" class="form-control" id="knowledge_version" name="version" maxlength="50" placeholder="v1.0" value="<?= h((string)($editRow['f_version'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold" for="knowledge_review_status"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_review_status_label', 'Review status')) ?></label>
                        <select class="form-select" id="knowledge_review_status" name="review_status">
                          <?php foreach ($reviewStatusOptions as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($selectedReviewStatus === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                    <div class="row g-2 mt-1">
                      <div class="col-md-6">
                        <label class="form-label fw-semibold" for="knowledge_effective_date"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_effective_date_label', 'Effective date')) ?></label>
                        <input type="date" class="form-control" id="knowledge_effective_date" name="effective_date" value="<?= h((string)($editRow['f_effectiveDate'] ?? '')) ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-semibold" for="knowledge_review_due_date"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_review_due_date_label', 'Review due date')) ?></label>
                        <input type="date" class="form-control" id="knowledge_review_due_date" name="review_due_date" value="<?= h((string)($editRow['f_reviewDueDate'] ?? '')) ?>">
                      </div>
                    </div>
                  </div>
                    </div>
                  </div>
                  </div>
                </form>
                  </div>

                  <div class="tab-pane fade" id="pdf-knowledge-pane" role="tabpanel" aria-labelledby="pdf-knowledge-tab" tabindex="0">
                    <div class="ai-knowledge-subtab-layout">
                      <div class="ai-knowledge-subtab-sidebar">
                    <ul class="nav nav-pills ai-knowledge-modal-subtabs" id="pdfKnowledgeSubtabs" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pdf-upload-tab" data-bs-toggle="tab" data-bs-target="#pdf-upload-pane" type="button" role="tab" aria-controls="pdf-upload-pane" aria-selected="true">
                          <i class="ri-upload-cloud-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_upload_pdf', 'Upload PDF')) ?>
                        </button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pdf-sources-tab" data-bs-toggle="tab" data-bs-target="#pdf-sources-pane" type="button" role="tab" aria-controls="pdf-sources-pane" aria-selected="false">
                          <i class="ri-file-list-3-line me-1"></i>Sources
                        </button>
                      </li>
                    </ul>
                      </div>

                      <div class="tab-content ai-knowledge-subtab-content">
                      <div class="tab-pane fade show active ai-knowledge-modal-section" id="pdf-upload-pane" role="tabpanel" aria-labelledby="pdf-upload-tab" tabindex="0">
                    <form method="post" enctype="multipart/form-data" autocomplete="off" id="pdfKnowledgeForm">
                      <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="upload_pdf">
                      <input type="hidden" name="MAX_FILE_SIZE" value="<?= h((string)($pdfUploadMaxMb * 1024 * 1024)) ?>">
                      <input type="hidden" name="review_status" value="draft">
                      <div class="ai-knowledge-upload-panel mb-3">
                        <div class="d-flex align-items-start gap-2">
                          <span class="ai-knowledge-icon bg-danger-subtle text-danger"><i class="ri-file-pdf-2-line fs-5"></i></span>
                          <div>
                            <div class="fw-semibold"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_pdf_upload_title', 'Upload PDF policy or system flow')) ?></div>
                            <div class="text-muted small"><?= h(sprintf(ai_chatbot_knowledge_label('aiChatbotKnowledge_pdf_upload_help', 'PDF sahaja. Saiz maksimum ikut Tetapan Sistem: %s MB.'), (string)$pdfUploadMaxMb)) ?></div>
                          </div>
                        </div>
                        <div class="mt-3">
                          <label class="form-label fw-semibold" for="knowledge_pdf_file"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_pdf_file_label', 'PDF file')) ?></label>
                          <input type="file" class="form-control" id="knowledge_pdf_file" name="knowledge_pdf_file" accept="application/pdf,.pdf" required <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                        </div>
                      </div>

                      <div class="mb-3">
                        <label class="form-label fw-semibold" for="pdf_source_title"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_source_title_label', 'Source title')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pdf_source_title" name="source_title" maxlength="255" placeholder="Contoh: Polisi Keselamatan Sistem" required <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                      </div>

                      <div class="row g-2">
                        <div class="col-md-6">
                          <label class="form-label fw-semibold" for="pdf_language"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_language_label', 'Language')) ?></label>
                          <select class="form-select" id="pdf_language" name="language" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                            <?php foreach ($languageOptions as $value => $label): ?>
                              <option value="<?= h($value) ?>"><?= h($label) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-semibold" for="pdf_priority"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_priority_label', 'Priority')) ?></label>
                          <input type="number" class="form-control" id="pdf_priority" name="priority" min="1" max="9999" value="100" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                        </div>
                      </div>

                      <div class="row g-2 mt-1">
                        <div class="col-md-6">
                          <label class="form-label fw-semibold" for="pdf_visibility"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_visibility_label', 'Visibility')) ?></label>
                          <select class="form-select" id="pdf_visibility" name="visibility" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                            <option value="selected_groups">Selected groups</option>
                            <option value="all_authenticated">All authenticated</option>
                            <option value="super_admin_only">Super admin only</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-semibold" for="pdf_version"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_version_label', 'Version')) ?></label>
                          <input type="text" class="form-control" id="pdf_version" name="version" maxlength="50" placeholder="v1.0" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                        </div>
                      </div>

                      <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_allowed_groups_label', 'Allowed groups')) ?></label>
                        <div class="ai-knowledge-group-list">
                          <?php if ($groups === []): ?>
                            <div class="text-muted small">Tiada group ditemui.</div>
                          <?php endif; ?>
                          <?php foreach ($groups as $group): ?>
                            <?php
                              $groupId = (string)($group['f_groupID'] ?? '');
                              $groupCode = trim((string)($group['f_groupKod'] ?? ''));
                              $groupName = trim((string)($group['f_groupName'] ?? $groupCode));
                            ?>
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="allowed_groups[]" id="pdf_kg_<?= h($groupId) ?>" value="<?= h($groupId) ?>" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                              <label class="form-check-label" for="pdf_kg_<?= h($groupId) ?>"><?= h($groupName) ?><?= $groupCode !== '' ? ' (' . h($groupCode) . ')' : '' ?></label>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>

                      <div class="row g-2">
                        <div class="col-md-6">
                          <label class="form-label fw-semibold" for="pdf_effective_date"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_effective_date_label', 'Effective date')) ?></label>
                          <input type="date" class="form-control" id="pdf_effective_date" name="effective_date" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-semibold" for="pdf_review_due_date"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_review_due_date_label', 'Review due date')) ?></label>
                          <input type="date" class="form-control" id="pdf_review_due_date" name="review_due_date" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                        </div>
                      </div>

                      <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold" for="pdf_tags"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_tags_label', 'Tags')) ?></label>
                        <input type="text" class="form-control" id="pdf_tags" name="tags" maxlength="500" placeholder="policy, sop, workflow" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                      </div>

                      <div class="ai-knowledge-upload-note mb-3">
                        <i class="ri-information-line me-1"></i>
                        <?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_pdf_note', 'PDF akan disimpan sebagai draft source. Sistem akan cuba extract text dan jana chunk draft selepas upload.')) ?>
                      </div>

                      <?php if (!$sourceTableAvailable): ?>
                        <div class="alert alert-warning mb-3"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_pdf_source_missing', 'Table PDF source belum tersedia. Sila pastikan migration schema PDF sudah dijalankan.')) ?></div>
                      <?php elseif (!$chunkTableAvailable): ?>
                        <div class="alert alert-warning mb-3"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_pdf_chunk_missing', 'Table PDF chunk belum tersedia. Upload dan extraction boleh berjalan, tetapi chunk tidak akan dijana.')) ?></div>
                      <?php endif; ?>
                    </form>
                      </div>

                      <div class="tab-pane fade ai-knowledge-modal-section" id="pdf-sources-pane" role="tabpanel" aria-labelledby="pdf-sources-tab" tabindex="0">
                    <div>
                      <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <div>
                          <div class="fw-semibold"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_uploaded_pdf_title', 'Uploaded PDF Sources')) ?></div>
                          <div class="text-muted small"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_uploaded_pdf_help', 'Text yang berjaya diekstrak disimpan sebagai sidecar .txt; chunk hanya digunakan oleh chatbot selepas PDF source diaktifkan.')) ?></div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary"><?= h((string)count($pdfSources)) ?></span>
                      </div>
                      <div class="table-responsive">
                        <table class="table table-sm table-striped ai-knowledge-table mb-0">
                          <thead>
                            <tr>
                              <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_table_title', 'Title')) ?></th>
                              <th>File</th>
                              <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_status_label', 'Status')) ?></th>
                              <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_table_retrieval', 'Retrieval')) ?></th>
                              <th class="text-end"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_table_actions', 'Actions')) ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if ($pdfSources === []): ?>
                              <tr><td colspan="5" class="text-muted text-center"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_no_pdf_source', 'Tiada PDF source dimuat naik.')) ?></td></tr>
                            <?php endif; ?>
                            <?php foreach (array_slice($pdfSources, 0, 8) as $source): ?>
                              <?php
                                $sourcePublicId = (string)($source['f_publicID'] ?? '');
                                $sourceStatus = (string)($source['f_status'] ?? 'draft');
                                $sourceStatusClass = match ($sourceStatus) {
                                    'active' => 'bg-success-subtle text-success',
                                    'archived' => 'bg-secondary-subtle text-secondary',
                                    default => 'bg-warning-subtle text-warning',
                                };
                                $extractStatus = (string)($source['f_extractionStatus'] ?? 'pending');
                                $sourceChunkCount = (int)($pdfChunkCounts[$sourcePublicId] ?? (int)($source['f_chunkCount'] ?? 0));
                                $sourceActivationState = ai_chatbot_pdf_activation_state($source, $sourceChunkCount);
                                $canActivateSource = $sourceActivationState['allowed'];
                                $sourceRetrievalState = ai_chatbot_pdf_retrieval_state($source, $sourceChunkCount);
                              ?>
                              <tr>
                                <td>
                                  <div class="fw-semibold"><?= h((string)($source['f_title'] ?? '')) ?></div>
                                  <div class="small text-muted"><?= h((string)($source['f_language'] ?? 'ms')) ?><?= !empty($source['f_version']) ? ' / ' . h((string)$source['f_version']) : '' ?></div>
                                </td>
                                <td>
                                  <div><?= h((string)($source['f_originalFilename'] ?? '')) ?></div>
                                  <div class="small text-muted"><?= h(number_format(((int)($source['f_fileSizeBytes'] ?? 0)) / 1024 / 1024, 2)) ?> MB</div>
                                </td>
                                <td>
                                  <span class="badge <?= h($sourceStatusClass) ?>"><?= h($sourceStatus) ?></span>
                                  <?php
                                    $extractClass = match ($extractStatus) {
                                        'processed' => 'text-success',
                                        'failed' => 'text-danger',
                                        'processing' => 'text-info',
                                        default => 'text-muted',
                                    };
                                  ?>
                                  <div class="small <?= h($extractClass) ?>"><?= h($extractStatus) ?><?= (int)($source['f_extractedCharCount'] ?? 0) > 0 ? ' / ' . h((string)(int)$source['f_extractedCharCount']) . ' chars' : '' ?></div>
                                  <div class="small text-muted">Chunks: <?= h((string)$sourceChunkCount) ?></div>
                                  <?php if (!empty($source['f_extractionError'])): ?>
                                    <div class="small text-danger"><?= h((string)$source['f_extractionError']) ?></div>
                                  <?php endif; ?>
                                </td>
                                <td>
                                  <span class="badge <?= h($sourceRetrievalState['class']) ?>"><?= h($sourceRetrievalState['label']) ?></span>
                                  <div class="small text-muted mt-1"><?= h($sourceRetrievalState['reason']) ?></div>
                                </td>
                                <td>
                                  <div class="ai-knowledge-actions">
                                    <?php foreach (['pdf_active' => 'Activate', 'pdf_draft' => 'Draft', 'pdf_archived' => 'Archive'] as $pdfAction => $label): ?>
                                      <?php
                                        $targetStatus = str_replace('pdf_', '', $pdfAction);
                                        $disabled = $targetStatus === $sourceStatus || ($pdfAction === 'pdf_active' && !$canActivateSource);
                                        $pdfActionIcon = match ($pdfAction) {
                                            'pdf_active' => 'ri-checkbox-circle-line',
                                            'pdf_archived' => 'ri-archive-line',
                                            default => 'ri-draft-line',
                                        };
                                      ?>
                                      <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="public_id" value="<?= h($sourcePublicId) ?>">
                                        <input type="hidden" name="action" value="<?= h($pdfAction) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary ai-knowledge-action-btn" data-bs-toggle="tooltip" data-bs-title="<?= h($label . ' PDF source') ?>" <?= $disabled ? 'disabled' : '' ?>>
                                          <i class="<?= h($pdfActionIcon) ?>" aria-hidden="true"></i><span class="visually-hidden"><?= h($label) ?></span>
                                        </button>
                                      </form>
                                    <?php endforeach; ?>
                                  </div>
                                  <?php if (!$canActivateSource && $sourceStatus !== 'active'): ?>
                                    <div class="small text-muted text-end mt-1"><?= h($sourceActivationState['reason']) ?></div>
                                  <?php endif; ?>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                      </div>
                    </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <div class="text-muted small">
                  <i class="ri-lock-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_footer_note', 'Content hanya digunakan selepas status dan visibility membenarkan retrieval.')) ?>
                </div>
                <div class="ai-knowledge-modal-actions">
                  <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_close', 'Close')) ?></button>
                  <button type="submit" class="btn btn-primary px-4" form="manualKnowledgeForm" id="manualKnowledgeSubmit" <?= !$tableAvailable ? 'disabled' : '' ?>>
                    <i class="ri-save-line me-1"></i><?= h($editRow ? ai_chatbot_knowledge_label('aiChatbotKnowledge_update', 'Update Knowledge') : ai_chatbot_knowledge_label('aiChatbotKnowledge_save', 'Save Knowledge')) ?>
                  </button>
                  <button type="submit" class="btn btn-primary px-4 d-none" form="pdfKnowledgeForm" id="pdfKnowledgeSubmit" <?= !$sourceTableAvailable ? 'disabled' : '' ?>>
                    <i class="ri-upload-cloud-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_upload_pdf', 'Upload PDF')) ?>
                  </button>
                </div>
              </div>
            </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card ai-knowledge-card">
              <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div>
                    <h5 class="card-title mb-1"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_registry_title', 'Knowledge Registry')) ?></h5>
                    <p class="text-muted mb-0"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_registry_subtitle', 'Active items sahaja dihantar kepada provider selepas filter language dan visibility.')) ?></p>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary px-4" id="aiKnowledgeCreateButton" data-bs-toggle="modal" data-bs-target="#aiKnowledgeEntryModal">
                      <i class="ri-add-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_create_button', 'Create Knowledge Item')) ?>
                    </button>
                    <a class="btn btn-outline-secondary px-4" id="aiKnowledgeSettingsButton" href="<?= h(base_url('pages/tetapan-sistem.php?tab=ai-chatbot')) ?>">
                      <i class="ri-settings-3-line me-1"></i><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_settings_button', 'AI Chatbot Settings')) ?>
                    </a>
                  </div>
                </div>
              </div>
              <div class="card-body">

                <div class="table-responsive">
                  <table class="table table-striped ai-knowledge-table" id="aiKnowledgeTable">
                    <thead>
                      <tr>
                        <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_table_title', 'Title')) ?></th>
                        <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_table_context', 'Context')) ?></th>
                        <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_visibility_label', 'Visibility')) ?></th>
                        <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_status_label', 'Status')) ?></th>
                        <th><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_table_retrieval', 'Retrieval')) ?></th>
                        <th class="text-end"><?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_table_actions', 'Actions')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($records as $row): ?>
                      <?php
                        $publicId = (string)($row['f_publicID'] ?? '');
                        $status = (string)($row['f_status'] ?? 'draft');
                        $rowLanguage = (string)($row['f_language'] ?? 'ms');
                        $rowLanguageLabel = $languageOptions[$rowLanguage] ?? $rowLanguage;
                        $reviewStatus = (string)($row['f_reviewStatus'] ?? 'draft');
                        $reviewStatusLabel = $reviewStatusOptions[$reviewStatus] ?? $reviewStatus;
                        $sourceTitle = trim((string)($row['f_sourceTitle'] ?? ''));
                        $version = trim((string)($row['f_version'] ?? ''));
                        $reviewDueDate = ai_chatbot_knowledge_display_date((string)($row['f_reviewDueDate'] ?? ''));
                        $retrievalState = ai_chatbot_manual_retrieval_state($row);
                        $activationState = ai_chatbot_manual_activation_state($row);
                        $statusClass = match ($status) {
                            'active' => 'bg-success-subtle text-success',
                            'archived' => 'bg-secondary-subtle text-secondary',
                            default => 'bg-warning-subtle text-warning',
                        };
                        $reviewStatusClass = match ($reviewStatus) {
                            'approved' => 'bg-success-subtle text-success',
                            'reviewed' => 'bg-info-subtle text-info',
                            'needs_update' => 'bg-danger-subtle text-danger',
                            default => 'bg-warning-subtle text-warning',
                        };
                      ?>
                      <tr>
                        <td class="ai-knowledge-title-cell align-top">
                          <div class="ai-knowledge-clamp-wrap">
                            <div class="ai-knowledge-clamp" data-knowledge-clamp>
                              <div class="fw-semibold"><?= h((string)($row['f_title'] ?? '')) ?></div>
                              <?php if (trim((string)($row['f_question'] ?? '')) !== ''): ?>
                                <div class="small text-muted"><?= h((string)($row['f_question'] ?? '')) ?></div>
                              <?php endif; ?>
                            </div>
                            <button type="button" class="ai-knowledge-expand d-none" data-knowledge-toggle><?= h($knowledgeI18n['show']) ?></button>
                          </div>
                        </td>
                        <td class="ai-knowledge-answer align-top">
                          <div class="ai-knowledge-clamp-wrap">
                            <div class="ai-knowledge-clamp" data-knowledge-clamp>
                              <div><?= h(strip_tags((string)($row['f_answer'] ?? ''))) ?></div>
                              <div class="small text-muted mt-1">Tags: <?= h((string)($row['f_tags'] ?? '-')) ?></div>
                              <?php if ($sourceTitle !== '' || $version !== ''): ?>
                                <div class="small text-muted mt-1">Source: <?= h($sourceTitle !== '' ? $sourceTitle : '-') ?><?= $version !== '' ? ' / ' . h($version) : '' ?></div>
                              <?php endif; ?>
                            </div>
                            <button type="button" class="ai-knowledge-expand d-none" data-knowledge-toggle><?= h($knowledgeI18n['show']) ?></button>
                          </div>
                        </td>
                        <td class="align-top">
                          <div><span class="badge bg-info-subtle text-info"><?= h($rowLanguageLabel) ?></span></div>
                          <div class="small mt-1"><?= h((string)($row['f_visibility'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string)($row['f_allowedGroups'] ?? '')) ?></div>
                        </td>
                        <td class="align-top">
                          <span class="badge <?= h($statusClass) ?>"><?= h($status) ?></span>
                          <span class="badge <?= h($reviewStatusClass) ?>"><?= h($reviewStatusLabel) ?></span>
                          <?php if ($reviewDueDate !== ''): ?>
                            <div class="small text-muted">Review due <?= h($reviewDueDate) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="align-top">
                          <span class="badge <?= h($retrievalState['class']) ?>"><?= h($retrievalState['label']) ?></span>
                          <div class="small text-muted mt-1"><?= h($retrievalState['reason']) ?></div>
                        </td>
                        <td class="align-top">
                          <div class="ai-knowledge-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary ai-knowledge-action-btn" data-ai-knowledge-edit="<?= h($publicId) ?>" data-bs-toggle="tooltip" data-bs-title="<?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_edit_tooltip', 'Edit knowledge')) ?>">
                              <i class="ri-pencil-line" aria-hidden="true"></i><span class="visually-hidden">Edit</span>
                            </button>
                            <?php foreach (['active' => 'Activate', 'draft' => 'Draft', 'archived' => 'Archive'] as $action => $label): ?>
                              <?php if ($status !== $action): ?>
                                <?php $actionDisabled = $action === 'active' && !$activationState['allowed']; ?>
                                <form method="post" class="d-inline">
                                  <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
                                  <input type="hidden" name="public_id" value="<?= h($publicId) ?>">
                                  <input type="hidden" name="action" value="<?= h($action) ?>">
                                  <?php
                                    $actionIcon = match ($action) {
                                        'active' => 'ri-checkbox-circle-line',
                                        'archived' => 'ri-archive-line',
                                        default => 'ri-draft-line',
                                    };
                                  ?>
                                  <button type="submit" class="btn btn-sm btn-outline-secondary ai-knowledge-action-btn" data-bs-toggle="tooltip" data-bs-title="<?= h($label) ?>" <?= $actionDisabled ? 'disabled' : '' ?>>
                                    <i class="<?= h($actionIcon) ?>" aria-hidden="true"></i><span class="visually-hidden"><?= h($label) ?></span>
                                  </button>
                                </form>
                              <?php endif; ?>
                            <?php endforeach; ?>
                            <form method="post">
                              <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
                              <input type="hidden" name="public_id" value="<?= h($publicId) ?>">
                              <input type="hidden" name="action" value="delete">
                              <button type="submit" class="btn btn-sm btn-outline-danger ai-knowledge-action-btn" data-bs-toggle="tooltip" data-bs-title="<?= h(ai_chatbot_knowledge_label('aiChatbotKnowledge_delete_tooltip', 'Delete knowledge')) ?>">
                                <i class="ri-delete-bin-line" aria-hidden="true"></i><span class="visually-hidden">Delete</span>
                              </button>
                            </form>
                          </div>
                          <?php if (!$activationState['allowed'] && $status !== 'active'): ?>
                            <div class="small text-muted text-end mt-1"><?= h($activationState['reason']) ?></div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
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
document.addEventListener('DOMContentLoaded', function () {
  var i18n = <?= json_encode($knowledgeI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var knowledgeTable = null;
  var entryModal = document.getElementById('aiKnowledgeEntryModal');
  var manualForm = document.getElementById('manualKnowledgeForm');
  var manualSubmit = document.getElementById('manualKnowledgeSubmit');
  var pdfSubmit = document.getElementById('pdfKnowledgeSubmit');
  var entryTabs = document.getElementById('aiKnowledgeEntryTabs');

  <?php if ($editRow): ?>
  if (entryModal && window.bootstrap && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(entryModal).show();
  }
  <?php endif; ?>

  function syncKnowledgeModalActions(activeTarget) {
    var isPdf = activeTarget === '#pdf-knowledge-pane';
    if (manualSubmit) {
      manualSubmit.classList.toggle('d-none', isPdf);
    }
    if (pdfSubmit) {
      pdfSubmit.classList.toggle('d-none', !isPdf);
    }
  }
  if (entryTabs) {
    entryTabs.addEventListener('shown.bs.tab', function (event) {
      syncKnowledgeModalActions(event.target.getAttribute('data-bs-target') || '');
    });
  }

  function fireKnowledgeAlert(type, message) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      return window.Swal.fire({
        icon: type || 'success',
        title: type === 'error' ? i18n.errorTitle : i18n.successTitle,
        text: message || '',
        confirmButtonText: i18n.close,
        customClass: {
          confirmButton: 'btn btn-primary px-4'
        },
        buttonsStyling: false
      });
    }
    window.alert(message || '');
    return Promise.resolve();
  }

  function initKnowledgeTooltips(root) {
    if (!window.bootstrap || !bootstrap.Tooltip) {
      return;
    }
    (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      var existing = bootstrap.Tooltip.getInstance(el);
      if (existing) {
        existing.dispose();
      }
      bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body', trigger: 'hover focus' });
    });
  }

  function initKnowledgeClamps(root) {
    (root || document).querySelectorAll('[data-knowledge-clamp]').forEach(function (clamp) {
      var wrap = clamp.closest('.ai-knowledge-clamp-wrap');
      var toggle = wrap ? wrap.querySelector('[data-knowledge-toggle]') : null;
      if (!toggle) {
        return;
      }
      clamp.classList.remove('is-expanded');
      toggle.textContent = i18n.show;
      window.requestAnimationFrame(function () {
        var isOverflowing = clamp.scrollHeight > clamp.clientHeight + 2;
        toggle.classList.toggle('d-none', !isOverflowing);
      });
      if (toggle.dataset.bound === '1') {
        return;
      }
      toggle.dataset.bound = '1';
      toggle.addEventListener('click', function () {
        var expanded = clamp.classList.toggle('is-expanded');
        toggle.textContent = expanded ? i18n.hide : i18n.show;
        if (knowledgeTable && typeof knowledgeTable.columns === 'function') {
          knowledgeTable.columns.adjust();
        }
      });
    });
  }

  function initKnowledgeDataTable() {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
      return;
    }
    var knowledgeDom = '<"row mb-2"<"col-sm-12 col-md-6 dt-top-left"l><"col-sm-12 col-md-6 d-flex justify-content-md-end dt-top-right"f>>' +
      't' +
      '<"dt-bottom-row mt-1 d-flex justify-content-between align-items-center"<"dt-info-left"i><"dt-paging-right d-flex justify-content-end"p>>';
    var options = window.DataTableStandard
      ? window.DataTableStandard.options({
          dom: knowledgeDom,
          pageLength: 10,
          order: [],
          responsive: true,
          searchPlaceholder: i18n.searchPlaceholder || 'Search knowledge...'
        })
      : {
          dom: knowledgeDom,
          pageLength: 10,
          order: [],
          responsive: true
        };
    if (jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#aiKnowledgeTable')) {
      knowledgeTable = jQuery('#aiKnowledgeTable').DataTable();
      return;
    }
    knowledgeTable = jQuery('#aiKnowledgeTable').DataTable(options);
    if (window.DataTableStandard) {
      window.DataTableStandard.decorate('#aiKnowledgeTable', {
        searchPlaceholder: i18n.searchPlaceholder || 'Search knowledge...'
      });
    }
  }

  function destroyKnowledgeDataTable() {
    if (window.jQuery && jQuery.fn && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#aiKnowledgeTable')) {
      jQuery('#aiKnowledgeTable').DataTable().destroy();
    }
    knowledgeTable = null;
  }

  function refreshKnowledgeTable() {
    return fetch(window.location.pathname, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error(i18n.reloadTableError);
        }
        return response.text();
      })
      .then(function (html) {
        var parsed = new DOMParser().parseFromString(html, 'text/html');
        var nextBody = parsed.querySelector('#aiKnowledgeTable tbody');
        var currentBody = document.querySelector('#aiKnowledgeTable tbody');
        if (!nextBody || !currentBody) {
          throw new Error(i18n.updateTableError);
        }
        destroyKnowledgeDataTable();
        currentBody.innerHTML = nextBody.innerHTML;
        var nextPdfPane = parsed.querySelector('#pdf-sources-pane');
        var currentPdfPane = document.querySelector('#pdf-sources-pane');
        if (nextPdfPane && currentPdfPane) {
          currentPdfPane.innerHTML = nextPdfPane.innerHTML;
        }
        initKnowledgeDataTable();
        initKnowledgeTooltips(document.getElementById('aiKnowledgeTable'));
        initKnowledgeTooltips(document.getElementById('pdf-sources-pane'));
        initKnowledgeClamps(document.getElementById('aiKnowledgeTable'));
      });
  }

  function postKnowledgeForm(form) {
    return fetch(window.location.href, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || i18n.actionFailed);
        }
        return payload;
      });
    });
  }

  function setIconButtonLoading(button, loading) {
    if (!button) {
      return function () {};
    }
    if (loading) {
      var tooltip = window.bootstrap && bootstrap.Tooltip ? bootstrap.Tooltip.getInstance(button) : null;
      if (tooltip) {
        tooltip.hide();
      }
      if (!button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
      }
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">Loading</span>';
      return function () {
        setIconButtonLoading(button, false);
      };
    }

    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
    button.disabled = false;
    button.removeAttribute('aria-busy');
    if (window.bootstrap && bootstrap.Tooltip) {
      var existing = bootstrap.Tooltip.getInstance(button);
      if (existing) {
        existing.dispose();
      }
      bootstrap.Tooltip.getOrCreateInstance(button, { container: 'body', trigger: 'hover focus' });
    }
    return function () {};
  }

  function setButtonLoading(button, loading, label) {
    if (!button) {
      return function () {};
    }
    if (loading) {
      if (!button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
      }
      if (button.tagName === 'BUTTON') {
        button.disabled = true;
      } else {
        button.classList.add('disabled');
        button.setAttribute('aria-disabled', 'true');
      }
      button.setAttribute('aria-busy', 'true');
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + (label || 'Loading');
      return function () {
        setButtonLoading(button, false);
      };
    }

    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
    if (button.tagName === 'BUTTON') {
      button.disabled = false;
    } else {
      button.classList.remove('disabled');
      button.removeAttribute('aria-disabled');
    }
    button.removeAttribute('aria-busy');
    return function () {};
  }

  function setFieldValue(name, value) {
    if (!manualForm) {
      return;
    }
    var field = manualForm.querySelector('[name="' + name + '"]');
    if (field) {
      field.value = value == null ? '' : String(value);
    }
  }

  function selectTab(buttonSelector) {
    var button = document.querySelector(buttonSelector);
    if (button && window.bootstrap && bootstrap.Tab) {
      bootstrap.Tab.getOrCreateInstance(button).show();
    }
  }

  function setManualMode(mode) {
    var isEdit = mode === 'edit';
    var title = document.getElementById('aiKnowledgeEntryModalLabel');
    if (title) {
      title.textContent = isEdit ? i18n.editTitle : i18n.createTitle;
    }
    if (manualSubmit) {
      manualSubmit.innerHTML = '<i class="ri-save-line me-1"></i>' + (isEdit ? <?= json_encode(ai_chatbot_knowledge_label('aiChatbotKnowledge_update', 'Update Knowledge'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> : <?= json_encode(ai_chatbot_knowledge_label('aiChatbotKnowledge_save', 'Save Knowledge'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
    }
  }

  function resetManualForm() {
    if (!manualForm) {
      return;
    }
    manualForm.reset();
    setFieldValue('public_id', '');
    setFieldValue('source_type', 'manual_text');
    setFieldValue('language', 'ms');
    setFieldValue('status', 'draft');
    setFieldValue('visibility', 'selected_groups');
    setFieldValue('priority', '100');
    setFieldValue('review_status', 'draft');
    manualForm.querySelectorAll('input[name="allowed_groups[]"]').forEach(function (input) {
      input.checked = false;
    });
    setManualMode('create');
    selectTab('#manual-knowledge-tab');
    selectTab('#manual-content-tab');
  }

  function populateManualForm(item) {
    resetManualForm();
    setFieldValue('public_id', item.public_id || '');
    setFieldValue('title', item.title || '');
    setFieldValue('question', item.question || '');
    setFieldValue('answer', item.answer || '');
    setFieldValue('language', item.language || 'ms');
    setFieldValue('status', item.status || 'draft');
    setFieldValue('visibility', item.visibility || 'selected_groups');
    setFieldValue('priority', item.priority || '100');
    setFieldValue('tags', item.tags || '');
    setFieldValue('source_title', item.source_title || '');
    setFieldValue('version', item.version || '');
    setFieldValue('review_status', item.review_status || 'draft');
    setFieldValue('effective_date', item.effective_date || '');
    setFieldValue('review_due_date', item.review_due_date || '');

    var groups = Array.isArray(item.allowed_groups) ? item.allowed_groups.map(String) : [];
    manualForm.querySelectorAll('input[name="allowed_groups[]"]').forEach(function (input) {
      input.checked = groups.indexOf(String(input.value)) !== -1;
    });
    setManualMode('edit');
    selectTab('#manual-knowledge-tab');
    selectTab('#manual-content-tab');
  }

  function openKnowledgeModal() {
    if (entryModal && window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(entryModal).show();
    }
  }

  document.querySelectorAll('[data-bs-target="#aiKnowledgeEntryModal"]').forEach(function (button) {
    button.addEventListener('click', function () {
      resetManualForm();
    });
  });

  var createButton = document.getElementById('aiKnowledgeCreateButton');
  var settingsButton = document.getElementById('aiKnowledgeSettingsButton');
  if (createButton) {
    createButton.addEventListener('click', function () {
      var stopCreateLoading = setButtonLoading(createButton, true, i18n.opening);
      if (entryModal) {
        entryModal.addEventListener('shown.bs.modal', function handleCreateShown() {
          entryModal.removeEventListener('shown.bs.modal', handleCreateShown);
          stopCreateLoading();
        });
        window.setTimeout(stopCreateLoading, 1200);
      } else {
        window.setTimeout(stopCreateLoading, 300);
      }
    });
  }
  if (settingsButton) {
    settingsButton.addEventListener('click', function () {
      setButtonLoading(settingsButton, true, i18n.opening);
    });
  }

  if (entryModal) {
    entryModal.addEventListener('hidden.bs.modal', function () {
      resetManualForm();
    });
  }

  document.addEventListener('click', function (event) {
    var editButton = event.target.closest('[data-ai-knowledge-edit]');
    if (!editButton) {
      return;
    }
    event.preventDefault();
    var publicId = editButton.getAttribute('data-ai-knowledge-edit') || '';
    if (!publicId) {
      return;
    }
    var stopEditLoading = setIconButtonLoading(editButton, true);
    fetch(window.location.pathname + '?ajax=knowledge_item&public_id=' + encodeURIComponent(publicId), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || !payload.ok) {
            throw new Error(payload.message || i18n.openFailed);
          }
          return payload.item || {};
        });
      })
      .then(function (item) {
        populateManualForm(item);
        openKnowledgeModal();
      })
      .catch(function (error) {
        void fireKnowledgeAlert('error', error.message || i18n.openFailed);
      })
      .finally(function () {
        stopEditLoading();
      });
  });

  if (manualForm) {
    manualForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (manualSubmit) {
        manualSubmit.disabled = true;
      }
      var originalButtonHtml = manualSubmit ? manualSubmit.innerHTML : '';
      if (manualSubmit) {
        manualSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + i18n.saving;
      }

      postKnowledgeForm(manualForm)
        .then(function (payload) {
          return fireKnowledgeAlert(payload.type || 'success', payload.message || i18n.saveSuccess).then(function () {
            if (window.history && window.location.search.indexOf('edit=') !== -1) {
              window.history.replaceState({}, document.title, window.location.pathname);
            }
            if (payload.public_id) {
              setFieldValue('public_id', payload.public_id);
              setManualMode('edit');
            }
            return refreshKnowledgeTable();
          });
        })
        .catch(function (error) {
          return fireKnowledgeAlert('error', error.message || i18n.saveFailed);
        })
        .finally(function () {
          if (manualSubmit) {
            manualSubmit.disabled = false;
            manualSubmit.innerHTML = originalButtonHtml;
          }
        });
    });
  }

  var pdfForm = document.getElementById('pdfKnowledgeForm');
  if (pdfForm) {
    pdfForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (pdfSubmit) {
        pdfSubmit.disabled = true;
      }
      var originalButtonHtml = pdfSubmit ? pdfSubmit.innerHTML : '';
      if (pdfSubmit) {
        pdfSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + i18n.uploading;
      }
      postKnowledgeForm(pdfForm)
        .then(function (payload) {
          return fireKnowledgeAlert(payload.type || 'success', payload.message || i18n.pdfSuccess).then(function () {
            pdfForm.reset();
            return refreshKnowledgeTable();
          });
        })
        .catch(function (error) {
          return fireKnowledgeAlert('error', error.message || i18n.pdfFailed);
        })
        .finally(function () {
          if (pdfSubmit) {
            pdfSubmit.disabled = false;
            pdfSubmit.innerHTML = originalButtonHtml;
          }
        });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || form === manualForm || form === pdfForm) {
      return;
    }
    if (!form.closest('#aiKnowledgeTable') && !form.closest('#pdf-sources-pane')) {
      return;
    }
    event.preventDefault();
    var actionInput = form.querySelector('input[name="action"]');
    var action = actionInput ? String(actionInput.value || '') : '';
    var confirmDelete = action === 'delete';
    var submitter = event.submitter || form.querySelector('button[type="submit"]');
    var run = function () {
      var stopActionLoading = setIconButtonLoading(submitter, true);
      postKnowledgeForm(form)
        .then(function (payload) {
          return fireKnowledgeAlert(payload.type || 'success', payload.message || i18n.actionSuccess).then(function () {
            return refreshKnowledgeTable();
          });
        })
        .catch(function (error) {
          return fireKnowledgeAlert('error', error.message || i18n.actionFailed);
        })
        .finally(function () {
          stopActionLoading();
        });
    };

    if (confirmDelete && window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: 'warning',
        title: i18n.deleteConfirmTitle,
        text: i18n.deleteConfirmText,
        showCancelButton: true,
        confirmButtonText: i18n.deleteConfirmButton,
        cancelButtonText: i18n.cancel,
        customClass: {
          confirmButton: 'btn btn-danger px-4',
          cancelButton: 'btn btn-outline-secondary px-4 ms-2'
        },
        buttonsStyling: false
      }).then(function (result) {
        if (result.isConfirmed) {
          run();
        }
      });
      return;
    }

    run();
  });

  initKnowledgeDataTable();
  initKnowledgeTooltips(document);
  initKnowledgeClamps(document);
});
</script>
</body>
</html>
