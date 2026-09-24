<?php
declare(strict_types=1);

namespace App\Services;

final class ScamAnalysisOrchestrator
{
    public function __construct(
        private ScamAnalysisService $local,
        private OpenAIService $ai,
    ) {
    }

    /** @param list<array<string, mixed>> $organizationRules @return array<string, mixed> */
    public function analyze(string $inputType, string $input, string $channel = 'business', array $organizationRules = []): array
    {
        $local = $this->local->analyze($inputType, $input);
        $organizationSignals = $this->organizationSignals($input, $organizationRules);
        $ai = null;
        $businessChannel = in_array($channel, ['outlook_addin', 'business_web', 'api', 'protect'], true);
        $mode = (string) env($businessChannel ? 'BUSINESS_AI_CHECK_MODE' : 'AI_CHECK_MODE', $businessChannel ? 'uncertain' : 'all');
        $needsAi = $mode === 'all' || ($mode === 'uncertain' && $local['status']['code'] !== 'strong_match');
        if ($needsAi) {
            $ai = $this->ai->analyzeCheck($inputType, $input, $local['matches'], $organizationSignals);
        }

        return [
            'channel' => $channel,
            'input_type' => $inputType,
            'normalized' => $local['normalized'],
            'status' => $local['status'],
            'top_match' => $local['top_match'],
            'matches' => $local['matches'],
            'has_match' => $local['has_match'],
            'input_excerpt' => $local['input_excerpt'],
            'redacted_excerpt' => $local['redacted_excerpt'],
            'organization_signals' => $organizationSignals,
            'ai_analysis' => $ai,
            'usage' => $this->ai->lastUsage(),
        ];
    }

    /** @param list<array<string, mixed>> $rules @return list<array{type: string, value: string, label: string, explanation: string}> */
    private function organizationSignals(string $input, array $rules): array
    {
        $normalized = mb_strtolower($input);
        $signals = [];
        foreach ($rules as $rule) {
            $needle = trim((string) ($rule['normalized_value'] ?? ''));
            if ($needle === '' || mb_strlen($needle) < 3 || !str_contains($normalized, $needle)) {
                continue;
            }
            $signals[] = [
                'type' => (string) ($rule['rule_type'] ?? 'context'),
                'value' => (string) ($rule['value'] ?? $needle),
                'label' => (string) ($rule['label'] ?? ''),
                'explanation' => (string) ($rule['explanation'] ?? ''),
            ];
        }
        return array_slice($signals, 0, 20);
    }
}
