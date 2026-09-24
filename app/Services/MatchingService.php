<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CheckRepository;
use App\Repositories\ScamRepository;

final class MatchingService
{
    public function __construct(
        private ScamRepository $scams,
        private CheckRepository $checks,
        private ?ScamAnalysisService $analysis = null,
        private ?ScamAnalysisOrchestrator $orchestrator = null,
    ) {
        $this->analysis ??= new ScamAnalysisService($this->scams);
    }

    /** @return array<string, mixed> */
    /** @param array<string, string> $attribution */
    public function check(string $inputType, string $input, string $ip, ?string $campaignIdentifier = null, array $attribution = []): array
    {
        $analysis = $this->orchestrator?->analyze($inputType, $input, 'public') ?? $this->analysis->analyze($inputType, $input);
        $normalized = (string) $analysis['normalized'];
        $matches = $analysis['matches'];
        $top = $matches[0] ?? null;
        $status = $analysis['status'];
        $checkId = $this->checks->create([
            'input_type' => $inputType,
            'redacted_input' => mb_substr((string) $analysis['redacted_excerpt'], 0, 500),
            'input_hash' => hash('sha256', $normalized),
            'status' => $status['code'],
            'ip_hash' => hash('sha256', $ip . '|' . (string) env('APP_KEY', '')),
            'campaign_identifier' => $campaignIdentifier,
            'attribution' => $attribution,
            'retention_days' => (int) env('CHECK_RETENTION_DAYS', '7'),
        ]);
        $this->checks->addMatches($checkId, $matches);

        return [
            'check_id' => $checkId,
            'input_type' => $inputType,
            'status' => $status,
            'top_match' => $top,
            'matches' => array_slice($matches, 0, 5),
            'has_match' => $top !== null,
            'input_excerpt' => $this->excerpt($input),
            'ai_analysis' => $analysis['ai_analysis'] ?? null,
            'usage' => $analysis['usage'] ?? [],
        ];
    }
}
