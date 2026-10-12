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

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/init.php';
    require_login();
    require_once __DIR__ . '/_helpers.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/AiChatbotActionSuggestionService.php';
    require_once __DIR__ . '/../classes/AiChatbotConversationRepository.php';
    require_once __DIR__ . '/../classes/AiChatbotKnowledgeContext.php';
    require_once __DIR__ . '/../classes/AiChatbotProjectContextRegistry.php';
    require_once __DIR__ . '/../classes/AiChatbotQuestionClassifier.php';
    require_once __DIR__ . '/../classes/AiChatbotService.php';
    require_once __DIR__ . '/../classes/AiChatbotSystemContext.php';
    require_once __DIR__ . '/../classes/AiChatbotUsageRepository.php';

    $t = static function (string $key, string $fallback): string {
        $value = function_exists('__') ? __($key) : null;
        return ($value === null || $value === '' || $value === $key) ? $fallback : (string)$value;
    };

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonErrorResponse($t('aiChatbot_error_invalid_method', 'Kaedah permintaan tidak sah.'), 405);
    }

    if (!isValidCsrfToken()) {
        jsonErrorResponse((string)(__('userGroup_csrf_invalid') ?: 'CSRF token tidak sah.'), 403);
    }

    $pdo = Database::getInstance('mysql')->getConnection();
    $profile = $GLOBALS['profile'] ?? [];
    $profile = is_array($profile) ? $profile : [];
    $service = new AiChatbotService();
    $usageRepository = new AiChatbotUsageRepository($pdo);
    $conversationRepository = new AiChatbotConversationRepository($pdo);
    $loginId = trim((string)($_SESSION['f_loginID'] ?? $_SESSION['user']['f_loginID'] ?? ($profile['f_loginID'] ?? '')));
    $userId = $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? ($profile['f_userID'] ?? null);
    $publicConfig = $service->publicConfig();
    $conversationSessionId = null;
    $conversationUserMessageId = null;
    $conversationAssistantMessageId = null;
    $projectContext = [];
    $projectContextMeta = [];

    if (!$service->isEnabled()) {
        jsonErrorResponse($t('aiChatbot_error_disabled', 'AI Chatbot belum diaktifkan.'), 403);
    }
    if (!$service->canAccess($profile, $pdo)) {
        jsonErrorResponse($t('aiChatbot_error_forbidden', 'Anda tidak dibenarkan menggunakan AI Chatbot prototype.'), 403);
    }

    if (!checkRateLimit('ai_chatbot_message', $service->rateLimitPerMinute(), 60)) {
        ai_chatbot_record_usage_safe($service, $usageRepository, [
            'user_id' => $userId,
            'login_id' => $loginId,
            'provider' => $publicConfig['provider'] ?? 'unknown',
            'model' => $publicConfig['model'] ?? 'unknown',
            'outcome' => 'rate_limited',
            'error_code' => 'session_rate_limit',
            'error_message' => 'Per-minute session rate limit triggered.',
            'request_meta' => ['limit_per_minute' => $service->rateLimitPerMinute()],
        ]);
        if (function_exists('audit_event')) {
            audit_event([
                'event_type' => 'AI_CHATBOT_RATE_LIMIT',
                'severity' => 'WARN',
                'outcome' => 'DENIED',
                'target_type' => 'ai_chatbot',
                'target_id' => 'prototype',
                'target_label' => 'AI Chatbot Prototype',
                'message' => 'AI chatbot rate limit triggered',
                'user_id' => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
                'actor_label' => $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null,
                'meta' => ['limit_per_minute' => $service->rateLimitPerMinute()],
            ]);
        }
        jsonErrorResponse($t('aiChatbot_error_rate_limit', 'Terlalu banyak permintaan. Sila cuba semula sebentar lagi.'), 429);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $message = trim((string)($data['message'] ?? ''));
    if ($message === '') {
        jsonErrorResponse($t('aiChatbot_error_empty_message', 'Sila masukkan mesej.'), 422);
    }

    if (mb_strlen($message, 'UTF-8') > $service->maxInputChars()) {
        jsonErrorResponse($t('aiChatbot_error_long_message', 'Mesej terlalu panjang.'), 422);
    }

    $runtimeContext = is_array($data['context'] ?? null) ? $data['context'] : [];
    $pagePath = ai_chatbot_context_path($runtimeContext['page_path'] ?? '');
    $pageTitle = ai_chatbot_context_string($runtimeContext['page_title'] ?? '', 160);
    $pageUiContext = ai_chatbot_page_ui_context($runtimeContext['page_ui'] ?? []);
    $isSuperAdmin = function_exists('is_user_super_admin') ? is_user_super_admin($profile, $pdo) : false;
    $classification = (new AiChatbotQuestionClassifier())->classify($message, $isSuperAdmin);
    $conversationContext = [
        'user_id' => $userId,
        'login_id' => $loginId,
        'staf_id' => $_SESSION['f_stafID'] ?? $_SESSION['user']['f_stafID'] ?? ($profile['f_stafID'] ?? null),
        'group_id' => (int)($_SESSION['group_active_id'] ?? ($profile['f_groupID'] ?? 0)),
        'group_kod' => (string)($_SESSION['group_active_code'] ?? ($profile['f_groupKod'] ?? '')),
        'provider' => $publicConfig['provider'] ?? 'unknown',
        'model' => $publicConfig['model'] ?? 'unknown',
        'access_mode' => $publicConfig['access_mode'] ?? 'super_admin_only',
        'title' => mb_substr($message, 0, 120, 'UTF-8'),
        'actor' => $loginId !== '' ? $loginId : ($_SESSION['f_stafID'] ?? 'ai-chatbot'),
    ];
    $userDailyLimit = $service->userDailyRequestLimit();
    if ($userDailyLimit > 0 && $loginId !== '' && $usageRepository->countToday($loginId) >= $userDailyLimit) {
        ai_chatbot_record_usage_safe($service, $usageRepository, [
            'user_id' => $userId,
            'login_id' => $loginId,
            'provider' => $publicConfig['provider'] ?? 'unknown',
            'model' => $publicConfig['model'] ?? 'unknown',
            'outcome' => 'rate_limited',
            'error_code' => 'user_daily_limit',
            'error_message' => 'Daily user request limit reached.',
            'request_meta' => ['limit' => $userDailyLimit],
        ]);
        jsonErrorResponse($t('aiChatbot_error_rate_limit', 'Terlalu banyak permintaan. Sila cuba semula sebentar lagi.'), 429);
    }

    $globalDailyLimit = $service->globalDailyRequestLimit();
    if ($globalDailyLimit > 0 && $usageRepository->countToday() >= $globalDailyLimit) {
        ai_chatbot_record_usage_safe($service, $usageRepository, [
            'user_id' => $userId,
            'login_id' => $loginId,
            'provider' => $publicConfig['provider'] ?? 'unknown',
            'model' => $publicConfig['model'] ?? 'unknown',
            'outcome' => 'rate_limited',
            'error_code' => 'global_daily_limit',
            'error_message' => 'Daily global request limit reached.',
            'request_meta' => ['limit' => $globalDailyLimit],
        ]);
        jsonErrorResponse($t('aiChatbot_error_rate_limit', 'Terlalu banyak permintaan. Sila cuba semula sebentar lagi.'), 429);
    }

    if (!empty($publicConfig['store_conversations'])) {
        $session = ai_chatbot_ensure_conversation_session_safe($conversationRepository, $conversationContext);
        $conversationSessionId = $session['id'] ?? null;
        if ($conversationSessionId) {
            $conversationUserMessageId = ai_chatbot_record_conversation_message_safe($conversationRepository, (int)$conversationSessionId, [
                'role' => 'user',
                'provider' => $publicConfig['provider'] ?? 'unknown',
                'model' => $publicConfig['model'] ?? 'unknown',
                'content' => $message,
                'status' => 'sent',
                'actor' => $conversationContext['actor'],
                'meta' => [
                    'message_length' => mb_strlen($message, 'UTF-8'),
                    'current_page_path' => $pagePath !== '' ? $pagePath : null,
                    'current_page_title' => $pageTitle !== '' ? $pageTitle : null,
                    'page_ui_context' => ai_chatbot_page_ui_meta($pageUiContext),
                    'question_category' => $classification['category'] ?? 'unknown',
                    'question_risk' => $classification['risk'] ?? 'low',
                    'question_needs_review' => (bool)($classification['needs_review'] ?? false),
                    'question_review_reason' => $classification['review_reason'] ?? null,
                    'blocked_detail' => (bool)($classification['blocked_detail'] ?? false),
                ],
            ], !empty($publicConfig['log_message_content']));
        }
    }

    $actor = [
        'lang' => (string)($_SESSION['lang'] ?? 'ms'),
        'role' => (string)($_SESSION['group_active_name'] ?? ($profile['f_groupName'] ?? '')),
        'active_group_id' => (string)((int)($_SESSION['group_active_id'] ?? ($profile['f_groupID'] ?? 0))),
        'active_group_code' => (string)($_SESSION['group_active_code'] ?? ($profile['f_groupKod'] ?? '')),
        'is_super_admin' => function_exists('is_user_super_admin') ? is_user_super_admin($profile, $pdo) : false,
        'is_privileged_project_admin' => $isPrivilegedProjectAdmin,
        'access_mode' => (string)($publicConfig['access_mode'] ?? ''),
        'app_title' => (string)($publicConfig['app_title'] ?? 'IQS-Framework AI Chatbot'),
        'current_page_path' => $pagePath,
        'current_page_title' => $pageTitle,
        'current_page_ui' => $pageUiContext,
        'question_classification' => $classification,
    ];
    $systemContext = (new AiChatbotSystemContext($pdo))->build($profile, $actor);
    if ($systemContext !== []) {
        $actor['system_context'] = $systemContext;
    }
    $knowledgeContext = (new AiChatbotKnowledgeContext($pdo))->build($message, $profile, $actor);
    if ($knowledgeContext !== []) {
        $actor['knowledge_context'] = $knowledgeContext;
    }
    $projectContext = [];
    if (empty($classification['blocked_detail'])) {
        $projectContext = AiChatbotProjectContextRegistry::default()->build($message, $profile, $actor);
        if ($projectContext !== []) {
            $actor['project_context'] = $projectContext;
        }
    }
    $projectContextMeta = ai_chatbot_project_context_meta($projectContext);
    $suggestedActions = (new AiChatbotActionSuggestionService())->build($message, $systemContext, $classification);
    $actor['retrieval_policy'] = [
        'mode' => 'permission_filtered',
        'requires_grounded_answer' => ai_chatbot_requires_grounded_answer($message),
        'blocked_detail' => !empty($classification['blocked_detail']),
        'system_context_available' => $systemContext !== [],
        'knowledge_context_available' => $knowledgeContext !== [],
        'project_context_available' => (bool)($projectContextMeta['has_records'] ?? false),
        'project_context_status' => $projectContextMeta['status'] ?? null,
        'project_context_provider' => $projectContextMeta['provider'] ?? null,
    ];

    // Runtime observability: audit project-context metadata (no raw records)
    if (function_exists('audit_event')) {
        $outcome = 'none';
        if (!empty($projectContextMeta['has_records'])) {
            $outcome = 'provided';
        } elseif (!empty($projectContextMeta['denied_reason'])) {
            $outcome = 'denied';
        } elseif (($projectContextMeta['status'] ?? '') === 'not_built') {
            $outcome = 'skipped';
        }

        audit_event([
            'event_type' => 'AI_CHATBOT_PROJECT_CONTEXT',
            'severity' => 'INFO',
            'outcome' => strtoupper($outcome),
            'target_type' => 'ai_chatbot',
            'target_id' => 'prototype',
            'target_label' => 'AI Chatbot Prototype',
            'message' => 'Project context metadata emitted for request (no raw rows).',
            'user_id' => $userId,
            'actor_label' => $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null,
            'meta' => [
                'provider' => $projectContextMeta['provider'] ?? null,
                'provider_label' => $projectContextMeta['provider_label'] ?? null,
                'match_score' => $projectContextMeta['match_score'] ?? null,
                'intent' => $projectContextMeta['intent'] ?? null,
                'scope' => $projectContextMeta['scope'] ?? null,
                'row_count' => $projectContextMeta['row_count'] ?? 0,
                'has_records' => !empty($projectContextMeta['has_records']),
                'denied_reason' => $projectContextMeta['denied_reason'] ?? null,
            ],
        ]);
    }

    $result = $service->sendMessage($message, $actor);
    if (!empty($publicConfig['store_conversations']) && $conversationSessionId) {
        $conversationAssistantMessageId = ai_chatbot_record_conversation_message_safe($conversationRepository, (int)$conversationSessionId, [
            'role' => 'assistant',
            'provider' => $result['provider'] ?? ($publicConfig['provider'] ?? 'unknown'),
            'model' => $result['model'] ?? ($publicConfig['model'] ?? 'unknown'),
            'content' => (string)($result['message'] ?? ''),
            'latency_ms' => $result['latency_ms'] ?? null,
            'status' => 'completed',
            'actor' => $conversationContext['actor'],
            'meta' => [
                'user_message_id' => $conversationUserMessageId,
                'knowledge_items_in_prompt' => $knowledgeContext['totals']['items_in_prompt'] ?? 0,
                'knowledge_retrieval_mode' => $knowledgeContext['retrieval_mode'] ?? null,
                'knowledge_expanded_term_count' => count(is_array($knowledgeContext['expanded_terms'] ?? null) ? $knowledgeContext['expanded_terms'] : []),
                'project_context' => $projectContextMeta,
                'suggested_action_count' => count($suggestedActions),
                'system_context_available' => $systemContext !== [],
                'knowledge_context_available' => $knowledgeContext !== [],
                'project_context_available' => (bool)($projectContextMeta['has_records'] ?? false),
            ],
        ], !empty($publicConfig['log_message_content']));
    }
    ai_chatbot_record_usage_safe($service, $usageRepository, [
        'session_id' => $conversationSessionId,
        'message_id' => $conversationAssistantMessageId,
        'user_id' => $userId,
        'login_id' => $loginId,
        'provider' => $result['provider'] ?? ($publicConfig['provider'] ?? 'unknown'),
        'model' => $result['model'] ?? ($publicConfig['model'] ?? 'unknown'),
        'latency_ms' => $result['latency_ms'] ?? null,
        'outcome' => 'success',
        'usage' => is_array($result['usage'] ?? null) ? $result['usage'] : [],
        'request_meta' => [
            'message_length' => mb_strlen($message, 'UTF-8'),
            'access_mode' => $publicConfig['access_mode'] ?? null,
            'current_page_path' => $pagePath !== '' ? $pagePath : null,
            'page_ui_context' => ai_chatbot_page_ui_meta($pageUiContext),
            'system_context_source' => $systemContext['source'] ?? null,
            'knowledge_context_source' => $knowledgeContext['source'] ?? null,
            'knowledge_retrieval_mode' => $knowledgeContext['retrieval_mode'] ?? null,
            'knowledge_items_in_prompt' => $knowledgeContext['totals']['items_in_prompt'] ?? 0,
            'knowledge_expanded_term_count' => count(is_array($knowledgeContext['expanded_terms'] ?? null) ? $knowledgeContext['expanded_terms'] : []),
            'project_context' => $projectContextMeta,
            'suggested_action_count' => count($suggestedActions),
            'requires_grounded_answer' => (bool)($actor['retrieval_policy']['requires_grounded_answer'] ?? false),
            'question_category' => $classification['category'] ?? 'unknown',
            'question_risk' => $classification['risk'] ?? 'low',
            'question_needs_review' => (bool)($classification['needs_review'] ?? false),
            'question_review_reason' => $classification['review_reason'] ?? null,
            'blocked_detail' => (bool)($classification['blocked_detail'] ?? false),
        ],
    ]);

    if (function_exists('audit_event')) {
        $meta = [
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'latency_ms' => $result['latency_ms'] ?? null,
            'message_length' => mb_strlen($message, 'UTF-8'),
            'question_category' => $classification['category'] ?? 'unknown',
            'question_risk' => $classification['risk'] ?? 'low',
            'project_context' => $projectContextMeta,
        ];
        audit_event([
            'event_type' => 'AI_CHATBOT_MESSAGE',
            'severity' => 'INFO',
            'outcome' => 'SUCCESS',
            'target_type' => 'ai_chatbot',
            'target_id' => 'prototype',
            'target_label' => 'AI Chatbot Prototype',
            'message' => 'AI chatbot message completed',
            'user_id' => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
            'actor_label' => $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null,
            'meta' => $meta,
        ]);
    }

    jsonSuccessResponse([
        'message' => (string)$result['message'],
        'provider' => (string)($result['provider'] ?? ''),
        'model' => (string)($result['model'] ?? ''),
        'latency_ms' => (int)($result['latency_ms'] ?? 0),
        'usage' => is_array($result['usage'] ?? null) ? $result['usage'] : [],
    ]);
} catch (InvalidArgumentException $e) {
    error_log('[ai-chatbot-message] validation: ' . $e->getMessage());
    $fallback = function_exists('__') ? __('aiChatbot_error_invalid_action') : null;
    jsonErrorResponse(($fallback && $fallback !== 'aiChatbot_error_invalid_action') ? (string)$fallback : 'Permintaan chatbot tidak sah.', 422);
} catch (Throwable $e) {
    if ($e instanceof ExternalServiceException) {
        FrameworkExceptionHandler::log($e, [
            'endpoint' => 'ai-chatbot-message',
            'model' => $publicConfig['model'] ?? null,
        ]);
    } else {
        error_log('[ai-chatbot-message] ' . $e->getMessage());
    }
    if (isset($service, $usageRepository)) {
        $errorMessageId = null;
        if (!empty($publicConfig['store_conversations']) && !empty($conversationSessionId) && isset($conversationRepository)) {
            $errorMessageId = ai_chatbot_record_conversation_message_safe($conversationRepository, (int)$conversationSessionId, [
                'role' => 'error',
                'provider' => $publicConfig['provider'] ?? 'unknown',
                'model' => $publicConfig['model'] ?? 'unknown',
                'content' => $e->getMessage(),
                'status' => str_contains(strtolower($e->getMessage()), 'blocked') ? 'blocked' : 'failed',
                'error_code' => get_class($e),
                'error_message' => $e->getMessage(),
                'actor' => $loginId ?? ($_SESSION['f_stafID'] ?? 'ai-chatbot'),
            ], false);
        }
        ai_chatbot_record_usage_safe($service, $usageRepository, [
            'session_id' => $conversationSessionId ?? null,
            'message_id' => $errorMessageId,
            'user_id' => $userId ?? null,
            'login_id' => $loginId ?? null,
            'provider' => $publicConfig['provider'] ?? 'unknown',
            'model' => $publicConfig['model'] ?? 'unknown',
            'outcome' => str_contains(strtolower($e->getMessage()), 'timed out') ? 'timeout' : 'failed',
            'error_code' => get_class($e),
            'error_message' => $e->getMessage(),
            'request_meta' => [
                'project_context' => is_array($projectContextMeta ?? null) ? $projectContextMeta : [],
            ],
        ]);
    }
    if (function_exists('audit_event')) {
        audit_event([
            'event_type' => 'AI_CHATBOT_MESSAGE',
            'severity' => $e instanceof ExternalServiceException ? 'WARN' : 'ERROR',
            'outcome' => 'FAIL',
            'target_type' => 'ai_chatbot',
            'target_id' => 'prototype',
            'target_label' => 'AI Chatbot Prototype',
            'message' => 'AI chatbot message failed',
            'user_id' => $_SESSION['user']['f_userID'] ?? $_SESSION['f_userID'] ?? $_SESSION['f_stafID'] ?? null,
            'actor_label' => $_SESSION['user']['f_nama'] ?? $_SESSION['f_nama'] ?? null,
            'meta' => [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'project_context' => is_array($projectContextMeta ?? null) ? $projectContextMeta : [],
            ],
        ]);
    }
    $fallback = function_exists('__') ? __('aiChatbot_error_generic') : null;
    jsonExceptionResponse(
        $e,
        ($fallback && $fallback !== 'aiChatbot_error_generic') ? (string)$fallback : 'AI Chatbot tidak dapat menjawab buat masa ini.',
        ['_skip_log' => true]
    );
}

