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

final class AiChatbotActionSuggestionService
{
    private const MAX_ACTIONS = 3;

    /**
     * @param array<string,mixed> $systemContext
     * @param array<string,mixed> $classification
     * @return array<int,array<string,mixed>>
     */
    public function build(string $message, array $systemContext, array $classification = []): array
    {
        $category = (string)($classification['category'] ?? 'unknown');
        if ($systemContext === [] || $category === 'sensitive_blocked' || !empty($classification['blocked_detail'])) {
            return [];
        }

        $menus = $this->visibleMenus($systemContext);
        if ($menus === []) {
            return [];
        }

        $terms = $this->extractTerms($message);
        $ranked = $this->rankMenus($menus, $terms, $category);
        $actions = [];
        foreach ($ranked as $menu) {
            $path = $this->safePath((string)($menu['path'] ?? ''));
            $label = $this->safeLabel((string)($menu['name'] ?? ''));
            if ($path === '' || $label === '') {
                continue;
            }

            $actions[] = [
                'type' => 'navigation',
                'label' => 'Open ' . $label,
                'url' => function_exists('base_url') ? base_url(ltrim($path, '/')) : '/' . ltrim($path, '/'),
                'method' => 'GET',
                'requires_confirmation' => false,
                'source' => 'allowed_visible_menu',
            ];

            if (count($actions) >= self::MAX_ACTIONS) {
                break;
            }
        }

        return $actions;
    }

    /**
     * @param array<string,mixed> $systemContext
     * @return array<int,array<string,string>>
     */
    private function visibleMenus(array $systemContext): array
    {
        $modules = is_array($systemContext['visible_modules'] ?? null) ? $systemContext['visible_modules'] : [];
        $menus = [];
        foreach ($modules as $module) {
            if (!is_array($module)) {
                continue;
            }
            $moduleName = $this->safeLabel((string)($module['name'] ?? ''));
            foreach ((is_array($module['menus'] ?? null) ? $module['menus'] : []) as $menu) {
                if (!is_array($menu)) {
                    continue;
                }
                $name = $this->safeLabel((string)($menu['name'] ?? ''));
                $path = $this->safePath((string)($menu['path'] ?? ''));
                if ($name === '' || $path === '') {
                    continue;
                }
                $menus[] = [
                    'module' => $moduleName,
                    'name' => $name,
                    'path' => $path,
                ];
            }
        }

        return $menus;
    }

    /**
     * @param array<int,array<string,string>> $menus
     * @param array<int,string> $terms
     * @return array<int,array<string,string>>
     */
    private function rankMenus(array $menus, array $terms, string $category): array
    {
        $ranked = [];
        foreach ($menus as $menu) {
            $haystack = mb_strtolower(($menu['module'] ?? '') . ' ' . ($menu['name'] ?? '') . ' ' . ($menu['path'] ?? ''), 'UTF-8');
            $score = 0;
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $score += 10;
                }
            }
            if ($category === 'navigation_help') {
                $score += 4;
            } elseif (in_array($category, ['system_help', 'access_help', 'troubleshooting'], true)) {
                $score += 2;
            }

            if ($score <= 0 && $terms !== []) {
                continue;
            }

            $menu['_score'] = (string)$score;
            $ranked[] = $menu;
        }

        usort($ranked, static fn(array $a, array $b): int => ((int)($b['_score'] ?? 0) <=> (int)($a['_score'] ?? 0)));
        return $ranked;
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
            'apa' => true, 'itu' => true, 'ini' => true, 'dan' => true, 'atau' => true, 'yang' => true,
            'untuk' => true, 'boleh' => true, 'macam' => true, 'mana' => true, 'saya' => true,
            'how' => true, 'what' => true, 'where' => true, 'the' => true, 'and' => true, 'for' => true,
            'can' => true, 'you' => true, 'please' => true,
        ];

        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part, 'UTF-8') < 3 || isset($stopwords[$part])) {
                continue;
            }
            $terms[] = $part;
            if (count($terms) >= 8) {
                break;
            }
        }

        return array_values(array_unique($terms));
    }

    private function safeLabel(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        return mb_substr(trim((string)$value), 0, 120, 'UTF-8');
    }

    private function safePath(string $value): string
    {
        $path = parse_url(trim($value), PHP_URL_PATH);
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = trim((string)$path);
        if (str_contains($path, '..') || !preg_match('/^[A-Za-z0-9_\-.\/]+$/', $path)) {
            return '';
        }

        return mb_substr($path, 0, 255, 'UTF-8');
    }
}
