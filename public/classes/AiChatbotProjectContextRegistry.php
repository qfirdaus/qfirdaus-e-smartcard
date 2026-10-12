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

require_once __DIR__ . '/AiChatbotSystemIdentity.php';
require_once __DIR__ . '/AiChatbotProjectContextProviderInterface.php';
require_once __DIR__ . '/AiChatbotEPmsContextProvider.php';

final class AiChatbotProjectContextRegistry
{
    public const DEFAULT_MINIMUM_MATCH_SCORE = 80;

    /** @var array<int,AiChatbotProjectContextProviderInterface> */
    private array $providers;

    /**
     * @param array<int,AiChatbotProjectContextProviderInterface> $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = array_values(array_filter(
            $providers,
            static fn(mixed $provider): bool => $provider instanceof AiChatbotProjectContextProviderInterface
        ));
    }

    public static function default(): self
    {
        return new self(self::defaultProviders());
    }

    /**
     * @return array<int,AiChatbotProjectContextProviderInterface>
     */
    public static function defaultProviders(): array
    {
        return [
            new AiChatbotEPmsContextProvider(),
        ];
    }

    /**
     * @return array<int,AiChatbotProjectContextProviderInterface>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * @return array<string,mixed>
     */
    public function diagnose(?AiChatbotSystemIdentity $identity = null, int $minimumScore = self::DEFAULT_MINIMUM_MATCH_SCORE): array
    {
        $identity ??= AiChatbotSystemIdentity::fromConfig();
        $resolution = $this->resolve($identity, $minimumScore);

        return [
            'system_name' => $identity->rawName(),
            'normalized_identity' => $identity->normalizedName(),
            'compact_identity' => $identity->compactName(),
            'minimum_score' => $minimumScore,
            'status' => $resolution['status'],
            'matched_provider' => $resolution['provider'] instanceof AiChatbotProjectContextProviderInterface
                ? $resolution['provider']->code()
                : '',
            'matched_label' => $resolution['provider'] instanceof AiChatbotProjectContextProviderInterface
                ? $resolution['provider']->label()
                : '',
            'matched_alias' => (string)($resolution['alias'] ?? ''),
            'match_score' => (int)($resolution['score'] ?? 0),
            'available_providers' => $resolution['candidates'],
        ];
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function build(string $message, array $profile, array $actor = [], ?AiChatbotSystemIdentity $identity = null): array
    {
        $identity ??= AiChatbotSystemIdentity::fromConfig();
        $diagnostic = $this->diagnose($identity);

        if ($diagnostic['status'] !== 'matched') {
            return [
                'source' => 'project_context_registry',
                'status' => $diagnostic['status'],
                'system_name' => $diagnostic['system_name'],
                'normalized_identity' => $diagnostic['normalized_identity'],
                'matched_provider' => '',
                'items' => [],
            ];
        }

        $provider = $this->findProviderByCode((string)$diagnostic['matched_provider']);
        if (!$provider) {
            return [
                'source' => 'project_context_registry',
                'status' => 'provider_not_found',
                'system_name' => $diagnostic['system_name'],
                'normalized_identity' => $diagnostic['normalized_identity'],
                'matched_provider' => (string)$diagnostic['matched_provider'],
                'items' => [],
            ];
        }

        $context = $provider->build($message, $profile, $actor);
        if ($context === []) {
            return [
                'source' => 'project_context_registry',
                'status' => 'matched_no_context',
                'system_name' => $diagnostic['system_name'],
                'normalized_identity' => $diagnostic['normalized_identity'],
                'matched_provider' => $provider->code(),
                'matched_label' => $provider->label(),
                'match_score' => (int)$diagnostic['match_score'],
                'items' => [],
            ];
        }

        return [
            'source' => 'project_context_registry',
            'status' => 'matched',
            'system_name' => $diagnostic['system_name'],
            'normalized_identity' => $diagnostic['normalized_identity'],
            'matched_provider' => $provider->code(),
            'matched_label' => $provider->label(),
            'matched_alias' => (string)$diagnostic['matched_alias'],
            'match_score' => (int)$diagnostic['match_score'],
            'context' => $context,
        ];
    }

    /**
     * @return array{status:string,provider:?AiChatbotProjectContextProviderInterface,score:int,alias:string,candidates:array<int,array<string,mixed>>}
     */
    private function resolve(AiChatbotSystemIdentity $identity, int $minimumScore): array
    {
        $candidates = [];
        foreach ($this->providers as $provider) {
            $match = $identity->matchAliases($provider->aliases(), 1);
            $candidates[] = [
                'code' => $provider->code(),
                'label' => $provider->label(),
                'score' => (int)$match['score'],
                'status' => (int)$match['score'] >= $minimumScore ? 'matched' : 'no_match',
                'alias' => (string)$match['alias'],
                'normalized_alias' => (string)$match['normalized_alias'],
                'provider' => $provider,
            ];
        }

        usort($candidates, static fn(array $a, array $b): int => ((int)$b['score']) <=> ((int)$a['score']));

        if ($candidates === []) {
            return [
                'status' => 'no_project_provider',
                'provider' => null,
                'score' => 0,
                'alias' => '',
                'candidates' => [],
            ];
        }

        $best = $candidates[0];
        $bestScore = (int)$best['score'];
        if ($bestScore < $minimumScore) {
            return [
                'status' => 'core_only',
                'provider' => null,
                'score' => $bestScore,
                'alias' => (string)$best['alias'],
                'candidates' => $this->publicCandidates($candidates),
            ];
        }

        if (count($candidates) > 1 && (int)$candidates[1]['score'] === $bestScore) {
            return [
                'status' => 'ambiguous',
                'provider' => null,
                'score' => $bestScore,
                'alias' => (string)$best['alias'],
                'candidates' => $this->publicCandidates($candidates),
            ];
        }

        return [
            'status' => 'matched',
            'provider' => $best['provider'],
            'score' => $bestScore,
            'alias' => (string)$best['alias'],
            'candidates' => $this->publicCandidates($candidates),
        ];
    }

    private function findProviderByCode(string $code): ?AiChatbotProjectContextProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->code() === $code) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     * @return array<int,array<string,mixed>>
     */
    private function publicCandidates(array $candidates): array
    {
        return array_map(static function (array $candidate): array {
            unset($candidate['provider']);
            return $candidate;
        }, $candidates);
    }
}