/**
 * @param array<string,mixed> $row
 */
function ai_chatbot_record_usage_safe(AiChatbotService $service, AiChatbotUsageRepository $repository, array $row): void
{
    if (!$service->shouldPersistUsage()) {
        return;
    }

    try {
        $repository->record($row);
    } catch (Throwable $e) {
        error_log('[ai-chatbot-usage] ' . $e->getMessage());
    }
}

/**
 * @param array<string,mixed> $context
 * @return array{id:int,public_id:string}|array{}
 */
function ai_chatbot_ensure_conversation_session_safe(AiChatbotConversationRepository $repository, array $context): array
{
    try {
        return $repository->ensureSession($context);
    } catch (Throwable $e) {
        error_log('[ai-chatbot-conversation-session] ' . $e->getMessage());
        return [];
    }
}

/**
 * @param array<string,mixed> $data
 */
function ai_chatbot_record_conversation_message_safe(AiChatbotConversationRepository $repository, int $sessionId, array $data, bool $storeContent): ?int
{
    try {
        $messageId = $repository->recordMessage($sessionId, $data, $storeContent);
        return $messageId > 0 ? $messageId : null;
    } catch (Throwable $e) {
        error_log('[ai-chatbot-conversation-message] ' . $e->getMessage());
        return null;
    }
}

