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

final class AiChatbotKnowledgeContext
{
    private const TABLE_NAME = 'tbl_ai_chat_knowledge';
    private const SOURCE_TABLE_NAME = 'tbl_ai_chat_knowledge_source';
    private const CHUNK_TABLE_NAME = 'tbl_ai_chat_knowledge_chunk';
    private const ALLOWED_LANGUAGES = ['ms', 'en'];
    private const MAX_ITEMS = 5;
    private const MAX_SOURCE_ITEMS = 8;
    private const MAX_TERMS = 6;
    private const MAX_EXPANDED_TERMS = 14;
    private const MAX_ANSWER_CHARS = 900;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function build(string $message, array $profile, array $actor = []): array
    {
        $manualTableAvailable = $this->tableExists(self::TABLE_NAME);
        $sourceTableAvailable = $this->tableExists(self::SOURCE_TABLE_NAME);
        $chunkTableAvailable = $this->tableExists(self::CHUNK_TABLE_NAME);
        if (!$manualTableAvailable && !$chunkTableAvailable) {
            return [];
        }

        $terms = $this->extractTerms($message);
        if ($terms === []) {
            return [];
        }
        $expandedTerms = $this->expandTerms($terms);

        $lang = $this->safeLang((string)($actor['lang'] ?? $_SESSION['lang'] ?? 'ms'));
        $groupId = (int)($actor['active_group_id'] ?? $_SESSION['group_active_id'] ?? ($profile['f_groupID'] ?? 0));
        $groupCode = strtoupper(trim((string)($actor['active_group_code'] ?? $profile['f_groupKod'] ?? '')));
        $isSuperAdmin = (bool)($actor['is_super_admin'] ?? false);

        try {
            $items = $this->fetchItems(
                $expandedTerms,
                $lang,
                $groupId,
                $groupCode,
                $isSuperAdmin,
                $manualTableAvailable,
                $sourceTableAvailable,
                $chunkTableAvailable
            );
            if ($items === []) {
                return [];
            }

            return [
                'source' => 'curated_knowledge_base',
                'retrieval_mode' => 'hybrid_manual_pdf_chunk_keyword_ranked',
                'visibility_filtered' => true,
                'search_terms' => $terms,
                'expanded_terms' => array_values(array_diff($expandedTerms, $terms)),
                'filters' => [
                    'language' => $lang,
                    'group_id' => $groupId,
                    'group_code' => $groupCode,
                    'super_admin' => $isSuperAdmin,
                ],
                'totals' => [
                    'items_in_prompt' => count($items),
                    'manual_table_available' => $manualTableAvailable,
                    'pdf_source_table_available' => $sourceTableAvailable,
                    'pdf_chunk_table_available' => $chunkTableAvailable,
                ],
                'items' => $items,
            ];
        } catch (Throwable $e) {
            error_log('[ai-chatbot-knowledge-context] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<int,string> $terms
     * @return array<int,array<string,mixed>>
     */
    private function fetchItems(
        array $terms,
        string $lang,
        int $groupId,
        string $groupCode,
        bool $isSuperAdmin,
        bool $manualTableAvailable,
        bool $sourceTableAvailable,
        bool $chunkTableAvailable
    ): array
    {
        $items = [];
        if ($manualTableAvailable) {
            $items = array_merge($items, $this->fetchManualItems($terms, $lang, $groupId, $groupCode, $isSuperAdmin));
        }
        if ($chunkTableAvailable && $sourceTableAvailable) {
            $items = array_merge($items, $this->fetchChunkItems($terms, $lang, $groupId, $groupCode, $isSuperAdmin));
        }

        usort($items, static function (array $a, array $b): int {
            $scoreCompare = ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $priorityCompare = ((int)($a['priority'] ?? 100)) <=> ((int)($b['priority'] ?? 100));
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
        });

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * @param array<int,string> $terms
     * @return array<int,array<string,mixed>>
     */
    private function fetchManualItems(array $terms, string $lang, int $groupId, string $groupCode, bool $isSuperAdmin): array
    {
        $params = [
            ':lang' => $lang,
            ':group_id' => (string)$groupId,
            ':group_code' => $groupCode,
            ':is_super_admin' => $isSuperAdmin ? 1 : 0,
            ':limit' => self::MAX_SOURCE_ITEMS,
        ];

        $searchParts = [];
        $scoreParts = [];
        foreach ($terms as $index => $term) {
            $titleSearchKey = $this->addTermParam($params, $index, 'manual_title_search', $term);
            $questionSearchKey = $this->addTermParam($params, $index, 'manual_question_search', $term);
            $tagsSearchKey = $this->addTermParam($params, $index, 'manual_tags_search', $term);
            $answerSearchKey = $this->addTermParam($params, $index, 'manual_answer_search', $term);
            $titleScoreKey = $this->addTermParam($params, $index, 'manual_title_score', $term);
            $questionScoreKey = $this->addTermParam($params, $index, 'manual_question_score', $term);
            $tagsScoreKey = $this->addTermParam($params, $index, 'manual_tags_score', $term);
            $answerScoreKey = $this->addTermParam($params, $index, 'manual_answer_score', $term);

            $searchParts[] = "(f_title LIKE {$titleSearchKey} OR COALESCE(f_question, '') LIKE {$questionSearchKey} OR COALESCE(f_tags, '') LIKE {$tagsSearchKey} OR f_answer LIKE {$answerSearchKey})";
            $scoreParts[] = "(CASE WHEN f_title LIKE {$titleScoreKey} THEN 12 ELSE 0 END)";
            $scoreParts[] = "(CASE WHEN COALESCE(f_question, '') LIKE {$questionScoreKey} THEN 9 ELSE 0 END)";
            $scoreParts[] = "(CASE WHEN COALESCE(f_tags, '') LIKE {$tagsScoreKey} THEN 8 ELSE 0 END)";
            $scoreParts[] = "(CASE WHEN f_answer LIKE {$answerScoreKey} THEN 3 ELSE 0 END)";
        }

        $sql = "
            SELECT
                f_title,
                f_question,
                f_answer,
                f_language,
                f_visibility,
                f_tags,
                f_priority,
                f_updatedDt,
                f_createdDt,
                (" . implode(' + ', $scoreParts) . ") AS relevance_score
            FROM " . self::TABLE_NAME . "
            WHERE f_status = 'active'
              AND f_language = :lang
              AND (
                f_visibility = 'all_authenticated'
                OR (:is_super_admin = 1 AND f_visibility = 'super_admin_only')
                OR (
                  f_visibility = 'selected_groups'
                  AND (
                    FIND_IN_SET(:group_id, REPLACE(COALESCE(f_allowedGroups, ''), ' ', '')) > 0
                    OR FIND_IN_SET(:group_code, UPPER(REPLACE(COALESCE(f_allowedGroups, ''), ' ', ''))) > 0
                  )
                )
              )
              AND (" . implode(' OR ', $searchParts) . ")
            ORDER BY relevance_score DESC, f_priority ASC, f_updatedDt DESC, f_createdDt DESC
            LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === ':limit' || $key === ':is_super_admin' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $title = $this->safeText((string)($row['f_title'] ?? ''), 180);
            $answer = $this->safeText(strip_tags((string)($row['f_answer'] ?? '')), self::MAX_ANSWER_CHARS);
            if ($title === '' || $answer === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'question' => $this->safeText((string)($row['f_question'] ?? ''), 240),
                'answer' => $answer,
                'language' => $this->safeText((string)($row['f_language'] ?? ''), 10),
                'visibility' => $this->safeText((string)($row['f_visibility'] ?? ''), 40),
                'tags' => $this->safeText((string)($row['f_tags'] ?? ''), 180),
                'score' => (string)((int)($row['relevance_score'] ?? 0)),
                'priority' => (int)($row['f_priority'] ?? 100),
                'updated_at' => (string)($row['f_updatedDt'] ?? $row['f_createdDt'] ?? ''),
                'source_type' => 'manual_text',
            ];
        }

        return $items;
    }

    /**
     * @param array<int,string> $terms
     * @return array<int,array<string,mixed>>
     */
    private function fetchChunkItems(array $terms, string $lang, int $groupId, string $groupCode, bool $isSuperAdmin): array
    {
        $params = [
            ':chunk_lang' => $lang,
            ':source_lang' => $lang,
            ':group_id' => (string)$groupId,
            ':group_code' => $groupCode,
            ':is_super_admin' => $isSuperAdmin ? 1 : 0,
            ':limit' => self::MAX_SOURCE_ITEMS,
        ];

        $searchParts = [];
        $scoreParts = [];
        foreach ($terms as $index => $term) {
            $titleSearchKey = $this->addTermParam($params, $index, 'chunk_title_search', $term);
            $tagsSearchKey = $this->addTermParam($params, $index, 'chunk_tags_search', $term);
            $textSearchKey = $this->addTermParam($params, $index, 'chunk_text_search', $term);
            $titleScoreKey = $this->addTermParam($params, $index, 'chunk_title_score', $term);
            $tagsScoreKey = $this->addTermParam($params, $index, 'chunk_tags_score', $term);
            $textScoreKey = $this->addTermParam($params, $index, 'chunk_text_score', $term);

            $searchParts[] = "(COALESCE(c.f_chunkTitle, '') LIKE {$titleSearchKey} OR COALESCE(c.f_tags, '') LIKE {$tagsSearchKey} OR c.f_chunkText LIKE {$textSearchKey})";
            $scoreParts[] = "(CASE WHEN COALESCE(c.f_chunkTitle, '') LIKE {$titleScoreKey} THEN 11 ELSE 0 END)";
            $scoreParts[] = "(CASE WHEN COALESCE(c.f_tags, '') LIKE {$tagsScoreKey} THEN 8 ELSE 0 END)";
            $scoreParts[] = "(CASE WHEN c.f_chunkText LIKE {$textScoreKey} THEN 4 ELSE 0 END)";
        }

        $sql = "
            SELECT
                c.f_chunkTitle,
                c.f_chunkText,
                c.f_sourcePublicID,
                c.f_chunkIndex,
                c.f_language,
                c.f_visibility,
                c.f_tags,
                c.f_priority,
                c.f_updatedDt,
                c.f_createdDt,
                (" . implode(' + ', $scoreParts) . ") AS relevance_score
            FROM " . self::CHUNK_TABLE_NAME . " c
            INNER JOIN " . self::SOURCE_TABLE_NAME . " s
              ON s.f_publicID = c.f_sourcePublicID
            WHERE c.f_status = 'active'
              AND s.f_status = 'active'
              AND s.f_extractionStatus = 'processed'
              AND c.f_language = :chunk_lang
              AND s.f_language = :source_lang
              AND (
                c.f_visibility = 'all_authenticated'
                OR (:is_super_admin = 1 AND c.f_visibility = 'super_admin_only')
                OR (
                  c.f_visibility = 'selected_groups'
                  AND (
                    FIND_IN_SET(:group_id, REPLACE(COALESCE(c.f_allowedGroups, ''), ' ', '')) > 0
                    OR FIND_IN_SET(:group_code, UPPER(REPLACE(COALESCE(c.f_allowedGroups, ''), ' ', ''))) > 0
                  )
                )
              )
              AND (" . implode(' OR ', $searchParts) . ")
            ORDER BY relevance_score DESC, c.f_priority ASC, c.f_updatedDt DESC, c.f_createdDt DESC
            LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === ':limit' || $key === ':is_super_admin' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $title = $this->safeText((string)($row['f_chunkTitle'] ?? ''), 180);
            if ($title === '') {
                $title = 'PDF knowledge chunk ' . ((int)($row['f_chunkIndex'] ?? 0) + 1);
            }

            $answer = $this->safeText(strip_tags((string)($row['f_chunkText'] ?? '')), self::MAX_ANSWER_CHARS);
            if ($answer === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'question' => '',
                'answer' => $answer,
                'language' => $this->safeText((string)($row['f_language'] ?? ''), 10),
                'visibility' => $this->safeText((string)($row['f_visibility'] ?? ''), 40),
                'tags' => $this->safeText((string)($row['f_tags'] ?? ''), 180),
                'score' => (string)((int)($row['relevance_score'] ?? 0)),
                'priority' => (int)($row['f_priority'] ?? 100),
                'updated_at' => (string)($row['f_updatedDt'] ?? $row['f_createdDt'] ?? ''),
                'source_type' => 'pdf_chunk',
                'source_public_id' => $this->safeText((string)($row['f_sourcePublicID'] ?? ''), 36),
            ];
        }

        return $items;
    }

    /**
     * @return array<int,string>
     */
    private function extractTerms(string $message): array
    {
        $message = mb_strtolower(strip_tags($message), 'UTF-8');
        $message = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', (string)$message);
        $parts = preg_split('/\s+/u', trim((string)$message)) ?: [];
        $stopwords = [
            'apa' => true, 'itu' => true, 'ini' => true, 'dan' => true, 'atau' => true,
            'yang' => true, 'untuk' => true, 'boleh' => true, 'macam' => true, 'mana' => true,
            'how' => true, 'what' => true, 'where' => true, 'the' => true, 'and' => true,
            'for' => true, 'can' => true, 'you' => true, 'please' => true,
        ];

        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part, 'UTF-8') < 3 || isset($stopwords[$part])) {
                continue;
            }
            $terms[] = $part;
            if (count($terms) >= self::MAX_TERMS) {
                break;
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param array<int,string> $terms
     * @return array<int,string>
     */
    private function expandTerms(array $terms): array
    {
        $aliases = $this->semanticAliases();
        $expanded = [];

        foreach ($terms as $term) {
            $term = mb_strtolower(trim($term), 'UTF-8');
            if ($term === '') {
                continue;
            }
            $expanded[] = $term;
            foreach ($aliases[$term] ?? [] as $alias) {
                $expanded[] = $alias;
            }
            if (count(array_unique($expanded)) >= self::MAX_EXPANDED_TERMS) {
                break;
            }
        }

        return array_slice(array_values(array_unique($expanded)), 0, self::MAX_EXPANDED_TERMS);
    }

    /**
     * Lightweight semantic alias map for common IQS support intents.
     *
     * @return array<string,array<int,string>>
     */
    private function semanticAliases(): array
    {
        return [
            'akaun' => ['account', 'login', 'akses'],
            'access' => ['akses', 'permission', 'role'],
            'akses' => ['access', 'permission', 'role', 'peranan'],
            'chatbot' => ['ai', 'assistant', 'pembantu'],
            'dashboard' => ['ringkasan', 'summary', 'home'],
            'emel' => ['email', 'mail', 'template'],
            'email' => ['emel', 'mail', 'template'],
            'error' => ['ralat', 'gagal', 'troubleshooting'],
            'gagal' => ['failed', 'error', 'ralat'],
            'help' => ['bantuan', 'panduan', 'manual'],
            'kebenaran' => ['akses', 'permission', 'role'],
            'login' => ['log masuk', 'akaun', 'password', 'kata laluan'],
            'manual' => ['panduan', 'help', 'dokumen'],
            'menu' => ['navigasi', 'sidebar', 'modul'],
            'modul' => ['module', 'menu', 'sidebar'],
            'navigation' => ['navigasi', 'menu', 'sidebar'],
            'navigasi' => ['navigation', 'menu', 'sidebar'],
            'notification' => ['notifikasi', 'makluman', 'peringatan'],
            'notifikasi' => ['notification', 'makluman', 'peringatan'],
            'page' => ['halaman', 'screen', 'menu'],
            'password' => ['kata laluan', 'login', 'akaun'],
            'permission' => ['akses', 'kebenaran', 'role'],
            'peranan' => ['role', 'akses', 'kumpulan'],
            'provider' => ['model', 'ai', 'chatbot'],
            'ralat' => ['error', 'gagal', 'troubleshooting'],
            'role' => ['peranan', 'akses', 'kumpulan'],
            'setting' => ['tetapan', 'configuration', 'config'],
            'settings' => ['tetapan', 'configuration', 'config'],
            'sistem' => ['system', 'tetapan', 'framework'],
            'system' => ['sistem', 'settings', 'framework'],
            'template' => ['emel', 'email', 'notification'],
            'tetapan' => ['settings', 'configuration', 'config'],
            'troubleshoot' => ['ralat', 'error', 'gagal'],
            'troubleshooting' => ['ralat', 'error', 'gagal'],
        ];
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ');
            $stmt->execute([':table_name' => $tableName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $params
     */
    private function addTermParam(array &$params, int $index, string $suffix, string $term): string
    {
        $key = ':term' . $index . '_' . $suffix;
        $params[$key] = '%' . $term . '%';

        return $key;
    }

    private function safeLang(string $lang): string
    {
        return in_array($lang, self::ALLOWED_LANGUAGES, true) ? $lang : 'ms';
    }

    private function safeText(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = trim((string)$value);

        return mb_substr($value, 0, max(1, $maxLength), 'UTF-8');
    }
}
