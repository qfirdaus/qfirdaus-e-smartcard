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

final class AiChatbotReviewDashboardService
{
    private const TABLE_NAME = 'tbl_ai_chat_usage';

    public function __construct(private PDO $pdo)
    {
    }

    public function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ');
            $stmt->execute([':table_name' => self::TABLE_NAME]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function build(int $days = 30, int $limit = 500): array
    {
        if (!$this->tableExists()) {
            return $this->emptyDashboard();
        }

        $days = max(1, min(365, $days));
        $limit = max(50, min(2000, $limit));
        $rows = $this->fetchRows($days, $limit);

        return [
            'table_available' => true,
            'days' => $days,
            'limit' => $limit,
            'summary' => $this->summary($rows),
            'outcomes' => $this->groupCount($rows, 'f_outcome'),
            'categories' => $this->groupCount($rows, 'question_category'),
            'risks' => $this->groupCount($rows, 'question_risk'),
            'provider_latency' => $this->providerLatency($rows),
            'review_items' => $this->reviewItems($rows),
            'failures' => $this->failureItems($rows),
            'no_knowledge' => $this->noKnowledgeItems($rows),
            'recent' => array_slice($rows, 0, 100),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'table_available' => false,
            'days' => 30,
            'limit' => 500,
            'summary' => [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'needs_review' => 0,
                'no_knowledge' => 0,
                'avg_latency_ms' => 0,
            ],
            'outcomes' => [],
            'categories' => [],
            'risks' => [],
            'provider_latency' => [],
            'review_items' => [],
            'failures' => [],
            'no_knowledge' => [],
            'recent' => [],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRows(int $days, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              f_aiChatUsageID,
              f_loginID,
              f_provider,
              f_model,
              f_requestType,
              f_totalTokens,
              f_latencyMs,
              f_httpStatus,
              f_outcome,
              f_errorCode,
              f_errorMessage,
              f_requestMetaJson,
              f_createdDt
            FROM " . self::TABLE_NAME . "
            WHERE f_createdDt >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY f_createdDt DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $meta = $this->decodeMeta($row['f_requestMetaJson'] ?? null);
            $rows[] = [
                'id' => (int)($row['f_aiChatUsageID'] ?? 0),
                'created_at' => (string)($row['f_createdDt'] ?? ''),
                'login_id' => (string)($row['f_loginID'] ?? ''),
                'provider' => (string)($row['f_provider'] ?? 'unknown'),
                'model' => (string)($row['f_model'] ?? 'unknown'),
                'request_type' => (string)($row['f_requestType'] ?? 'chat'),
                'total_tokens' => $this->intValue($row['f_totalTokens'] ?? null),
                'latency_ms' => $this->intValue($row['f_latencyMs'] ?? null),
                'http_status' => $this->intValue($row['f_httpStatus'] ?? null),
                'outcome' => (string)($row['f_outcome'] ?? 'unknown'),
                'error_code' => (string)($row['f_errorCode'] ?? ''),
                'error_message' => (string)($row['f_errorMessage'] ?? ''),
                'message_length' => $this->intValue($meta['message_length'] ?? null),
                'current_page_path' => (string)($meta['current_page_path'] ?? ''),
                'question_category' => (string)($meta['question_category'] ?? 'unknown'),
                'question_risk' => (string)($meta['question_risk'] ?? 'unknown'),
                'question_needs_review' => $this->boolValue($meta['question_needs_review'] ?? false),
                'question_review_reason' => (string)($meta['question_review_reason'] ?? ''),
                'blocked_detail' => $this->boolValue($meta['blocked_detail'] ?? false),
                'knowledge_items_in_prompt' => $this->intValue($meta['knowledge_items_in_prompt'] ?? null),
                'requires_grounded_answer' => $this->boolValue($meta['requires_grounded_answer'] ?? false),
                'project_context' => is_array($meta['project_context'] ?? null) ? $meta['project_context'] : [],
                'project_provider' => (string)($meta['project_context']['provider'] ?? ''),
                'project_provider_label' => (string)($meta['project_context']['provider_label'] ?? ''),
                'project_match_score' => $this->intValue($meta['project_context']['match_score'] ?? null),
                'project_intent' => (string)($meta['project_context']['intent'] ?? ''),
                'project_scope' => (string)($meta['project_context']['scope'] ?? ''),
                'project_row_count' => $this->intValue($meta['project_context']['row_count'] ?? null),
                'project_has_records' => $this->boolValue($meta['project_context']['has_records'] ?? false),
                'project_denied_reason' => (string)($meta['project_context']['denied_reason'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,int>
     */
    private function summary(array $rows): array
    {
        $latencyTotal = 0;
        $latencyCount = 0;
        $summary = [
            'total' => count($rows),
            'success' => 0,
            'failed' => 0,
            'needs_review' => 0,
            'no_knowledge' => 0,
            'avg_latency_ms' => 0,
        ];

        foreach ($rows as $row) {
            if (($row['outcome'] ?? '') === 'success') {
                $summary['success']++;
            } else {
                $summary['failed']++;
            }
            if (!empty($row['question_needs_review']) || in_array((string)($row['question_category'] ?? ''), ['unknown', 'sensitive_blocked'], true)) {
                $summary['needs_review']++;
            }
            if ($this->isNoKnowledgeCandidate($row)) {
                $summary['no_knowledge']++;
            }
            $latency = (int)($row['latency_ms'] ?? 0);
            if ($latency > 0) {
                $latencyTotal += $latency;
                $latencyCount++;
            }
        }

        $summary['avg_latency_ms'] = $latencyCount > 0 ? (int)round($latencyTotal / $latencyCount) : 0;
        return $summary;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{label:string,total:int}>
     */
    private function groupCount(array $rows, string $key): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $label = trim((string)($row[$key] ?? ''));
            if ($label === '') {
                $label = 'unknown';
            }
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        arsort($counts);

        $items = [];
        foreach ($counts as $label => $total) {
            $items[] = ['label' => (string)$label, 'total' => (int)$total];
        }
        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function providerLatency(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = (string)($row['provider'] ?? 'unknown') . ' / ' . (string)($row['model'] ?? 'unknown');
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $key, 'total' => 0, 'success' => 0, 'failed' => 0, 'latency_total' => 0, 'latency_count' => 0];
            }
            $groups[$key]['total']++;
            if (($row['outcome'] ?? '') === 'success') {
                $groups[$key]['success']++;
            } else {
                $groups[$key]['failed']++;
            }
            $latency = (int)($row['latency_ms'] ?? 0);
            if ($latency > 0) {
                $groups[$key]['latency_total'] += $latency;
                $groups[$key]['latency_count']++;
            }
        }

        $items = [];
        foreach ($groups as $group) {
            $items[] = [
                'label' => $group['label'],
                'total' => (int)$group['total'],
                'success' => (int)$group['success'],
                'failed' => (int)$group['failed'],
                'avg_latency_ms' => (int)($group['latency_count'] > 0 ? round($group['latency_total'] / $group['latency_count']) : 0),
            ];
        }
        usort($items, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));
        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function reviewItems(array $rows): array
    {
        return array_values(array_slice(array_filter($rows, static function (array $row): bool {
            return !empty($row['question_needs_review'])
                || in_array((string)($row['question_category'] ?? ''), ['unknown', 'sensitive_blocked'], true)
                || !empty($row['blocked_detail']);
        }), 0, 100));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function failureItems(array $rows): array
    {
        return array_values(array_slice(array_filter($rows, static fn(array $row): bool => (string)($row['outcome'] ?? '') !== 'success'), 0, 100));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function noKnowledgeItems(array $rows): array
    {
        return array_values(array_slice(array_filter($rows, fn(array $row): bool => $this->isNoKnowledgeCandidate($row)), 0, 100));
    }

    /**
     * @param array<string,mixed> $row
     */
    private function isNoKnowledgeCandidate(array $row): bool
    {
        return (string)($row['outcome'] ?? '') === 'success'
            && (int)($row['knowledge_items_in_prompt'] ?? 0) <= 0
            && (
                !empty($row['requires_grounded_answer'])
                || in_array((string)($row['question_category'] ?? ''), ['system_help', 'navigation_help', 'access_help', 'troubleshooting', 'unknown'], true)
            );
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMeta(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes'], true);
    }
}