function ai_chatbot_context_path(mixed $value): string
{
    $path = ai_chatbot_context_string($value, 255);
    if ($path === '') {
        return '';
    }

    $parsedPath = parse_url($path, PHP_URL_PATH);
    if (is_string($parsedPath) && $parsedPath !== '') {
        $path = $parsedPath;
    }

    if (!str_starts_with($path, '/')) {
        return '';
    }

    return ai_chatbot_context_string($path, 255);
}

/**
 * @return array<string,mixed>
 */
function ai_chatbot_page_ui_context(mixed $value): array
{
    $input = is_array($value) ? $value : [];
    $context = [
        'heading' => ai_chatbot_safe_ui_text($input['heading'] ?? '', 120),
        'active_tab' => ai_chatbot_safe_ui_text($input['active_tab'] ?? '', 100),
        'modal_title' => ai_chatbot_safe_ui_text($input['modal_title'] ?? '', 120),
        'form_labels' => ai_chatbot_ui_text_list($input['form_labels'] ?? [], 18, 80),
        'validation_errors' => ai_chatbot_ui_text_list($input['validation_errors'] ?? [], 8, 160),
        'table_headings' => ai_chatbot_ui_text_list($input['table_headings'] ?? [], 20, 80),
    ];

    return array_filter($context, static fn($item): bool => is_array($item) ? $item !== [] : $item !== '');
}

