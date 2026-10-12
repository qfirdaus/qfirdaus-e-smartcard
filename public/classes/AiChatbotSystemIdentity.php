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

final class AiChatbotSystemIdentity
{
    private const DEFAULT_NOISE_WORDS = [
        'sistem',
        'system',
        'portal',
        'aplikasi',
        'application',
        'app',
        'development',
        'staging',
        'production',
        'prod',
        'dev',
        'test',
        'testing',
        'demo',
        'uat',
    ];

    private string $normalizedName;

    /** @var array<int,string> */
    private array $tokens;

    /**
     * @param array<int,string>|null $noiseWords
     */
    public function __construct(
        private readonly string $systemName,
        ?array $noiseWords = null
    ) {
        $this->normalizedName = self::normalize($systemName, $noiseWords);
        $this->tokens = self::tokenizeNormalized($this->normalizedName);
    }

    public static function fromName(string $systemName): self
    {
        return new self($systemName);
    }

    public static function fromConfig(): self
    {
        $systemName = function_exists('app_config')
            ? (string)app_config('system.name', '')
            : '';

        return new self($systemName);
    }

    public function rawName(): string
    {
        return $this->systemName;
    }

    public function normalizedName(): string
    {
        return $this->normalizedName;
    }

    public function compactName(): string
    {
        return self::compact($this->normalizedName);
    }

    /**
     * @return array<int,string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * @param array<int,string> $aliases
     * @return array{status:string,score:int,alias:string,normalized_alias:string,ambiguous:bool,candidates:array<int,array{alias:string,normalized_alias:string,score:int}>}
     */
    public function matchAliases(array $aliases, int $minimumScore = 80): array
    {
        $candidates = [];
        foreach ($aliases as $alias) {
            $alias = trim((string)$alias);
            if ($alias === '') {
                continue;
            }

            $normalizedAlias = self::normalize($alias);
            $score = $this->scoreNormalizedAlias($normalizedAlias);
            if ($score <= 0) {
                continue;
            }

            $candidates[] = [
                'alias' => $alias,
                'normalized_alias' => $normalizedAlias,
                'score' => $score,
            ];
        }

        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        $best = $candidates[0] ?? ['alias' => '', 'normalized_alias' => '', 'score' => 0];
        $bestScore = (int)$best['score'];
        return [
            'status' => $bestScore >= $minimumScore ? 'matched' : 'no_match',
            'score' => $bestScore,
            'alias' => (string)$best['alias'],
            'normalized_alias' => (string)$best['normalized_alias'],
            'ambiguous' => false,
            'candidates' => $candidates,
        ];
    }

    public function scoreAlias(string $alias): int
    {
        return $this->scoreNormalizedAlias(self::normalize($alias));
    }

    /**
     * @param array<int,string>|null $noiseWords
     */
    public static function normalize(string $value, ?array $noiseWords = null): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = self::lower(trim($value));
        $value = preg_replace('/(?<=[\p{L}\p{N}])[\-_](?=[\p{L}\p{N}])/u', '', (string)$value);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', (string)$value);
        $value = preg_replace('/\s+/u', ' ', (string)$value);
        $value = trim((string)$value);

        if ($value === '') {
            return '';
        }

        $noise = [];
        foreach (($noiseWords ?? self::DEFAULT_NOISE_WORDS) as $word) {
            $word = trim((string)$word);
            if ($word !== '') {
                $noise[self::compact(self::lower($word))] = true;
            }
        }

        $tokens = [];
        foreach (preg_split('/\s+/u', $value) ?: [] as $token) {
            $token = trim((string)$token);
            if ($token === '') {
                continue;
            }
            if (isset($noise[self::compact($token)])) {
                continue;
            }
            $tokens[] = $token;
        }

        return trim(implode(' ', array_values(array_unique($tokens))));
    }

    public static function compact(string $value): string
    {
        return preg_replace('/\s+/u', '', trim($value)) ?: '';
    }

    /**
     * @return array<int,string>
     */
    private static function tokenizeNormalized(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            static fn(string $token): bool => $token !== ''
        ));
    }

    private function scoreNormalizedAlias(string $normalizedAlias): int
    {
        if ($this->normalizedName === '' || $normalizedAlias === '') {
            return 0;
        }

        if ($this->normalizedName === $normalizedAlias) {
            return 100;
        }

        $systemCompact = $this->compactName();
        $aliasCompact = self::compact($normalizedAlias);
        if ($systemCompact !== '' && $systemCompact === $aliasCompact) {
            return 95;
        }

        $aliasTokens = self::tokenizeNormalized($normalizedAlias);
        $aliasTokenCount = count($aliasTokens);

        if ($aliasTokenCount > 0 && str_contains(' ' . $this->normalizedName . ' ', ' ' . $normalizedAlias . ' ')) {
            return $aliasTokenCount >= 2 ? 90 : 85;
        }

        if ($aliasCompact !== '' && str_contains($systemCompact, $aliasCompact)) {
            return self::length($aliasCompact) >= 4 ? 85 : 0;
        }

        if ($aliasTokenCount >= 2 && str_contains(' ' . $normalizedAlias . ' ', ' ' . $this->normalizedName . ' ')) {
            return 75;
        }

        return 0;
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
