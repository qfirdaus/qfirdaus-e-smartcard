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

final class AiChatbotQuestionClassifier
{
    /**
     * @return array<string,mixed>
     */
    public function classify(string $message, bool $isPrivilegedProjectAdmin = false): array
    {
        $text = $this->normalize($message);

        if ($text === '') {
            return $this->result('unknown', 'low', false, 'empty_message');
        }

        if ($this->containsAny($text, [
            'bypass', 'hack', 'sql', 'sql injection', 'query database', 'database query',
            'drop table', 'dump database', 'database dump', 'schema database',
            'database schema', 'struktur table', 'struktur database', 'nama table',
            'senarai table', 'table monitoring', 'column database', 'kolum database', 'api key',
            'token', 'csrf', 'cookie', 'password', 'kata laluan', 'elevate', 'escalate',
            'role escalation', 'permission escalation', 'hidden route', 'route tersembunyi',
            'akses tersembunyi', 'cara masuk admin', 'super admin password',
        ])) {
            return $this->result('sensitive_blocked', 'high', true, 'sensitive_or_bypass_request');
        }

        if (!$isPrivilegedProjectAdmin && $this->containsAny($text, [
            'orang lain', 'pengguna lain', 'user lain', 'staf lain', 'semua owner',
            'semua staf', 'semua pengguna', 'semua projek', 'komen feedback',
            'raw feedback', 'feedback comment', 'dokumen laporan', 'document path',
            'laluan dokumen', 'file laporan',
        ])) {
            return $this->result('sensitive_blocked', 'high', true, 'cross_user_data_request');
        }

        if ($this->containsAny($text, ['error', 'ralat', 'gagal', 'failed', 'cannot', 'tak boleh', 'tidak boleh', 'problem', 'masalah'])) {
            return $this->result('troubleshooting', 'medium', false, 'troubleshooting_terms');
        }

        if ($this->containsAny($text, ['akses', 'access', 'permission', 'role', 'peranan', 'kumpulan', 'group', 'dibenarkan', 'forbidden'])) {
            return $this->result('access_help', 'medium', false, 'access_terms');
        }

        if ($this->containsAny($text, ['menu', 'modul', 'module', 'page', 'halaman', 'dashboard', 'navigation', 'navigasi', 'pergi ke', 'di mana'])) {
            return $this->result('navigation_help', 'low', false, 'navigation_terms');
        }

        if ($this->containsAny($text, ['setting', 'settings', 'tetapan', 'configure', 'configuration', 'provider', 'model', 'chatbot', 'pengguna', 'user', 'login'])) {
            return $this->result('system_help', 'medium', false, 'system_terms');
        }

        if ($this->containsAny($text, ['sistem', 'system', 'fungsi', 'workflow', 'proses', 'cara guna', 'how to'])) {
            return $this->result('system_help', 'low', false, 'general_system_terms');
        }

        return $this->result('unknown', 'low', false, 'no_known_terms');
    }

    /**
     * @return array<string,mixed>
     */
    private function result(string $category, string $risk, bool $blocked, string $reason): array
    {
        return [
            'category' => $category,
            'risk' => $risk,
            'blocked_detail' => $blocked,
            'review_reason' => $reason,
            'needs_review' => in_array($category, ['unknown', 'sensitive_blocked'], true),
        ];
    }

    /**
     * @param array<int,string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $message): string
    {
        $message = strip_tags($message);
        $message = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$message);
        $message = preg_replace('/\s+/u', ' ', (string)$message);

        return trim((string)$message);
    }
}
