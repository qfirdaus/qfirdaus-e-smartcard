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

require_once __DIR__ . '/AiChatbotProviderRegistry.php';
require_once __DIR__ . '/SystemConfigConstants.php';

final class AiChatbotService
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? self::loadConfig();
    }

    /**
     * @return array<string,mixed>
     */
    public static function loadConfig(): array
    {
        $config = [
            'enabled' => false,
            'provider' => 'ollama',
            'model' => 'llama3.2:3b',
            'base_url' => 'http://127.0.0.1:11434',
            'api_key' => '',
            'timeout_seconds' => 30,
            'max_input_chars' => 2000,
            'max_output_tokens' => 800,
            'rate_limit_per_minute' => 10,
            'user_daily_request_limit' => 100,
            'global_daily_request_limit' => 1000,
            'persist_usage' => true,
            'store_conversations' => false,
            'log_message_content' => false,
            'character_name' => 'IQS Assistant',
            'character_avatar' => 'assets/images/ai/assistant.png',
            'welcome_message' => 'Hai, saya boleh bantu tentang penggunaan sistem ini.',
            'access_mode' => 'super_admin_only',
            'allowed_groups' => '',
            'app_url' => 'https://iqs-framework.dev',
            'app_title' => 'IQS-Framework AI Chatbot',
        ];

        foreach (self::loadDbConfigOverrides() as $key => $value) {
            if (!array_key_exists($key, $config)) {
                continue;
            }
            $config[$key] = $value;
        }

        return self::normalizeConfig($config);
    }

    /**
     * @return array<string,mixed>
     */
    public function publicConfig(): array
    {
        return [
            'enabled' => (bool)$this->config['enabled'],
            'provider' => (string)$this->config['provider'],
            'model' => (string)$this->config['model'],
            'base_url' => (string)$this->config['base_url'],
            'character_name' => (string)$this->config['character_name'],
            'character_avatar' => (string)$this->config['character_avatar'],
            'welcome_message' => (string)$this->config['welcome_message'],
            'app_title' => (string)$this->config['app_title'],
            'access_mode' => (string)$this->config['access_mode'],
            'allowed_groups' => (string)$this->config['allowed_groups'],
            'rate_limit_per_minute' => (int)$this->config['rate_limit_per_minute'],
            'user_daily_request_limit' => (int)$this->config['user_daily_request_limit'],
            'global_daily_request_limit' => (int)$this->config['global_daily_request_limit'],
            'persist_usage' => (bool)$this->config['persist_usage'],
            'store_conversations' => (bool)$this->config['store_conversations'],
            'log_message_content' => (bool)$this->config['log_message_content'],
        ];
    }

    public function isEnabled(): bool
    {
        return (bool)$this->config['enabled'];
    }

    public function rateLimitPerMinute(): int
    {
        return max(1, (int)$this->config['rate_limit_per_minute']);
    }

    public function maxInputChars(): int
    {
        return max(1, (int)$this->config['max_input_chars']);
    }

    public function userDailyRequestLimit(): int
    {
        return max(0, (int)$this->config['user_daily_request_limit']);
    }

    public function globalDailyRequestLimit(): int
    {
        return max(0, (int)$this->config['global_daily_request_limit']);
    }

    public function shouldPersistUsage(): bool
    {
        return (bool)$this->config['persist_usage'];
    }

    /**
     * @param array<string,mixed> $profile
     */
    public function canAccess(array $profile = [], ?PDO $pdo = null, bool $requireEnabled = true): bool
    {
        if ($requireEnabled && !$this->isEnabled()) {
            return false;
        }

        $mode = strtolower(trim((string)($this->config['access_mode'] ?? 'super_admin_only')));
        if ($mode === 'all_authenticated') {
            return !empty($_SESSION['f_stafID'])
                || !empty($_SESSION['f_loginID'])
                || !empty($_SESSION['user']);
        }

        $isSuperAdmin = function_exists('is_user_super_admin') && $pdo instanceof PDO
            ? is_user_super_admin($profile, $pdo)
            : false;

        if ($mode === 'super_admin_only') {
            return $isSuperAdmin;
        }

        if ($mode === 'selected_groups') {
            if ($isSuperAdmin) {
                return true;
            }

            $allowed = $this->allowedGroupTokens();
            if ($allowed === []) {
                return false;
            }

            $active = function_exists('prestasi_resolve_active_group') && $pdo instanceof PDO
                ? prestasi_resolve_active_group($profile, $pdo)
                : [
                    'id' => (int)($_SESSION['group_active_id'] ?? ($profile['f_groupID'] ?? 0)),
                    'kod' => (string)($profile['f_groupKod'] ?? ''),
                ];

            $groupId = (string)((int)($active['id'] ?? 0));
            $groupCode = strtoupper(trim((string)($active['kod'] ?? '')));

            return ($groupId !== '0' && in_array($groupId, $allowed, true))
                || ($groupCode !== '' && in_array($groupCode, $allowed, true));
        }

        return false;
    }

    /**
     * @return array<int,string>
     */
    private function allowedGroupTokens(): array
    {
        $raw = strtoupper(trim((string)($this->config['allowed_groups'] ?? '')));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function sendMessage(string $message, array $actor = []): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Message is required.');
        }

        if (mb_strlen($message, 'UTF-8') > $this->maxInputChars()) {
            throw new InvalidArgumentException('Message is too long.');
        }

        if (!$this->isEnabled()) {
            throw new RuntimeException('AI chatbot is disabled.');
        }

        $provider = AiChatbotProviderRegistry::resolve($this->config);
        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($actor),
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];

        return $provider->send($messages, [
            'temperature' => 0.3,
        ]);
    }

    /**
     * @param array<string,mixed> $actor
     */
    private function systemPrompt(array $actor): string
    {
        $lang = $this->promptContextValue($actor['lang'] ?? ($_SESSION['lang'] ?? 'ms'), 40);
        $role = $this->promptContextValue($actor['role'] ?? '', 120);
        $groupId = $this->promptContextValue($actor['active_group_id'] ?? '', 20);
        $groupCode = $this->promptContextValue($actor['active_group_code'] ?? '', 60);
        $accessMode = $this->promptContextValue($actor['access_mode'] ?? ($this->config['access_mode'] ?? ''), 80);
        $appTitle = $this->promptContextValue($actor['app_title'] ?? ($this->config['app_title'] ?? ''), 120);
        $pagePath = $this->promptContextValue($actor['current_page_path'] ?? '', 255);
        $pageTitle = $this->promptContextValue($actor['current_page_title'] ?? '', 160);
        $pageUiContext = is_array($actor['current_page_ui'] ?? null) ? $actor['current_page_ui'] : [];
        $systemContext = is_array($actor['system_context'] ?? null) ? $actor['system_context'] : [];
        $knowledgeContext = is_array($actor['knowledge_context'] ?? null) ? $actor['knowledge_context'] : [];
        $projectContext = is_array($actor['project_context'] ?? null) ? $actor['project_context'] : [];
        $retrievalPolicy = is_array($actor['retrieval_policy'] ?? null) ? $actor['retrieval_policy'] : [];
        $classification = is_array($actor['question_classification'] ?? null) ? $actor['question_classification'] : [];

        $parts = [
            'You are the IQS-Framework internal assistant.',
            'Your scope is limited to helping users understand and use the current IQS-Framework system.',
            'Answer in the user language where possible. If the user writes Malay, answer in Malay.',
            'Be concise, practical, and honest.',
            'If the user asks about topics unrelated to this system, politely say that you can only help with this system.',
            'Apply role-aware answer boundaries. Answer only within the current user role and access context.',
            'Do not provide detailed steps for administrator-only settings, hidden menus, restricted routes, provider/API setup, permission management, or internal configuration unless the current user context clearly allows that access.',
            'If the user asks about a restricted feature outside their current access, give a general support response and ask them to contact the system administrator. Do not reveal hidden menu names, routes, role structures, or setup steps.',
            'Do not claim you have performed system actions.',
            'Do not request or reveal passwords, tokens, cookies, CSRF tokens, API keys, or internal configuration.',
            'This prototype is read-only. Do not offer database writes, permission changes, or account changes.',
            'Do not invent system details. If the available context is insufficient, say that you do not have enough system information yet.',
            'Safe runtime context may include the app title, current page, visible UI labels/headings, active role/group, and chatbot access mode. Use it only as access-scoped guidance.',
            'Visible page UI context contains labels and headings only. It never authorizes hidden routes or actions, and it must not be treated as user-entered form values.',
            'Do not infer hidden menus, hidden routes, permission structures, or unavailable features from the current page path.',
            'If allowed visible system context is provided, treat it as already filtered by the application for the current active group.',
            'Use only the provided visible modules and menus when answering navigation questions. Do not add menu names or routes that are not listed in that context.',
            'If curated knowledge context is provided, use it as active permission-filtered help content for the current user visibility. Do not reveal knowledge items that are not provided.',
            'If curated knowledge does not contain the answer, say that the knowledge base does not have enough information yet instead of inventing details.',
            'If project data context is provided, use it only as permission-filtered live/current operational data for the current deployed system.',
            'Treat project database context as authoritative for current status, counts, assignments, dates, and latest operational values.',
            'Treat curated knowledge context as authoritative for policy, SOP, manual explanations, definitions, and workflow meaning.',
            'When both project data and curated knowledge are relevant, combine them carefully: live data answers what is current, knowledge base explains what it means or what process applies.',
            'Never ask the model to generate SQL and never mention internal table or column names unless they are explicitly present in user-visible documentation.',
            'Never reveal other users private project data, raw feedback comments, raw document paths, hidden records, or data not present in the permission-filtered project context.',
            'For system-specific questions about pages, menus, settings, roles, access, users, providers, models, configuration, or workflows, answer only from the permission-filtered runtime, visible system, or curated knowledge context provided in this prompt.',
            'If a system-specific answer cannot be grounded in the provided context, say that you do not have enough permission-filtered system context yet and suggest contacting the system administrator or support team.',
            'Use the question classification as a safety hint. If the category is sensitive_blocked or blocked_detail is true, refuse operational details and provide only a brief safe support response.',
            'If the category is unknown and provided context is insufficient, say that the chatbot does not have enough active retrieved knowledge yet.',
        ];

        if ($appTitle !== '') {
            $parts[] = 'Current system title: ' . $appTitle . '.';
        }

        if ($lang !== '') {
            $parts[] = 'Current user language: ' . $lang . '.';
        }

        if ($role !== '') {
            $parts[] = 'Current user role label: ' . $role . '.';
        }

        if ($groupCode !== '') {
            $parts[] = 'Current active group code: ' . strtoupper($groupCode) . '.';
        }

        if ($groupId !== '' && $groupId !== '0') {
            $parts[] = 'Current active group ID: ' . $groupId . '.';
        }

        if ($accessMode !== '') {
            $parts[] = 'Current chatbot access mode: ' . $accessMode . '.';
        }

        if ($pageTitle !== '') {
            $parts[] = 'Current page title: ' . $pageTitle . '.';
        }

        if ($pagePath !== '') {
            $parts[] = 'Current page path: ' . $pagePath . '.';
        }

        $pageUi = $this->formatPageUiContext($pageUiContext);
        if ($pageUi !== '') {
            $parts[] = $pageUi;
        }

        $retrieval = $this->formatRetrievalPolicy($retrievalPolicy);
        if ($retrieval !== '') {
            $parts[] = $retrieval;
        }

        $questionPolicy = $this->formatQuestionClassification($classification);
        if ($questionPolicy !== '') {
            $parts[] = $questionPolicy;
        }

        $visibleContext = $this->formatVisibleSystemContext($systemContext);
        if ($visibleContext !== '') {
            $parts[] = $visibleContext;
        }

        $knowledge = $this->formatKnowledgeContext($knowledgeContext);
        if ($knowledge !== '') {
            $parts[] = $knowledge;
        }

        $project = $this->formatProjectContext($projectContext);
        if ($project !== '') {
            $parts[] = $project;
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string,mixed> $classification
     */
    private function formatQuestionClassification(array $classification): string
    {
        if ($classification === []) {
            return '';
        }

        $category = $this->promptContextValue($classification['category'] ?? 'unknown', 60);
        $risk = $this->promptContextValue($classification['risk'] ?? 'low', 30);
        $blocked = !empty($classification['blocked_detail']);
        $needsReview = !empty($classification['needs_review']);
        $reason = $this->promptContextValue($classification['review_reason'] ?? '', 100);

        return implode("\n", [
            'Current question classification: ' . ($category !== '' ? $category : 'unknown') . '.',
            'Question risk level: ' . ($risk !== '' ? $risk : 'low') . '.',
            'Block detailed operational answer: ' . ($blocked ? 'yes' : 'no') . '.',
            'Needs review loop attention: ' . ($needsReview ? 'yes' : 'no') . '.',
            'Classification reason: ' . ($reason !== '' ? $reason : 'not_provided') . '.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function formatPageUiContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $lines = ['Visible page UI context provided by the browser:'];
        foreach ([
            'heading' => 'Page heading',
            'active_tab' => 'Active tab',
            'modal_title' => 'Open modal title',
        ] as $key => $label) {
            $value = $this->promptContextValue($context[$key] ?? '', 120);
            if ($value !== '') {
                $lines[] = $label . ': ' . $value . '.';
            }
        }

        foreach ([
            'form_labels' => ['Visible form labels', 18, 80],
            'validation_errors' => ['Visible validation/errors', 8, 160],
            'table_headings' => ['Visible table headings', 20, 80],
        ] as $key => [$label, $maxItems, $maxLength]) {
            $items = is_array($context[$key] ?? null) ? $context[$key] : [];
            $clean = [];
            foreach ($items as $item) {
                $text = $this->promptContextValue($item, (int)$maxLength);
                if ($text !== '') {
                    $clean[] = $text;
                }
                if (count($clean) >= (int)$maxItems) {
                    break;
                }
            }
            if ($clean !== []) {
                $lines[] = $label . ': ' . implode('; ', $clean) . '.';
            }
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * @param array<string,mixed> $policy
     */
    private function formatRetrievalPolicy(array $policy): string
    {
        if ($policy === []) {
            return '';
        }

        $mode = $this->promptContextValue($policy['mode'] ?? 'permission_filtered', 80);
        $requiresGrounded = !empty($policy['requires_grounded_answer']);
        $systemAvailable = !empty($policy['system_context_available']);
        $knowledgeAvailable = !empty($policy['knowledge_context_available']);
        $projectAvailable = !empty($policy['project_context_available']);
        $projectStatus = $this->promptContextValue($policy['project_context_status'] ?? '', 80);
        $projectProvider = $this->promptContextValue($policy['project_context_provider'] ?? '', 80);

        return implode("\n", [
            'Current retrieval policy: ' . ($mode !== '' ? $mode : 'permission_filtered') . '.',
            'System-specific grounding required: ' . ($requiresGrounded ? 'yes' : 'no') . '.',
            'Visible system context available: ' . ($systemAvailable ? 'yes' : 'no') . '.',
            'Curated knowledge context available: ' . ($knowledgeAvailable ? 'yes' : 'no') . '.',
            'Project data context available: ' . ($projectAvailable ? 'yes' : 'no') . '.',
            'Project data context status: ' . ($projectStatus !== '' ? $projectStatus : 'not_provided') . '.',
            'Project data context provider: ' . ($projectProvider !== '' ? $projectProvider : 'not_provided') . '.',
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function formatKnowledgeContext(array $context): string
    {
        $items = is_array($context['items'] ?? null) ? $context['items'] : [];
        if ($items === []) {
            return '';
        }

        $lines = ['Active permission-filtered curated knowledge context visible to the current user:'];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = $this->promptContextValue($item['title'] ?? '', 180);
            $question = $this->promptContextValue($item['question'] ?? '', 240);
            $answer = $this->promptContextValue($item['answer'] ?? '', 900);
            $sourceType = $this->promptContextValue($item['source_type'] ?? '', 40);
            if ($title === '' || $answer === '') {
                continue;
            }

            $lines[] = ((int)$index + 1) . '. ' . $title;
            if ($sourceType !== '') {
                $lines[] = 'Source type: ' . $sourceType;
            }
            if ($question !== '') {
                $lines[] = 'Question: ' . $question;
            }
            $lines[] = 'Answer: ' . $answer;
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * @param array<string,mixed> $context
     */
    private function formatVisibleSystemContext(array $context): string
    {
        $modules = is_array($context['visible_modules'] ?? null) ? $context['visible_modules'] : [];
        if ($modules === []) {
            return '';
        }

        $lines = ['Allowed visible system context for the current active group:'];
        $totals = is_array($context['totals'] ?? null) ? $context['totals'] : [];
        $moduleCount = (int)($totals['modules_in_prompt'] ?? count($modules));
        $menuCount = (int)($totals['menus_in_prompt'] ?? 0);
        $lines[] = 'Context totals in prompt: ' . $moduleCount . ' modules, ' . $menuCount . ' menus.';

        $currentPageMenu = is_array($context['current_page_menu'] ?? null) ? $context['current_page_menu'] : [];
        $currentMenuName = $this->promptContextValue($currentPageMenu['menu'] ?? '', 120);
        $currentModuleName = $this->promptContextValue($currentPageMenu['module'] ?? '', 120);
        if ($currentMenuName !== '') {
            $lines[] = 'Current page matched allowed menu: ' . $currentMenuName . ($currentModuleName !== '' ? ' under ' . $currentModuleName : '') . '.';
        }

        foreach ($modules as $module) {
            if (!is_array($module)) {
                continue;
            }

            $moduleName = $this->promptContextValue($module['name'] ?? '', 120);
            if ($moduleName === '') {
                continue;
            }

            $menuParts = [];
            $menus = is_array($module['menus'] ?? null) ? $module['menus'] : [];
            foreach ($menus as $menu) {
                if (!is_array($menu)) {
                    continue;
                }
                $menuName = $this->promptContextValue($menu['name'] ?? '', 120);
                $menuPath = $this->promptContextValue($menu['path'] ?? '', 180);
                if ($menuName === '') {
                    continue;
                }
                $menuParts[] = $menuName . ($menuPath !== '' ? ' [' . $menuPath . ']' : '');
            }

            $lines[] = '- ' . $moduleName . ': ' . ($menuParts !== [] ? implode('; ', $menuParts) : 'no visible menu listed');
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function formatProjectContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $status = $this->promptContextValue($context['status'] ?? '', 80);
        $provider = $this->promptContextValue($context['matched_provider'] ?? '', 80);
        $label = $this->promptContextValue($context['matched_label'] ?? '', 160);
        $inner = is_array($context['context'] ?? null) ? $context['context'] : [];
        $intent = $this->promptContextValue($inner['intent'] ?? '', 80);
        $scope = $this->promptContextValue($inner['scope'] ?? '', 120);
        $innerStatus = $this->promptContextValue($inner['status'] ?? '', 80);
        $records = is_array($inner['items'] ?? null) ? $inner['items'] : [];

        if ($records === [] && is_array($inner['records'] ?? null)) {
            $records = $inner['records'];
        }

        $lines = ['Permission-filtered project data context for the current deployed system:'];
        $lines[] = 'Registry status: ' . ($status !== '' ? $status : 'not_provided') . '.';
        if ($provider !== '' || $label !== '') {
            $lines[] = 'Matched project provider: ' . ($label !== '' ? $label : $provider) . ($provider !== '' ? ' [' . $provider . ']' : '') . '.';
        }
        if ($innerStatus !== '') {
            $lines[] = 'Provider context status: ' . $innerStatus . '.';
        }
        if ($intent !== '') {
            $lines[] = 'Detected project intent: ' . $intent . '.';
        }
        if ($scope !== '') {
            $lines[] = 'Applied project data scope: ' . $scope . '.';
        }

        if ($records === []) {
            if (in_array($innerStatus, ['data_unavailable', 'denied_missing_staff_scope', 'unsupported_intent'], true)) {
                $lines[] = 'No project records are available for this question and current user context.';
                return implode("\n", $lines);
            }

            return '';
        }

        $lines[] = 'Project data records visible to the current user:';
        foreach (array_slice($records, 0, 8) as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $parts = [];
            foreach ($record as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    continue;
                }

                $safeKey = $this->promptContextValue((string)$key, 50);
                $safeValue = $this->promptContextValue($value, 180);
                if ($safeKey === '' || $safeValue === '') {
                    continue;
                }

                $parts[] = $safeKey . '=' . $safeValue;
            }

            if ($parts !== []) {
                $lines[] = ((int)$index + 1) . '. ' . implode('; ', $parts) . '.';
            }
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    private function promptContextValue(mixed $value, int $maxLength): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
        $text = trim((string)$text);

        $maxLength = max(1, $maxLength);
        return function_exists('mb_substr')
            ? mb_substr($text, 0, $maxLength, 'UTF-8')
            : substr($text, 0, $maxLength);
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadDbConfigOverrides(): array
    {
        try {
            require_once __DIR__ . '/Database.php';
            require_once __DIR__ . '/Config.php';

            $pdo = Database::getInstance('mysql')->getConnection();
            $configModel = new Config($pdo);
            $group = $configModel->getGroup(SystemConfigConstants::CONFIG_GROUP_AI_CHATBOT);

            return is_array($group) ? $group : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function normalizeConfig(array $config): array
    {
        foreach ([
            'enabled',
            'persist_usage',
            'store_conversations',
            'log_message_content',
        ] as $key) {
            $config[$key] = self::normalizeBoolValue($config[$key] ?? false);
        }

        foreach ([
            'timeout_seconds',
            'max_input_chars',
            'max_output_tokens',
            'rate_limit_per_minute',
            'user_daily_request_limit',
            'global_daily_request_limit',
        ] as $key) {
            $config[$key] = (int)($config[$key] ?? 0);
        }

        foreach ($config as $key => $value) {
            if (!is_bool($value) && !is_int($value)) {
                $config[$key] = trim((string)$value);
            }
        }

        return $config;
    }

    private static function normalizeBoolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

}