/**
 * @param array<string,mixed> $context
 * @return array<string,mixed>
 */
function ai_chatbot_page_ui_meta(array $context): array
{
    return [
        'heading_available' => !empty($context['heading']),
        'active_tab_available' => !empty($context['active_tab']),
        'modal_title_available' => !empty($context['modal_title']),
        'form_label_count' => count(is_array($context['form_labels'] ?? null) ? $context['form_labels'] : []),
        'validation_error_count' => count(is_array($context['validation_errors'] ?? null) ? $context['validation_errors'] : []),
        'table_heading_count' => count(is_array($context['table_headings'] ?? null) ? $context['table_headings'] : []),
    ];
}

/**
 * @return array<int,string>
 */
function ai_chatbot_ui_text_list(mixed $value, int $maxItems, int $maxLength): array
{
    $items = is_array($value) ? $value : [];
    $out = [];
    $seen = [];
    foreach ($items as $item) {
        $text = ai_chatbot_safe_ui_text($item, $maxLength);
        $key = mb_strtolower($text, 'UTF-8');
        if ($text === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $text;
        if (count($out) >= $maxItems) {
            break;
        }
    }

    return $out;
}

function ai_chatbot_safe_ui_text(mixed $value, int $maxLength): string
{
    $text = ai_chatbot_context_string($value, $maxLength);
    if ($text === '') {
        return '';
    }

    if (preg_match('/(csrf|token|secret|api\s*key|apikey|password|cookie|authorization)/i', $text) === 1) {
        return '';
    }

    return $text;
}

function ai_chatbot_context_string(mixed $value, int $maxLength): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
    $text = trim((string)$text);

    return mb_substr($text, 0, max(1, $maxLength), 'UTF-8');
}

function ai_chatbot_requires_grounded_answer(string $message): bool
{
    $message = mb_strtolower($message, 'UTF-8');
    $terms = [
        'akses',
        'access',
        'admin',
        'chatbot',
        'configure',
        'daftar',
        'dashboard',
        'edit',
        'fungsi',
        'halaman',
        'kemaskini',
        'login',
        'menu',
        'model',
        'modul',
        'module',
        'page',
        'pengguna',
        'permission',
        'provider',
        'role',
        'route',
        'setting',
        'sistem',
        'system',
        'tetapan',
        'user',
    ];

    foreach ($terms as $term) {
        if (str_contains($message, $term)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string,mixed> $context
 */
function ai_chatbot_project_context_has_records(array $context): bool
{
    $inner = is_array($context['context'] ?? null) ? $context['context'] : [];
    if ((int)($inner['row_count'] ?? 0) > 0) {
        return true;
    }

    return is_array($inner['items'] ?? null) && $inner['items'] !== [];
}

/**
 * Return project context audit metadata only. Do not include raw project rows.
 *
 * @param array<string,mixed> $context
 * @return array<string,mixed>
 */
function ai_chatbot_project_context_meta(array $context): array
{
    if ($context === []) {
        return [
            'status' => 'not_built',
            'provider' => null,
            'match_score' => null,
            'intent' => null,
            'scope' => null,
            'row_count' => 0,
            'has_records' => false,
            'denied_reason' => null,
        ];
    }

    $inner = is_array($context['context'] ?? null) ? $context['context'] : [];
    $innerStatus = ai_chatbot_meta_string($inner['status'] ?? null, 80);
    $status = ai_chatbot_meta_string($context['status'] ?? null, 80);
    $deniedReason = null;

    if (in_array($innerStatus, ['denied_missing_staff_scope', 'unsupported_intent', 'data_unavailable'], true)) {
        $deniedReason = $innerStatus;
    } elseif (in_array($status, ['core_only', 'ambiguous', 'no_project_provider', 'provider_not_found'], true)) {
        $deniedReason = $status;
    }

    return [
        'status' => $status !== '' ? $status : 'unknown',
        'provider' => ai_chatbot_meta_string($context['matched_provider'] ?? null, 80) ?: null,
        'provider_label' => ai_chatbot_meta_string($context['matched_label'] ?? null, 160) ?: null,
        'match_score' => is_numeric($context['match_score'] ?? null) ? (int)$context['match_score'] : null,
        'intent' => ai_chatbot_meta_string($inner['intent'] ?? null, 80) ?: null,
        'scope' => ai_chatbot_meta_string($inner['scope'] ?? null, 120) ?: null,
        'provider_context_status' => $innerStatus !== '' ? $innerStatus : null,
        'row_count' => max(0, (int)($inner['row_count'] ?? 0)),
        'has_records' => ai_chatbot_project_context_has_records($context),
        'denied_reason' => $deniedReason,
    ];
}

function ai_chatbot_meta_string(mixed $value, int $maxLength): string
{
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
    $value = trim((string)$value);
    $maxLength = max(1, $maxLength);

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}
